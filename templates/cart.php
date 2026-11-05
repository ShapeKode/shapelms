<?php
/**
 * Cart page (rendered by the shapelms/cart block).
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Modules\Checkout\Module as Checkout;
use ShapeLMS\Services\CartService;
use ShapeLMS\Services\CourseService;

if ( ! shapelms()->get( \ShapeLMS\Core\Modules\ModuleRegistry::class )->is_active( 'checkout' ) || ! Checkout::cart_enabled() || ! is_user_logged_in() ) {
	echo '<p class="shapelms-notice">' . esc_html__( 'The cart is not available.', 'shapelms' ) . '</p>';
	return;
}

$shapelms_ids     = shapelms()->get( CartService::class )->course_ids( get_current_user_id() );
$shapelms_courses = array();
$shapelms_total   = 0.0;

foreach ( $shapelms_ids as $shapelms_id ) {
	$shapelms_course    = shapelms()->get( CourseService::class )->to_array( $shapelms_id, false );
	$shapelms_courses[] = $shapelms_course;
	$shapelms_total    += $shapelms_course['pricing']['current'];
}
?>
<div class="shapelms-checkout shapelms-cart" id="shapelms-cart" <?php echo \ShapeLMS\Frontend\Frontend::data_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<div class="shapelms-checkout__main">
		<?php if ( ! $shapelms_courses ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Your cart is empty.', 'shapelms' ); ?> <a href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Browse courses', 'shapelms' ); ?></a></p>
		<?php else : ?>
			<p class="shapelms-checkout__message" data-shapelms-cart-message aria-live="polite"></p>
			<?php foreach ( $shapelms_courses as $shapelms_course ) : ?>
				<div class="shapelms-cart__item">
					<?php if ( $shapelms_course['image'] ) : ?>
						<img class="shapelms-cart__image" src="<?php echo esc_url( $shapelms_course['image'] ); ?>" alt="">
					<?php endif; ?>
					<a class="shapelms-cart__title" href="<?php echo esc_url( $shapelms_course['url'] ); ?>"><?php echo esc_html( $shapelms_course['title'] ); ?></a>
					<strong><?php echo esc_html( $shapelms_course['price_html'] ); ?></strong>
					<button type="button" class="shapelms-btn shapelms-btn--secondary" data-shapelms-cart-remove="<?php echo esc_attr( (string) $shapelms_course['id'] ); ?>">
						<?php esc_html_e( 'Remove', 'shapelms' ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php if ( $shapelms_courses ) : ?>
		<aside class="shapelms-checkout__summary">
			<h2><?php esc_html_e( 'Order summary', 'shapelms' ); ?></h2>
			<div class="shapelms-checkout__total">
				<span><?php esc_html_e( 'Total', 'shapelms' ); ?></span>
				<span><?php echo esc_html( shapelms_format_price( $shapelms_total ) ); ?></span>
			</div>
			<a class="shapelms-btn shapelms-btn--primary shapelms-btn--block shapelms-cart__checkout" rel="nofollow" href="<?php echo esc_url( add_query_arg( 'course', implode( ',', $shapelms_ids ), \ShapeLMS\Core\Pages::get_url( 'checkout' ) ) ); ?>">
				<?php esc_html_e( 'Proceed to checkout', 'shapelms' ); ?>
			</a>
		</aside>
	<?php endif; ?>
</div>
