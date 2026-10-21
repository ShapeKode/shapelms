<?php
/**
 * Enrollment service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Events;
use ShapeLMS\Database\EnrollmentRepository;
use ShapeLMS\Database\ProgressRepository;

/**
 * Enrolls and unenrolls students and answers access questions.
 */
class EnrollmentService {

	/**
	 * Enrollments.
	 *
	 * @var EnrollmentRepository
	 */
	private $enrollments;

	/**
	 * Progress.
	 *
	 * @var ProgressRepository
	 */
	private $progress;

	/**
	 * Courses.
	 *
	 * @var CourseService
	 */
	private $courses;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository $enrollments Enrollments.
	 * @param ProgressRepository   $progress    Progress.
	 * @param CourseService        $courses     Courses.
	 */
	public function __construct( EnrollmentRepository $enrollments, ProgressRepository $progress, CourseService $courses ) {
		$this->enrollments = $enrollments;
		$this->progress    = $progress;
		$this->courses     = $courses;
	}

	/**
	 * Returns the enrollment row, or null.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return object|null
	 */
	public function get( $user_id, $course_id ) {
		return $this->enrollments->get( $user_id, $course_id );
	}

	/**
	 * Whether the user has an active or completed enrollment.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public function is_enrolled( $user_id, $course_id ) {
		$row = $this->get( $user_id, $course_id );

		if ( ! $row || ! in_array( $row->status, array( 'active', 'completed' ), true ) ) {
			return false;
		}

		if ( $row->expires_at && strtotime( $row->expires_at ) < time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the user can view course content (enrolled, instructor or admin).
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public function can_access( $user_id, $course_id ) {
		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, Capabilities::MANAGE ) || InstructorService::is_course_instructor( $user_id, $course_id ) ) {
			return true;
		}

		/**
		 * Filters whether a user can access a course. Membership add-ons hook here.
		 *
		 * @param bool $can       Current decision.
		 * @param int  $user_id   User id.
		 * @param int  $course_id Course id.
		 */
		return (bool) apply_filters( 'shapelms_can_access_course', $this->is_enrolled( $user_id, $course_id ), $user_id, $course_id );
	}

	/**
	 * Enrolls a user.
	 *
	 * @param int    $user_id   User id.
	 * @param int    $course_id Course id.
	 * @param string $source    manual|free|order|woocommerce|import.
	 * @param int    $order_id  Order id when the source is a purchase.
	 * @return int|\WP_Error Enrollment id.
	 */
	public function enroll( $user_id, $course_id, $source = 'manual', $order_id = 0 ) {
		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'shapelms_invalid_user', __( 'User not found.', 'shapelms' ) );
		}

		if ( ! CourseService::is_course( $course_id ) ) {
			return new \WP_Error( 'shapelms_invalid_course', __( 'Course not found.', 'shapelms' ) );
		}

		$existing = $this->get( $user_id, $course_id );

		if ( $existing && in_array( $existing->status, array( 'active', 'completed' ), true ) ) {
			return (int) $existing->id;
		}

		if ( 'manual' !== $source && 'import' !== $source && $this->courses->is_full( $course_id ) ) {
			return new \WP_Error( 'shapelms_course_full', __( 'This course is full.', 'shapelms' ) );
		}

		$data = array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'status'           => 'active',
			'source'           => sanitize_key( $source ),
			'order_id'         => (int) $order_id,
			'progress'         => 0,
			'started_at'       => shapelms_now(),
			'completed_at'     => null,
			'expires_at'       => null,
			'last_activity_at' => shapelms_now(),
		);

		if ( $existing ) {
			$this->enrollments->update( (int) $existing->id, $data );
			$id = (int) $existing->id;
		} else {
			$id = $this->enrollments->insert( $data );
		}

		if ( ! $id ) {
			return new \WP_Error( 'shapelms_enroll_failed', __( 'Could not enroll the user.', 'shapelms' ) );
		}

		if ( ! user_can( $user_id, Capabilities::EDIT_COURSES ) && ! in_array( Capabilities::STUDENT, (array) $user->roles, true ) ) {
			$user->add_role( Capabilities::STUDENT );
		}

		Events::fire(
			Events::COURSE_ENROLLED,
			$user_id,
			array(
				'course_id' => $course_id,
				'source'    => $source,
				'order_id'  => (int) $order_id,
			)
		);

		return $id;
	}

	/**
	 * Enrolls the current user in a free course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return int|\WP_Error
	 */
	public function enroll_free( $user_id, $course_id ) {
		$pricing = CourseService::pricing( $course_id );

		if ( ! $pricing['is_free'] ) {
			return new \WP_Error( 'shapelms_not_free', __( 'This course must be purchased.', 'shapelms' ) );
		}

		if ( 'publish' !== get_post_status( $course_id ) ) {
			return new \WP_Error( 'shapelms_not_published', __( 'This course is not available yet.', 'shapelms' ) );
		}

		return $this->enroll( $user_id, $course_id, 'free' );
	}

	/**
	 * Cancels an enrollment (keeps progress unless reset is true).
	 *
	 * @param int    $user_id   User id.
	 * @param int    $course_id Course id.
	 * @param string $status    cancelled|expired.
	 * @param bool   $reset     Delete progress rows too.
	 * @return bool
	 */
	public function unenroll( $user_id, $course_id, $status = 'cancelled', $reset = false ) {
		$row = $this->get( $user_id, $course_id );

		if ( ! $row ) {
			return false;
		}

		$this->enrollments->update(
			(int) $row->id,
			array(
				'status' => 'expired' === $status ? 'expired' : 'cancelled',
			)
		);

		if ( $reset ) {
			$this->progress->reset( $user_id, $course_id );
		}

		Events::fire( Events::COURSE_UNENROLLED, $user_id, array( 'course_id' => (int) $course_id ) );

		return true;
	}

	/**
	 * Resets progress and reopens a completed enrollment.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public function reset_progress( $user_id, $course_id ) {
		$row = $this->get( $user_id, $course_id );

		if ( ! $row ) {
			return false;
		}

		$this->progress->reset( $user_id, $course_id );

		return $this->enrollments->update(
			(int) $row->id,
			array(
				'status'       => 'active',
				'progress'     => 0,
				'completed_at' => null,
			)
		);
	}

	/**
	 * Unenrolls every student who bought through an order (refund).
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function revoke_order( $order_id ) {
		$rows = $this->enrollments->where( array( 'order_id' => (int) $order_id ) );

		foreach ( $rows as $row ) {
			$this->unenroll( (int) $row->user_id, (int) $row->course_id );
		}
	}

	/**
	 * Enrollment list for a user with course data.
	 *
	 * @param int    $user_id User id.
	 * @param string $status  active|completed|all.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_user( $user_id, $status = 'all' ) {
		$where = array( 'user_id' => (int) $user_id );

		if ( 'all' === $status ) {
			$where['status'] = array( 'active', 'completed' );
		} else {
			$where['status'] = sanitize_key( $status );
		}

		$rows = $this->enrollments->where(
			$where,
			array(
				'orderby' => 'last_activity_at',
				'order'   => 'DESC',
			)
		);

		$list = array();

		foreach ( $rows as $row ) {
			$course = $this->courses->to_array( (int) $row->course_id, false );
			if ( ! $course || 'trash' === $course['status'] ) {
				continue;
			}
			$list[] = array(
				'enrollment' => $this->row_to_array( $row ),
				'course'     => $course,
			);
		}

		return $list;
	}

	/**
	 * Converts a row to the API shape.
	 *
	 * @param object $row Row.
	 * @return array<string, mixed>
	 */
	public function row_to_array( $row ) {
		return array(
			'id'               => (int) $row->id,
			'user_id'          => (int) $row->user_id,
			'course_id'        => (int) $row->course_id,
			'status'           => (string) $row->status,
			'source'           => (string) $row->source,
			'order_id'         => (int) $row->order_id,
			'progress'         => (int) $row->progress,
			'started_at'       => (string) $row->started_at,
			'completed_at'     => $row->completed_at,
			'expires_at'       => $row->expires_at,
			'last_activity_at' => $row->last_activity_at,
		);
	}
}
