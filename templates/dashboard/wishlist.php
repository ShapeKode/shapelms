<?php
/**
 * Dashboard: wishlist.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_ids = \ShapeLMS\Modules\Wishlist\Module::ids( (int) $args['user']->ID );
?>
<h2><?php esc_html_e( 'Wishlist', 'shapelms' ); ?></h2>

<?php if ( $shapelms_ids ) : ?>
	<div class="shapelms-grid shapelms-grid--2">
		<?php foreach ( $shapelms_ids as $shapelms_id ) : ?>
			<?php
			if ( 'publish' !== get_post_status( $shapelms_id ) ) {
				continue;
			}
			shapelms_get_template( 'parts/course-card.php', array( 'course_id' => $shapelms_id ) );
			?>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'Your wishlist is empty.', 'shapelms' ); ?></p></div>
<?php endif; ?>
