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
					'item_type'  => PostTypes::QUIZ === get_post_type( $item_id ) ? 'quiz' : 'lesson',
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
	 * @return array{total: int, done: int, percent: int, completed: bool, statuses: array<int, string>, last_item: int, next_item: int}
	 */
	public function summary( $user_id, $course_id ) {
		$items    = Curriculum::items( $course_id );
		$statuses = $this->progress->statuses_for_course( $user_id, $course_id );
		$total    = count( $items );
		$done     = 0;
		$next     = 0;

		foreach ( $items as $item ) {
			$id = (int) $item['id'];
			if ( isset( $statuses[ $id ] ) && 'completed' === $statuses[ $id ] ) {
				++$done;
			} elseif ( ! $next ) {
				$next = $id;
			}
		}

		$percent = $total > 0 ? (int) floor( ( $done / $total ) * 100 ) : 0;

		return array(
			'total'     => $total,
			'done'      => $done,
			'percent'   => $percent,
			'completed' => $total > 0 && $done >= $total,
			'statuses'  => $statuses,
			'last_item' => $this->progress->last_item( $user_id, $course_id ),
			'next_item' => $next,
		);
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
