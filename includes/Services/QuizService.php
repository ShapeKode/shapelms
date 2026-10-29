<?php
/**
 * Quiz service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Events;
use ShapeLMS\Database\AttemptRepository;
use ShapeLMS\Quiz\Question;
use ShapeLMS\Quiz\QuestionTypes;

/**
 * Starts, answers, submits and grades quiz attempts. Grading is always server-side.
 */
class QuizService {

	/**
	 * Attempts.
	 *
	 * @var AttemptRepository
	 */
	private $attempts;

	/**
	 * Enrollment service.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment;

	/**
	 * Progress.
	 *
	 * @var ProgressService
	 */
	private $progress;

	/**
	 * Constructor.
	 *
	 * @param AttemptRepository $attempts   Attempts.
	 * @param EnrollmentService $enrollment Enrollment service.
	 * @param ProgressService   $progress   Progress.
	 */
	public function __construct( AttemptRepository $attempts, EnrollmentService $enrollment, ProgressService $progress ) {
		$this->attempts   = $attempts;
		$this->enrollment = $enrollment;
		$this->progress   = $progress;
	}

	/**
	 * Quiz settings.
	 *
	 * @param int $quiz_id Quiz id.
	 * @return array<string, mixed>
	 */
	public static function settings( $quiz_id ) {
		return array(
			'time_limit'         => (int) get_post_meta( $quiz_id, '_shapelms_time_limit', true ),
			'attempts_allowed'   => (int) get_post_meta( $quiz_id, '_shapelms_attempts_allowed', true ),
			'pass_mark'          => (int) get_post_meta( $quiz_id, '_shapelms_pass_mark', true ),
			'randomize'          => (bool) get_post_meta( $quiz_id, '_shapelms_randomize', true ),
			'feedback_mode'      => (string) get_post_meta( $quiz_id, '_shapelms_feedback_mode', true ),
			'show_answers'       => (bool) get_post_meta( $quiz_id, '_shapelms_show_answers', true ),
			'questions_per_page' => (int) get_post_meta( $quiz_id, '_shapelms_questions_per_page', true ),
		);
	}

	/**
	 * Questions of a quiz in order.
	 *
	 * @param int $quiz_id Quiz id.
	 * @return Question[]
	 */
	public static function questions( $quiz_id ) {
		$posts = get_posts(
			array(
				'post_type'     => PostTypes::QUESTION,
				'post_parent'   => (int) $quiz_id,
				'post_status'   => 'publish',
				'numberposts'   => -1,
				'orderby'       => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'no_found_rows' => true,
			)
		);

		return array_map(
			static function ( $post ) {
				return new Question( $post );
			},
			$posts
		);
	}

	/**
	 * Questions for a new attempt: the quiz's own questions, plus any an add-on adds (for example
	 * random questions drawn from a question bank).
	 *
	 * @param int $quiz_id Quiz id.
	 * @param int $user_id User id (0 for a preview count).
	 * @return Question[]
	 */
	public static function attempt_questions( $quiz_id, $user_id = 0 ) {
		/**
		 * Filters the questions of a new attempt. Return Question objects; each attempt stores its own
		 * list, so different attempts can get different questions.
		 *
		 * @param Question[] $questions Questions.
		 * @param int        $quiz_id   Quiz id.
		 * @param int        $user_id   User id.
		 */
		$questions = (array) apply_filters( 'shapelms_quiz_attempt_questions', self::questions( $quiz_id ), (int) $quiz_id, (int) $user_id );
		$types     = QuestionTypes::all();

		// Skip questions whose type is no longer registered (for example after its add-on was switched off).
		return array_values(
			array_filter(
				$questions,
				static function ( $question ) use ( $types ) {
					return $question instanceof Question && isset( $types[ $question->type ] );
				}
			)
		);
	}

	/**
	 * Total points available.
	 *
	 * @param Question[] $questions Questions.
	 * @return float
	 */
	public static function max_score( array $questions ) {
		$total = 0.0;
		foreach ( $questions as $question ) {
			$total += $question->points;
		}

		return $total;
	}

	/**
	 * Quiz info for a student (no answers).
	 *
	 * @param int $quiz_id Quiz id.
	 * @param int $user_id User id.
	 * @return array<string, mixed>
	 */
	public function overview( $quiz_id, $user_id ) {
		$settings  = self::settings( $quiz_id );
		$questions = self::attempt_questions( $quiz_id, 0 );
		$finished  = $this->attempts->count_finished( $user_id, $quiz_id );
		$open      = $this->attempts->open_attempt( $user_id, $quiz_id );
		$history   = $this->attempts->for_user_quiz( $user_id, $quiz_id );

		return array(
			'id'              => (int) $quiz_id,
			'title'           => get_the_title( $quiz_id ),
			'description'     => apply_filters( 'the_content', get_post_field( 'post_content', $quiz_id ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			'settings'        => $settings,
			'question_count'  => count( $questions ),
			'max_score'       => self::max_score( $questions ),
			'attempts_used'   => $finished,
			'attempts_left'   => $settings['attempts_allowed'] > 0 ? max( 0, $settings['attempts_allowed'] - $finished ) : -1,
			'open_attempt_id' => $open ? (int) $open->id : 0,
			'passed'          => $this->attempts->has_passed( $user_id, $quiz_id ),
			'history'         => array_map(
				array( $this, 'attempt_to_array' ),
				array_filter(
					$history,
					static function ( $row ) {
						return 'in_progress' !== $row->status;
					}
				)
			),
		);
	}

	/**
	 * Starts (or resumes) an attempt.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return array<string, mixed>|\WP_Error Attempt payload with questions.
	 */
	public function start( $user_id, $quiz_id ) {
		$course_id = Curriculum::course_id_for( $quiz_id );

		if ( ! $course_id || PostTypes::QUIZ !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			return new \WP_Error( 'shapelms_invalid_quiz', __( 'Quiz not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		if ( ! $this->enrollment->can_access( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_not_enrolled', __( 'You are not enrolled in this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		if ( $this->progress->is_locked( $user_id, $course_id, $quiz_id ) ) {
			return new \WP_Error( 'shapelms_item_locked', __( 'Complete the previous lessons before taking this quiz.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$open = $this->attempts->open_attempt( $user_id, $quiz_id );

		if ( $open ) {
			if ( $open->expires_at && strtotime( $open->expires_at ) < time() ) {
				$this->finish( (int) $open->id, true );
			} else {
				return $this->attempt_payload( $open );
			}
		}

		$settings  = self::settings( $quiz_id );
		$questions = self::attempt_questions( $quiz_id, (int) $user_id );

		if ( empty( $questions ) ) {
			return new \WP_Error( 'shapelms_empty_quiz', __( 'This quiz has no questions yet.', 'shapelms' ) );
		}

		$finished = $this->attempts->count_finished( $user_id, $quiz_id );

		if ( $settings['attempts_allowed'] > 0 && $finished >= $settings['attempts_allowed'] ) {
			return new \WP_Error( 'shapelms_no_attempts', __( 'You have used all your attempts for this quiz.', 'shapelms' ) );
		}

		$order = array_map(
			static function ( $question ) {
				return $question->id();
			},
			$questions
		);

		if ( $settings['randomize'] ) {
			shuffle( $order );
		}

		$now        = time();
		$expires_at = $settings['time_limit'] > 0 ? gmdate( 'Y-m-d H:i:s', $now + ( $settings['time_limit'] * 60 ) ) : null;

		$attempt_id = $this->attempts->insert(
			array(
				'user_id'        => (int) $user_id,
				'course_id'      => $course_id,
				'quiz_id'        => (int) $quiz_id,
				'attempt_number' => $finished + 1,
				'status'         => 'in_progress',
				'max_score'      => self::max_score( $questions ),
				'question_order' => wp_json_encode( $order ),
				'started_at'     => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'     => $expires_at,
			)
		);

		$this->progress->start( $user_id, $quiz_id );

		Events::fire(
			Events::QUIZ_STARTED,
			$user_id,
			array(
				'course_id'  => $course_id,
				'quiz_id'    => (int) $quiz_id,
				'attempt_id' => $attempt_id,
			)
		);

		return $this->attempt_payload( $this->attempts->find( $attempt_id ) );
	}

	/**
	 * Saves an answer to an open attempt. With feedback_mode = instant the grade is returned.
	 *
	 * @param int   $user_id     User id.
	 * @param int   $attempt_id  Attempt id.
	 * @param int   $question_id Question id.
	 * @param mixed $raw_answer  Raw answer.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function answer( $user_id, $attempt_id, $question_id, $raw_answer ) {
		$attempt = $this->owned_open_attempt( $user_id, $attempt_id );

		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		$order = (array) json_decode( (string) $attempt->question_order, true );

		if ( ! in_array( (int) $question_id, array_map( 'intval', $order ), true ) ) {
			return new \WP_Error( 'shapelms_invalid_question', __( 'Question is not part of this attempt.', 'shapelms' ) );
		}

		$question = Question::load( $question_id );

		if ( ! $question ) {
			return new \WP_Error( 'shapelms_invalid_question', __( 'Question not found.', 'shapelms' ) );
		}

		/**
		 * Filters whether an answer may be saved now (add-ons use it for per-question time limits).
		 * Return a WP_Error to refuse it.
		 *
		 * @param true|\WP_Error $allowed  True to allow.
		 * @param object         $attempt  Attempt row.
		 * @param Question       $question Question.
		 * @param int            $user_id  User id.
		 */
		$allowed = apply_filters( 'shapelms_quiz_can_answer', true, $attempt, $question, (int) $user_id );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$type   = QuestionTypes::get( $question->type );
		$answer = $type->sanitize_answer( $raw_answer, $question );
		$grade  = $type->grade( $answer, $question );

		$this->attempts->save_answer( (int) $attempt->id, $question->id(), $answer, $grade );

		$settings = self::settings( (int) $attempt->quiz_id );
		$response = array( 'saved' => true );

		if ( 'instant' === $settings['feedback_mode'] ) {
			$response['is_correct']  = $grade['is_correct'];
			$response['points']      = $grade['points'];
			$response['explanation'] = wp_kses_post( $question->explanation );
			if ( $settings['show_answers'] ) {
				$response['correct'] = $type->correct_answer( $question );
			}
		}

		return $response;
	}

	/**
	 * Submits an attempt: grades everything and closes it.
	 *
	 * @param int               $user_id    User id.
	 * @param int               $attempt_id Attempt id.
	 * @param array<int, mixed> $answers    Optional question_id => answer map submitted all at once.
	 * @return array<string, mixed>|\WP_Error Result.
	 */
	public function submit( $user_id, $attempt_id, array $answers = array() ) {
		$attempt = $this->owned_open_attempt( $user_id, $attempt_id );

		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}

		foreach ( $answers as $question_id => $raw ) {
			$this->answer( $user_id, $attempt_id, (int) $question_id, $raw );
		}

		$expired = $attempt->expires_at && strtotime( $attempt->expires_at ) < ( time() - 30 );

		return $this->finish( (int) $attempt->id, (bool) $expired );
	}

	/**
	 * Closes an attempt and stores the result.
	 *
	 * @param int  $attempt_id Attempt id.
	 * @param bool $expired    Whether it timed out.
	 * @return array<string, mixed>
	 */
	private function finish( $attempt_id, $expired = false ) {
		$attempt = $this->attempts->find( $attempt_id );
		$tally   = $this->tally( $attempt );

		// An answer a question type could not grade on its own (is_correct NULL) waits for a person.
		if ( $tally['pending'] ) {
			$this->attempts->update(
				$attempt_id,
				array(
					'status'      => 'pending_review',
					'score'       => $tally['score'],
					'max_score'   => $tally['max'],
					'percentage'  => $tally['percentage'],
					'passed'      => 0,
					'finished_at' => shapelms_now(),
				)
			);

			Events::fire(
				Events::QUIZ_PENDING_REVIEW,
				(int) $attempt->user_id,
				array(
					'course_id'  => (int) $attempt->course_id,
					'quiz_id'    => (int) $attempt->quiz_id,
					'attempt_id' => (int) $attempt_id,
					'object_id'  => (int) $attempt->quiz_id,
				)
			);

			return $this->result( (int) $attempt->user_id, $attempt_id );
		}

		return $this->close( $attempt, $tally, $expired ? 'expired' : 'completed' );
	}

	/**
	 * Score of an attempt from its saved answers.
	 *
	 * @param object $attempt Attempt row.
	 * @return array{score: float, max: float, percentage: float, pending: int}
	 */
	private function tally( $attempt ) {
		$order   = array_map( 'intval', (array) json_decode( (string) $attempt->question_order, true ) );
		$answers = $this->attempts->answers( (int) $attempt->id );
		$score   = 0.0;
		$pending = 0;
		$max     = 0.0;

		foreach ( $order as $question_id ) {
			$question = Question::load( $question_id );
			if ( ! $question ) {
				continue;
			}
			$max += (float) $question->points;
			if ( isset( $answers[ $question_id ] ) ) {
				$score += (float) $answers[ $question_id ]->points;
				if ( null === $answers[ $question_id ]->is_correct ) {
					++$pending;
				}
			}
		}

		return array(
			'score'      => $score,
			'max'        => $max,
			'percentage' => $max > 0 ? round( ( $score / $max ) * 100, 2 ) : 0,
			'pending'    => $pending,
		);
	}

	/**
	 * Closes an attempt with its final score and records progress.
	 *
	 * @param object                                                           $attempt Attempt row.
	 * @param array{score: float, max: float, percentage: float, pending: int} $tally   Score.
	 * @param string                                                           $status  completed|expired.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function close( $attempt, array $tally, $status ) {
		$attempt_id = (int) $attempt->id;
		$settings   = self::settings( (int) $attempt->quiz_id );
		$score      = $tally['score'];
		$max        = $tally['max'];
		$percentage = $tally['percentage'];
		$passed     = $percentage >= $settings['pass_mark'];

		$this->attempts->update(
			$attempt_id,
			array(
				'status'      => $status,
				'score'       => $score,
				'max_score'   => $max,
				'percentage'  => $percentage,
				'passed'      => $passed ? 1 : 0,
				'finished_at' => $attempt->finished_at ? $attempt->finished_at : shapelms_now(),
			)
		);

		$this->progress->complete_quiz( (int) $attempt->user_id, (int) $attempt->quiz_id, $passed );

		Events::fire(
			Events::QUIZ_FINISHED,
			(int) $attempt->user_id,
			array(
				'course_id'  => (int) $attempt->course_id,
				'quiz_id'    => (int) $attempt->quiz_id,
				'attempt_id' => $attempt_id,
				'score'      => $percentage,
				'passed'     => $passed,
			)
		);

		return $this->result( (int) $attempt->user_id, $attempt_id );
	}

	/**
	 * Grades one answer that is waiting for review. When nothing else is waiting, the attempt is
	 * scored, closed and the quiz progress recorded. Callers check that the user may grade.
	 *
	 * @param int   $attempt_id  Attempt id.
	 * @param int   $question_id Question id.
	 * @param float $points      Points awarded (clamped to the question's points).
	 * @return array<string, mixed>|\WP_Error The attempt result.
	 */
	public function grade_answer( $attempt_id, $question_id, $points ) {
		$attempt = $this->attempts->find( $attempt_id );

		if ( ! $attempt || ! in_array( $attempt->status, array( 'pending_review', 'completed', 'expired' ), true ) ) {
			return new \WP_Error( 'shapelms_not_pending', __( 'This attempt cannot be graded.', 'shapelms' ), array( 'status' => 409 ) );
		}

		$was_closed = 'pending_review' !== $attempt->status;
		$was_passed = (bool) (int) $attempt->passed;

		$answers  = $this->attempts->answers( (int) $attempt_id );
		$question = Question::load( $question_id );

		if ( ! $question || ! isset( $answers[ (int) $question_id ] ) ) {
			return new \WP_Error( 'shapelms_invalid_question', __( 'Question not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		$max    = (float) $question->points;
		$points = max( 0.0, min( $max, (float) $points ) );

		$this->attempts->save_answer(
			(int) $attempt_id,
			(int) $question_id,
			json_decode( (string) $answers[ (int) $question_id ]->answer, true ),
			array(
				'is_correct' => $points >= $max,
				'points'     => $points,
				'max_points' => $max,
			)
		);

		$attempt = $this->attempts->find( $attempt_id );
		$tally   = $this->tally( $attempt );

		if ( $tally['pending'] ) {
			$this->attempts->update(
				(int) $attempt_id,
				array(
					'score'      => $tally['score'],
					'percentage' => $tally['percentage'],
				)
			);

			return $this->result( (int) $attempt->user_id, (int) $attempt_id );
		}

		if ( ! $was_closed ) {
			return $this->close( $attempt, $tally, 'completed' );
		}

		return $this->regrade( $attempt, $tally, $was_passed );
	}

	/**
	 * Applies a changed grade to a finished attempt: new score and pass state, course progress
	 * follows (a quiz no longer passed in any attempt is not complete any more).
	 *
	 * @param object                                                           $attempt    Attempt row.
	 * @param array{score: float, max: float, percentage: float, pending: int} $tally      Score.
	 * @param bool                                                             $was_passed Pass state before.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function regrade( $attempt, array $tally, $was_passed ) {
		$settings = self::settings( (int) $attempt->quiz_id );
		$passed   = $tally['percentage'] >= $settings['pass_mark'];

		$this->attempts->update(
			(int) $attempt->id,
			array(
				'score'      => $tally['score'],
				'max_score'  => $tally['max'],
				'percentage' => $tally['percentage'],
				'passed'     => $passed ? 1 : 0,
			)
		);

		if ( $passed ) {
			$this->progress->complete_quiz( (int) $attempt->user_id, (int) $attempt->quiz_id, true );
		} elseif ( $was_passed && ! $this->attempts->has_passed( (int) $attempt->user_id, (int) $attempt->quiz_id ) && (bool) shapelms_get_setting( 'course', 'completion_requires_quizzes', true ) ) {
			$this->progress->reopen_quiz( (int) $attempt->user_id, (int) $attempt->quiz_id );
		}

		Events::fire(
			'shapelms_quiz_regraded',
			(int) $attempt->user_id,
			array(
				'course_id'  => (int) $attempt->course_id,
				'quiz_id'    => (int) $attempt->quiz_id,
				'attempt_id' => (int) $attempt->id,
				'score'      => $tally['percentage'],
				'passed'     => $passed,
			)
		);

		return $this->result( (int) $attempt->user_id, (int) $attempt->id );
	}

	/**
	 * Result of a finished attempt with per-question review when allowed.
	 *
	 * @param int $user_id    User id.
	 * @param int $attempt_id Attempt id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function result( $user_id, $attempt_id ) {
		$attempt = $this->attempts->find( $attempt_id );

		if ( ! $attempt || ( (int) $attempt->user_id !== (int) $user_id && ! current_user_can( 'manage_shapelms' ) && ! InstructorService::is_course_instructor( $user_id, (int) $attempt->course_id ) ) ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Attempt not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		$settings = self::settings( (int) $attempt->quiz_id );
		$data     = $this->attempt_to_array( $attempt );
		$review   = 'none' !== $settings['feedback_mode'] || (int) $attempt->user_id !== (int) $user_id;

		if ( $review ) {
			$answers = $this->attempts->answers( $attempt_id );
			$order   = (array) json_decode( (string) $attempt->question_order, true );
			$list    = array();

			foreach ( $order as $question_id ) {
				$question = Question::load( (int) $question_id );
				if ( ! $question ) {
					continue;
				}
				$type   = QuestionTypes::get( $question->type );
				$answer = isset( $answers[ $question->id() ] ) ? $answers[ $question->id() ] : null;
				$item   = array(
					'id'          => $question->id(),
					'type'        => $question->type,
					'title'       => $question->text(),
					'description' => wp_kses_post( $question->description() ),
					'data'        => $type->public_data( $question ),
					'answer'      => $answer ? json_decode( (string) $answer->answer, true ) : null,
					'is_correct'  => $answer ? ( null === $answer->is_correct ? null : (bool) $answer->is_correct ) : false,
					'points'      => $answer ? (float) $answer->points : 0,
					'max_points'  => $question->points,
					'explanation' => wp_kses_post( $question->explanation ),
				);
				if ( $settings['show_answers'] || (int) $attempt->user_id !== (int) $user_id ) {
					$item['correct'] = $type->correct_answer( $question );
				}
				$list[] = $item;
			}

			$data['questions'] = $list;
		}

		$data['settings'] = $settings;

		/**
		 * Filters an attempt result (add-ons can add per-question feedback).
		 *
		 * @param array  $data    Result.
		 * @param object $attempt Attempt row.
		 */
		return apply_filters( 'shapelms_quiz_result', $data, $attempt );
	}

	/**
	 * Attempt payload with public question data for the player.
	 *
	 * @param object $attempt Attempt row.
	 * @return array<string, mixed>
	 */
	private function attempt_payload( $attempt ) {
		$order     = (array) json_decode( (string) $attempt->question_order, true );
		$answers   = $this->attempts->answers( (int) $attempt->id );
		$questions = array();

		foreach ( $order as $question_id ) {
			$question = Question::load( (int) $question_id );
			if ( ! $question ) {
				continue;
			}
			$questions[] = self::question_payload( $question, $attempt, isset( $answers[ $question->id() ] ) ? json_decode( (string) $answers[ $question->id() ]->answer, true ) : null );
		}

		return array(
			'attempt'     => $this->attempt_to_array( $attempt ),
			'settings'    => self::settings( (int) $attempt->quiz_id ),
			'questions'   => $questions,
			'server_time' => time(),
		);
	}

	/**
	 * One question as the player gets it (no correct answers).
	 *
	 * @param Question $question Question.
	 * @param object   $attempt  Attempt row.
	 * @param mixed    $answer   Saved answer, if any.
	 * @return array<string, mixed>
	 */
	public static function question_payload( Question $question, $attempt, $answer = null ) {
		$type = QuestionTypes::get( $question->type );
		$item = array(
			'id'          => $question->id(),
			'type'        => $question->type,
			'title'       => $question->text(),
			'description' => 'fill_blank' === $question->type ? '' : wp_kses_post( $question->description() ),
			'points'      => $question->points,
			'data'        => $type->public_data( $question ),
			'answer'      => $answer,
		);

		/**
		 * Filters one question in the attempt sent to the player (never add correct answers here).
		 *
		 * @param array<string, mixed> $item     Question data.
		 * @param Question             $question Question.
		 * @param object               $attempt  Attempt row.
		 */
		return (array) apply_filters( 'shapelms_quiz_question_payload', $item, $question, $attempt );
	}

	/**
	 * Finds an in-progress attempt owned by the user.
	 *
	 * @param int $user_id    User id.
	 * @param int $attempt_id Attempt id.
	 * @return object|\WP_Error
	 */
	private function owned_open_attempt( $user_id, $attempt_id ) {
		$attempt = $this->attempts->find( $attempt_id );

		if ( ! $attempt || (int) $attempt->user_id !== (int) $user_id ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Attempt not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		if ( 'in_progress' !== $attempt->status ) {
			return new \WP_Error( 'shapelms_attempt_closed', __( 'This attempt has already been submitted.', 'shapelms' ) );
		}

		if ( $attempt->expires_at && strtotime( $attempt->expires_at ) < ( time() - 30 ) ) {
			$this->finish( (int) $attempt->id, true );
			return new \WP_Error( 'shapelms_attempt_expired', __( 'Time is up. The attempt has been submitted.', 'shapelms' ) );
		}

		return $attempt;
	}

	/**
	 * Row to API shape.
	 *
	 * @param object $row Attempt row.
	 * @return array<string, mixed>
	 */
	public function attempt_to_array( $row ) {
		return array(
			'id'             => (int) $row->id,
			'user_id'        => (int) $row->user_id,
			'course_id'      => (int) $row->course_id,
			'quiz_id'        => (int) $row->quiz_id,
			'attempt_number' => (int) $row->attempt_number,
			'status'         => (string) $row->status,
			'score'          => (float) $row->score,
			'max_score'      => (float) $row->max_score,
			'percentage'     => (float) $row->percentage,
			'passed'         => (bool) $row->passed,
			'started_at'     => (string) $row->started_at,
			'expires_at'     => $row->expires_at,
			'finished_at'    => $row->finished_at,
		);
	}
}
