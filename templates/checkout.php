<?php
/**
 * Checkout page (rendered by the shapelms/checkout block).
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Modules\Checkout\Module as Checkout;

$shapelms_registry = shapelms()->get( \ShapeLMS\Core\Modules\ModuleRegistry::class );

if ( ! $shapelms_registry->is_active( 'checkout' ) || ! Checkout::is_active_engine() ) {
	echo '<p class="shapelms-notice">' . esc_html__( 'Checkout is not available.', 'shapelms' ) . '</p>';
	return;
}

$shapelms_checkout = $shapelms_registry->get( 'checkout' );
$shapelms_courses  = Checkout::requested_courses();
$shapelms_gateways = $shapelms_checkout->enabled_gateways();
$shapelms_total    = 0.0;
$shapelms_ids      = array();

foreach ( $shapelms_courses as $shapelms_course ) {
	if ( ! $shapelms_course['already_enrolled'] && ! $shapelms_course['pricing']['is_free'] ) {
		$shapelms_total += $shapelms_course['pricing']['current'];
		$shapelms_ids[]  = $shapelms_course['id'];
	}
}

$shapelms_data_attributes = \ShapeLMS\Frontend\Frontend::data_attributes(
	array_merge(
		$shapelms_checkout->client_data(),
		array(
			'course-ids' => implode( ',', $shapelms_ids ),
			'total'      => number_format( $shapelms_total, 2, '.', '' ),
			'currency'   => \ShapeLMS\Services\CurrencyService::code(),
		)
	)
);
?>
<div class="shapelms-checkout" id="shapelms-checkout" <?php echo $shapelms_data_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<div class="shapelms-checkout__main">
		<h1><?php esc_html_e( 'Checkout', 'shapelms' ); ?></h1>

		<?php if ( ! $shapelms_courses ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'No course selected.', 'shapelms' ); ?> <a href="<?php echo esc_url( get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Browse courses', 'shapelms' ); ?></a></p>
		<?php elseif ( ! is_user_logged_in() ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Please log in or create an account to continue.', 'shapelms' ); ?></p>
			<div class="shapelms-checkout__auth">
				<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( shapelms_login_url( (string) get_permalink() . '?course=' . implode( ',', wp_list_pluck( $shapelms_courses, 'id' ) ) ) ); ?>"><?php esc_html_e( 'Log in', 'shapelms' ); ?></a>
				<?php if ( \ShapeLMS\Frontend\Auth::is_open() ) : ?>
					<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( shapelms_register_url( (string) get_permalink() . '?course=' . implode( ',', wp_list_pluck( $shapelms_courses, 'id' ) ) ) ); ?>"><?php esc_html_e( 'Create account', 'shapelms' ); ?></a>
				<?php endif; ?>
			</div>
		<?php elseif ( ! $shapelms_ids ) : ?>
			<p class="shapelms-notice shapelms-notice--success"><?php esc_html_e( 'You already have access to these courses.', 'shapelms' ); ?></p>
			<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( $shapelms_courses[0]['url'] ); ?>"><?php esc_html_e( 'Go to course', 'shapelms' ); ?></a>
		<?php elseif ( ! $shapelms_gateways ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'No payment method is configured yet. Please contact the site owner.', 'shapelms' ); ?></p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Payment method', 'shapelms' ); ?></h2>
			<div class="shapelms-gateways" role="radiogroup">
				<?php $shapelms_first = true; ?>
				<?php foreach ( $shapelms_gateways as $shapelms_gateway ) : ?>
					<label class="shapelms-gateway">
						<input type="radio" name="shapelms_gateway" value="<?php echo esc_attr( $shapelms_gateway->id() ); ?>" <?php checked( $shapelms_first ); ?>>
						<span><?php echo esc_html( $shapelms_gateway->label() ); ?></span>
					</label>
					<?php $shapelms_first = false; ?>
				<?php endforeach; ?>
			</div>

			<div class="shapelms-gateway__panel" data-gateway-panel="stripe" hidden>
				<div id="shapelms-stripe-element"></div>
			</div>
			<div class="shapelms-gateway__panel" data-gateway-panel="paypal" hidden>
				<div id="shapelms-paypal-buttons"></div>
			</div>

			<button type="button" class="shapelms-btn shapelms-btn--primary shapelms-btn--block" data-shapelms-pay>
				<?php echo esc_html( sprintf( /* translators: %s: total */ __( 'Pay %s', 'shapelms' ), shapelms_format_price( $shapelms_total ) ) ); ?>
			</button>
			<p class="shapelms-checkout__message" data-shapelms-checkout-message aria-live="polite"></p>
			<p class="shapelms-muted"><?php esc_html_e( 'Payments are processed securely by the selected provider. Your card details never touch this site.', 'shapelms' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $shapelms_courses ) : ?>
		<aside class="shapelms-checkout__summary">
			<h2><?php esc_html_e( 'Order summary', 'shapelms' ); ?></h2>
			<?php foreach ( $shapelms_courses as $shapelms_course ) : ?>
				<div class="shapelms-checkout__item">
					<span><?php echo esc_html( $shapelms_course['title'] ); ?></span>
					<strong>
						<?php if ( $shapelms_course['already_enrolled'] ) : ?>
							<?php esc_html_e( 'Enrolled', 'shapelms' ); ?>
						<?php else : ?>
							<?php echo esc_html( $shapelms_course['price_html'] ); ?>
						<?php endif; ?>
					</strong>
				</div>
			<?php endforeach; ?>
			<div class="shapelms-checkout__total">
				<span><?php esc_html_e( 'Total', 'shapelms' ); ?></span>
				<span><?php echo esc_html( shapelms_format_price( $shapelms_total ) ); ?></span>
			</div>
		</aside>
	<?php endif; ?>
</div>
