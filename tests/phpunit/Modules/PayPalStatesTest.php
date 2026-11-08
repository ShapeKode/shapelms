<?php
/**
 * PayPal payment states: pending, completed, refused, and a buyer who left before the end.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Database\OrderRepository;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\OrderService;

/**
 * Webhook events and the confirm step against a faked PayPal API.
 */
class ShapeLMS_PayPalStatesTest extends ShapeLMS_TestCase {

	/**
	 * Student.
	 *
	 * @var int
	 */
	private $student;

	/**
	 * Paid course.
	 *
	 * @var int
	 */
	private $course;

	/**
	 * Order id.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * What the faked PayPal answers on /capture.
	 *
	 * @var array<string, mixed>
	 */
	private $capture = array();

	/**
	 * What the faked PayPal answers when an order is read.
	 *
	 * @var array<string, mixed>
	 */
	private $details = array();

	/**
	 * Status code of a capture.
	 *
	 * @var int
	 */
	private $capture_code = 200;

	/**
	 * How many captures were requested.
	 *
	 * @var int
	 */
	private $captures = 0;

	/**
	 * A pending PayPal order for a $40 course, and PayPal's API faked.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->set_setting( 'commerce', 'paypal_enabled', true );
		$this->set_setting( 'commerce', 'paypal_client_id', 'id' );
		$this->set_setting( 'commerce', 'paypal_secret', 'secret' );
		$this->set_setting( 'commerce', 'paypal_webhook_id', 'WH1' );

		$this->student = $this->make_student();
		$this->course  = $this->make_course(
			array(
				'meta' => array(
					'price_type' => 'paid',
					'price'      => 40,
				),
			)
		);

		$order          = $this->service( OrderService::class )->create( $this->student, array( $this->course ), 'paypal' );
		$this->order_id = (int) $order->id;
		$this->service( OrderRepository::class )->update( $this->order_id, array( 'transaction_id' => 'PP1' ) );

		add_filter( 'pre_http_request', array( $this, 'fake_paypal' ), 10, 3 );
	}

	/**
	 * Removes the fake.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_paypal' ), 10 );
		parent::tear_down();
	}

	/**
	 * Answers PayPal's API calls.
	 *
	 * @param mixed                $pre  Short-circuit value.
	 * @param array<string, mixed> $args Request args.
	 * @param string               $url  URL.
	 * @return array<string, mixed>
	 */
	public function fake_paypal( $pre, $args, $url ) {
		$body = array( 'access_token' => 'token' );
		$code = 200;

		if ( false !== strpos( $url, 'verify-webhook-signature' ) ) {
			$body = array( 'verification_status' => 'SUCCESS' );
		} elseif ( false !== strpos( $url, '/capture' ) ) {
			++$this->captures;
			$body = $this->capture;
			$code = $this->capture_code;
		} elseif ( false !== strpos( $url, '/v2/checkout/orders/' ) ) {
			$body = $this->details;
		}

		return array(
			'response' => array( 'code' => $code ),
			'body'     => wp_json_encode( $body ),
		);
	}

	/**
	 * PayPal's response to a capture.
	 *
	 * @param string $status Capture status.
	 * @return array<string, mixed>
	 */
	private function captured( $status ) {
		return array(
			'status'         => 'COMPLETED',
			'purchase_units' => array(
				array(
					'payments' => array(
						'captures' => array(
							array(
								'id'     => 'CAP1',
								'status' => $status,
								'amount' => array(
									'value'         => '40.00',
									'currency_code' => $this->order()->currency,
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Current order row.
	 *
	 * @return object
	 */
	private function order() {
		return $this->service( OrderRepository::class )->find( $this->order_id );
	}

	/**
	 * Whether the student got the course.
	 *
	 * @return bool
	 */
	private function enrolled() {
		return $this->service( EnrollmentService::class )->is_enrolled( $this->student, $this->course );
	}

	/**
	 * Delivers a webhook event.
	 *
	 * @param string               $type     Event type.
	 * @param array<string, mixed> $resource Resource.
	 * @return void
	 */
	private function event( $type, array $resource ) {
		$request = new WP_REST_Request( 'POST', '/shapelms/v1/webhooks/paypal' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'event_type' => $type,
					'resource'   => $resource,
				)
			)
		);

		$this->assertSame( 200, rest_do_request( $request )->get_status() );
	}

	/**
	 * A capture event.
	 *
	 * @param string $type   Event suffix.
	 * @param string $amount Amount.
	 * @param string $currency Currency ('' = the order's).
	 * @return void
	 */
	private function capture_event( $type, $amount = '40.00', $currency = '' ) {
		$this->event(
			'PAYMENT.CAPTURE.' . $type,
			array(
				'id'        => 'CAP1',
				'custom_id' => (string) $this->order_id,
				'amount'    => array(
					'value'         => $amount,
					'currency_code' => $currency ? $currency : $this->order()->currency,
				),
			)
		);
	}

	/**
	 * Sends the buyer's confirm step.
	 *
	 * @return WP_REST_Response
	 */
	private function confirm() {
		wp_set_current_user( $this->student );
		$request = new WP_REST_Request( 'POST', '/shapelms/v1/checkout/confirm' );
		$request->set_param( 'order_key', $this->order()->order_key );
		$request->set_param( 'data', array( 'paypal_order_id' => 'PP1' ) );

		return rest_do_request( $request );
	}

	/**
	 * Money that arrives later opens the course by itself. This is the delayed payment case.
	 */
	public function test_pending_then_completed_opens_the_course() {
		$this->capture = $this->captured( 'PENDING' );
		$response      = $this->confirm();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['completed'] );
		$this->assertStringContainsString( $this->order()->order_key, $response->get_data()['redirect'] );
		$this->assertSame( 'on-hold', $this->order()->status );
		$this->assertFalse( $this->enrolled() );

		// Waiting orders block a second purchase, so the buyer cannot pay twice.
		$this->assertNotNull( $this->service( OrderService::class )->open_order( $this->student, $this->course ) );

		$this->capture_event( 'COMPLETED' );

		$this->assertSame( 'completed', $this->order()->status );
		$this->assertSame( 'CAP1', $this->order()->transaction_id );
		$this->assertTrue( $this->enrolled() );
	}

	/**
	 * A refused capture is a plain error, and the buyer can try again.
	 */
	public function test_declined_capture_stays_payable() {
		$this->capture = $this->captured( 'DECLINED' );

		$this->assertSame( 402, $this->confirm()->get_status() );
		$this->assertSame( 'pending', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}

	/**
	 * The buyer's money is gone even if the capture answer is short: the details are fetched.
	 */
	public function test_short_capture_answer_reads_the_details() {
		$this->capture = array( 'status' => 'COMPLETED' );
		$this->details = $this->captured( 'COMPLETED' );

		$this->assertSame( 200, $this->confirm()->get_status() );
		$this->assertSame( 'completed', $this->order()->status );
		$this->assertTrue( $this->enrolled() );
	}

	/**
	 * If the details cannot be read either, the order waits instead of telling a buyer who paid that it failed.
	 */
	public function test_unreadable_capture_waits() {
		$this->capture = array( 'status' => 'COMPLETED' );
		$this->details = array( 'status' => 'COMPLETED' );

		$this->assertSame( 200, $this->confirm()->get_status() );
		$this->assertSame( 'on-hold', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}

	/**
	 * PayPal's reason decides what the buyer reads.
	 */
	public function test_buyer_reads_the_reason_for_a_refusal() {
		$this->capture_code = 422;
		$this->capture      = array(
			'message' => 'The requested action could not be performed, semantically incorrect, or failed business validation.',
			'details' => array( array( 'issue' => 'INSTRUMENT_DECLINED' ) ),
		);
		$this->details      = array( 'status' => 'APPROVED' );

		$response = $this->confirm();

		$this->assertSame( 402, $response->get_status() );
		$this->assertStringContainsString( 'declined this payment method', $response->as_error()->get_error_message() );
		$this->assertSame( 'pending', $this->order()->status );
	}

	/**
	 * A delayed payment that fails ends the order.
	 */
	public function test_pending_then_denied_fails_the_order() {
		$this->service( OrderService::class )->hold( $this->order_id );
		$this->capture_event( 'DENIED' );

		$this->assertSame( 'failed', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}

	/**
	 * A late failure from an old session does not take a paid course away.
	 */
	public function test_failure_never_undoes_a_paid_order() {
		$this->service( OrderService::class )->complete( $this->order_id, 'CAP1' );

		foreach ( array( 'DENIED', 'DECLINED', 'PENDING' ) as $type ) {
			$this->capture_event( $type );
			$this->assertSame( 'completed', $this->order()->status, $type );
		}

		$this->assertTrue( $this->enrolled() );
	}

	/**
	 * A completed event for too little money or the wrong currency changes nothing.
	 */
	public function test_wrong_amount_or_currency_is_ignored() {
		$this->capture_event( 'COMPLETED', '1.00' );
		$this->assertSame( 'pending', $this->order()->status );

		$this->capture_event( 'COMPLETED', '40.00', 'XXX' );
		$this->assertSame( 'pending', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}

	/**
	 * Buyer approved and closed the tab: PayPal's event takes the money and opens the course.
	 */
	public function test_approved_event_captures_for_a_buyer_who_left() {
		$this->capture = $this->captured( 'COMPLETED' );
		$this->event(
			'CHECKOUT.ORDER.APPROVED',
			array(
				'id'             => 'PP1',
				'purchase_units' => array( array( 'custom_id' => (string) $this->order_id ) ),
			)
		);

		$this->assertSame( 1, $this->captures );
		$this->assertSame( 'completed', $this->order()->status );
		$this->assertTrue( $this->enrolled() );
	}

	/**
	 * The same event for a capture that stays pending leaves the order waiting.
	 */
	public function test_approved_event_with_pending_capture_waits() {
		$this->capture = $this->captured( 'PENDING' );
		$this->event(
			'CHECKOUT.ORDER.APPROVED',
			array(
				'id'             => 'PP1',
				'purchase_units' => array( array( 'custom_id' => (string) $this->order_id ) ),
			)
		);

		$this->assertSame( 'on-hold', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}

	/**
	 * An event naming another PayPal order, or a paid order, captures nothing.
	 */
	public function test_approved_event_needs_the_right_order() {
		$this->capture = $this->captured( 'COMPLETED' );
		$units         = array( array( 'custom_id' => (string) $this->order_id ) );

		$this->event(
			'CHECKOUT.ORDER.APPROVED',
			array(
				'id'             => 'OTHER',
				'purchase_units' => $units,
			)
		);
		$this->assertSame( 0, $this->captures );
		$this->assertSame( 'pending', $this->order()->status );

		$this->service( OrderService::class )->complete( $this->order_id, 'CAP1' );
		$this->event(
			'CHECKOUT.ORDER.APPROVED',
			array(
				'id'             => 'PP1',
				'purchase_units' => $units,
			)
		);
		$this->assertSame( 0, $this->captures );
	}

	/**
	 * Orders of other gateways are out of reach.
	 */
	public function test_other_gateway_order_is_ignored() {
		$this->service( OrderRepository::class )->update( $this->order_id, array( 'gateway' => 'stripe' ) );
		$this->capture_event( 'COMPLETED' );

		$this->assertSame( 'pending', $this->order()->status );
	}

	/**
	 * Without a webhook id no event can be verified, so none changes an order.
	 */
	public function test_unverified_events_change_nothing() {
		$this->set_setting( 'commerce', 'paypal_webhook_id', '' );
		$this->capture_event( 'COMPLETED' );

		$this->assertSame( 'pending', $this->order()->status );
		$this->assertFalse( $this->enrolled() );
	}
}
