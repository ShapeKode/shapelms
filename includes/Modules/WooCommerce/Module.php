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
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Services\EnrollmentService;

/**
 * Sells courses as WooCommerce products: enrolls on payment, revokes on refund/cancel.
 */
class Module extends AbstractModule {

	const PRODUCT_META = '_shapelms_course_id';

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
		if ( ! class_exists( 'WooCommerce' ) || 'woocommerce' !== shapelms_get_setting( 'commerce', 'engine', 'native' ) ) {
			return;
		}

		add_action( 'woocommerce_order_status_completed', array( $this, 'grant' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'grant' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'revoke' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'revoke' ) );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'revoke' ) );

		add_filter( 'shapelms_course_buy_url', array( $this, 'buy_url' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'shapelms_course_data', array( $this, 'course_data' ), 10, 2 );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );
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

		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}

		$enrollment = shapelms()->get( EnrollmentService::class );

		foreach ( $this->course_ids( $order ) as $course_id ) {
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
			if ( $row && 'woocommerce' === $row->source ) {
				$enrollment->unenroll( $order->get_user_id(), $course_id );
			}
		}
	}

	/**
	 * Buy URL = add to cart + checkout.
	 *
	 * @param string $url       URL.
	 * @param int    $course_id Course id.
	 * @return string
	 */
	public function buy_url( $url, $course_id ) {
		$product_id = (int) get_post_meta( $course_id, '_shapelms_woo_product_id', true );

		if ( ! $product_id || ! function_exists( 'wc_get_checkout_url' ) ) {
			return $url;
		}

		return add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() );
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
		$course_id = (int) $request->get_param( 'course_id' );
		$existing  = (int) get_post_meta( $course_id, '_shapelms_woo_product_id', true );

		if ( $existing && 'product' === get_post_type( $existing ) ) {
			return array(
				'product_id' => $existing,
				'edit_url'   => get_edit_post_link( $existing, 'raw' ),
			);
		}

		$pricing = \ShapeLMS\Services\CourseService::pricing( $course_id );
		$product = new \WC_Product_Simple();
		$product->set_name( get_the_title( $course_id ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( (string) $pricing['price'] );
		if ( $pricing['on_sale'] ) {
			$product->set_sale_price( (string) $pricing['sale_price'] );
		}
		$product_id = $product->save();

		update_post_meta( $product_id, self::PRODUCT_META, $course_id );
		update_post_meta( $course_id, '_shapelms_woo_product_id', $product_id );

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
			update_post_meta( $course_id, '_shapelms_woo_product_id', $product_id );
		} else {
			$previous = (int) get_post_meta( $product_id, self::PRODUCT_META, true );
			if ( $previous && (int) get_post_meta( $previous, '_shapelms_woo_product_id', true ) === (int) $product_id ) {
				delete_post_meta( $previous, '_shapelms_woo_product_id' );
			}
			delete_post_meta( $product_id, self::PRODUCT_META );
		}
	}
}
