<?php
/**
 * WooCommerce module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Frontend\Dashboard;
use ShapeLMS\Services\CartService;
use ShapeLMS\Services\CourseService;
use ShapeLMS\Services\CurrencyService;
use ShapeLMS\Services\EnrollmentService;

/**
 * Sells courses as WooCommerce products. Each paid course keeps one hidden product in
 * sync with its price, and the enrollment follows the status of the order.
 */
class Module extends AbstractModule {

	const PRODUCT_META = '_shapelms_course_id';
	const COURSE_META  = '_shapelms_woo_product_id';
	const BACKFILLED   = 'shapelms_woo_products_backfilled';

	/**
	 * True while this module saves a product, so the save is not read back into the course.
	 *
	 * @var bool
	 */
	private $syncing = false;

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'woocommerce';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'WooCommerce', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Sell courses through WooCommerce products and checkout.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		$selected = 'woocommerce' === shapelms_get_setting( 'commerce', 'engine', 'native' );

		if ( ! $selected || ! class_exists( 'WooCommerce' ) ) {
			// Courses created while WooCommerce is not the engine get their products on the next switch.
			if ( get_option( self::BACKFILLED ) ) {
				delete_option( self::BACKFILLED );
			}

			if ( $selected ) {
				add_filter( 'shapelms_course_buy_url', '__return_empty_string' );
				add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			}

			return;
		}

		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'revoke' ) );

		add_filter( 'shapelms_course_buy_url', array( $this, 'buy_url' ), 10, 2 );
		add_filter( 'shapelms_course_buy_label', array( $this, 'buy_label' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_after_add' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'require_account' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'require_account' ) );
		add_filter( 'shapelms_format_price', array( $this, 'format_price' ), 10, 3 );
		add_action( 'shapelms_enroll_box_actions', array( $this, 'print_notices' ) );
		add_action( 'template_redirect', array( $this, 'redirect_to_courses' ) );
		add_filter( 'woocommerce_cart_item_permalink', array( $this, 'cart_item_permalink' ), 10, 2 );
		add_filter( 'woocommerce_order_item_permalink', array( $this, 'order_item_permalink' ), 10, 2 );

		add_action( 'shapelms_course_saved', array( $this, 'course_saved' ) );
		add_action( 'woocommerce_update_product', array( $this, 'pull_price' ) );
		add_action( 'before_delete_post', array( $this, 'clear_deleted_product' ) );
		add_action( 'admin_init', array( $this, 'backfill' ) );

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'shapelms_course_data', array( $this, 'course_data' ), 10, 2 );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );
	}

	/**
	 * Tells admins that paid courses cannot be bought while WooCommerce is off.
	 *
	 * @return void
	 */
	public function missing_woocommerce_notice() {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'ShapeLMS sells courses through WooCommerce, but WooCommerce is not active. Students cannot buy paid courses until you activate it or change the commerce engine in Settings → Selling.', 'shapelms' ) );
	}

	/**
	 * Keeps the enrollment in line with the order status.
	 *
	 * @param int    $order_id Order id.
	 * @param string $from     Previous status.
	 * @param string $to       New status.
	 * @return void
	 */
	public function status_changed( $order_id, $from, $to ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $this->course_ids( $order ) ) {
			return;
		}

		$auto = 'processing' === $to && $this->paid_online( $order );

		if ( 'completed' !== $to && ! $auto ) {
			$this->revoke( $order_id );
			return;
		}

		$this->grant( $order_id );

		// Orders with other products stay open for the shop; admins changing a status keep control.
		if ( $auto && ! is_admin() && $this->only_courses( $order ) ) {
			$order->update_status( 'completed', __( 'Course order completed automatically by ShapeLMS.', 'shapelms' ) );
		}
	}

	/**
	 * Whether a processing order was paid online. Manual payments wait for an admin to complete the order.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	private function paid_online( $order ) {
		if ( ! shapelms_get_setting( 'commerce', 'woo_auto_complete', true ) ) {
			return false;
		}

		/**
		 * Filters the WooCommerce gateways whose money an admin has to confirm.
		 *
		 * @param string[] $gateways Gateway ids.
		 */
		$manual = (array) apply_filters( 'shapelms_woo_manual_gateways', array( 'cod', 'cheque', 'bacs' ) );

		return ! in_array( $order->get_payment_method(), $manual, true );
	}

	/**
	 * Whether every line of the order is a course.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	private function only_courses( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( ! get_post_meta( (int) $item->get_product_id(), self::PRODUCT_META, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Course ids in an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return int[]
	 */
	private function course_ids( $order ) {
		$ids = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$course_id  = (int) get_post_meta( $product_id, self::PRODUCT_META, true );
			$found      = $course_id && PostTypes::COURSE === get_post_type( $course_id ) ? array( $course_id ) : array();

			/**
			 * Courses a WooCommerce product grants. By default the course linked to the product.
			 *
			 * @param int[]     $found      Course ids.
			 * @param int       $product_id Product id.
			 * @param \WC_Order $order      Order.
			 */
			$found = (array) apply_filters( 'shapelms_woo_product_course_ids', $found, $product_id, $order );

			foreach ( $found as $found_id ) {
				if ( PostTypes::COURSE === get_post_type( (int) $found_id ) ) {
					$ids[] = (int) $found_id;
				}
			}
		}

		return array_unique( $ids );
	}

	/**
	 * Enrolls the buyer.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function grant( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$course_ids = $this->course_ids( $order );

		// Checkout requires an account for courses, so only orders made by hand can get here.
		if ( ! $order->get_user_id() ) {
			if ( $course_ids ) {
				$order->add_order_note( __( 'ShapeLMS could not enroll the buyer: this order has no customer account.', 'shapelms' ) );
			}
			return;
		}

		$enrollment = shapelms()->get( EnrollmentService::class );

		foreach ( $course_ids as $course_id ) {
			$enrollment->enroll( $order->get_user_id(), $course_id, 'woocommerce', (int) $order_id );
		}
	}

	/**
	 * Revokes access.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function revoke( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}

		$enrollment = shapelms()->get( EnrollmentService::class );

		foreach ( $this->course_ids( $order ) as $course_id ) {
			$row = $enrollment->get( $order->get_user_id(), $course_id );
			// Only the order that gave the access can take it away.
			if ( $row && 'woocommerce' === $row->source && (int) $order_id === (int) $row->order_id && in_array( $row->status, array( 'active', 'completed' ), true ) ) {
				$enrollment->unenroll( $order->get_user_id(), $course_id );
			}
		}
	}

	/**
	 * Buy URL: adds the product from the course page, then redirect_after_add() takes over.
	 * Empty when the course cannot be sold, so the buy box never leads to an empty cart.
	 *
	 * @param string $url       URL.
	 * @param int    $course_id Course id.
	 * @return string
	 */
	public function buy_url( $url, $course_id ) {
		$product = wc_get_product( (int) get_post_meta( $course_id, self::COURSE_META, true ) );

		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		if ( $this->in_cart( $product ) ) {
			return CartService::enabled() ? wc_get_cart_url() : wc_get_checkout_url();
		}

		$buy = add_query_arg( 'add-to-cart', $product->get_id(), (string) get_permalink( $course_id ) );

		return is_user_logged_in() ? $buy : shapelms_login_url( $buy );
	}

	/**
	 * The buy button's label when the "Buy Now" setting is off.
	 *
	 * @param string $label     Label.
	 * @param int    $course_id Course id.
	 * @return string
	 */
	public function buy_label( $label, $course_id ) {
		if ( ! CartService::enabled() ) {
			return $label;
		}

		$product = wc_get_product( (int) get_post_meta( $course_id, self::COURSE_META, true ) );

		return $product && $this->in_cart( $product ) ? __( 'View cart', 'shapelms' ) : __( 'Add to cart', 'shapelms' );
	}

	/**
	 * Whether a product is in the cart.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	private function in_cart( $product ) {
		$cart = WC()->cart;

		return $cart && $cart->find_product_in_cart( $cart->generate_cart_id( $product->get_id() ) );
	}

	/**
	 * Where a buyer goes after adding a course: straight to checkout, or back to the course
	 * when the "Buy Now" setting is off. The redirect also drops add-to-cart from the URL,
	 * so a reload does not add the course twice.
	 *
	 * @param string|false     $url     Redirect URL.
	 * @param \WC_Product|null $product Product added to the cart.
	 * @return string|false
	 */
	public function redirect_after_add( $url, $product = null ) {
		$course_id = $product ? (int) get_post_meta( $product->get_id(), self::PRODUCT_META, true ) : 0;

		if ( ! $course_id ) {
			return $url;
		}

		if ( ! CartService::enabled() ) {
			return wc_get_checkout_url();
		}

		// WooCommerce's own "Redirect to the cart page after successful addition" setting still applies.
		return 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ? wc_get_cart_url() : (string) get_permalink( $course_id );
	}

	/**
	 * Applies the buy box rules to every way into the cart, including direct links.
	 *
	 * @param bool        $purchasable Current decision.
	 * @param \WC_Product $product     Product.
	 * @return bool
	 */
	public function is_purchasable( $purchasable, $product ) {
		$course_id = (int) get_post_meta( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), self::PRODUCT_META, true );

		// Orders an admin builds by hand are not limited by these rules.
		if ( ! $purchasable || ! $course_id || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $purchasable;
		}

		if ( 'publish' !== get_post_status( $course_id ) || CourseService::pricing( $course_id )['is_free'] ) {
			return false;
		}

		$enrollment = shapelms()->get( EnrollmentService::class );
		$user_id    = get_current_user_id();

		if ( $user_id && ( $enrollment->is_enrolled( $user_id, $course_id ) || $enrollment->missing_prerequisites( $user_id, $course_id ) ) ) {
			return false;
		}

		return ! shapelms()->get( CourseService::class )->is_full( $course_id );
	}

	/**
	 * Requires an account when the cart holds a course: enrollments belong to a user.
	 *
	 * @param bool $value WooCommerce's setting.
	 * @return bool
	 */
	public function require_account( $value ) {
		if ( is_user_logged_in() || ! did_action( 'wp_loaded' ) || ! WC()->cart ) {
			return $value;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( get_post_meta( (int) $item['product_id'], self::PRODUCT_META, true ) ) {
				return true;
			}
		}

		return $value;
	}

	/**
	 * Shows prices the way the WooCommerce checkout charges them.
	 *
	 * @param string $formatted Formatted price.
	 * @param float  $amount    Amount.
	 * @param string $code      Currency code.
	 * @return string
	 */
	public function format_price( $formatted, $amount, $code ) {
		if ( CurrencyService::code() !== $code ) {
			return $formatted;
		}

		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Prints WooCommerce's messages (a failed add to cart) on the course page.
	 *
	 * @return void
	 */
	public function print_notices() {
		if ( WC()->session ) {
			wc_print_notices();
		}
	}

	/**
	 * Sends the buyer of a completed course order from the order received page to their
	 * courses, when the setting is on. Runs before any output, so the redirect always works.
	 *
	 * @return void
	 */
	public function redirect_to_courses() {
		if ( ! shapelms_get_setting( 'commerce', 'woo_redirect_courses', false ) || ! is_order_received_page() ) {
			return;
		}

		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key is the proof here, as on WooCommerce's own page.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( ! $order || ! $order->key_is_valid( $key ) || ! $order->has_status( 'completed' ) || get_current_user_id() !== $order->get_user_id() || ! $this->course_ids( $order ) ) {
			return;
		}

		/**
		 * Filters where buyers go after a completed WooCommerce course order.
		 *
		 * @param string    $url   URL.
		 * @param \WC_Order $order Order.
		 */
		wp_safe_redirect( (string) apply_filters( 'shapelms_woo_redirect_url', Dashboard::url( 'courses' ), $order ) );
		exit;
	}

	/**
	 * Cart lines link to the course: its product is hidden from the shop.
	 *
	 * @param string              $permalink Link.
	 * @param array<string,mixed> $item      Cart item.
	 * @return string
	 */
	public function cart_item_permalink( $permalink, $item ) {
		$course_id = (int) get_post_meta( (int) $item['product_id'], self::PRODUCT_META, true );

		return $course_id ? (string) get_permalink( $course_id ) : $permalink;
	}

	/**
	 * Order lines link to the course.
	 *
	 * @param string         $permalink Link.
	 * @param \WC_Order_Item $item      Order item.
	 * @return string
	 */
	public function order_item_permalink( $permalink, $item ) {
		$course_id = $item instanceof \WC_Order_Item_Product ? (int) get_post_meta( (int) $item->get_product_id(), self::PRODUCT_META, true ) : 0;

		return $course_id ? (string) get_permalink( $course_id ) : $permalink;
	}

	/**
	 * Updates the product when a course is saved.
	 *
	 * @param int $course_id Course id.
	 * @return void
	 */
	public function course_saved( $course_id ) {
		$this->sync_product( $course_id );
	}

	/**
	 * Creates the product of a paid course, or brings the linked one up to date.
	 * A course that became free keeps its link for when it is paid again.
	 *
	 * @param int $course_id Course id.
	 * @return int Product id, 0 for a free course.
	 */
	public function sync_product( $course_id ) {
		$course_id = (int) $course_id;
		$pricing   = CourseService::pricing( $course_id );

		if ( $pricing['is_free'] || PostTypes::COURSE !== get_post_type( $course_id ) ) {
			return 0;
		}

		$product = wc_get_product( $this->linked_product_id( $course_id ) );

		if ( ! $product ) {
			$product = new \WC_Product_Simple();
			$product->set_catalog_visibility( 'hidden' );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
		}

		$this->syncing = true;

		// Variable and other product types set by hand keep their own prices.
		if ( $product->is_type( 'simple' ) ) {
			$product->set_name( get_the_title( $course_id ) );
			$product->set_status( 'publish' );
			$product->set_image_id( (int) get_post_thumbnail_id( $course_id ) );
			$product->set_regular_price( (string) $pricing['price'] );
			$product->set_sale_price( $pricing['on_sale'] ? (string) $pricing['sale_price'] : '' );
			$product->save();
		}

		$this->syncing = false;

		update_post_meta( $product->get_id(), self::PRODUCT_META, $course_id );
		update_post_meta( $course_id, self::COURSE_META, $product->get_id() );

		return $product->get_id();
	}

	/**
	 * The course's product id, found through either side of the link.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	private function linked_product_id( $course_id ) {
		$product_id = (int) get_post_meta( $course_id, self::COURSE_META, true );

		if ( $product_id && 'product' === get_post_type( $product_id ) ) {
			return $product_id;
		}

		$found = get_posts(
			array(
				'post_type'     => 'product',
				'post_status'   => 'any',
				'numberposts'   => 1,
				'fields'        => 'ids',
				'no_found_rows' => true,
				'meta_key'      => self::PRODUCT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup while saving a course.
				'meta_value'    => (string) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one lookup while saving a course.
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Copies a price edited on the product screen back to the course, so both show the same.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public function pull_price( $product_id ) {
		$course_id = (int) get_post_meta( $product_id, self::PRODUCT_META, true );
		$product   = wc_get_product( $product_id );

		if ( $this->syncing || ! $course_id || ! $product || ! $product->is_type( 'simple' ) || 'paid' !== get_post_meta( $course_id, '_shapelms_price_type', true ) ) {
			return;
		}

		update_post_meta( $course_id, '_shapelms_price', (float) $product->get_regular_price() );
		update_post_meta( $course_id, '_shapelms_sale_price', (float) $product->get_sale_price() );
	}

	/**
	 * Forgets a product that was deleted for good.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function clear_deleted_product( $post_id ) {
		$course_id = 'product' === get_post_type( $post_id ) ? (int) get_post_meta( $post_id, self::PRODUCT_META, true ) : 0;

		if ( $course_id && (int) get_post_meta( $course_id, self::COURSE_META, true ) === (int) $post_id ) {
			delete_post_meta( $course_id, self::COURSE_META );
		}
	}

	/**
	 * Gives paid courses that have no product one, once after WooCommerce becomes the engine.
	 *
	 * @return void
	 */
	public function backfill() {
		if ( get_option( self::BACKFILLED ) || ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$course_ids = get_posts(
			array(
				'post_type'     => PostTypes::COURSE,
				'post_status'   => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
				'meta_key'      => '_shapelms_price_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- runs once.
				'meta_value'    => 'paid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- runs once.
			)
		);

		foreach ( $course_ids as $course_id ) {
			if ( ! wc_get_product( $this->linked_product_id( (int) $course_id ) ) ) {
				$this->sync_product( (int) $course_id );
			}
		}

		update_option( self::BACKFILLED, 1 );
	}

	/**
	 * Adds the product link to course data.
	 *
	 * @param array    $data Course data.
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public function course_data( $data, $post ) {
		$data['woo_product_url'] = $data['woo_product_id'] ? get_edit_post_link( $data['woo_product_id'], 'raw' ) : '';

		return $data;
	}

	/**
	 * Routes: create/link a product from the builder.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			'shapelms/v1',
			'/woocommerce/product',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_product' ),
				'permission_callback' => static function ( $request ) {
					$course_id = (int) $request->get_param( 'course_id' );

					return PostTypes::COURSE === get_post_type( $course_id ) && current_user_can( 'shapelms_edit_course', $course_id );
				},
				'args'                => array(
					'course_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Creates a simple product for a course (or re-links an existing one).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_product( $request ) {
		$product_id = $this->sync_product( (int) $request->get_param( 'course_id' ) );

		if ( ! $product_id ) {
			return new \WP_Error( 'shapelms_woo_no_price', __( 'Save the course with a price first.', 'shapelms' ), array( 'status' => 400 ) );
		}

		return array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	/**
	 * Course selector on the product edit screen.
	 *
	 * @return void
	 */
	public function product_field() {
		global $post;

		$courses = get_posts(
			array(
				'post_type'     => PostTypes::COURSE,
				'post_status'   => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'   => 100,
				'no_found_rows' => true,
			)
		);
		$options = array( '' => __( '— None —', 'shapelms' ) );
		foreach ( $courses as $course ) {
			$options[ $course->ID ] = $course->post_title;
		}

		woocommerce_wp_select(
			array(
				'id'          => self::PRODUCT_META,
				'label'       => __( 'ShapeLMS course', 'shapelms' ),
				'options'     => $options,
				'value'       => (string) get_post_meta( $post->ID, self::PRODUCT_META, true ),
				'desc_tip'    => true,
				'description' => __( 'Buyers are enrolled in this course when the order is paid.', 'shapelms' ),
			)
		);
	}

	/**
	 * Saves the selector.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public function save_product_field( $product_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its own nonce before this hook.
		$course_id = isset( $_POST[ self::PRODUCT_META ] ) ? absint( wp_unslash( $_POST[ self::PRODUCT_META ] ) ) : 0;

		if ( $course_id && PostTypes::COURSE === get_post_type( $course_id ) ) {
			update_post_meta( $product_id, self::PRODUCT_META, $course_id );
			update_post_meta( $course_id, self::COURSE_META, $product_id );
			$this->pull_price( $product_id );
		} else {
			$previous = (int) get_post_meta( $product_id, self::PRODUCT_META, true );
			if ( $previous && (int) get_post_meta( $previous, self::COURSE_META, true ) === (int) $product_id ) {
				delete_post_meta( $previous, self::COURSE_META );
			}
			delete_post_meta( $product_id, self::PRODUCT_META );
		}
	}
}
