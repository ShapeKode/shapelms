<?php
/**
 * Changing a course's status from the admin course list.
 *
 * @package ShapeLMS
 */

/**
 * Status changes, the trash view and restoring from it.
 */
class ShapeLMS_CourseStatusTest extends ShapeLMS_TestCase {

	/**
	 * Sends a request as a user.
	 *
	 * @param int                  $user_id User.
	 * @param string               $method  Method.
	 * @param string               $path    Path below the namespace.
	 * @param array<string, mixed> $body    JSON body.
	 * @return WP_REST_Response
	 */
	private function call( $user_id, $method, $path, array $body = array() ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( $method, '/shapelms/v1/' . $path );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}

	/**
	 * Ids in the trash view of the course list.
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	private function trash_view( $user_id ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'GET', '/shapelms/v1/courses' );
		$request->set_param( 'status', 'trash' );

		return array_column( rest_do_request( $request )->get_data()['items'], 'id' );
	}

	/**
	 * Each status a manager picks is saved.
	 */
	public function test_manager_changes_status() {
		$course_id = $this->make_course();
		$admin     = $this->make_instructor();

		foreach ( array( 'draft', 'pending', 'private', 'publish' ) as $status ) {
			$response = $this->call( $admin, 'PUT', 'courses/' . $course_id, array( 'status' => $status ) );
			$this->assertSame( $status, $response->get_data()['status'] );
		}
	}

	/**
	 * A trashed course shows up in the trash view and comes back with its slug.
	 */
	public function test_restore_from_trash_brings_back_slug() {
		$course_id = $this->make_course();
		$admin     = $this->make_instructor();
		$slug      = get_post_field( 'post_name', $course_id );

		$this->call( $admin, 'DELETE', 'courses/' . $course_id );
		$this->assertSame( 'trash', get_post_status( $course_id ) );
		$this->assertContains( $course_id, $this->trash_view( $admin ) );

		$response = $this->call( $admin, 'PUT', 'courses/' . $course_id, array( 'status' => 'publish' ) );
		$this->assertSame( 'publish', $response->get_data()['status'] );
		$this->assertSame( $slug, get_post_field( 'post_name', $course_id ) );
		$this->assertNotContains( $course_id, $this->trash_view( $admin ) );
	}

	/**
	 * Instructors see only their own trash, and without the publish right a restore goes to review.
	 */
	public function test_instructor_trash_and_restore() {
		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$other      = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$course_id  = $this->make_course();
		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_author' => $instructor,
			)
		);
		update_post_meta( $course_id, '_shapelms_instructors', array() );

		$this->assertSame( 200, $this->call( $instructor, 'DELETE', 'courses/' . $course_id )->get_status() );
		$this->assertContains( $course_id, $this->trash_view( $instructor ) );
		$this->assertNotContains( $course_id, $this->trash_view( $other ) );

		$this->assertSame( 403, $this->call( $other, 'PUT', 'courses/' . $course_id, array( 'status' => 'draft' ) )->get_status() );
		$this->assertSame( 'trash', get_post_status( $course_id ) );

		$response = $this->call( $instructor, 'PUT', 'courses/' . $course_id, array( 'status' => 'publish' ) );
		$this->assertSame( 'pending', $response->get_data()['status'] );
	}

	/**
	 * Students cannot read the trash view.
	 */
	public function test_student_does_not_see_trash() {
		$course_id = $this->make_course();
		wp_trash_post( $course_id );

		$this->assertNotContains( $course_id, $this->trash_view( $this->make_student() ) );
	}
}
