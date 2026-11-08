<?php
/**
 * Minimal WooCommerce stubs for static analysis.
 *
 * @package ShapeLMS
 */

// phpcs:ignoreFile

defined( 'ABSPATH' ) || exit;

class WooCommerce {
	/** @var WC_Cart|null */
	public $cart;
	/** @var object|null */
	public $session;
}

/** @return WooCommerce */
function WC() {}

class WC_Cart {
	/** @param int $product_id @return string */
	public function generate_cart_id( $product_id ) {}
	/** @param string $cart_id @return string */
	public function find_product_in_cart( $cart_id ) {}
	/** @return array<string, array<string, mixed>> */
	public function get_cart() {}
}

class WC_Product {
	/** @return int */
	public function get_id() {}
	/** @return int */
	public function get_parent_id() {}
	/** @return bool */
	public function is_purchasable() {}
	/** @param string $type @return bool */
	public function is_type( $type ) {}
	/** @return string */
	public function get_regular_price() {}
	/** @return string */
	public function get_sale_price() {}
	/** @param string $name @return void */
	public function set_name( $name ) {}
	/** @param string $status @return void */
	public function set_status( $status ) {}
	/** @param int $id @return void */
	public function set_image_id( $id ) {}
	/** @param string $visibility @return void */
	public function set_catalog_visibility( $visibility ) {}
	/** @param bool $virtual @return void */
	public function set_virtual( $virtual ) {}
	/** @param bool $sold @return void */
	public function set_sold_individually( $sold ) {}
	/** @param string $price @return void */
	public function set_regular_price( $price ) {}
	/** @param string $price @return void */
	public function set_sale_price( $price ) {}
	/** @return int */
	public function save() {}
}

class WC_Order_Item {}

class WC_Order {
	/** @return int */
	public function get_user_id() {}
	/** @return string */
	public function get_view_order_url() {}
	/** @return string */
	public function get_payment_method() {}
	/** @param string $key @return bool */
	public function key_is_valid( $key ) {}
	/** @param string $status @return bool */
	public function has_status( $status ) {}
	/** @param string $status @param string $note @return bool */
	public function update_status( $status, $note = '' ) {}
	/** @param string $note @return int */
	public function add_order_note( $note ) {}

	/** @return array<int, WC_Order_Item_Product> */
	public function get_items() {}
}

class WC_Order_Item_Product extends WC_Order_Item {
	/** @return int */
	public function get_product_id() {}
}

class WC_Product_Simple extends WC_Product {}

/**
 * @param int $order_id
 * @return WC_Order|false
 */
function wc_get_order( $order_id ) {}

/**
 * @param array<string, mixed> $args
 * @return WC_Order[]
 */
function wc_get_orders( $args ) {}

/** @return string */
function wc_get_checkout_url() {}
function wc_get_cart_url() {}

/**
 * @param string $page
 * @return int
 */
function wc_get_page_id( $page ) {}

/**
 * @param mixed $product
 * @return WC_Product|false|null
 */
function wc_get_product( $product = false ) {}

/**
 * @param float $price
 * @return string
 */
function wc_price( $price ) {}

/** @return void */
function wc_print_notices() {}

/** @return bool */
function is_order_received_page() {}

/**
 * @param array<string, mixed> $field
 * @return void
 */
function woocommerce_wp_select( $field ) {}
