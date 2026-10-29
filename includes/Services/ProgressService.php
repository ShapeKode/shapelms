<?php
/**
 * Progress and completion.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Events;
use ShapeLMS\Database\EnrollmentRepository;
use ShapeLMS\Database\ProgressRepository;

/**
 * Tracks lesson progress and completes courses.
 */
class ProgressService {

	/**
	 * Progress.
	 *
	 * @var ProgressRepository
	 */
	private $progress;

	/**
	 * Enrollments.
	 *
	 * @var EnrollmentRepository
	 */
	private $enrollments;

	/**
	 * Enrollment service.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment_service;

	/**
	 * Constructor.
	 *
	 * @param ProgressRepository   $progress           Progress.
	 * @param EnrollmentRepository $enrollments        Enrollments.
	 * @param EnrollmentService    $enrollment_service Enrollment service.
	 */
	public function __construct( ProgressRepository $progress, EnrollmentRepository $enrollments, EnrollmentService $enrollment_service ) {
		$this->progress           = $progress;
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
	}

	/**
	 * Records that a user opened an item.
	 *
	 * @param int $user_id User id.
	 * @param int $item_id Lesson or quiz id.
	 * @return void
	 */
	public function start( $user_id, $item_id ) {
		$course_id = Curriculum::course_id_for( $item_id );

		if ( ! $course_id || ! $this->enrollment_service->is_enrolled( $user_id, $course_id ) ) {
			return;
		}

		$row = $this->progress->get( $user_id, $item_id );

		if ( ! $row ) {
			$this->progress->insert(
				array(
					'user_id'    => (int) $user_id,
					'course_id'  => $course_id,
					'item_id'    => (int) $item_id,
					'item_type'  => Curriculum::type_of( (string) get_post_type( $item_id ) ) ? Curriculum::type_of( (string) get_post_type( $item_id ) ) : 'lesson',
					'status'     => 'started',
					'started_at' => shapelms_now(),
				)
			);
		}

		$this->touch( $user_id, $course_id );
	}

	/**
	 * Marks a lesson complete.
	 *
	 * @param int $user_id   User id.
	 * @param int $lesson_id Lesson id.
	 * @return array<string, mixed>|\WP_Error Progress summary.
	 */
	public function complete_lesson( $user_id, $lesson_id ) {
		$course_id = Curriculum::course_id_for( $lesson_id );

		if ( ! $course_id || PostTypes::LESSON !== get_post_type( $lesson_id ) ) {
			return new \WP_Error( 'shapelms_invalid_lesson', __( 'Lesson not found.', 'shapelms' ) );
		}

		if ( ! $this->enrollment_service->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_not_enrolled', __( 'You are not enrolled in this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		if ( $this->is_locked( $user_id, $course_id, $lesson_id ) ) {
			return new \WP_Error( 'shapelms_item_locked', __( 'Complete the previous lessons first.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$allowed = $this->can_complete_lesson( $user_id, $lesson_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$this->mark_item( $user_id, $course_id, $lesson_id, 'lesson' );

		Events::fire(
			Events::LESSON_COMPLETED,
			$user_id,
			array(
				'course_id' => $course_id,
				'lesson_id' => (int) $lesson_id,
			)
		);

		return $this->recalculate( $user_id, $course_id );
	}

	/**
	 * Whether the student may mark a lesson complete now (add-ons can require something first).
	 *
	 * @param int $user_id   User id.
	 * @param int $lesson_id Lesson id.
	 * @return true|\WP_Error
	 */
	public function can_complete_lesson( $user_id, $lesson_id ) {
		/**
		 * Filters whether a student may complete a lesson. Return a WP_Error to block it;
		 * its message is shown to the student.
		 *
		 * @param true|\WP_Error $allowed   True to allow.
		 * @param int            $lesson_id Lesson id.
		 * @param int            $user_id   User id.
		 */
		$allowed = apply_filters( 'shapelms_can_complete_lesson', true, (int) $lesson_id, (int) $user_id );

		if ( ! is_wp_error( $allowed ) ) {
			return true;
		}

		// Always answer REST clients with 403, whatever status the add-on set (or forgot).
		$data = $allowed->get_error_data();
		if ( ! is_array( $data ) || empty( $data['status'] ) ) {
			$allowed->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => 403 ) ) );
		}

		return $allowed;
	}

	/**
	 * Marks an add-on item complete (for example a passed assignment). Lessons and quizzes have
	 * their own rules and cannot be completed through this method.
	 *
	 * @param int $user_id User id.
	 * @param int $item_id Item id.
	 * @return array<string, mixed>|\WP_Error Progress summary.
	 */
	public function complete_item( $user_id, $item_id ) {
		$type      = Curriculum::type_of( (string) get_post_type( $item_id ) );
		$course_id = Curriculum::course_id_for( $item_id );

		if ( ! $type || in_array( $type, array( 'lesson', 'quiz' ), true ) || ! $course_id ) {
			return new \WP_Error( 'shapelms_invalid_item', __( 'Item not found.', 'shapelms' ) );
		}

		if ( ! $this->enrollment_service->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_not_enrolled', __( 'You are not enrolled in this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$this->mark_item( $user_id, $course_id, $item_id, $type );

		Events::fire(
			'shapelms_item_completed',
			$user_id,
			array(
				'course_id' => $course_id,
				'object_id' => (int) $item_id,
				'item_type' => $type,
			)
		);

		return $this->recalculate( $user_id, $course_id );
	}

	/**
	 * Marks an add-on item not complete again (for example an assignment regraded below the pass mark).
	 *
	 * @param int $user_id User id.
	 * @param int $item_id Item id.
	 * @return void
	 */
	public function reopen_item( $user_id, $item_id ) {
		$type      = Curriculum::type_of( (string) get_post_type( $item_id ) );
		$course_id = Curriculum::course_id_for( $item_id );
		$row       = $this->progress->get( $user_id, $item_id );

		if ( ! $type || in_array( $type, array( 'lesson', 'quiz' ), true ) || ! $course_id || ! $row || 'completed' !== $row->status ) {
			return;
		}

		$this->progress->update(
			(int) $row->id,
			array(
				'status'       => 'started',
				'completed_at' => null,
			)
		);

		$this->recalculate( $user_id, $course_id );
	}

	/**
	 * Marks a quiz complete after a passing (or any, per settings) attempt.
	 *
	 * @param int  $user_id User id.
	 * @param int  $quiz_id Quiz id.
	 * @param bool $passed  Whether the attempt passed.
	 * @return array<string, mixed>
	 */
	public function complete_quiz( $user_id, $quiz_id, $passed ) {
		$course_id = Curriculum::course_id_for( $quiz_id );

		if ( ! $course_id ) {
			return array();
		}

		$requires_pass = (bool) shapelms_get_setting( 'course', 'completion_requires_quizzes', true );

		if ( $passed || ! $requires_pass ) {
			$this->mark_item( $user_id, $course_id, $quiz_id, 'quiz' );
		}

		return $this->recalculate( $user_id, $course_id );
	}

	/**
	 * Marks a quiz not complete again after a re-grade took away its only pass.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return void
	 */
	public function reopen_quiz( $user_id, $quiz_id ) {
		$course_id = Curriculum::course_id_for( $quiz_id );
		$row       = $this->progress->get( $user_id, $quiz_id );

		if ( ! $course_id || ! $row || 'completed' !== $row->status ) {
			return;
		}

		$this->progress->update(
			(int) $row->id,
			array(
				'status'       => 'started',
				'completed_at' => null,
			)
		);

		$this->recalculate( $user_id, $course_id );
	}

	/**
	 * Upserts a completed progress row.
	 *
	 * @param int    $user_id   User id.
	 * @param int    $course_id Course id.
	 * @param int    $item_id   Item id.
	 * @param string $type      lesson|quiz.
	 * @return void
	 */
	private function mark_item( $user_id, $course_id, $item_id, $type ) {
		$row = $this->progress->get( $user_id, $item_id );
		$now = shapelms_now();

		if ( $row ) {
			if ( 'completed' !== $row->status ) {
				$this->progress->update(
					(int) $row->id,
					array(
						'status'       => 'completed',
						'completed_at' => $now,
					)
				);
			}
			return;
		}

		$this->progress->insert(
			array(
				'user_id'      => (int) $user_id,
				'course_id'    => (int) $course_id,
				'item_id'      => (int) $item_id,
				'item_type'    => $type,
				'status'       => 'completed',
				'started_at'   => $now,
				'completed_at' => $now,
			)
		);
	}

	/**
	 * Recalculates course progress and completes the course when everything is done.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return array<string, mixed>
	 */
	public function recalculate( $user_id, $course_id ) {
		$summary    = $this->summary( $user_id, $course_id );
		$enrollment = $this->enrollments->get( $user_id, $course_id );

		if ( ! $enrollment ) {
			return $summary;
		}

		$update = array(
			'progress'         => (int) $summary['percent'],
			'last_activity_at' => shapelms_now(),
		);

		if ( $summary['completed'] && 'completed' !== $enrollment->status ) {
			$update['status']       = 'completed';
			$update['completed_at'] = shapelms_now();
		}

		$this->enrollments->update( (int) $enrollment->id, $update );

		if ( $summary['completed'] && 'completed' !== $enrollment->status ) {
			Events::fire( Events::COURSE_COMPLETED, $user_id, array( 'course_id' => (int) $course_id ) );
		}

		return $summary;
	}

	/**
	 * Progress summary for a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return array{total: int, done: int, percent: int, completed: bool, statuses: array<int, string>, last_item: int, next_item: int, sequential: bool, locked: int[], lock_messages: array<int, string>}
	 */
	public function summary( $user_id, $course_id ) {
		$items      = Curriculum::items( $course_id );
		$statuses   = $this->progress->statuses_for_course( $user_id, $course_id );
		$total      = count( $items );
		$done       = 0;
		$next       = 0;
		$first_open = 0;
		$locked     = array();
		$messages   = array();
		$bypass     = $this->bypasses_locks( $user_id, $course_id );
		$sequential = self::is_sequential( $course_id ) && ! $bypass;

		foreach ( $items as $item ) {
			$id        = (int) $item['id'];
			$completed = isset( $statuses[ $id ] ) && 'completed' === $statuses[ $id ];

			if ( $completed ) {
				++$done;
			}

			$is_locked = ! $completed && $sequential && $next && $next !== $id;
			if ( ! $completed && ! $bypass ) {
				$is_locked = $this->filter_locked( $is_locked, $id, $user_id, $course_id );
			}

			if ( $is_locked ) {
				$locked[]        = $id;
				$messages[ $id ] = $this->lock_message( $user_id, $course_id, $id );
			} elseif ( ! $completed && ! $first_open ) {
				$first_open = $id;
			}

			if ( ! $completed && ! $next ) {
				$next = $id;
			}
		}

		// Resume at the first item the student can actually open.
		if ( $next && in_array( $next, $locked, true ) ) {
			$next = $first_open;
		}

		$percent = $total > 0 ? (int) floor( ( $done / $total ) * 100 ) : 0;

		return array(
			'total'         => $total,
			'done'          => $done,
			'percent'       => $percent,
			'completed'     => $total > 0 && $done >= $total,
			'statuses'      => $statuses,
			'last_item'     => $this->progress->last_item( $user_id, $course_id ),
			'next_item'     => $next,
			'sequential'    => $sequential,
			'locked'        => $locked,
			'lock_messages' => $messages,
		);
	}

	/**
	 * Whether a course locks items until the previous one is completed.
	 *
	 * Per-course meta (linear|free) wins; "default" falls back to the global setting.
	 *
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function is_sequential( $course_id ) {
		$mode = (string) get_post_meta( $course_id, '_shapelms_progression', true );

		if ( 'linear' === $mode ) {
			$sequential = true;
		} elseif ( 'free' === $mode ) {
			$sequential = false;
		} else {
			$sequential = (bool) shapelms_get_setting( 'course', 'sequential_learning', false );
		}

		/**
		 * Filters whether a course uses sequential learning (add-ons may override it).
		 *
		 * @param bool $sequential Whether items are locked in order.
		 * @param int  $course_id  Course id.
		 */
		return (bool) apply_filters( 'shapelms_course_is_sequential', $sequential, $course_id );
	}

	/**
	 * Whether an item is locked for the user because earlier items are not completed.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @param int $item_id   Lesson or quiz id.
	 * @return bool
	 */
	public function is_locked( $user_id, $course_id, $item_id ) {
		if ( $this->bypasses_locks( $user_id, $course_id ) ) {
			return false;
		}

		$statuses = $this->progress->statuses_for_course( $user_id, $course_id );

		if ( isset( $statuses[ (int) $item_id ] ) && 'completed' === $statuses[ (int) $item_id ] ) {
			return false;
		}

		$locked = false;

		if ( self::is_sequential( $course_id ) ) {
			foreach ( Curriculum::item_ids( $course_id ) as $id ) {
				if ( $id === (int) $item_id ) {
					break;
				}
				if ( ! isset( $statuses[ $id ] ) || 'completed' !== $statuses[ $id ] ) {
					$locked = true;
					break;
				}
			}
		}

		return $this->filter_locked( $locked, (int) $item_id, $user_id, $course_id );
	}

	/**
	 * Lets add-ons lock an item for other reasons (a schedule, a date, a prerequisite lesson).
	 *
	 * @param bool $locked    Current decision.
	 * @param int  $item_id   Lesson or quiz id.
	 * @param int  $user_id   User id.
	 * @param int  $course_id Course id.
	 * @return bool
	 */
	private function filter_locked( $locked, $item_id, $user_id, $course_id ) {
		/**
		 * Filters whether a lesson or quiz is locked for a student. Course instructors and
		 * managers never reach this filter.
		 *
		 * @param bool $locked    Whether the item is locked.
		 * @param int  $item_id   Lesson or quiz id.
		 * @param int  $user_id   User id.
		 * @param int  $course_id Course id.
		 */
		return (bool) apply_filters( 'shapelms_item_locked', (bool) $locked, (int) $item_id, (int) $user_id, (int) $course_id );
	}

	/**
	 * Why an item is locked, shown to the student.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @param int $item_id   Lesson or quiz id.
	 * @return string
	 */
	public function lock_message( $user_id, $course_id, $item_id ) {
		/**
		 * Filters the message that explains why an item is locked.
		 *
		 * @param string $message   Message.
		 * @param int    $item_id   Lesson or quiz id.
		 * @param int    $user_id   User id.
		 * @param int    $course_id Course id.
		 */
		return (string) apply_filters( 'shapelms_item_lock_message', __( 'Complete the previous lessons to unlock', 'shapelms' ), (int) $item_id, (int) $user_id, (int) $course_id );
	}

	/**
	 * Instructors of the course and LMS managers are never locked out.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	private function bypasses_locks( $user_id, $course_id ) {
		return user_can( $user_id, Capabilities::MANAGE ) || InstructorService::is_course_instructor( $user_id, $course_id );
	}

	/**
	 * Item the user should resume at.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return int
	 */
	public function resume_item( $user_id, $course_id ) {
		$summary = $this->summary( $user_id, $course_id );

		if ( $summary['next_item'] ) {
			return $summary['next_item'];
		}

		if ( $summary['last_item'] ) {
			return $summary['last_item'];
		}

		return Curriculum::first_item( $course_id );
	}

	/**
	 * Updates last activity on the enrollment.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return void
	 */
	private function touch( $user_id, $course_id ) {
		$enrollment = $this->enrollments->get( $user_id, $course_id );

		if ( $enrollment ) {
			$this->enrollments->update( (int) $enrollment->id, array( 'last_activity_at' => shapelms_now() ) );
		}
	}
}
