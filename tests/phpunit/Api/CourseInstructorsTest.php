<?php
/**
 * Only the course author or a manager decides who else teaches a course.
 *
 * @package ShapeLMS
 */

/**
 * Co-instructor list permissions on PUT /courses/{id}.
 */
class CourseInstructorsTest extends ShapeLMS_TestCase {

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
	 * A co-instructor can edit the course but not change who teaches it; the author can.
	 */
	public function test_only_author_changes_instructors() {
		$course_id = $this->make_course();
		$author    = (int) get_post_field( 'post_author', $course_id );
		$co        = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$other     = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		update_post_meta( $course_id, '_shapelms_instructors', array( $co ) );

		$this->assertSame( 200, $this->put( $co, $course_id, array( 'title' => 'Edited by co-instructor' ) )->get_status() );
		$this->assertSame( 403, $this->put( $co, $course_id, array( 'meta' => array( 'instructors' => array( $co, $other ) ) ) )->get_status() );
		$this->assertSame( array( $co ), array_map( 'intval', (array) get_post_meta( $course_id, '_shapelms_instructors', true ) ) );

		$this->assertSame( 403, $this->put( $co, $course_id, array( 'meta' => array( '_shapelms_instructors' => array( $co, $other ) ) ) )->get_status() );
		$this->assertSame( 403, $this->put( $co, $course_id, array( 'meta' => array( 'INSTRUCTORS' => array( $co, $other ) ) ) )->get_status() );

		// Sending the unchanged list (as the builder may) is fine.
		$this->assertSame( 200, $this->put( $co, $course_id, array( 'meta' => array( 'instructors' => array( $co ) ) ) )->get_status() );

		$response = $this->put( $author, $course_id, array( 'meta' => array( 'instructors' => array( $co, $other ) ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['can_manage_instructors'] );
		$this->assertSame( array( $co, $other ), array_map( 'intval', (array) get_post_meta( $course_id, '_shapelms_instructors', true ) ) );
	}
}
