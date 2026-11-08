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
	public function request( $method, $endpoint, array $body = array() ) {
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
					// Without this a capture may answer with only its id and status.
					'Prefer'            => 'return=representation',
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
			$issue   = isset( $json['details'][0]['issue'] ) ? sanitize_key( strtolower( (string) $json['details'][0]['issue'] ) ) : '';
			$message = isset( $json['message'] ) ? sanitize_text_field( $json['message'] ) : __( 'PayPal error.', 'shapelms' );

			return new WP_Error( 'shapelms_paypal', self::buyer_message( $issue, $message ), array( 'issue' => $issue ) );
		}

		return is_array( $json ) ? $json : array();
	}

	/**
	 * The words a buyer sees for the refusals they can act on. PayPal's own are written for developers.
	 *
	 * @param string $issue   PayPal issue, lower case.
	 * @param string $message PayPal's message.
	 * @return string
	 */
	private static function buyer_message( $issue, $message ) {
		switch ( $issue ) {
			case 'instrument_declined':
			case 'transaction_refused':
			case 'payer_cannot_pay':
				return __( 'PayPal declined this payment method. Please try again with another one.', 'shapelms' );
			case 'order_not_approved':
			case 'payer_action_required':
				return __( 'The payment was not approved in PayPal. Please try again.', 'shapelms' );
		}

		return $message;
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

		// The money has moved by now, so a short answer must not read as a refusal: ask for the details.
		if ( empty( $result['purchase_units'][0]['payments']['captures'] ) ) {
			$details = $this->request( 'GET', '/v2/checkout/orders/' . rawurlencode( $paypal_id ) );
			$result  = is_wp_error( $details ) ? $result : $details;
		}

		$capture = $result['purchase_units'][0]['payments']['captures'][0] ?? array();
		$status  = $capture['status'] ?? '';

		// Could not read the capture after all: keep the order open for the webhook, do not refuse it.
		if ( '' === $status ) {
			return new WP_Error( 'shapelms_paypal_pending', __( 'Payment has not completed yet.', 'shapelms' ) );
		}

		// A completed order can still hold a pending capture: the money has not arrived yet.
		if ( 'PENDING' === $status ) {
			return new WP_Error( 'shapelms_paypal_pending', __( 'Payment has not completed yet.', 'shapelms' ) );
		}

		if ( 'COMPLETED' !== $status ) {
			return new WP_Error( 'shapelms_paypal', __( 'PayPal did not accept this payment.', 'shapelms' ) );
		}

		if ( ! $this->capture_matches( $capture, $order ) ) {
			return new WP_Error( 'shapelms_paypal', __( 'Payment amount does not match the order.', 'shapelms' ) );
		}

		return isset( $capture['id'] ) ? sanitize_text_field( $capture['id'] ) : $paypal_id;
	}

	/**
	 * Webhook: verifies with PayPal's verify endpoint and maps refunds.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{order_id: int, transaction_id: string, action: string, amount?: float}|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		$webhook_id = (string) Settings::get( 'commerce', 'paypal_webhook_id', '' );
		$webhook_id = (string) apply_filters( 'shapelms_paypal_webhook_id', $webhook_id ? $webhook_id : get_option( 'shapelms_paypal_webhook_id', '' ) );
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

		if ( $webhook_id ) {
			/**
			 * Fires for every PayPal webhook event whose signature PayPal confirmed.
			 *
			 * @param array<string, mixed> $event Event.
			 */
			do_action( 'shapelms_paypal_webhook_event', $event );
		}

		// Without a webhook id nothing can be verified, so nothing may change an order.
		if ( ! $webhook_id ) {
			return self::webhook_result( 0, 'ignore' );
		}

		switch ( $event['event_type'] ) {
			case 'CHECKOUT.ORDER.APPROVED':
				return $this->capture_approved( $resource );

			case 'PAYMENT.CAPTURE.COMPLETED':
				$order = $this->webhook_order( $resource );

				return $order && $this->capture_matches( $resource, $order )
					? self::webhook_result( (int) $order->id, 'complete', isset( $resource['id'] ) ? sanitize_text_field( $resource['id'] ) : '' )
					: self::webhook_result( 0, 'ignore' );

			case 'PAYMENT.CAPTURE.PENDING':
				$order = $this->webhook_order( $resource );

				return self::webhook_result( $order ? (int) $order->id : 0, 'hold' );

			case 'PAYMENT.CAPTURE.DENIED':
			case 'PAYMENT.CAPTURE.DECLINED':
				$order = $this->webhook_order( $resource );

				return self::webhook_result( $order ? (int) $order->id : 0, 'fail' );

			case 'PAYMENT.CAPTURE.REFUNDED':
				$result = self::webhook_result( isset( $resource['custom_id'] ) ? (int) $resource['custom_id'] : 0, 'refund' );
				// The refund carries the running total; less than the order total is a partial refund.
				if ( isset( $resource['seller_payable_breakdown']['total_refunded_amount']['value'] ) ) {
					$result['amount'] = (float) $resource['seller_payable_breakdown']['total_refunded_amount']['value'];
				}
				return $result;

			case 'PAYMENT.CAPTURE.REVERSED':
				return self::webhook_result( isset( $resource['custom_id'] ) ? (int) $resource['custom_id'] : 0, 'refund' );
		}

		return self::webhook_result( 0, 'ignore' );
	}

	/**
	 * The buyer approved the payment but the page never finished it, for example because they
	 * closed the tab. Takes the money now.
	 *
	 * @param array<string, mixed> $paypal_order PayPal order from the event.
	 * @return array{order_id: int, transaction_id: string, action: string}
	 */
	private function capture_approved( array $paypal_order ) {
		$paypal_id = isset( $paypal_order['id'] ) ? sanitize_text_field( (string) $paypal_order['id'] ) : '';
		$order     = $this->webhook_order( $paypal_order['purchase_units'][0] ?? array() );

		if ( ! $order || ! $paypal_id || $paypal_id !== $order->transaction_id || ! in_array( $order->status, array( 'pending', 'failed' ), true ) ) {
			return self::webhook_result( 0, 'ignore' );
		}

		$result = $this->confirm( $order, array( 'paypal_order_id' => $paypal_id ) );

		if ( is_wp_error( $result ) ) {
			return self::webhook_result( 'shapelms_paypal_pending' === $result->get_error_code() ? (int) $order->id : 0, 'hold' );
		}

		return self::webhook_result( (int) $order->id, 'complete', $result );
	}

	/**
	 * The PayPal order an event belongs to.
	 *
	 * @param array<string, mixed> $paypal_order Object holding custom_id.
	 * @return object|null
	 */
	private function webhook_order( array $paypal_order ) {
		$order_id = isset( $paypal_order['custom_id'] ) ? (int) $paypal_order['custom_id'] : 0;
		$order    = $order_id ? shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->find( $order_id ) : null;

		return $order && $this->id() === $order->gateway ? $order : null;
	}

	/**
	 * Whether a capture paid the full order in the order's currency.
	 *
	 * @param array<string, mixed> $capture Capture.
	 * @param object               $order   Order.
	 * @return bool
	 */
	private function capture_matches( array $capture, $order ) {
		$amount = isset( $capture['amount']['value'] ) ? (float) $capture['amount']['value'] : 0;

		return strtoupper( (string) ( $capture['amount']['currency_code'] ?? '' ) ) === strtoupper( (string) $order->currency ) && $amount + 0.001 >= (float) $order->total;
	}

	/**
	 * What the checkout module should do with an event.
	 *
	 * @param int    $order_id       Order id (0 = ignore).
	 * @param string $action         complete|hold|fail|refund|ignore.
	 * @param string $transaction_id Transaction id.
	 * @return array{order_id: int, transaction_id: string, action: string}
	 */
	private static function webhook_result( $order_id, $action, $transaction_id = '' ) {
		return array(
			'order_id'       => $order_id,
			'transaction_id' => $transaction_id,
			'action'         => $action,
		);
	}
}
