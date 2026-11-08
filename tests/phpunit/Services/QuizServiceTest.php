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
class ShapeLMS_QuizServiceTest extends ShapeLMS_TestCase {

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
	 * While an attempt is open its owner gets no result, even for answers already saved.
	 */
	public function test_open_attempt_hides_result_from_owner() {
		update_post_meta( $this->quiz, '_shapelms_show_answers', true );

		$service  = $this->service( QuizService::class );
		$payload  = $service->start( $this->student, $this->quiz );
		$attempt  = $payload['attempt']['id'];
		$question = $payload['questions'][0]['id'];

		wp_set_current_user( $this->student );
		$this->assertTrue( $service->answer( $this->student, $attempt, $question, 'a' )['saved'] );

		$open = $service->result( $this->student, $attempt );
		$this->assertWPError( $open );
		$this->assertSame( 'shapelms_attempt_open', $open->get_error_code() );
		$this->assertSame( 409, $open->get_error_data()['status'] );

		$request  = new WP_REST_Request( 'GET', '/shapelms/v1/quizzes/attempts/' . $attempt );
		$response = rest_do_request( $request );
		$this->assertSame( 409, $response->get_status() );
		$this->assertStringNotContainsString( '"correct"', (string) wp_json_encode( $response->get_data() ) );

		$this->assertArrayHasKey( 'correct', $service->submit( $this->student, $attempt, array() )['questions'][0] );
		$this->assertArrayHasKey( 'correct', $service->result( $this->student, $attempt )['questions'][0] );
	}

	/**
	 * A manager can still look at someone else's open attempt.
	 */
	public function test_manager_can_read_open_attempt() {
		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );
		$attempt = $payload['attempt']['id'];
		$manager = $this->make_instructor();

		$service->answer( $this->student, $attempt, $payload['questions'][0]['id'], 'a' );

		wp_set_current_user( $manager );
		$result = $service->result( $manager, $attempt );
		$this->assertCount( 1, $result['questions'], 'Only what the server holds, like Tutor.' );
	}

	/**
	 * Outside instant mode nothing reaches the server until the quiz is submitted.
	 */
	public function test_answers_wait_for_submit() {
		update_post_meta( $this->quiz, '_shapelms_feedback_mode', 'end' );

		$service  = $this->service( QuizService::class );
		$payload  = $service->start( $this->student, $this->quiz );
		$attempt  = $payload['attempt']['id'];
		$question = $payload['questions'][0]['id'];

		$refused = $service->answer( $this->student, $attempt, $question, 'a' );
		$this->assertWPError( $refused );
		$this->assertSame( 'shapelms_answer_on_submit', $refused->get_error_code() );
		$this->assertFalse( $payload['questions'][0]['checkable'] );

		$result = $service->submit( $this->student, $attempt, array( $question => 'a' ) );
		$this->assertEquals( 1, $result['score'] );
	}

	/**
	 * A checked answer is final: it cannot be changed by checking again or on submit.
	 */
	public function test_checked_answer_is_final() {
		$service  = $this->service( QuizService::class );
		$payload  = $service->start( $this->student, $this->quiz );
		$attempt  = $payload['attempt']['id'];
		$question = $payload['questions'][0]['id'];

		$this->assertTrue( $payload['questions'][0]['checkable'] );
		$this->assertFalse( $service->answer( $this->student, $attempt, $question, 'b' )['is_correct'] );

		$again = $service->answer( $this->student, $attempt, $question, 'a' );
		$this->assertWPError( $again );
		$this->assertSame( 'shapelms_answer_committed', $again->get_error_code() );

		// Reloading brings the feedback back and keeps it locked.
		$resumed = $service->start( $this->student, $this->quiz );
		$this->assertFalse( $resumed['questions'][0]['checked']['is_correct'] );
		$this->assertArrayNotHasKey( 'checked', $resumed['questions'][1] );

		$result = $service->submit( $this->student, $attempt, array( $question => 'a' ) );
		$this->assertEquals( 0, $result['score'] );
	}

	/**
	 * An empty check is refused, so it cannot burn the question.
	 */
	public function test_check_needs_an_answer() {
		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );

		$this->assertSame( 'shapelms_answer_required', $service->answer( $this->student, $payload['attempt']['id'], $payload['questions'][0]['id'], '' )->get_error_code() );
	}

	/**
	 * Only choice questions are checked one by one; the rest wait for submit, even in instant mode.
	 */
	public function test_other_types_wait_in_instant_mode() {
		add_filter( 'shapelms_quiz_checkable_types', '__return_empty_array' );

		$service = $this->service( QuizService::class );
		$payload = $service->start( $this->student, $this->quiz );
		$refused = $service->answer( $this->student, $payload['attempt']['id'], $payload['questions'][0]['id'], 'a' );

		remove_filter( 'shapelms_quiz_checkable_types', '__return_empty_array' );

		$this->assertSame( 'shapelms_answer_on_submit', $refused->get_error_code() );
	}

	/**
	 * An add-on can keep a question saving as it changes (per-question time limits need this).
	 */
	public function test_save_now_filter() {
		update_post_meta( $this->quiz, '_shapelms_feedback_mode', 'end' );
		add_filter( 'shapelms_quiz_save_answer_now', '__return_true' );

		$service  = $this->service( QuizService::class );
		$payload  = $service->start( $this->student, $this->quiz );
		$saved    = $service->answer( $this->student, $payload['attempt']['id'], $payload['questions'][0]['id'], 'b' );
		$replaced = $service->answer( $this->student, $payload['attempt']['id'], $payload['questions'][0]['id'], 'a' );

		remove_filter( 'shapelms_quiz_save_answer_now', '__return_true' );

		$this->assertSame( array( 'saved' => true ), $saved );
		$this->assertSame( array( 'saved' => true ), $replaced );
	}

	/**
	 * The quiz page knows about a running attempt, and closes one whose time ran out.
	 */
	public function test_running_attempt() {
		global $wpdb;

		$service = $this->service( QuizService::class );
		$this->assertFalse( $service->has_running_attempt( $this->student, $this->quiz ) );

		$attempt = $service->start( $this->student, $this->quiz )['attempt']['id'];
		$this->assertTrue( $service->has_running_attempt( $this->student, $this->quiz ) );

		$wpdb->update( $wpdb->prefix . 'shapelms_quiz_attempts', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ), array( 'id' => $attempt ) );
		$this->assertFalse( $service->has_running_attempt( $this->student, $this->quiz ) );
		$this->assertSame( 'expired', $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}shapelms_quiz_attempts WHERE id = %d", $attempt ) ) );
	}

	/**
	 * An attempt whose time ran out closes when its result is asked for.
	 */
	public function test_expired_attempt_closes_on_result() {
		global $wpdb;

		$service = $this->service( QuizService::class );
		$attempt = $service->start( $this->student, $this->quiz )['attempt']['id'];
		$wpdb->update( $wpdb->prefix . 'shapelms_quiz_attempts', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ), array( 'id' => $attempt ) );

		wp_set_current_user( $this->student );
		$this->assertSame( 'expired', $service->result( $this->student, $attempt )['status'] );
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
