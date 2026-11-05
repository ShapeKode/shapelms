<?php
/**
 * Order page: shown on the checkout page after an order is placed.
 *
 * @var array $args { order, gateway }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_order   = $args['order'];
$shapelms_gateway = $args['gateway'];
$shapelms_paid    = 'completed' === $shapelms_order->status;
$shapelms_held    = 'on-hold' === $shapelms_order->status;
$shapelms_items   = shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->items( (int) $shapelms_order->id );
$shapelms_steps   = $shapelms_held && $shapelms_gateway instanceof \ShapeLMS\Modules\Checkout\OfflineGateway ? $shapelms_gateway->instructions() : '';
?>
<div class="shapelms-receipt">
	<?php if ( $shapelms_paid ) : ?>
		<h1><?php esc_html_e( 'Order Confirmed', 'shapelms' ); ?></h1>
		<p><?php esc_html_e( 'You will receive an order confirmation email shortly.', 'shapelms' ); ?></p>
	<?php elseif ( $shapelms_held ) : ?>
		<h1><?php esc_html_e( 'Order Confirmed', 'shapelms' ); ?></h1>
		<p><?php esc_html_e( 'You will receive an order confirmation email shortly. You get access to the course as soon as we receive your payment.', 'shapelms' ); ?></p>
	<?php else : ?>
		<h1><?php esc_html_e( 'Order details', 'shapelms' ); ?></h1>
		<p class="shapelms-notice"><?php esc_html_e( 'This order is not paid.', 'shapelms' ); ?></p>
	<?php endif; ?>

	<?php if ( $shapelms_steps ) : ?>
		<?php // A pre block keeps line breaks, and keeps WordPress from turning the hyphens of an account number into dashes. ?>
		<pre class="shapelms-receipt__steps"><?php echo esc_html( $shapelms_steps ); ?></pre>
	<?php endif; ?>

	<ul class="shapelms-receipt__meta">
		<li><span><?php esc_html_e( 'Order number', 'shapelms' ); ?></span><strong>#<?php echo esc_html( (string) $shapelms_order->id ); ?></strong></li>
		<?php if ( $shapelms_gateway ) : ?>
			<li><span><?php esc_html_e( 'Payment method', 'shapelms' ); ?></span><strong><?php echo esc_html( $shapelms_gateway->label() ); ?></strong></li>
		<?php endif; ?>
		<?php foreach ( $shapelms_items as $shapelms_item ) : ?>
			<li><span><?php echo esc_html( $shapelms_item->title ); ?></span><strong><?php echo esc_html( shapelms_format_price( (float) $shapelms_item->price ) ); ?></strong></li>
		<?php endforeach; ?>
		<li class="shapelms-receipt__total"><span><?php esc_html_e( 'Total', 'shapelms' ); ?></span><strong><?php echo esc_html( shapelms_format_price( (float) $shapelms_order->total ) ); ?></strong></li>
		<?php if ( $shapelms_held ) : ?>
			<li><span><?php esc_html_e( 'Amount due', 'shapelms' ); ?></span><strong><?php echo esc_html( shapelms_format_price( (float) $shapelms_order->total ) ); ?></strong></li>
		<?php endif; ?>
	</ul>

	<div class="shapelms-receipt__actions">
		<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( \ShapeLMS\Frontend\Dashboard::url( 'orders' ) ); ?>"><?php esc_html_e( 'Check order list', 'shapelms' ); ?></a>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Continue shopping', 'shapelms' ); ?></a>
	</div>
</div>
