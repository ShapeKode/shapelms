<?php
/**
 * Dashboard: my learning.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_user_id  = (int) $args['user']->ID;
$shapelms_items    = shapelms()->get( \ShapeLMS\Services\EnrollmentService::class )->for_user( $shapelms_user_id, 'all' );
$shapelms_progress = shapelms()->get( \ShapeLMS\Services\ProgressService::class );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$shapelms_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

if ( 'all' !== $shapelms_filter ) {
	$shapelms_items = array_filter(
		$shapelms_items,
		static function ( $item ) use ( $shapelms_filter ) {
			return $item['enrollment']['status'] === $shapelms_filter;
		}
	);
}
?>
<h2><?php esc_html_e( 'My learning', 'shapelms' ); ?></h2>

<p class="shapelms-dashboard__filters">
	<?php
	foreach ( array(
		'all'       => __( 'All', 'shapelms' ),
		'active'    => __( 'In progress', 'shapelms' ),
		'completed' => __( 'Completed', 'shapelms' ),
	) as $shapelms_key => $shapelms_label ) :
		?>
		<a class="shapelms-tag<?php echo $shapelms_key === $shapelms_filter ? '' : ' shapelms-tag--muted'; ?>" href="<?php echo esc_url( add_query_arg( 'status', $shapelms_key, \ShapeLMS\Frontend\Dashboard::url( 'courses' ) ) ); ?>"><?php echo esc_html( $shapelms_label ); ?></a>
	<?php endforeach; ?>
</p>

<?php if ( $shapelms_items ) : ?>
	<?php foreach ( $shapelms_items as $shapelms_item ) : ?>
		<?php
		shapelms_get_template(
			'dashboard/parts/enrolled-card.php',
			array(
				'item'   => $shapelms_item,
				'resume' => $shapelms_progress->resume_item( $shapelms_user_id, (int) $shapelms_item['course']['id'] ),
			)
		);
		?>
	<?php endforeach; ?>
<?php else : ?>
	<div class="shapelms-empty">
		<p><?php esc_html_e( 'No courses here yet.', 'shapelms' ); ?></p>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Browse courses', 'shapelms' ); ?></a>
	</div>
<?php endif; ?>
