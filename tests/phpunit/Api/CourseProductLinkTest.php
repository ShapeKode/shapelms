<?php
/**
 * Linking a course to a WooCommerce product.
 *
 * @package ShapeLMS
 */

/**
 * Saving a course overwrites its product, so the link is guarded.
 */
class ShapeLMS_CourseProductLinkTest extends ShapeLMS_TestCase {

	/**
	 * Registers the product post type, which WooCommerce adds on a real site.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		register_post_type( 'product' );
	}

	/**
	 * Removes the post type again.
	 *
	 * @return void
	 */
	public function tear_down() {
		unregister_post_type( 'product' );
		parent::tear_down();
	}

	/**
	 * Creates a product owned by an administrator.
	 *
	 * @param int $course_id Course it already sells (0 = none).
	 * @return int
	 */
	private function make_product( $course_id = 0 ) {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_author' => $this->make_instructor(),
			)
		);

		if ( $course_id ) {
			update_post_meta( $id, '_shapelms_course_id', $course_id );
		}

		return $id;
	}

	/**
	 * Sends a product id for a course.
	 *
	 * @param int $user_id   User.
	 * @param int $course_id Course.
	 * @param int $product   Product id.
	 * @return WP_REST_Response
	 */
	private function link( $user_id, $course_id, $product ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/courses/' . $course_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'meta' => array( 'woo_product_id' => $product ) ) ) );

		return rest_do_request( $request );
	}

	/**
	 * An instructor's own course.
	 *
	 * @param int $instructor Instructor.
	 * @return int
	 */
	private function course_of( $instructor ) {
		$course_id = $this->make_course();
		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_author' => $instructor,
			)
		);

		return $course_id;
	}

	/**
	 * An instructor cannot point a course at a product they cannot edit, under any spelling.
	 */
	public function test_instructor_cannot_take_a_product() {
		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$course_id  = $this->course_of( $instructor );
		$product    = $this->make_product();

		$this->assertSame( 403, $this->link( $instructor, $course_id, $product )->get_status() );
		$this->assertSame( '', get_post_meta( $course_id, '_shapelms_woo_product_id', true ) );

		wp_set_current_user( $instructor );
		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/courses/' . $course_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'meta' => array( '_shapelms_woo_product_id' => $product ) ) ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	/**
	 * A product sells one course only, as in Tutor LMS.
	 */
	public function test_product_of_another_course_is_refused() {
		$other     = $this->make_course();
		$course_id = $this->make_course();
		$response  = $this->link( $this->make_instructor(), $course_id, $this->make_product( $other ) );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'shapelms_product_linked', $response->as_error()->get_error_code() );
	}

	/**
	 * Something that is not a product is refused.
	 */
	public function test_invalid_product_is_refused() {
		$course_id = $this->make_course();

		$this->assertSame( 400, $this->link( $this->make_instructor(), $course_id, $course_id )->get_status() );
	}

	/**
	 * Someone who can edit the product can link it; sending the current value is not a change.
	 */
	public function test_manager_links_a_free_product() {
		$course_id = $this->make_course();
		$product   = $this->make_product( $course_id );
		$manager   = $this->make_instructor();

		$this->assertSame( 200, $this->link( $manager, $course_id, $product )->get_status() );
		$this->assertSame( $product, (int) get_post_meta( $course_id, '_shapelms_woo_product_id', true ) );

		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$own        = $this->course_of( $instructor );
		update_post_meta( $own, '_shapelms_woo_product_id', $product );
		$this->assertSame( 200, $this->link( $instructor, $own, $product )->get_status() );
	}

	/**
	 * An instructor cannot remove the link either.
	 */
	public function test_instructor_cannot_unlink() {
		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$course_id  = $this->course_of( $instructor );
		$product    = $this->make_product( $course_id );
		update_post_meta( $course_id, '_shapelms_woo_product_id', $product );

		$this->assertSame( 403, $this->link( $instructor, $course_id, 0 )->get_status() );
		$this->assertSame( $product, (int) get_post_meta( $course_id, '_shapelms_woo_product_id', true ) );
	}
}
