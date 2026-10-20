<?php
/**
 * Minimal WooCommerce stubs for static analysis.
 *
 * @package ShapeLMS
 */

// phpcs:ignoreFile

class WooCommerce {}

class WC_Order {
	/** @return int */
	public function get_user_id() {}

	/** @return array<int, WC_Order_Item_Product> */
	public function get_items() {}
}

class WC_Order_Item_Product {
	/** @return int */
	public function get_product_id() {}
}

class WC_Product_Simple {
	/** @param string $name @return void */
	public function set_name( $name ) {}
	/** @param string $status @return void */
	public function set_status( $status ) {}
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

/**
 * @param int $order_id
 * @return WC_Order|false
 */
function wc_get_order( $order_id ) {}

/** @return string */
function wc_get_checkout_url() {}

/**
 * @param array<string, mixed> $field
 * @return void
 */
function woocommerce_wp_select( $field ) {}
