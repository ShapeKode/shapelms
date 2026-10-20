<?php
/**
 * Announcements list.
 *
 * @var array $args { items }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_items = isset( $args['items'] ) ? $args['items'] : array();

if ( ! $shapelms_items ) {
	return;
}
?>
<section class="shapelms-box shapelms-announcements" id="announcements">
	<h2 class="shapelms-box__title"><?php esc_html_e( 'Announcements', 'shapelms' ); ?></h2>
	<?php foreach ( $shapelms_items as $shapelms_item ) : ?>
		<article class="shapelms-announcement">
			<h3 class="shapelms-announcement__title"><?php echo esc_html( $shapelms_item['title'] ); ?></h3>
			<p class="shapelms-muted"><?php echo esc_html( sprintf( /* translators: 1: author, 2: date */ __( '%1$s · %2$s', 'shapelms' ), $shapelms_item['author'], $shapelms_item['date'] ) ); ?></p>
			<div class="shapelms-prose"><?php echo wp_kses_post( $shapelms_item['content'] ); ?></div>
		</article>
	<?php endforeach; ?>
</section>
