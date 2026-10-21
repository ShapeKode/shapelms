<?php
/**
 * Payment gateway interface.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;

/**
 * A gateway creates a payment for an order, confirms it server-side and handles webhooks.
 */
interface GatewayInterface {

	/**
	 * Gateway id.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the gateway is configured and switched on.
	 *
	 * @return bool
	 */
	public function enabled();

	/**
	 * Data the checkout JS needs (public keys, SDK URL, test mode).
	 *
	 * @return array<string, string>
	 */
	public function client_data();

	/**
	 * Creates a payment for the order. Returns data the client uses to complete it.
	 *
	 * @param object $order Order row.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_payment( $order );

	/**
	 * Verifies with the provider that the payment succeeded and returns the transaction id.
	 *
	 * @param object               $order Order row.
	 * @param array<string, mixed> $data  Client data (payment intent id, PayPal order id).
	 * @return string|\WP_Error Transaction id.
	 */
	public function confirm( $order, array $data );

	/**
	 * Handles a provider webhook. Returns the order id to complete, 0 to ignore, or WP_Error.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{order_id: int, transaction_id: string, action: string}|\WP_Error
	 */
	public function webhook( WP_REST_Request $request );
}
