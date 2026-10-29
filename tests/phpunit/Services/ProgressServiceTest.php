<?php
/**
 * Progress service tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Core\Events;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;

/**
 * Completion, percentages, sequential locks and resume.
 */
class ProgressServiceTest extends ShapeLMS_TestCase {

	/**
	 * Course id.
	 *
	 * @var int
	 */
	private $course;

	/**
	 * Student id.
	 *
	 * @var int
	 */
	private $student;

	/**
	 * Enrolls a student in a fresh course.
	 */
	public function set_up() {
		parent::set_up();
		$this->course  = $this->make_course();
		$this->student = $this->make_student();
		$this->service( EnrollmentService::class )->enroll_free( $this->student, $this->course );
	}

	/**
	 * Completing a lesson updates the percentage and enrollment progress.
	 */
	public function test_complete_lesson_updates_summary() {
		$progress = $this->service( ProgressService::class );
		$lessons  = $this->lessons( $this->course );

		$summary = $progress->complete_lesson( $this->student, $lessons[0] );

		$this->assertSame( 4, $summary['total'] );
		$this->assertSame( 1, $summary['done'] );
		$this->assertSame( 25, $summary['percent'] );
		$this->assertFalse( $summary['completed'] );
		$this->assertSame( $lessons[1], $summary['next_item'] );
		$this->assertSame( 'completed', $summary['statuses'][ $lessons[0] ] );

		$row = $this->service( EnrollmentService::class )->get( $this->student, $this->course );
		$this->assertSame( 25, (int) $row->progress );
	}

	/**
	 * Non-enrolled users cannot complete lessons.
	 */
	public function test_complete_lesson_requires_enrollment() {
		$other  = $this->make_student();
		$result = $this->service( ProgressService::class )->complete_lesson( $other, $this->lessons( $this->course )[0] );

		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_not_enrolled', $result->get_error_code() );
	}

	/**
	 * Finishing every item completes the course and fires the event.
	 */
	public function test_course_completion_fires_event() {
		$progress = $this->service( ProgressService::class );
		$fired    = array();
		add_action(
			Events::COURSE_COMPLETED,
			static function ( $user_id, $context ) use ( &$fired ) {
				$fired[] = array( $user_id, $context['course_id'] );
			},
			10,
			2
		);

		foreach ( $this->lessons( $this->course ) as $lesson ) {
			$progress->complete_lesson( $this->student, $lesson );
		}
		$this->assertFalse( $progress->summary( $this->student, $this->course )['completed'], 'Quiz still pending' );

		$summary = $progress->complete_quiz( $this->student, $this->quiz( $this->course ), true );

		$this->assertTrue( $summary['completed'] );
		$this->assertSame( 100, $summary['percent'] );
		$this->assertSame( array( array( $this->student, $this->course ) ), $fired );
		$this->assertSame( 'completed', $this->service( EnrollmentService::class )->get( $this->student, $this->course )->status );
	}

	/**
	 * A failed quiz only counts when the setting allows it.
	 */
	public function test_failed_quiz_respects_setting() {
		$progress = $this->service( ProgressService::class );
		$quiz     = $this->quiz( $this->course );

		$summary = $progress->complete_quiz( $this->student, $quiz, false );
		$this->assertArrayNotHasKey( $quiz, $summary['statuses'] );

		$this->set_setting( 'course', 'completion_requires_quizzes', false );
		$summary = $progress->complete_quiz( $this->student, $quiz, false );
		$this->assertSame( 'completed', $summary['statuses'][ $quiz ] );
	}

	/**
	 * Free order: nothing is locked.
	 */
	public function test_free_order_has_no_locks() {
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );

		$this->assertFalse( ProgressService::is_sequential( $this->course ) );
		$this->assertSame( array(), $progress->summary( $this->student, $this->course )['locked'] );
		$this->assertFalse( $progress->is_locked( $this->student, $this->course, $items[3] ) );
	}

	/**
	 * Sequential: everything after the first incomplete item is locked and unlocks in order.
	 */
	public function test_sequential_locks_items_in_order() {
		update_post_meta( $this->course, '_shapelms_progression', 'linear' );
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );

		$this->assertTrue( ProgressService::is_sequential( $this->course ) );

		$summary = $progress->summary( $this->student, $this->course );
		$this->assertTrue( $summary['sequential'] );
		$this->assertSame( array( $items[1], $items[2], $items[3] ), $summary['locked'] );
		$this->assertFalse( $progress->is_locked( $this->student, $this->course, $items[0] ) );
		$this->assertTrue( $progress->is_locked( $this->student, $this->course, $items[1] ) );

		$blocked = $progress->complete_lesson( $this->student, $items[1] );
		$this->assertWPError( $blocked );
		$this->assertSame( 'shapelms_item_locked', $blocked->get_error_code() );

		$progress->complete_lesson( $this->student, $items[0] );
		$this->assertFalse( $progress->is_locked( $this->student, $this->course, $items[1] ) );
		$this->assertTrue( $progress->is_locked( $this->student, $this->course, $items[2] ) );
		$this->assertSame( array( $items[2], $items[3] ), $progress->summary( $this->student, $this->course )['locked'] );
	}

	/**
	 * The global setting applies when the course uses the default, and "free" overrides it.
	 */
	public function test_sequential_global_setting_and_override() {
		$this->set_setting( 'course', 'sequential_learning', true );
		$this->assertTrue( ProgressService::is_sequential( $this->course ) );

		update_post_meta( $this->course, '_shapelms_progression', 'free' );
		$this->assertFalse( ProgressService::is_sequential( $this->course ) );
	}

	/**
	 * Course instructors and managers are never locked.
	 */
	public function test_instructors_bypass_locks() {
		update_post_meta( $this->course, '_shapelms_progression', 'linear' );
		$progress = $this->service( ProgressService::class );
		$author   = (int) get_post_field( 'post_author', $this->course );
		$items    = $this->items( $this->course );

		$this->assertFalse( $progress->is_locked( $author, $this->course, $items[3] ) );
		$this->assertSame( array(), $progress->summary( $author, $this->course )['locked'] );
	}

	/**
	 * Add-ons can lock any item through the shapelms_item_locked filter, with their own message.
	 */
	public function test_item_locked_filter_and_message() {
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );
		$target   = $items[1];

		$lock = static function ( $locked, $item_id ) use ( $target ) {
			return $item_id === $target ? true : $locked;
		};
		$msg  = static function ( $message, $item_id ) use ( $target ) {
			return $item_id === $target ? 'Opens next week' : $message;
		};
		add_filter( 'shapelms_item_locked', $lock, 10, 2 );
		add_filter( 'shapelms_item_lock_message', $msg, 10, 2 );

		$this->assertTrue( $progress->is_locked( $this->student, $this->course, $target ) );
		$this->assertFalse( $progress->is_locked( $this->student, $this->course, $items[2] ) );

		$summary = $progress->summary( $this->student, $this->course );
		$this->assertSame( array( $target ), $summary['locked'] );
		$this->assertSame( 'Opens next week', $summary['lock_messages'][ $target ] );

		$blocked = $progress->complete_lesson( $this->student, $target );
		$this->assertWPError( $blocked );

		// Instructors never hit the filter.
		$author = (int) get_post_field( 'post_author', $this->course );
		$this->assertFalse( $progress->is_locked( $author, $this->course, $target ) );

		remove_filter( 'shapelms_item_locked', $lock, 10 );
		remove_filter( 'shapelms_item_lock_message', $msg, 10 );
	}

	/**
	 * Add-ons can block lesson completion with a reason.
	 */
	public function test_can_complete_lesson_filter() {
		$progress = $this->service( ProgressService::class );
		$lesson   = $this->lessons( $this->course )[0];

		$this->assertTrue( $progress->can_complete_lesson( $this->student, $lesson ) );

		$block = static function ( $allowed, $lesson_id ) use ( $lesson ) {
			return $lesson_id === $lesson ? new WP_Error( 'needs_work', 'Submit your work first.' ) : $allowed;
		};
		add_filter( 'shapelms_can_complete_lesson', $block, 10, 2 );

		$result = $progress->complete_lesson( $this->student, $lesson );
		$this->assertWPError( $result );
		$this->assertSame( 'needs_work', $result->get_error_code() );
		$this->assertArrayNotHasKey( $lesson, $progress->summary( $this->student, $this->course )['statuses'] );

		remove_filter( 'shapelms_can_complete_lesson', $block, 10 );
		$this->assertIsArray( $progress->complete_lesson( $this->student, $lesson ) );
	}

	/**
	 * When the next item is locked by an add-on, resume skips to the first open item.
	 */
	public function test_resume_skips_filter_locked_item() {
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );
		$first    = $items[0];

		$lock = static function ( $locked, $item_id ) use ( $first ) {
			return $item_id === $first ? true : $locked;
		};
		add_filter( 'shapelms_item_locked', $lock, 10, 2 );

		$this->assertSame( $items[1], $progress->resume_item( $this->student, $this->course ) );

		remove_filter( 'shapelms_item_locked', $lock, 10 );
	}

	/**
	 * Resume goes to the first incomplete item, then the last item.
	 */
	public function test_resume_item() {
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );

		$this->assertSame( $items[0], $progress->resume_item( $this->student, $this->course ) );

		$progress->complete_lesson( $this->student, $items[0] );
		$this->assertSame( $items[1], $progress->resume_item( $this->student, $this->course ) );
	}
}
