<?php
/**
 * Quiz service tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;
use ShapeLMS\Services\QuizService;

/**
 * Attempts, grading, attempt limits and locks.
 */
class QuizServiceTest extends ShapeLMS_TestCase {

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
	 * Quiz id.
	 *
	 * @var int
	 */
	private $quiz;

	/**
	 * Enrolls a student.
	 */
	public function set_up() {
		parent::set_up();
		$this->course  = $this->make_course();
		$this->student = $this->make_student();
		$this->quiz    = $this->quiz( $this->course );
		$this->service( EnrollmentService::class )->enroll_free( $this->student, $this->course );
	}

	/**
	 * Maps question titles to ids from the attempt payload.
	 *
	 * @param array<string, mixed> $payload Attempt payload.
	 * @return array<string, int>
	 */
	private function question_ids( array $payload ) {
		$map = array();
		foreach ( $payload['questions'] as $question ) {
			$map[ $question['title'] ] = (int) $question['id'];
		}

		return $map;
	}

	/**
	 * The attempt payload never includes correct answers.
	 */
	public function test_start_hides_answers() {
		$payload = $this->service( QuizService::class )->start( $this->student, $this->quiz );

		$this->assertIsArray( $payload );
		$this->assertCount( 2, $payload['questions'] );
		$this->assertSame( 'in_progress', $payload['attempt']['status'] );
		$this->assertStringNotContainsString( '"correct"', wp_json_encode( $payload['questions'] ) );
	}

	/**
	 * Non-enrolled users cannot start.
	 */
	public function test_start_requires_access() {
		$result = $this->service( QuizService::class )->start( $this->make_student(), $this->quiz );

		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_not_enrolled', $result->get_error_code() );
	}

	/**
	 * All correct answers pass and complete the quiz item.
	 */
	public function test_submit_all_correct_passes() {
		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );
		$ids     = $this->question_ids( $payload );

		$result = $service->submit(
			$this->student,
			(int) $payload['attempt']['id'],
			array(
				$ids['Single']        => 'a',
				$ids['True or false'] => 'true',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'completed', $result['status'] );
		$this->assertSame( 100.0, (float) $result['percentage'] );
		$this->assertTrue( (bool) $result['passed'] );
		$this->assertCount( 2, $result['questions'] );
		$this->assertTrue( $result['questions'][0]['is_correct'] );

		$statuses = $this->service( ProgressService::class )->summary( $this->student, $this->course )['statuses'];
		$this->assertSame( 'completed', $statuses[ $this->quiz ] );
	}

	/**
	 * Half right scores 50% and does not pass a 60% mark; wrong answers never leak points.
	 */
	public function test_submit_partial_fails_against_higher_pass_mark() {
		update_post_meta( $this->quiz, '_shapelms_pass_mark', 60 );
		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );
		$ids     = $this->question_ids( $payload );

		$result = $service->submit(
			$this->student,
			(int) $payload['attempt']['id'],
			array(
				$ids['Single']        => 'b',
				$ids['True or false'] => 'true',
			)
		);

		$this->assertSame( 50.0, (float) $result['percentage'] );
		$this->assertFalse( (bool) $result['passed'] );
		$statuses = $this->service( ProgressService::class )->summary( $this->student, $this->course )['statuses'];
		$this->assertNotSame( 'completed', isset( $statuses[ $this->quiz ] ) ? $statuses[ $this->quiz ] : '' );
	}

	/**
	 * Attempt limits are enforced.
	 */
	public function test_attempt_limit() {
		update_post_meta( $this->quiz, '_shapelms_attempts_allowed', 1 );
		$service = $this->service( QuizService::class );

		$payload = $service->start( $this->student, $this->quiz );
		$service->submit( $this->student, (int) $payload['attempt']['id'], array() );

		$again = $service->start( $this->student, $this->quiz );
		$this->assertWPError( $again );
		$this->assertSame( 'shapelms_no_attempts', $again->get_error_code() );
	}

	/**
	 * Starting again resumes the open attempt instead of creating a new one.
	 */
	public function test_start_resumes_open_attempt() {
		$service = $this->service( QuizService::class );

		$first  = $service->start( $this->student, $this->quiz );
		$second = $service->start( $this->student, $this->quiz );

		$this->assertSame( $first['attempt']['id'], $second['attempt']['id'] );
	}

	/**
	 * A type that can't grade itself (is_correct null) sends the attempt to review; grading closes it.
	 */
	public function test_manual_review_flow() {
		$manual = new class() implements \ShapeLMS\Quiz\QuestionTypeInterface {
			public function id() {
				return 'unit_manual';
			}
			public function label() {
				return 'Manual';
			}
			public function public_data( \ShapeLMS\Quiz\Question $question ) {
				return array();
			}
			public function sanitize_answer( $raw, \ShapeLMS\Quiz\Question $question ) {
				return '' === (string) $raw ? null : sanitize_text_field( (string) $raw );
			}
			public function grade( $answer, \ShapeLMS\Quiz\Question $question ) {
				return array(
					'is_correct' => null,
					'points'     => 0.0,
					'max_points' => $question->points,
				);
			}
			public function correct_answer( \ShapeLMS\Quiz\Question $question ) {
				return array();
			}
		};
		$register = static function ( $types ) use ( $manual ) {
			$types[] = $manual;
			return $types;
		};
		add_filter( 'shapelms_question_types', $register );
		$reset = new ReflectionProperty( \ShapeLMS\Quiz\QuestionTypes::class, 'types' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reset->setAccessible( true );
		}
		$reset->setValue( null, null );

		// Turn the true/false question into the manual type, worth 2 points.
		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );
		$ids     = $this->question_ids( $payload );
		update_post_meta( $ids['True or false'], '_shapelms_type', 'unit_manual' );
		update_post_meta( $ids['True or false'], '_shapelms_points', 2 );

		$fired = 0;
		$count = static function () use ( &$fired ) {
			++$fired;
		};
		add_action( \ShapeLMS\Core\Events::QUIZ_PENDING_REVIEW, $count );

		$result = $service->submit(
			$this->student,
			(int) $payload['attempt']['id'],
			array(
				$ids['Single']        => 'a',
				$ids['True or false'] => 'My long answer',
			)
		);

		$this->assertSame( 'pending_review', $result['status'] );
		$this->assertFalse( (bool) $result['passed'] );
		$this->assertSame( 1, $fired );
		$progress = $this->service( ProgressService::class );
		$statuses = $progress->summary( $this->student, $this->course )['statuses'];
		$this->assertNotSame( 'completed', $statuses[ $this->quiz ] ?? '' );

		// A pending attempt uses an attempt slot.
		update_post_meta( $this->quiz, '_shapelms_attempts_allowed', 1 );
		$again = $service->start( $this->student, $this->quiz );
		$this->assertWPError( $again );
		$this->assertSame( 'shapelms_no_attempts', $again->get_error_code() );

		// Grading the last pending answer closes the attempt: 1 + 2 of 3 points = 100%.
		$closed = $service->grade_answer( (int) $payload['attempt']['id'], $ids['True or false'], 5 );
		$this->assertSame( 'completed', $closed['status'] );
		$this->assertSame( 3.0, (float) $closed['score'] );
		$this->assertTrue( (bool) $closed['passed'] );
		$this->assertSame( 'completed', $progress->summary( $this->student, $this->course )['statuses'][ $this->quiz ] );

		// Re-grading a finished attempt updates its result and the course progress.
		$lower = $service->grade_answer( (int) $payload['attempt']['id'], $ids['True or false'], 0 );
		$this->assertSame( 1.0, (float) $lower['score'] );
		$this->assertFalse( (bool) $lower['passed'] );
		$this->assertNotSame( 'completed', $progress->summary( $this->student, $this->course )['statuses'][ $this->quiz ] ?? '' );

		$again_up = $service->grade_answer( (int) $payload['attempt']['id'], $ids['True or false'], 2 );
		$this->assertTrue( (bool) $again_up['passed'] );
		$this->assertSame( 'completed', $progress->summary( $this->student, $this->course )['statuses'][ $this->quiz ] );

		// An attempt still in progress cannot be graded.
		$this->assertWPError( $service->grade_answer( 999999, $ids['True or false'], 1 ) );

		remove_filter( 'shapelms_question_types', $register );
		remove_action( \ShapeLMS\Core\Events::QUIZ_PENDING_REVIEW, $count );
		$reset->setValue( null, null );
	}

	/**
	 * A quiz behind incomplete lessons cannot be started in sequential mode.
	 */
	public function test_locked_quiz_cannot_start() {
		update_post_meta( $this->course, '_shapelms_progression', 'linear' );
		$service = $this->service( QuizService::class );

		$blocked = $service->start( $this->student, $this->quiz );
		$this->assertWPError( $blocked );
		$this->assertSame( 'shapelms_item_locked', $blocked->get_error_code() );

		// The two lessons before the quiz.
		$progress = $this->service( ProgressService::class );
		$items    = $this->items( $this->course );
		$progress->complete_lesson( $this->student, $items[0] );
		$progress->complete_lesson( $this->student, $items[1] );

		$this->assertIsArray( $service->start( $this->student, $this->quiz ) );
	}
}
