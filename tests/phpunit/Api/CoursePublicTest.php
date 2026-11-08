<?php
/**
 * Public courses.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Services\CourseService;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ProgressService;
use ShapeLMS\Services\QuizService;

/**
 * Which courses can be read without enrolling, and what stays behind enrollment.
 */
class ShapeLMS_CoursePublicTest extends ShapeLMS_TestCase {

	/**
	 * A free, published course with the public flag.
	 *
	 * @return int
	 */
	private function public_course() {
		$course_id = $this->make_course();
		update_post_meta( $course_id, '_shapelms_public', true );

		return $course_id;
	}

	/**
	 * Saves course fields through the builder endpoint.
	 *
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $body      Body.
	 * @return WP_REST_Response
	 */
	private function put( $course_id, array $body ) {
		wp_set_current_user( $this->make_instructor() );
		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/courses/' . $course_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * The flag alone is not enough: anything that closes the course wins over it.
	 */
	public function test_only_free_live_courses_are_public() {
		$course_id = $this->public_course();
		$this->assertTrue( CourseService::is_public( $course_id ) );

		update_post_meta( $course_id, '_shapelms_price_type', 'paid' );
		update_post_meta( $course_id, '_shapelms_price', 49 );
		$this->assertFalse( CourseService::is_public( $course_id ) );
		delete_post_meta( $course_id, '_shapelms_price_type' );

		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			wp_update_post(
				array(
					'ID'          => $course_id,
					'post_status' => $status,
				)
			);
			$this->assertFalse( CourseService::is_public( $course_id ), $status );
		}

		wp_update_post(
			array(
				'ID'            => $course_id,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$this->assertFalse( CourseService::is_public( $course_id ) );
	}

	/**
	 * Only lessons and quizzes open; add-on item types stay with enrolled students.
	 */
	public function test_add_on_items_stay_closed() {
		$course_id = $this->public_course();

		$this->assertTrue( CourseService::is_public_item( $course_id, 'lesson' ) );
		$this->assertTrue( CourseService::is_public_item( $course_id, 'quiz' ) );
		$this->assertFalse( CourseService::is_public_item( $course_id, 'assignment' ) );
		$this->assertSame( $this->items( $course_id )[0], CourseService::first_public_item( $course_id ) );
	}

	/**
	 * A reader is not a student: no access, no progress, no quiz attempt. Enrolling still works.
	 */
	public function test_reading_does_not_grant_enrollment() {
		$course_id = $this->public_course();
		$student   = $this->make_student();
		$lesson    = $this->lessons( $course_id )[0];
		$progress  = $this->service( ProgressService::class );
		$quizzes   = $this->service( QuizService::class );
		$enroll    = $this->service( EnrollmentService::class );

		$this->assertFalse( $enroll->can_access( $student, $course_id ) );
		$this->assertWPError( $progress->complete_lesson( $student, $lesson ) );
		$this->assertWPError( $quizzes->start( $student, $this->quiz( $course_id ) ) );

		$this->assertNotWPError( $enroll->enroll_free( $student, $course_id ) );
		$this->assertTrue( $enroll->can_access( $student, $course_id ) );
		$this->assertNotWPError( $progress->complete_lesson( $student, $lesson ) );
	}

	/**
	 * Going paid or hidden turns the flag off for good.
	 */
	public function test_saving_paid_or_hidden_clears_the_flag() {
		$course_id = $this->make_course();

		$data = $this->put( $course_id, array( 'meta' => array( 'public' => true ) ) )->get_data();
		$this->assertTrue( $data['is_public'] );

		$data = $this->put(
			$course_id,
			array(
				'meta' => array(
					'price_type' => 'paid',
					'price'      => 49,
				),
			)
		)->get_data();
		$this->assertFalse( $data['is_public'] );

		$this->put( $course_id, array( 'meta' => array( 'price_type' => 'free' ) ) );
		$this->assertFalse( CourseService::is_public( $course_id ) );

		$this->put( $course_id, array( 'meta' => array( 'public' => true ) ) );
		$this->assertTrue( CourseService::is_public( $course_id ) );

		$this->put(
			$course_id,
			array(
				'visibility' => 'password',
				'password'   => 'secret',
			)
		);
		$this->assertSame( '', get_post_meta( $course_id, '_shapelms_public', true ) );
	}
}
