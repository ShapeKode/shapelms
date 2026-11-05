<?php
/**
 * Cart service tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Services\CartService;
use ShapeLMS\Services\EnrollmentService;

/**
 * What can enter the cart and what stays in it.
 */
class ShapeLMS_CartServiceTest extends ShapeLMS_TestCase {

	/**
	 * Creates a paid course.
	 *
	 * @return int
	 */
	private function paid_course() {
		return $this->make_course(
			array(
				'meta' => array(
					'price_type' => 'paid',
					'price'      => 40,
				),
			)
		);
	}

	/**
	 * The cart is used only when the "Buy Now" setting is switched off.
	 */
	public function test_cart_is_off_until_buy_now_is_disabled() {
		$this->assertFalse( CartService::enabled() );

		update_option( 'shapelms_settings', array( 'commerce' => array( 'buy_now' => false ) ) );
		\ShapeLMS\Core\Settings::flush();

		$this->assertTrue( CartService::enabled() );
	}

	/**
	 * A course is stored once and can be removed.
	 */
	public function test_add_and_remove() {
		$user   = self::factory()->user->create();
		$course = $this->paid_course();
		$cart   = $this->service( CartService::class );

		$this->assertTrue( $cart->add( $user, $course ) );
		$this->assertTrue( $cart->add( $user, $course ) );
		$this->assertSame( array( $course ), $cart->course_ids( $user ) );

		$cart->remove( $user, $course );
		$this->assertSame( array(), $cart->course_ids( $user ) );
	}

	/**
	 * Free courses and courses the student owns cannot enter the cart.
	 */
	public function test_refuses_free_and_owned_courses() {
		$user = self::factory()->user->create();
		$paid = $this->paid_course();
		$cart = $this->service( CartService::class );

		$this->service( EnrollmentService::class )->enroll( $user, $paid, 'manual' );

		$this->assertWPError( $cart->add( $user, $this->make_course() ) );
		$this->assertWPError( $cart->add( $user, $paid ) );
	}

	/**
	 * A course that can no longer be bought drops out of the list, so checkout never refuses the order.
	 */
	public function test_unpublished_course_leaves_the_list() {
		$user   = self::factory()->user->create();
		$course = $this->paid_course();
		$cart   = $this->service( CartService::class );

		$cart->add( $user, $course );
		wp_update_post(
			array(
				'ID'          => $course,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), $cart->course_ids( $user ) );
	}
}
