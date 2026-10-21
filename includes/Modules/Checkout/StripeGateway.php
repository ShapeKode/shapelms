<?php
/**
 * Stripe gateway (Payment Intents + Payment Element).
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
 * Talks to the Stripe API with wp_remote_* (no SDK bundled).
 */
class StripeGateway implements GatewayInterface {

	const API = 'https://api.stripe.com/v1/';

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'stripe';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Card (Stripe)', 'shapelms' );
	}

	/**
	 * Enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return (bool) Settings::get( 'commerce', 'stripe_enabled', false ) && $this->secret() && $this->publishable();
	}

	/**
	 * Secret key.
	 *
	 * @return string
	 */
	private function secret() {
		return (string) Settings::get( 'commerce', 'stripe_secret_key', '' );
	}

	/**
	 * Publishable key.
	 *
	 * @return string
	 */
	private function publishable() {
		return (string) Settings::get( 'commerce', 'stripe_publishable_key', '' );
	}

	/**
	 * Client data.
	 *
	 * @return array<string, string>
	 */
	public function client_data() {
		return array(
			'stripe-key' => $this->publishable(),
			'stripe-sdk' => 'https://js.stripe.com/v3/',
		);
	}

	/**
	 * Stripe API request.
	 *
	 * @param string               $method   GET|POST.
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $body     Body (form-encoded).
	 * @param string               $idempotency Idempotency key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( $method, $endpoint, array $body = array(), $idempotency = '' ) {
		$headers = array(
			'Authorization'  => 'Bearer ' . $this->secret(),
			'Stripe-Version' => '2024-06-20',
		);

		if ( $idempotency ) {
			$headers['Idempotency-Key'] = $idempotency;
		}

		$response = wp_remote_request(
			self::API . $endpoint,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => 'POST' === $method ? $body : null,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $json ) ) {
			return new WP_Error( 'shapelms_stripe', __( 'Unexpected response from Stripe.', 'shapelms' ) );
		}

		if ( isset( $json['error'] ) ) {
			return new WP_Error( 'shapelms_stripe', isset( $json['error']['message'] ) ? sanitize_text_field( $json['error']['message'] ) : __( 'Stripe error.', 'shapelms' ) );
		}

		return $json;
	}

	/**
	 * Zero-decimal currencies.
	 *
	 * @return string[]
	 */
	private function zero_decimal() {
		return array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
	}

	/**
	 * Amount in the smallest unit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @return int
	 */
	private function minor( $amount, $currency ) {
		return in_array( strtoupper( $currency ), $this->zero_decimal(), true ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	/**
	 * Creates a PaymentIntent.
	 *
	 * @param object $order Order.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_payment( $order ) {
		$intent = $this->request(
			'POST',
			'payment_intents',
			array(
				'amount'                    => $this->minor( (float) $order->total, $order->currency ),
				'currency'                  => strtolower( $order->currency ),
				'automatic_payment_methods' => array( 'enabled' => 'true' ),
				'description'               => sprintf( /* translators: 1: order id, 2: site name */ __( 'Order #%1$d on %2$s', 'shapelms' ), $order->id, get_bloginfo( 'name' ) ),
				'receipt_email'             => $order->billing_email,
				'metadata'                  => array(
					'shapelms_order_id' => (string) $order->id,
					'site'              => home_url(),
				),
			),
			'shapelms_order_' . $order->id . '_' . md5( (string) $order->total )
		);

		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->update( (int) $order->id, array( 'transaction_id' => sanitize_text_field( $intent['id'] ) ) );

		return array(
			'client_secret' => $intent['client_secret'],
			'intent_id'     => $intent['id'],
		);
	}

	/**
	 * Confirms the PaymentIntent succeeded.
	 *
	 * @param object               $order Order.
	 * @param array<string, mixed> $data  { intent_id }.
	 * @return string|WP_Error
	 */
	public function confirm( $order, array $data ) {
		$intent_id = isset( $data['intent_id'] ) ? sanitize_text_field( (string) $data['intent_id'] ) : '';

		if ( ! $intent_id || 0 !== strpos( $intent_id, 'pi_' ) ) {
			return new WP_Error( 'shapelms_stripe', __( 'Invalid payment reference.', 'shapelms' ) );
		}

		$intent = $this->request( 'GET', 'payment_intents/' . rawurlencode( $intent_id ) );

		if ( is_wp_error( $intent ) ) {
			return $intent;
		}

		if ( empty( $intent['metadata']['shapelms_order_id'] ) || (int) $intent['metadata']['shapelms_order_id'] !== (int) $order->id ) {
			return new WP_Error( 'shapelms_stripe', __( 'Payment does not belong to this order.', 'shapelms' ) );
		}

		if ( 'succeeded' !== $intent['status'] ) {
			return new WP_Error( 'shapelms_stripe_pending', __( 'Payment has not completed yet.', 'shapelms' ) );
		}

		if ( ! $this->matches_order( $intent, $order ) ) {
			return new WP_Error( 'shapelms_stripe', __( 'Payment does not match the order.', 'shapelms' ) );
		}

		return $intent['id'];
	}

	/**
	 * Whether a PaymentIntent belongs to this site and covers the order in the right currency.
	 *
	 * @param array<string, mixed> $intent PaymentIntent object.
	 * @param object               $order  Order row.
	 * @return bool
	 */
	private function matches_order( array $intent, $order ) {
		$site = isset( $intent['metadata']['site'] ) ? (string) $intent['metadata']['site'] : '';

		if ( home_url() !== $site ) {
			return false;
		}

		if ( strtolower( (string) $order->currency ) !== strtolower( (string) ( $intent['currency'] ?? '' ) ) ) {
			return false;
		}

		return (int) ( $intent['amount_received'] ?? 0 ) >= $this->minor( (float) $order->total, $order->currency );
	}

	/**
	 * Webhook: verifies the signature and maps events to order actions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{order_id: int, transaction_id: string, action: string}|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		$secret    = (string) Settings::get( 'commerce', 'stripe_webhook_secret', '' );
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );

		if ( ! $secret || ! $signature ) {
			return new WP_Error( 'shapelms_stripe_webhook', __( 'Webhook not configured.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$parts = array();
		foreach ( explode( ',', $signature ) as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 === count( $pair ) ) {
				$parts[ trim( $pair[0] ) ][] = trim( $pair[1] );
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return new WP_Error( 'shapelms_stripe_webhook', __( 'Invalid signature header.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$timestamp = (int) $parts['t'][0];
		if ( abs( time() - $timestamp ) > 300 ) {
			return new WP_Error( 'shapelms_stripe_webhook', __( 'Signature timestamp out of range.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		$valid    = false;
		foreach ( $parts['v1'] as $candidate ) {
			if ( hash_equals( $expected, $candidate ) ) {
				$valid = true;
			}
		}

		if ( ! $valid ) {
			return new WP_Error( 'shapelms_stripe_webhook', __( 'Invalid signature.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_Error( 'shapelms_stripe_webhook', __( 'Invalid payload.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$object   = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
		$order_id = isset( $object['metadata']['shapelms_order_id'] ) ? (int) $object['metadata']['shapelms_order_id'] : 0;

		switch ( $event['type'] ) {
			case 'payment_intent.succeeded':
				$order = $order_id ? shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->find( $order_id ) : null;

				if ( ! $order || ! $this->matches_order( $object, $order ) ) {
					return array(
						'order_id'       => 0,
						'transaction_id' => '',
						'action'         => 'ignore',
					);
				}

				return array(
					'order_id'       => $order_id,
					'transaction_id' => isset( $object['id'] ) ? sanitize_text_field( $object['id'] ) : '',
					'action'         => 'complete',
				);

			case 'charge.refunded':
				$intent_id      = isset( $object['payment_intent'] ) ? sanitize_text_field( $object['payment_intent'] ) : '';
				$order          = $intent_id ? shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->find_by_transaction( $intent_id ) : null;
				$fully_refunded = ! empty( $object['refunded'] ) || ( isset( $object['amount_refunded'], $object['amount'] ) && (int) $object['amount_refunded'] >= (int) $object['amount'] );

				return array(
					'order_id'       => $order && $fully_refunded ? (int) $order->id : 0,
					'transaction_id' => $intent_id,
					'action'         => 'refund',
				);

			case 'payment_intent.payment_failed':
				$site = isset( $object['metadata']['site'] ) ? (string) $object['metadata']['site'] : '';

				return array(
					'order_id'       => home_url() === $site ? $order_id : 0,
					'transaction_id' => '',
					'action'         => 'fail',
				);
		}

		return array(
			'order_id'       => 0,
			'transaction_id' => '',
			'action'         => 'ignore',
		);
	}
}
