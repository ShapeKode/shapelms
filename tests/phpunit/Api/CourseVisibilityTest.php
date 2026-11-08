<?php
/**
 * Public, private and password-protected courses.
 *
 * @package ShapeLMS
 */

/**
 * Course visibility through the builder endpoint and the access rules behind it.
 */
class ShapeLMS_CourseVisibilityTest extends ShapeLMS_TestCase {

	/**
	 * Clears the password cookie.
	 */
	public function tear_down() {
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		parent::tear_down();
	}

	/**
	 * Sends a course update as a user.
	 *
	 * @param int                  $user_id   User.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $body      Body.
	 * @return WP_REST_Response
	 */
	private function put( $user_id, $course_id, array $body ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/courses/' . $course_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * Reads a course as a user.
	 *
	 * @param int $user_id   User (0 for a visitor).
	 * @param int $course_id Course.
	 * @return WP_REST_Response
	 */
	private function read( $user_id, $course_id ) {
		wp_set_current_user( $user_id );

		return rest_do_request( new WP_REST_Request( 'GET', '/shapelms/v1/courses/' . $course_id ) );
	}

	/**
	 * Sets the cookie core sets after the visitor typed a password.
	 *
	 * @param string $password Password.
	 * @return void
	 */
	private function type_password( $password ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( $password );
	}

	/**
	 * An empty password never lifts or sets the protection.
	 */
	public function test_password_cannot_be_empty() {
		$course_id = $this->make_course();
		$admin     = $this->make_instructor();

		$this->assertSame(
			400,
			$this->put(
				$admin,
				$course_id,
				array(
					'visibility' => 'password',
					'password'   => '   ',
				)
			)->get_status()
		);
		$this->assertSame( 'public', \ShapeLMS\Services\CourseService::visibility( $course_id ) );

		$response = $this->put(
			$admin,
			$course_id,
			array(
				'visibility' => 'password',
				'password'   => ' secret ',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'password', $response->get_data()['visibility'] );
		$this->assertSame( 'secret', $response->get_data()['password'] );
		$this->assertSame( 'publish', get_post_status( $course_id ) );
	}

	/**
	 * Locked courses hide their content and block enrolling until the password is typed.
	 */
	public function test_locked_course_hides_content_and_blocks_enrollment() {
		$course_id = $this->make_course();
		$student   = $this->make_student();
		wp_update_post(
			array(
				'ID'            => $course_id,
				'post_password' => 'secret',
				'post_content'  => 'Behind the form',
			)
		);

		$data = $this->read( 0, $course_id )->get_data();
		$this->assertSame( '', $data['content'] );
		$this->assertSame( array(), $data['curriculum'] );
		$this->assertArrayNotHasKey( 'password', $data );

		$enrollment = $this->service( \ShapeLMS\Services\EnrollmentService::class );
		wp_set_current_user( $student );
		$this->assertWPError( $enrollment->enroll_free( $student, $course_id ) );

		$this->type_password( 'wrong' );
		$this->assertWPError( $enrollment->enroll_free( $student, $course_id ) );

		$this->type_password( 'secret' );
		$this->assertIsInt( $enrollment->enroll_free( $student, $course_id ) );
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		// Once enrolled the student no longer needs the cookie.
		$this->assertSame( 'Behind the form', $this->read( $student, $course_id )->get_data()['content'] );
	}

	/**
	 * Private courses open only for managers, instructors and students already in them.
	 */
	public function test_private_course_access() {
		$course_id = $this->make_course();
		$admin     = $this->make_instructor();
		$student   = $this->make_student();
		$outsider  = $this->make_student();
		$this->service( \ShapeLMS\Services\EnrollmentService::class )->enroll( $student, $course_id, 'manual' );

		$response = $this->put( $admin, $course_id, array( 'visibility' => 'private' ) );
		$this->assertSame( 'private', $response->get_data()['status'] );

		$this->assertSame( 404, $this->read( 0, $course_id )->get_status() );
		$this->assertSame( 404, $this->read( $outsider, $course_id )->get_status() );
		$this->assertSame( 200, $this->read( $student, $course_id )->get_status() );
		$this->assertWPError( $this->service( \ShapeLMS\Services\EnrollmentService::class )->enroll_free( $outsider, $course_id ) );
	}

	/**
	 * Leaving private waits as a draft, unless the same save publishes the course.
	 */
	public function test_leaving_private_does_not_publish() {
		$course_id = $this->make_course( array( 'status' => 'private' ) );
		$admin     = $this->make_instructor();

		$this->assertSame( 'draft', $this->put( $admin, $course_id, array( 'visibility' => 'public' ) )->get_data()['status'] );

		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_status' => 'private',
			)
		);
		$response = $this->put(
			$admin,
			$course_id,
			array(
				'visibility' => 'public',
				'status'     => 'publish',
			)
		);
		$this->assertSame( 'publish', $response->get_data()['status'] );
	}

	/**
	 * An instructor who cannot publish cannot go private; a password is fine.
	 */
	public function test_instructor_without_publish_right() {
		$course_id  = $this->make_course();
		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_author' => $instructor,
			)
		);

		$this->assertSame( 403, $this->put( $instructor, $course_id, array( 'visibility' => 'private' ) )->get_status() );
		$this->assertSame( 'publish', get_post_status( $course_id ) );

		$response = $this->put(
			$instructor,
			$course_id,
			array(
				'visibility' => 'password',
				'password'   => 'secret',
			)
		);
		$this->assertSame( 200, $response->get_status() );
	}
}
