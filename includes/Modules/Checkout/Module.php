<?php
/**
 * Native checkout module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Core\Pages;
use ShapeLMS\Database\OrderRepository;
use ShapeLMS\Services\CourseService;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\OrderService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * One-time payments with Stripe and PayPal, no cart.
 */
class Module extends AbstractModule {

	/**
	 * Gateways.
	 *
	 * @var array<string, GatewayInterface>
	 */
	private $gateways = array();

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'checkout';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'ShapeLMS checkout', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Built-in checkout for one-time course payments with Stripe and PayPal.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		/**
		 * Filters the payment gateways.
		 *
		 * @param GatewayInterface[] $gateways Gateways.
		 */
		$gateways = apply_filters( 'shapelms_payment_gateways', array( new StripeGateway(), new PayPalGateway() ) );

		foreach ( $gateways as $gateway ) {
			if ( $gateway instanceof GatewayInterface ) {
				$this->gateways[ $gateway->id() ] = $gateway;
			}
		}

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'shapelms_enqueue_frontend', array( $this, 'assets' ) );
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tab' ) );
	}

	/**
	 * Enabled gateways.
	 *
	 * @return array<string, GatewayInterface>
	 */
	public function enabled_gateways() {
		return array_filter(
			$this->gateways,
			static function ( $gateway ) {
				return $gateway->enabled();
			}
		);
	}

	/**
	 * Whether native checkout is the active engine.
	 *
	 * @return bool
	 */
	public static function is_active_engine() {
		return 'native' === shapelms_get_setting( 'commerce', 'engine', 'native' );
	}

	/**
	 * Enqueues checkout JS and the provider SDKs on the checkout page only.
	 *
	 * @return void
	 */
	public function assets() {
		if ( ! Pages::is_page( 'checkout' ) && ! ( is_singular() && has_block( 'shapelms/checkout' ) ) ) {
			return;
		}

		foreach ( $this->enabled_gateways() as $gateway ) {
			$data = $gateway->client_data();
			if ( ! empty( $data[ $gateway->id() . '-sdk' ] ) ) {
				wp_enqueue_script( 'shapelms-sdk-' . $gateway->id(), $data[ $gateway->id() . '-sdk' ], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- provider SDKs are unversioned.
			}
		}

		wp_enqueue_script( 'shapelms-checkout', SHAPELMS_URL . 'assets/js/checkout.js', array( 'shapelms-frontend' ), SHAPELMS_VERSION, true );
		wp_set_script_translations( 'shapelms-checkout', 'shapelms', SHAPELMS_DIR . 'languages' );
	}

	/**
	 * Data attributes for the checkout root.
	 *
	 * @return array<string, string>
	 */
	public function client_data() {
		$data = array( 'gateways' => implode( ',', array_keys( $this->enabled_gateways() ) ) );

		foreach ( $this->enabled_gateways() as $gateway ) {
			foreach ( $gateway->client_data() as $key => $value ) {
				if ( substr( $key, -4 ) !== '-sdk' ) {
					$data[ $key ] = $value;
				}
			}
		}

		return $data;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			'shapelms/v1',
			'/checkout/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_order' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'course_ids' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
					'gateway'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/checkout/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'order_key' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'data'      => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/webhooks/(?P<gateway>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true', // Public by design: each gateway verifies the provider's signature (Stripe HMAC, PayPal verify endpoint) before anything is acted on.
			)
		);
	}

	/**
	 * Creates an order and the gateway payment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_order( WP_REST_Request $request ) {
		if ( ! self::is_active_engine() ) {
			return new WP_Error( 'shapelms_checkout_disabled', __( 'Checkout is not available.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$gateway_id = $request->get_param( 'gateway' );
		$gateways   = $this->enabled_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return new WP_Error( 'shapelms_invalid_gateway', __( 'Payment method not available.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$service = shapelms()->get( OrderService::class );
		$order   = $service->create( get_current_user_id(), array_map( 'intval', (array) $request->get_param( 'course_ids' ) ), $gateway_id );

		if ( is_wp_error( $order ) ) {
			$order->add_data( array( 'status' => 400 ) );
			return $order;
		}

		if ( (float) $order->total <= 0 ) {
			$service->complete( (int) $order->id, 'free' );
			return array(
				'order_key' => $order->order_key,
				'completed' => true,
				'redirect'  => $this->success_url( $order ),
			);
		}

		$payment = $gateways[ $gateway_id ]->create_payment( $order );

		if ( is_wp_error( $payment ) ) {
			$payment->add_data( array( 'status' => 400 ) );
			return $payment;
		}

		return array_merge(
			array(
				'order_key' => $order->order_key,
				'order_id'  => (int) $order->id,
				'total'     => (float) $order->total,
				'currency'  => $order->currency,
			),
			$payment
		);
	}

	/**
	 * Confirms a payment server-side and completes the order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function confirm( WP_REST_Request $request ) {
		$orders = shapelms()->get( OrderRepository::class );
		$order  = $orders->find_by_key( $request->get_param( 'order_key' ) );

		if ( ! $order || get_current_user_id() !== (int) $order->user_id ) {
			return new WP_Error( 'shapelms_not_found', __( 'Order not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		if ( 'completed' === $order->status ) {
			return array(
				'completed' => true,
				'redirect'  => $this->success_url( $order ),
			);
		}

		if ( ! in_array( $order->status, array( 'pending', 'failed' ), true ) ) {
			return new WP_Error( 'shapelms_order_closed', __( 'This order can no longer be paid.', 'shapelms' ), array( 'status' => 409 ) );
		}

		$gateways = $this->enabled_gateways();
		if ( ! isset( $gateways[ $order->gateway ] ) ) {
			return new WP_Error( 'shapelms_invalid_gateway', __( 'Payment method not available.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$data   = $request->get_param( 'data' );
		$result = $gateways[ $order->gateway ]->confirm( $order, is_array( $data ) ? $data : array() );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 402 ) );
			return $result;
		}

		shapelms()->get( OrderService::class )->complete( (int) $order->id, $result );

		return array(
			'completed' => true,
			'redirect'  => $this->success_url( $order ),
		);
	}

	/**
	 * Webhook endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		$gateway_id = sanitize_key( (string) $request->get_param( 'gateway' ) );

		if ( ! isset( $this->gateways[ $gateway_id ] ) ) {
			return new WP_Error( 'shapelms_not_found', __( 'Unknown gateway.', 'shapelms' ), array( 'status' => 404 ) );
		}

		$result = $this->gateways[ $gateway_id ]->webhook( $request );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$service = shapelms()->get( OrderService::class );

		if ( $result['order_id'] ) {
			switch ( $result['action'] ) {
				case 'complete':
					$order = shapelms()->get( OrderRepository::class )->find( $result['order_id'] );
					if ( $order && in_array( $order->status, array( 'pending', 'failed' ), true ) ) {
						$service->complete( $result['order_id'], $result['transaction_id'] );
					}
					break;
				case 'refund':
					$service->revoke( $result['order_id'], 'refunded' );
					break;
				case 'fail':
					$order = shapelms()->get( OrderRepository::class )->find( $result['order_id'] );
					if ( $order && 'pending' === $order->status ) {
						$service->revoke( $result['order_id'], 'failed' );
					}
					break;
			}
		}

		return array( 'received' => true );
	}

	/**
	 * Where to send the buyer after payment.
	 *
	 * @param object $order Order.
	 * @return string
	 */
	private function success_url( $order ) {
		$items = shapelms()->get( OrderRepository::class )->items( (int) $order->id );
		$url   = 1 === count( $items ) ? (string) get_permalink( (int) $items[0]->course_id ) : Pages::get_url( 'dashboard' );

		/**
		 * Filters the URL after a successful purchase.
		 *
		 * @param string $url   URL.
		 * @param object $order Order.
		 */
		return apply_filters( 'shapelms_checkout_success_url', add_query_arg( 'purchased', '1', $url ), $order );
	}

	/**
	 * Courses requested on the checkout page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function requested_courses() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only course selection.
		$raw = isset( $_GET['course'] ) ? sanitize_text_field( wp_unslash( $_GET['course'] ) ) : '';
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		if ( ! $ids ) {
			return array();
		}

		$courses    = shapelms()->get( CourseService::class );
		$enrollment = shapelms()->get( EnrollmentService::class );
		$list       = array();

		foreach ( $ids as $id ) {
			$course = $courses->to_array( $id, false );
			if ( ! $course || 'publish' !== $course['status'] ) {
				continue;
			}
			$course['already_enrolled'] = is_user_logged_in() && $enrollment->is_enrolled( get_current_user_id(), $id );
			$list[]                     = $course;
		}

		return $list;
	}

	/**
	 * Dashboard tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tab( $tabs ) {
		$tabs['orders'] = array(
			'label'    => __( 'Purchases', 'shapelms' ),
			'template' => 'dashboard/orders.php',
			'section'  => 'student',
			'order'    => 40,
		);

		return $tabs;
	}
}
