<?php
/**
 * Enrollment service tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;

/**
 * Enroll, unenroll, caps and prerequisites.
 */
class EnrollmentServiceTest extends ShapeLMS_TestCase {

	/**
	 * Enrolling in a free course creates an active row and adds the student role.
	 */
	public function test_enroll_free_course() {
		$course  = $this->make_course();
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );

		$id = $service->enroll_free( $student, $course );

		$this->assertIsInt( $id );
		$this->assertTrue( $service->is_enrolled( $student, $course ) );
		$this->assertTrue( $service->can_access( $student, $course ) );
		$this->assertContains( Capabilities::STUDENT, get_userdata( $student )->roles );

		$row = $service->get( $student, $course );
		$this->assertSame( 'active', $row->status );
		$this->assertSame( 'free', $row->source );
	}

	/**
	 * Paid courses cannot be joined through the free path.
	 */
	public function test_enroll_free_rejects_paid_course() {
		$course  = $this->make_course(
			array(
				'meta' => array(
					'price_type' => 'paid',
					'price'      => 20,
				),
			)
		);
		$student = $this->make_student();

		$result = $this->service( EnrollmentService::class )->enroll_free( $student, $course );

		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_not_free', $result->get_error_code() );
	}

	/**
	 * Enrolling twice returns the same row.
	 */
	public function test_enroll_is_idempotent() {
		$course  = $this->make_course();
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );

		$first  = $service->enroll( $student, $course, 'manual' );
		$second = $service->enroll( $student, $course, 'free' );

		$this->assertSame( $first, $second );
	}

	/**
	 * Unenroll closes access, reset reopens it.
	 */
	public function test_unenroll_and_reenroll() {
		$course  = $this->make_course();
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );

		$service->enroll( $student, $course );
		$this->assertTrue( $service->unenroll( $student, $course ) );
		$this->assertFalse( $service->is_enrolled( $student, $course ) );

		$service->enroll( $student, $course );
		$this->assertTrue( $service->is_enrolled( $student, $course ) );
	}

	/**
	 * Max students is enforced for self-enrollment but not for manual enrollment.
	 */
	public function test_course_capacity() {
		$course  = $this->make_course( array( 'meta' => array( 'max_students' => 1 ) ) );
		$service = $this->service( EnrollmentService::class );

		$service->enroll_free( $this->make_student(), $course );

		$blocked = $service->enroll_free( $this->make_student(), $course );
		$this->assertWPError( $blocked );
		$this->assertSame( 'shapelms_course_full', $blocked->get_error_code() );

		$manual = $service->enroll( $this->make_student(), $course, 'manual' );
		$this->assertIsInt( $manual );
	}

	/**
	 * Prerequisite courses must be completed before self-enrolling.
	 */
	public function test_prerequisites_block_enrollment_until_completed() {
		$first   = $this->make_course( array( 'title' => 'First' ) );
		$second  = $this->make_course( array( 'title' => 'Second' ) );
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );

		update_post_meta( $second, '_shapelms_prerequisites', array( $first ) );

		$this->assertSame( array( $first ), EnrollmentService::prerequisites( $second ) );
		$this->assertSame( array( $first ), $service->missing_prerequisites( $student, $second ) );

		$blocked = $service->enroll_free( $student, $second );
		$this->assertWPError( $blocked );
		$this->assertSame( 'shapelms_prerequisites', $blocked->get_error_code() );

		// Manual (admin) enrollment ignores prerequisites.
		$this->assertIsInt( $service->enroll( $student, $second, 'manual' ) );
		$service->unenroll( $student, $second );

		// Complete the first course.
		$service->enroll_free( $student, $first );
		$progress = $this->service( ProgressService::class );
		foreach ( $this->lessons( $first ) as $lesson ) {
			$progress->complete_lesson( $student, $lesson );
		}
		$this->set_setting( 'course', 'completion_requires_quizzes', false );
		$progress->complete_quiz( $student, $this->quiz( $first ), false );

		$this->assertSame( 'completed', $service->get( $student, $first )->status );
		$this->assertSame( array(), $service->missing_prerequisites( $student, $second ) );
		$this->assertIsInt( $service->enroll_free( $student, $second ) );
	}

	/**
	 * A buyer who has paid always gets access, even if the course filled up or prerequisites changed meanwhile.
	 */
	public function test_paid_order_enrollment_skips_capacity_and_prerequisites() {
		$first   = $this->make_course( array( 'title' => 'Intro' ) );
		$course  = $this->make_course( array( 'meta' => array( 'max_students' => 1 ) ) );
		$service = $this->service( EnrollmentService::class );
		update_post_meta( $course, '_shapelms_prerequisites', array( $first ) );
		$service->enroll( $this->make_student(), $course, 'manual' );

		$buyer = $this->make_student();
		$this->assertWPError( $service->enroll( $buyer, $course, 'order' ) );
		$this->assertIsInt( $service->enroll( $buyer, $course, 'order', 123 ) );
		$this->assertTrue( $service->is_enrolled( $buyer, $course ) );
	}

	/**
	 * Time-limited access that ran out: no access, not listed, and enrolling again reopens it with the
	 * progress kept (an add-on such as memberships sets expires_at).
	 */
	public function test_expired_access_reopens_and_keeps_progress() {
		$course   = $this->make_course();
		$student  = $this->make_student();
		$service  = $this->service( EnrollmentService::class );
		$progress = $this->service( ProgressService::class );
		$lesson   = $this->lessons( $course )[0];

		$id = $service->enroll( $student, $course, 'membership' );
		$this->assertIsInt( $id );
		$this->assertNotWPError( $progress->complete_lesson( $student, $lesson ) );

		shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class )->update( $id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );

		$this->assertFalse( $service->is_enrolled( $student, $course ) );
		$this->assertSame( array(), $service->for_user( $student ) );

		$this->assertSame( $id, $service->enroll( $student, $course, 'order', 77 ) );
		$this->assertTrue( $service->is_enrolled( $student, $course ) );

		$row = $service->get( $student, $course );
		$this->assertNull( $row->expires_at );
		$this->assertSame( 'order', $row->source );
		$this->assertSame( 77, (int) $row->order_id );
		$this->assertSame( 1, $progress->summary( $student, $course )['done'] );
	}

	/**
	 * Add-ons can withdraw an active enrollment through shapelms_is_enrolled.
	 */
	public function test_is_enrolled_filter() {
		$course  = $this->make_course();
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );
		$service->enroll( $student, $course, 'membership' );

		$deny = static function ( $enrolled, $row ) {
			return 'membership' === $row->source ? false : $enrolled;
		};
		add_filter( 'shapelms_is_enrolled', $deny, 10, 2 );
		$this->assertFalse( $service->is_enrolled( $student, $course ) );
		remove_filter( 'shapelms_is_enrolled', $deny, 10 );

		$this->assertTrue( $service->is_enrolled( $student, $course ) );
	}

	/**
	 * Unpublished or self-referencing prerequisites are ignored.
	 */
	public function test_prerequisites_ignore_invalid_ids() {
		$course = $this->make_course();
		$draft  = $this->make_course( array( 'status' => 'draft' ) );

		update_post_meta( $course, '_shapelms_prerequisites', array( $course, $draft, 999999 ) );

		$this->assertSame( array(), EnrollmentService::prerequisites( $course ) );
	}

	/**
	 * Expired enrollments are not active.
	 */
	public function test_expired_enrollment_is_not_enrolled() {
		$course  = $this->make_course();
		$student = $this->make_student();
		$service = $this->service( EnrollmentService::class );

		$id = $service->enroll( $student, $course );
		$this->service( \ShapeLMS\Database\EnrollmentRepository::class )->update( $id, array( 'expires_at' => '2000-01-01 00:00:00' ) );

		$this->assertFalse( $service->is_enrolled( $student, $course ) );
	}
}
