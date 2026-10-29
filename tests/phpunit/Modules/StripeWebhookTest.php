<?php
/**
 * Stripe webhook signature checks and the add-on event action.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Modules\Checkout\StripeGateway;

/**
 * Only correctly signed events reach add-ons.
 */
class StripeWebhookTest extends ShapeLMS_TestCase {

	/**
	 * Builds a webhook request.
	 *
	 * @param string $payload   Body.
	 * @param string $signature Stripe-Signature header.
	 * @return WP_REST_Request
	 */
	private function request( $payload, $signature ) {
		$request = new WP_REST_Request( 'POST', '/shapelms/v1/webhooks/stripe' );
		$request->set_body( $payload );
		$request->set_header( 'stripe-signature', $signature );

		return $request;
	}

	/**
	 * A signed event fires shapelms_stripe_webhook_event; a forged one does not.
	 */
	public function test_event_action_only_for_valid_signatures() {
		$this->set_setting( 'commerce', 'stripe_webhook_secret', 'whsec_unit' );

		$seen = array();
		$spy  = static function ( $event ) use ( &$seen ) {
			$seen[] = $event['type'];
		};
		add_action( 'shapelms_stripe_webhook_event', $spy );

		$payload = wp_json_encode(
			array(
				'type' => 'invoice.paid',
				'data' => array( 'object' => array( 'id' => 'in_1' ) ),
			)
		);
		$time    = time();
		$gateway = new StripeGateway();

		$ok = $gateway->webhook( $this->request( $payload, 't=' . $time . ',v1=' . hash_hmac( 'sha256', $time . '.' . $payload, 'whsec_unit' ) ) );
		$this->assertIsArray( $ok );
		$this->assertSame( array( 'invoice.paid' ), $seen );

		$forged = $gateway->webhook( $this->request( $payload, 't=' . $time . ',v1=' . hash_hmac( 'sha256', $time . '.' . $payload, 'wrong' ) ) );
		$this->assertWPError( $forged );

		$old = $gateway->webhook( $this->request( $payload, 't=' . ( $time - 3600 ) . ',v1=' . hash_hmac( 'sha256', ( $time - 3600 ) . '.' . $payload, 'whsec_unit' ) ) );
		$this->assertWPError( $old );

		$this->assertSame( array( 'invoice.paid' ), $seen );
		remove_action( 'shapelms_stripe_webhook_event', $spy );
	}
}
