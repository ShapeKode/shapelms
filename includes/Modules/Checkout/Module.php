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

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Core\Pages;
use ShapeLMS\Core\Settings;
use ShapeLMS\Core\Events;
use ShapeLMS\Database\OrderRepository;
use ShapeLMS\Services\CartService;
use ShapeLMS\Services\CourseService;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\OrderService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * One-time payments with Stripe, PayPal, cash on delivery and bank transfer. Courses are
 * bought directly, or through a cart when the "Buy Now" setting is off.
 */
class Module extends AbstractModule {

	const BILLING_META = 'shapelms_billing';

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
		return __( 'Built-in checkout for one-time course payments with Stripe, PayPal, cash on delivery and bank transfer.', 'shapelms' );
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
		$gateways = apply_filters( 'shapelms_payment_gateways', array( new StripeGateway(), new PayPalGateway(), new CodGateway(), new BacsGateway() ) );

		foreach ( $gateways as $gateway ) {
			if ( $gateway instanceof GatewayInterface ) {
				$this->gateways[ $gateway->id() ] = $gateway;
			}
		}

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'shapelms_enqueue_frontend', array( $this, 'assets' ) );
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tab' ) );
		add_filter( 'shapelms_page_id', array( $this, 'checkout_page_id' ), 10, 2 );
		add_filter( 'shapelms_course_buy_url', array( $this, 'buy_url' ), 5, 2 );
		add_filter( 'shapelms_course_buy_label', array( $this, 'buy_label' ), 10, 2 );
		add_filter( 'shapelms_save_settings', array( $this, 'clean_webhook_fields' ) );
		add_filter( 'shapelms_save_settings', array( $this, 'prepare_cart_page' ) );
		add_action( 'admin_init', array( $this, 'repair_page' ) );
		add_action( 'template_redirect', array( $this, 'restrict_page' ) );
		add_action( 'template_redirect', array( $this, 'restrict_cart_page' ) );
		add_action( Events::COURSE_ENROLLED, array( $this, 'clear_enrolled_course' ), 10, 2 );
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
	 * Keeps the checkout page to buyers who can use it: guests log in first, a course that
	 * cannot be bought goes back to its page, and with nothing to buy they go to the courses.
	 *
	 * @return void
	 */
	public function restrict_page() {
		if ( ! self::is_active_engine() || ! Pages::is_page( 'checkout' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$current = add_query_arg( array_map( 'sanitize_text_field', wp_unslash( $_GET ) ), Pages::get_url( 'checkout' ) );
		$order   = isset( $_GET['order'] );
		$course  = isset( $_GET['course'] ) ? sanitize_text_field( wp_unslash( $_GET['course'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! is_user_logged_in() ) {
			$target = $order || $course ? shapelms_login_url( $current ) : (string) get_post_type_archive_link( PostTypes::COURSE );
		} elseif ( $order ) {
			return;
		} elseif ( ! self::requested_courses() ) {
			$target = (string) get_post_type_archive_link( PostTypes::COURSE );
		} elseif ( ctype_digit( $course ) && is_wp_error( shapelms()->get( OrderService::class )->can_purchase( get_current_user_id(), (int) $course ) ) ) {
			$target = (string) get_permalink( (int) $course );
		} else {
			return;
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Keeps the cart page to logged-in students while the cart is in use.
	 *
	 * @return void
	 */
	public function restrict_cart_page() {
		if ( ! Pages::is_page( 'cart' ) || ( self::cart_enabled() && is_user_logged_in() ) ) {
			return;
		}

		wp_safe_redirect( self::cart_enabled() ? shapelms_login_url( Pages::get_url( 'cart' ) ) : (string) get_post_type_archive_link( PostTypes::COURSE ) );
		exit;
	}

	/**
	 * A course the student now has access to no longer belongs in the cart. Removing it here
	 * also keeps it from coming back if the access ends later.
	 *
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $context Event context.
	 * @return void
	 */
	public function clear_enrolled_course( $user_id, $context ) {
		shapelms()->get( CartService::class )->remove( (int) $user_id, (int) ( $context['course_id'] ?? 0 ) );
	}

	/**
	 * Billing fields, in order. All are required.
	 *
	 * @return array<string, string> Field => label.
	 */
	public static function billing_fields() {
		return array(
			'first_name' => __( 'First name', 'shapelms' ),
			'last_name'  => __( 'Last name', 'shapelms' ),
			'email'      => __( 'Email', 'shapelms' ),
			'phone'      => __( 'Phone', 'shapelms' ),
			'country'    => __( 'Country', 'shapelms' ),
			'state'      => __( 'State', 'shapelms' ),
			'city'       => __( 'City', 'shapelms' ),
			'zip_code'   => __( 'Postcode / ZIP', 'shapelms' ),
			'address'    => __( 'Address', 'shapelms' ),
		);
	}

	/**
	 * What to prefill: the last billing details, or what the account knows.
	 *
	 * @param int $user_id User id.
	 * @return array<string, string>
	 */
	public static function billing_defaults( $user_id ) {
		$saved = get_user_meta( $user_id, self::BILLING_META, true );

		if ( is_array( $saved ) && $saved ) {
			return $saved;
		}

		$user = get_userdata( $user_id );

		return $user ? array(
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'email'      => $user->user_email,
		) : array();
	}

	/**
	 * Checks the billing details and the terms from the order request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error Clean details.
	 */
	private function validate_billing( WP_REST_Request $request ) {
		$input   = (array) $request->get_param( 'billing' );
		$billing = array();

		foreach ( self::billing_fields() as $key => $label ) {
			$value = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';

			if ( '' === $value ) {
				/* translators: %s: field label */
				return new WP_Error( 'shapelms_billing', sprintf( __( '%s is required.', 'shapelms' ), $label ), array( 'status' => 400 ) );
			}

			$billing[ $key ] = $value;
		}

		$billing['email'] = sanitize_email( $billing['email'] );

		if ( ! is_email( $billing['email'] ) ) {
			return new WP_Error( 'shapelms_billing', __( 'Enter a valid email address.', 'shapelms' ), array( 'status' => 400 ) );
		}

		if ( self::terms_page() ) {
			if ( ! rest_sanitize_boolean( $request->get_param( 'terms' ) ) ) {
				return new WP_Error( 'shapelms_terms', __( 'Please accept the terms and conditions.', 'shapelms' ), array( 'status' => 400 ) );
			}
			$billing['accepted_on'] = shapelms_now();
		}

		return $billing;
	}

	/**
	 * The terms page students must accept, 0 when none is set.
	 *
	 * @return int
	 */
	public static function terms_page() {
		$page_id = (int) shapelms_get_setting( 'commerce', 'terms_page', 0 );

		return $page_id && 'publish' === get_post_status( $page_id ) ? $page_id : 0;
	}

	/**
	 * Keeps the checkout page usable: a stored page that is gone, or that belongs to
	 * WooCommerce, would send buyers to an empty cart.
	 *
	 * @param int    $page_id Page id.
	 * @param string $key     Page key.
	 * @return int
	 */
	public function checkout_page_id( $page_id, $key ) {
		if ( 'checkout' !== $key || ! self::is_active_engine() || self::usable_page( $page_id ) ) {
			return $page_id;
		}

		$pages  = get_option( 'shapelms_pages', array() );
		$stored = is_array( $pages ) && isset( $pages['checkout'] ) ? (int) $pages['checkout'] : 0;

		return self::usable_page( $stored ) ? $stored : 0;
	}

	/**
	 * Whether a page can serve as the ShapeLMS checkout.
	 *
	 * @param int $page_id Page id.
	 * @return bool
	 */
	private static function usable_page( $page_id ) {
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return false;
		}

		return ! function_exists( 'wc_get_page_id' ) || wc_get_page_id( 'checkout' ) !== (int) $page_id;
	}

	/**
	 * Clears a checkout page setting that cannot work and brings the page back when it is missing.
	 *
	 * @return void
	 */
	public function repair_page() {
		if ( ! self::is_active_engine() || ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$selected = (int) shapelms_get_setting( 'general', 'checkout_page', 0 );

		if ( $selected && ! self::usable_page( $selected ) ) {
			Settings::save( array( 'general' => array( 'checkout_page' => 0 ) ) );
		}

		if ( ! Pages::get_id( 'checkout' ) ) {
			Pages::restore( 'checkout' );
		}

		if ( CartService::enabled() && ! self::has_cart_page() ) {
			Pages::restore( 'cart' );
		}
	}

	/**
	 * Whether the cart page can be opened.
	 *
	 * @return bool
	 */
	private static function has_cart_page() {
		$page_id = Pages::get_id( 'cart' );

		return $page_id && 'publish' === get_post_status( $page_id );
	}

	/**
	 * Brings the cart page back as soon as the "Buy Now" setting is switched off, so the
	 * buttons work right after saving.
	 *
	 * @param array<string, array<string, mixed>> $settings Settings.
	 * @return array<string, array<string, mixed>>
	 */
	public function prepare_cart_page( $settings ) {
		if ( 'native' === ( $settings['commerce']['engine'] ?? '' ) && empty( $settings['commerce']['buy_now'] ) && ! self::has_cart_page() ) {
			Pages::restore( 'cart' );
		}

		return $settings;
	}

	/**
	 * The buy button: an order waiting for payment opens its order page instead of a second
	 * order, and a site without a checkout page has nothing to link to.
	 *
	 * @param string $url       URL.
	 * @param int    $course_id Course id.
	 * @return string
	 */
	public function buy_url( $url, $course_id ) {
		if ( ! self::is_active_engine() ) {
			return $url;
		}

		if ( ! Pages::get_id( 'checkout' ) ) {
			return '';
		}

		$cart = CartService::enabled();

		if ( $cart && ! self::has_cart_page() ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			// With a cart the student comes back to the course and adds it from there.
			return shapelms_login_url( $cart ? (string) get_permalink( $course_id ) : $url );
		}

		$open = shapelms()->get( OrderService::class )->open_order( get_current_user_id(), (int) $course_id );

		if ( $open ) {
			return self::order_url( $open );
		}

		return $cart ? Pages::get_url( 'cart' ) : $url;
	}

	/**
	 * The buy button's label while the cart is in use.
	 *
	 * @param string $label     Label.
	 * @param int    $course_id Course id.
	 * @return string
	 */
	public function buy_label( $label, $course_id ) {
		if ( ! self::cart_enabled() ) {
			return $label;
		}

		$user_id = get_current_user_id();

		if ( shapelms()->get( OrderService::class )->open_order( $user_id, (int) $course_id ) ) {
			return __( 'View order', 'shapelms' );
		}

		return $user_id && shapelms()->get( CartService::class )->has( $user_id, (int) $course_id ) ? __( 'View cart', 'shapelms' ) : __( 'Add to cart', 'shapelms' );
	}

	/**
	 * Whether the buy button adds the course to the cart instead of following its link.
	 * Guests log in first, and a course already in the cart only needs the link.
	 *
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function adds_to_cart( $course_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! self::cart_enabled() ) {
			return false;
		}

		$cart = shapelms()->get( CartService::class );

		return ! $cart->has( $user_id, (int) $course_id ) && true === $cart->can_add( $user_id, (int) $course_id );
	}

	/**
	 * Whether students of this engine buy through the cart.
	 *
	 * @return bool
	 */
	public static function cart_enabled() {
		return self::is_active_engine() && CartService::enabled();
	}

	/**
	 * Empties webhook fields that hold the webhook URL: that is what Stripe and PayPal
	 * call, not the secret they hand back, and every webhook would fail with it.
	 *
	 * @param array<string, array<string, mixed>> $settings Settings.
	 * @return array<string, array<string, mixed>>
	 */
	public function clean_webhook_fields( $settings ) {
		foreach ( array( 'stripe_webhook_secret', 'paypal_webhook_id' ) as $key ) {
			$value = (string) ( $settings['commerce'][ $key ] ?? '' );
			if ( '/' === substr( $value, 0, 1 ) || 0 === strpos( $value, 'http' ) ) {
				$settings['commerce'][ $key ] = '';
			}
		}

		return $settings;
	}

	/**
	 * URL of an order's page.
	 *
	 * @param object $order Order.
	 * @return string
	 */
	public static function order_url( $order ) {
		return add_query_arg( 'order', $order->order_key, Pages::get_url( 'checkout' ) );
	}

	/**
	 * The order of the page being viewed, for its owner only.
	 *
	 * @return object|null
	 */
	public static function viewed_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; the secret key and the owner check protect it.
		$key   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
		$order = $key ? shapelms()->get( OrderRepository::class )->find_by_key( $key ) : null;

		return $order && get_current_user_id() === (int) $order->user_id ? $order : null;
	}

	/**
	 * A gateway by id, enabled or not: old orders keep their payment method.
	 *
	 * @param string $id Gateway id.
	 * @return GatewayInterface|null
	 */
	public function gateway( $id ) {
		return isset( $this->gateways[ $id ] ) ? $this->gateways[ $id ] : null;
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
					'billing'    => array(
						'type'    => 'object',
						'default' => array(),
					),
					'terms'      => array(
						'type'    => 'boolean',
						'default' => false,
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
			'/cart',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_to_cart' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'course_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/cart/(?P<course_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'remove_from_cart' ),
				'permission_callback' => 'is_user_logged_in',
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
	 * Adds a course to the cart.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function add_to_cart( WP_REST_Request $request ) {
		if ( ! self::cart_enabled() ) {
			return new WP_Error( 'shapelms_cart_disabled', __( 'The cart is not available.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$added = shapelms()->get( CartService::class )->add( get_current_user_id(), (int) $request->get_param( 'course_id' ) );

		if ( is_wp_error( $added ) ) {
			$added->add_data( array( 'status' => 400 ) );

			return $added;
		}

		return array( 'label' => __( 'View cart', 'shapelms' ) );
	}

	/**
	 * Removes a course from the cart.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, bool>
	 */
	public function remove_from_cart( WP_REST_Request $request ) {
		shapelms()->get( CartService::class )->remove( get_current_user_id(), (int) $request->get_param( 'course_id' ) );

		return array( 'removed' => true );
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

		$billing = $this->validate_billing( $request );

		if ( is_wp_error( $billing ) ) {
			return $billing;
		}

		$service = shapelms()->get( OrderService::class );
		$order   = $service->create( get_current_user_id(), array_map( 'intval', (array) $request->get_param( 'course_ids' ) ), $gateway_id, $billing );

		if ( is_wp_error( $order ) ) {
			$order->add_data( array( 'status' => 400 ) );
			return $order;
		}
		update_user_meta( get_current_user_id(), self::BILLING_META, array_intersect_key( $billing, self::billing_fields() ) );

		if ( (float) $order->total <= 0 ) {
			$service->complete( (int) $order->id, 'free' );
			$free_items = shapelms()->get( OrderRepository::class )->items( (int) $order->id );

			return array(
				'order_key' => $order->order_key,
				'completed' => true,
				// A single course that costs nothing goes straight to the course.
				'redirect'  => 1 === count( $free_items ) ? (string) get_permalink( (int) $free_items[0]->course_id ) : $this->success_url( $order ),
			);
		}

		if ( $gateways[ $gateway_id ] instanceof OfflineGateway ) {
			$service->hold( (int) $order->id );

			return array(
				'order_key' => $order->order_key,
				'completed' => true,
				'redirect'  => self::order_url( $order ),
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
		/**
		 * Filters the URL after a successful purchase.
		 *
		 * @param string $url   URL.
		 * @param object $order Order.
		 */
		return apply_filters( 'shapelms_checkout_success_url', self::order_url( $order ), $order );
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

		/**
		 * Filters the items shown on the checkout page. Add-ons can add their own items for ids that
		 * are not courses. Each item needs: id, title, url, price_html, already_enrolled and
		 * pricing { is_free, current }.
		 *
		 * @param array<int, array<string, mixed>> $list Items.
		 * @param int[]                            $ids  Requested ids.
		 */
		return (array) apply_filters( 'shapelms_checkout_items', $list, array_values( $ids ) );
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
