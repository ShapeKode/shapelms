<?php
/**
 * PayPal gateway (Orders API v2 + JS SDK buttons).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Settings;
use WP_Error;
use WP_REST_Request;

/**
 * Creates and captures PayPal orders server-side.
 */
class PayPalGateway implements GatewayInterface {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'paypal';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'PayPal', 'shapelms' );
	}

	/**
	 * Enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) Settings::get( 'commerce', 'paypal_enabled', false ) && $this->client_id() && Settings::get( 'commerce', 'paypal_secret', '' );
	}

	/**
	 * Client id.
	 *
	 * @return string
	 */
	private function client_id() {
		return (string) Settings::get( 'commerce', 'paypal_client_id', '' );
	}

	/**
	 * API base.
	 *
	 * @return string
	 */
	private function base() {
		return Settings::get( 'commerce', 'paypal_sandbox', true ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Client data.
	 *
	 * @return array<string, string>
	 */
	public function client_data() {
		return array(
			'paypal-client' => $this->client_id(),
			'paypal-sdk'    => add_query_arg(
				array(
					'client-id' => $this->client_id(),
					'currency'  => \ShapeLMS\Services\CurrencyService::code(),
					'intent'    => 'capture',
				),
				'https://www.paypal.com/sdk/js'
			),
		);
	}

	/**
	 * OAuth token (cached).
	 *
	 * @return string|WP_Error
	 */
	private function token() {
		$cache_key = 'shapelms_paypal_token_' . md5( $this->client_id() . $this->base() );
		$cached    = get_transient( $cache_key );

		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$this->base() . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id() . ':' . Settings::get( 'commerce', 'paypal_secret', '' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth header required by PayPal OAuth.
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( empty( $json['access_token'] ) ) {
			return new WP_Error( 'shapelms_paypal', __( 'Could not authenticate with PayPal. Check your credentials.', 'shapelms' ) );
		}

		set_transient( $cache_key, $json['access_token'], max( 60, (int) ( $json['expires_in'] ?? 3600 ) - 60 ) );

		return $json['access_token'];
	}

	/**
	 * API request.
	 *
	 * @param string               $method   Method.
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $body     JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( $method, $endpoint, array $body = array() ) {
		$token = $this->token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_request(
			$this->base() . $endpoint,
			array(
				'method'  => $method,
				'headers' => array(
					'Authorization'     => 'Bearer ' . $token,
					'Content-Type'      => 'application/json',
					'PayPal-Request-Id' => wp_generate_uuid4(),
				),
				'body'    => $body ? wp_json_encode( $body ) : null,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$message = isset( $json['message'] ) ? sanitize_text_field( $json['message'] ) : __( 'PayPal error.', 'shapelms' );
			return new WP_Error( 'shapelms_paypal', $message );
		}

		return is_array( $json ) ? $json : array();
	}

	/**
	 * Creates a PayPal order.
	 *
	 * @param object $order Order.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_payment( $order ) {
		$result = $this->request(
			'POST',
			'/v2/checkout/orders',
			array(
				'intent'              => 'CAPTURE',
				'purchase_units'      => array(
					array(
						'reference_id' => (string) $order->id,
						'custom_id'    => (string) $order->id,
						'description'  => sprintf( /* translators: 1: order id, 2: site name */ __( 'Order #%1$d on %2$s', 'shapelms' ), $order->id, get_bloginfo( 'name' ) ),
						'amount'       => array(
							'currency_code' => strtoupper( $order->currency ),
							'value'         => number_format( (float) $order->total, 2, '.', '' ),
						),
					),
				),
				'application_context' => array(
					'shipping_preference' => 'NO_SHIPPING',
					'user_action'         => 'PAY_NOW',
					'brand_name'          => get_bloginfo( 'name' ),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->update( (int) $order->id, array( 'transaction_id' => sanitize_text_field( $result['id'] ) ) );

		return array( 'paypal_order_id' => $result['id'] );
	}

	/**
	 * Captures the PayPal order.
	 *
	 * @param object               $order Order.
	 * @param array<string, mixed> $data  { paypal_order_id }.
	 * @return string|WP_Error
	 */
	public function confirm( $order, array $data ) {
		$paypal_id = isset( $data['paypal_order_id'] ) ? sanitize_text_field( (string) $data['paypal_order_id'] ) : '';

		if ( ! $paypal_id || $paypal_id !== $order->transaction_id ) {
			return new WP_Error( 'shapelms_paypal', __( 'Payment does not belong to this order.', 'shapelms' ) );
		}

		$result = $this->request( 'POST', '/v2/checkout/orders/' . rawurlencode( $paypal_id ) . '/capture' );

		if ( is_wp_error( $result ) ) {
			$details = $this->request( 'GET', '/v2/checkout/orders/' . rawurlencode( $paypal_id ) );
			if ( ! is_wp_error( $details ) && isset( $details['status'] ) && 'COMPLETED' === $details['status'] ) {
				$result = $details;
			} else {
				return $result;
			}
		}

		if ( empty( $result['status'] ) || 'COMPLETED' !== $result['status'] ) {
			return new WP_Error( 'shapelms_paypal_pending', __( 'Payment has not completed yet.', 'shapelms' ) );
		}

		$capture = $result['purchase_units'][0]['payments']['captures'][0] ?? array();
		$amount  = isset( $capture['amount']['value'] ) ? (float) $capture['amount']['value'] : 0;

		if ( $amount + 0.001 < (float) $order->total ) {
			return new WP_Error( 'shapelms_paypal', __( 'Payment amount does not match the order.', 'shapelms' ) );
		}

		return isset( $capture['id'] ) ? sanitize_text_field( $capture['id'] ) : $paypal_id;
	}

	/**
	 * Webhook: verifies with PayPal's verify endpoint and maps refunds.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{order_id: int, transaction_id: string, action: string}|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		$webhook_id = (string) apply_filters( 'shapelms_paypal_webhook_id', get_option( 'shapelms_paypal_webhook_id', '' ) );
		$event      = json_decode( $request->get_body(), true );

		if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
			return new WP_Error( 'shapelms_paypal_webhook', __( 'Invalid payload.', 'shapelms' ), array( 'status' => 400 ) );
		}

		if ( $webhook_id ) {
			$verify = $this->request(
				'POST',
				'/v1/notifications/verify-webhook-signature',
				array(
					'auth_algo'         => $request->get_header( 'paypal-auth-algo' ),
					'cert_url'          => $request->get_header( 'paypal-cert-url' ),
					'transmission_id'   => $request->get_header( 'paypal-transmission-id' ),
					'transmission_sig'  => $request->get_header( 'paypal-transmission-sig' ),
					'transmission_time' => $request->get_header( 'paypal-transmission-time' ),
					'webhook_id'        => $webhook_id,
					'webhook_event'     => $event,
				)
			);

			if ( is_wp_error( $verify ) || empty( $verify['verification_status'] ) || 'SUCCESS' !== $verify['verification_status'] ) {
				return new WP_Error( 'shapelms_paypal_webhook', __( 'Invalid signature.', 'shapelms' ), array( 'status' => 400 ) );
			}
		}

		$resource = isset( $event['resource'] ) ? $event['resource'] : array();

		if ( 'PAYMENT.CAPTURE.REFUNDED' === $event['event_type'] ) {
			$custom = isset( $resource['custom_id'] ) ? (int) $resource['custom_id'] : 0;
			return array(
				'order_id'       => $custom,
				'transaction_id' => '',
				'action'         => $webhook_id ? 'refund' : 'ignore',
			);
		}

		return array(
			'order_id'       => 0,
			'transaction_id' => '',
			'action'         => 'ignore',
		);
	}
}
