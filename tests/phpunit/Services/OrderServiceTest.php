<?php
/**
 * Order service tests.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\OrderService;
use ShapeLMS\Services\ProgressService;

/**
 * Order creation, completion, refunds and prerequisites.
 */
class OrderServiceTest extends ShapeLMS_TestCase {

	/**
	 * Creates a paid course.
	 *
	 * @param float $price Price.
	 * @param float $sale  Sale price.
	 * @return int
	 */
	private function paid_course( $price = 40, $sale = 0 ) {
		return $this->make_course(
			array(
				'meta' => array(
					'price_type' => 'paid',
					'price'      => $price,
					'sale_price' => $sale,
				),
			)
		);
	}

	/**
	 * Only paid, published, not-yet-owned courses become order items; sale prices apply.
	 */
	public function test_create_builds_items_and_totals() {
		$paid    = $this->paid_course( 40, 25 );
		$free    = $this->make_course();
		$owned   = $this->paid_course( 10 );
		$student = $this->make_student();
		$this->service( EnrollmentService::class )->enroll( $student, $owned, 'manual' );

		$service = $this->service( OrderService::class );
		$row     = $service->create( $student, array( $paid, $free, $owned, $paid ), 'stripe' );

		$this->assertIsObject( $row );
		$order = $service->to_array( $row );
		$this->assertSame( 'pending', $order['status'] );
		$this->assertSame( 25.0, (float) $order['total'] );
		$this->assertCount( 1, $order['items'] );
		$this->assertSame( $paid, (int) $order['items'][0]['course_id'] );
	}

	/**
	 * Add-ons can sell their own order lines that grant several courses (bundles use this),
	 * and a refund revokes every course the line granted.
	 */
	public function test_custom_order_item_grants_several_courses() {
		$first      = $this->paid_course( 30 );
		$second     = $this->paid_course( 50 );
		$student    = $this->make_student();
		$package    = self::factory()->post->create( array( 'post_title' => 'Package' ) );
		$service    = $this->service( OrderService::class );
		$enrollment = $this->service( EnrollmentService::class );

		$item = static function ( $value, $id ) use ( $package ) {
			return $id === $package ? array(
				'course_id' => $package,
				'title'     => 'Package',
				'price'     => 60,
			) : $value;
		};
		$grant = static function ( $ids, $row ) use ( $package, $first, $second ) {
			return (int) $row->course_id === $package ? array( $first, $second ) : $ids;
		};
		add_filter( 'shapelms_order_item', $item, 10, 2 );
		add_filter( 'shapelms_order_item_course_ids', $grant, 10, 2 );

		$order = $service->create( $student, array( $package ), 'stripe' );
		$this->assertIsObject( $order );
		$this->assertSame( 60.0, (float) $order->total );

		$this->assertTrue( $service->complete( (int) $order->id, 'txn_pkg' ) );
		$this->assertTrue( $enrollment->is_enrolled( $student, $first ) );
		$this->assertTrue( $enrollment->is_enrolled( $student, $second ) );

		$this->assertTrue( $service->revoke( (int) $order->id ) );
		$this->assertFalse( $enrollment->is_enrolled( $student, $first ) );
		$this->assertFalse( $enrollment->is_enrolled( $student, $second ) );

		remove_filter( 'shapelms_order_item', $item, 10 );
		remove_filter( 'shapelms_order_item_course_ids', $grant, 10 );
	}

	/**
	 * An add-on can stop an order with an error, or skip an id.
	 */
	public function test_custom_order_item_error_and_skip() {
		$student = $this->make_student();
		$service = $this->service( OrderService::class );

		$stop = static function () {
			return new WP_Error( 'unit_stop', 'Stopped' );
		};
		add_filter( 'shapelms_order_item', $stop );
		$result = $service->create( $student, array( $this->paid_course() ), 'stripe' );
		remove_filter( 'shapelms_order_item', $stop );
		$this->assertWPError( $result );
		$this->assertSame( 'unit_stop', $result->get_error_code() );

		add_filter( 'shapelms_order_item', '__return_false' );
		$result = $service->create( $student, array( $this->paid_course() ), 'stripe' );
		remove_filter( 'shapelms_order_item', '__return_false' );
		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_empty_order', $result->get_error_code() );
	}

	/**
	 * Revoking an order tells add-ons, with the new status.
	 */
	public function test_revoke_fires_order_revoked() {
		$student = $this->make_student();
		$service = $this->service( OrderService::class );
		$order   = $service->create( $student, array( $this->paid_course() ), 'stripe' );
		$seen    = array();
		$spy     = static function ( $row, $status ) use ( &$seen ) {
			$seen[] = array( (int) $row->id, $status );
		};

		add_action( 'shapelms_order_revoked', $spy, 10, 2 );
		$service->complete( (int) $order->id );
		$service->set_status( (int) $order->id, 'cancelled' );
		remove_action( 'shapelms_order_revoked', $spy, 10 );

		$this->assertSame( array( array( (int) $order->id, 'cancelled' ) ), $seen );
	}

	/**
	 * Nothing to buy is an error.
	 */
	public function test_create_rejects_empty_cart() {
		$result = $this->service( OrderService::class )->create( $this->make_student(), array( $this->make_course() ), 'stripe' );

		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_empty_order', $result->get_error_code() );
	}

	/**
	 * Completing an order enrolls the buyer; revoking removes access.
	 */
	public function test_complete_enrolls_and_revoke_unenrolls() {
		$paid       = $this->paid_course();
		$student    = $this->make_student();
		$service    = $this->service( OrderService::class );
		$enrollment = $this->service( EnrollmentService::class );

		$order = $service->create( $student, array( $paid ), 'paypal' );
		$this->assertIsObject( $order );
		$this->assertFalse( $enrollment->is_enrolled( $student, $paid ) );

		$this->assertTrue( $service->complete( (int) $order->id, 'txn_1' ) );
		$this->assertTrue( $enrollment->is_enrolled( $student, $paid ) );
		$this->assertSame( 'order', $enrollment->get( $student, $paid )->source );

		$this->assertTrue( $service->revoke( (int) $order->id ) );
		$this->assertFalse( $enrollment->is_enrolled( $student, $paid ) );
	}

	/**
	 * A course with unmet prerequisites cannot be bought.
	 */
	public function test_create_rejects_unmet_prerequisites() {
		$first   = $this->make_course( array( 'title' => 'Intro' ) );
		$paid    = $this->paid_course();
		$student = $this->make_student();
		update_post_meta( $paid, '_shapelms_prerequisites', array( $first ) );

		$result = $this->service( OrderService::class )->create( $student, array( $paid ), 'stripe' );
		$this->assertWPError( $result );
		$this->assertSame( 'shapelms_prerequisites', $result->get_error_code() );

		$this->service( EnrollmentService::class )->enroll_free( $student, $first );
		$progress = $this->service( ProgressService::class );
		foreach ( $this->lessons( $first ) as $lesson ) {
			$progress->complete_lesson( $student, $lesson );
		}
		$progress->complete_quiz( $student, $this->quiz( $first ), true );

		$this->assertIsObject( $this->service( OrderService::class )->create( $student, array( $paid ), 'stripe' ) );
	}
}
