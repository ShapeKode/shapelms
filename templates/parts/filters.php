<?php
/**
 * Archive filters.
 *
 * Rendered three ways: layout=sidebar (vertical panel), layout=top (horizontal
 * bar) and layout=sort (the small sort form in the toolbar of the sidebar layout).
 *
 * @var array $args { layout }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;
use ShapeLMS\Frontend\Frontend;

$shapelms_layout = isset( $args['layout'] ) ? $args['layout'] : 'top';
$shapelms_active = Frontend::active_filters();
$shapelms_level  = isset( $shapelms_active['level'] ) ? $shapelms_active['level'] : '';
$shapelms_price  = isset( $shapelms_active['price'] ) ? $shapelms_active['price'] : '';
$shapelms_sort   = isset( $shapelms_active['orderby'] ) ? $shapelms_active['orderby'] : '';
$shapelms_search = isset( $shapelms_active['s'] ) ? $shapelms_active['s'] : '';
$shapelms_action = (string) get_post_type_archive_link( PostTypes::COURSE );

$shapelms_taxonomy   = get_taxonomy( Taxonomies::CATEGORY );
$shapelms_cat_var    = $shapelms_taxonomy && $shapelms_taxonomy->query_var ? (string) $shapelms_taxonomy->query_var : '';
$shapelms_current    = $shapelms_cat_var && isset( $shapelms_active['category'] ) ? $shapelms_active['category'] : '';
$shapelms_categories = $shapelms_cat_var ? get_terms(
	array(
		'taxonomy'   => Taxonomies::CATEGORY,
		'hide_empty' => true,
	)
) : array();
$shapelms_categories = is_array( $shapelms_categories ) ? $shapelms_categories : array();

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
	''        => __( 'Newest', 'shapelms' ),
	'title'   => __( 'Title A–Z', 'shapelms' ),
	'price'   => __( 'Price: low to high', 'shapelms' ),
	'popular' => __( 'Most popular', 'shapelms' ),
);

if ( 'sort' === $shapelms_layout ) :
	?>
	<form class="shapelms-filters shapelms-filters--sort" method="get" action="<?php echo esc_url( $shapelms_action ); ?>" data-shapelms-filters>
		<input type="hidden" name="post_type" value="shapelms_course">
		<?php if ( $shapelms_search ) : ?>
			<input type="hidden" name="s" value="<?php echo esc_attr( $shapelms_search ); ?>">
		<?php endif; ?>
		<?php if ( $shapelms_level ) : ?>
			<input type="hidden" name="level" value="<?php echo esc_attr( $shapelms_level ); ?>">
		<?php endif; ?>
		<?php if ( $shapelms_price ) : ?>
			<input type="hidden" name="price" value="<?php echo esc_attr( $shapelms_price ); ?>">
		<?php endif; ?>
		<?php if ( $shapelms_current && $shapelms_cat_var ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $shapelms_cat_var ); ?>" value="<?php echo esc_attr( $shapelms_current ); ?>">
		<?php endif; ?>
		<label class="shapelms-filters__sort">
			<span><?php esc_html_e( 'Sort by', 'shapelms' ); ?></span>
			<select name="orderby" class="shapelms-filters__select">
				<?php foreach ( $shapelms_orders as $shapelms_value => $shapelms_label ) : ?>
					<option value="<?php echo esc_attr( $shapelms_value ); ?>" <?php selected( $shapelms_sort, $shapelms_value ); ?>><?php echo esc_html( $shapelms_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="shapelms-btn shapelms-btn--secondary shapelms-filters__apply"><?php esc_html_e( 'Apply', 'shapelms' ); ?></button>
	</form>
	<?php
	return;
endif;

if ( 'sidebar' === $shapelms_layout ) :
	?>
	<form class="shapelms-filters shapelms-filters--sidebar" method="get" action="<?php echo esc_url( $shapelms_action ); ?>" data-shapelms-filters>
		<input type="hidden" name="post_type" value="shapelms_course">
		<?php if ( $shapelms_sort ) : ?>
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $shapelms_sort ); ?>">
		<?php endif; ?>

		<div class="shapelms-filters__head">
			<h2 class="shapelms-filters__title"><?php esc_html_e( 'Filters', 'shapelms' ); ?></h2>
			<?php if ( $shapelms_active ) : ?>
				<a class="shapelms-filters__clear" href="<?php echo esc_url( $shapelms_action ); ?>"><?php esc_html_e( 'Clear all', 'shapelms' ); ?></a>
			<?php endif; ?>
		</div>

		<label class="shapelms-filters__search">
			<span class="screen-reader-text"><?php esc_html_e( 'Search courses', 'shapelms' ); ?></span>
			<span class="shapelms-filters__search-icon" aria-hidden="true"></span>
			<input type="search" name="s" value="<?php echo esc_attr( $shapelms_search ); ?>" placeholder="<?php esc_attr_e( 'Search courses…', 'shapelms' ); ?>">
		</label>

		<?php if ( $shapelms_categories ) : ?>
			<fieldset class="shapelms-filters__group">
				<legend><?php esc_html_e( 'Category', 'shapelms' ); ?></legend>
				<label class="shapelms-filters__option">
					<input type="radio" name="<?php echo esc_attr( $shapelms_cat_var ); ?>" value="" <?php checked( '', $shapelms_current ); ?>>
					<span><?php esc_html_e( 'All categories', 'shapelms' ); ?></span>
				</label>
				<?php foreach ( $shapelms_categories as $shapelms_term ) : ?>
					<label class="shapelms-filters__option">
						<input type="radio" name="<?php echo esc_attr( $shapelms_cat_var ); ?>" value="<?php echo esc_attr( $shapelms_term->slug ); ?>" <?php checked( $shapelms_current, $shapelms_term->slug ); ?>>
						<span><?php echo esc_html( $shapelms_term->name ); ?></span>
						<small><?php echo esc_html( number_format_i18n( $shapelms_term->count ) ); ?></small>
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>

		<fieldset class="shapelms-filters__group">
			<legend><?php esc_html_e( 'Level', 'shapelms' ); ?></legend>
			<?php foreach ( $shapelms_levels as $shapelms_value => $shapelms_label ) : ?>
				<label class="shapelms-filters__option">
					<input type="radio" name="level" value="<?php echo esc_attr( $shapelms_value ); ?>" <?php checked( $shapelms_level, $shapelms_value ); ?>>
					<span><?php echo esc_html( $shapelms_label ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<fieldset class="shapelms-filters__group">
			<legend><?php esc_html_e( 'Price', 'shapelms' ); ?></legend>
			<?php foreach ( $shapelms_prices as $shapelms_value => $shapelms_label ) : ?>
				<label class="shapelms-filters__option">
					<input type="radio" name="price" value="<?php echo esc_attr( $shapelms_value ); ?>" <?php checked( $shapelms_price, $shapelms_value ); ?>>
					<span><?php echo esc_html( $shapelms_label ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<button type="submit" class="shapelms-btn shapelms-btn--primary shapelms-btn--block shapelms-filters__apply"><?php esc_html_e( 'Apply filters', 'shapelms' ); ?></button>
	</form>
	<?php
	return;
endif;
?>
<form class="shapelms-filters shapelms-filters--top" method="get" action="<?php echo esc_url( $shapelms_action ); ?>" data-shapelms-filters>
	<input type="hidden" name="post_type" value="shapelms_course">

	<label class="shapelms-filters__search">
		<span class="screen-reader-text"><?php esc_html_e( 'Search courses', 'shapelms' ); ?></span>
		<span class="shapelms-filters__search-icon" aria-hidden="true"></span>
		<input type="search" name="s" value="<?php echo esc_attr( $shapelms_search ); ?>" placeholder="<?php esc_attr_e( 'Search courses…', 'shapelms' ); ?>">
	</label>

	<?php if ( $shapelms_categories ) : ?>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Category', 'shapelms' ); ?></span>
			<select name="<?php echo esc_attr( $shapelms_cat_var ); ?>" class="shapelms-filters__select">
				<option value=""><?php esc_html_e( 'All categories', 'shapelms' ); ?></option>
				<?php foreach ( $shapelms_categories as $shapelms_term ) : ?>
					<option value="<?php echo esc_attr( $shapelms_term->slug ); ?>" <?php selected( $shapelms_current, $shapelms_term->slug ); ?>><?php echo esc_html( $shapelms_term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>

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
				<option value="<?php echo esc_attr( $shapelms_value ); ?>" <?php selected( $shapelms_sort, $shapelms_value ); ?>><?php echo esc_html( $shapelms_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<button type="submit" class="shapelms-btn shapelms-btn--secondary shapelms-filters__apply"><?php esc_html_e( 'Filter', 'shapelms' ); ?></button>
</form>
