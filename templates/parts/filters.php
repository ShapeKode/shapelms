<?php
/**
 * Archive filters.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
$shapelms_level   = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
$shapelms_price   = isset( $_GET['price'] ) ? sanitize_key( wp_unslash( $_GET['price'] ) ) : '';
$shapelms_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
$shapelms_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable

$shapelms_levels = array(
	''             => __( 'All levels', 'shapelms' ),
	'beginner'     => __( 'Beginner', 'shapelms' ),
	'intermediate' => __( 'Intermediate', 'shapelms' ),
	'advanced'     => __( 'Advanced', 'shapelms' ),
);
$shapelms_prices = array(
	''     => __( 'Free & paid', 'shapelms' ),
	'free' => __( 'Free', 'shapelms' ),
	'paid' => __( 'Paid', 'shapelms' ),
);
$shapelms_orders = array(
	''      => __( 'Newest', 'shapelms' ),
	'title' => __( 'Title A–Z', 'shapelms' ),
	'price' => __( 'Price: low to high', 'shapelms' ),
);
?>
<form class="shapelms-filters" method="get" action="">
	<?php if ( is_post_type_archive() ) : ?>
		<input type="hidden" name="post_type" value="shapelms_course">
	<?php endif; ?>

	<label class="shapelms-filters__search">
		<span class="screen-reader-text"><?php esc_html_e( 'Search courses', 'shapelms' ); ?></span>
		<input type="search" name="s" value="<?php echo esc_attr( $shapelms_search ); ?>" placeholder="<?php esc_attr_e( 'Search courses…', 'shapelms' ); ?>">
	</label>

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Level', 'shapelms' ); ?></span>
		<select name="level" class="shapelms-filters__select">
			<?php foreach ( $shapelms_levels as $shapelms_value => $shapelms_label ) : ?>
				<option value="<?php echo esc_attr( $shapelms_value ); ?>" <?php selected( $shapelms_level, $shapelms_value ); ?>><?php echo esc_html( $shapelms_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Price', 'shapelms' ); ?></span>
		<select name="price" class="shapelms-filters__select">
			<?php foreach ( $shapelms_prices as $shapelms_value => $shapelms_label ) : ?>
				<option value="<?php echo esc_attr( $shapelms_value ); ?>" <?php selected( $shapelms_price, $shapelms_value ); ?>><?php echo esc_html( $shapelms_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Sort by', 'shapelms' ); ?></span>
		<select name="orderby" class="shapelms-filters__select">
			<?php foreach ( $shapelms_orders as $shapelms_value => $shapelms_label ) : ?>
				<option value="<?php echo esc_attr( $shapelms_value ); ?>" <?php selected( $shapelms_orderby, $shapelms_value ); ?>><?php echo esc_html( $shapelms_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<button type="submit" class="shapelms-btn shapelms-btn--secondary"><?php esc_html_e( 'Filter', 'shapelms' ); ?></button>
</form>
