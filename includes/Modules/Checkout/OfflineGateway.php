<?php
/**
 * Base class for gateways that take the money outside the site.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Request;

/**
 * The order is placed on hold and the student gets access when an admin completes it.
 */
abstract class OfflineGateway implements GatewayInterface {

	/**
	 * Short text shown under the method at checkout.
	 *
	 * @return string
	 */
	abstract public function description();

	/**
	 * What the student has to do next, for the order page and the email. Plain text.
	 *
	 * @return string
	 */
	abstract public function instructions();

	/**
	 * Client data.
	 *
	 * @return array<string, string>
	 */
	public function client_data() {
		return array();
	}

	/**
	 * Nothing to create at a provider.
	 *
	 * @param object $order Order.
	 * @return array<string, mixed>
	 */
	public function create_payment( $order ) {
		return array();
	}

	/**
	 * Students cannot confirm an offline payment themselves.
	 *
	 * @param object               $order Order.
	 * @param array<string, mixed> $data  Data.
	 * @return WP_Error
	 */
	public function confirm( $order, array $data ) {
		return new WP_Error( 'shapelms_offline_payment', __( 'This order is completed by the site owner after the payment arrives.', 'shapelms' ) );
	}

	/**
	 * No provider, no webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		return new WP_Error( 'shapelms_offline_payment', __( 'This payment method has no webhook.', 'shapelms' ), array( 'status' => 400 ) );
	}
}
