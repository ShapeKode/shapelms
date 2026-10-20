<?php
/**
 * Dashboard: overview.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_user_id    = (int) $args['user']->ID;
$shapelms_enrollment = shapelms()->get( \ShapeLMS\Services\EnrollmentService::class );
$shapelms_progress   = shapelms()->get( \ShapeLMS\Services\ProgressService::class );
$shapelms_all        = $shapelms_enrollment->for_user( $shapelms_user_id, 'all' );
$shapelms_active     = array_filter(
	$shapelms_all,
	static function ( $item ) {
		return 'active' === $item['enrollment']['status'];
	}
);
$shapelms_completed  = array_filter(
	$shapelms_all,
	static function ( $item ) {
		return 'completed' === $item['enrollment']['status'];
	}
);
$shapelms_attempts   = shapelms()->get( \ShapeLMS\Database\AttemptRepository::class )->count(
	array(
		'user_id' => $shapelms_user_id,
		'status'  => array( 'completed', 'expired' ),
	)
);
$shapelms_recent     = array_slice( $shapelms_active, 0, 3 );
?>
<h2><?php echo esc_html( sprintf( /* translators: %s: name */ __( 'Welcome back, %s', 'shapelms' ), $args['user']->first_name ? $args['user']->first_name : $args['user']->display_name ) ); ?></h2>

<div class="shapelms-stats">
	<div class="shapelms-stat"><span><?php esc_html_e( 'Enrolled courses', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $shapelms_all ) ) ); ?></strong></div>
	<div class="shapelms-stat"><span><?php esc_html_e( 'In progress', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $shapelms_active ) ) ); ?></strong></div>
	<div class="shapelms-stat"><span><?php esc_html_e( 'Completed', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $shapelms_completed ) ) ); ?></strong></div>
	<div class="shapelms-stat"><span><?php esc_html_e( 'Quiz attempts', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $shapelms_attempts ) ); ?></strong></div>
</div>

<?php if ( $shapelms_recent ) : ?>
	<h3><?php esc_html_e( 'Continue learning', 'shapelms' ); ?></h3>
	<?php foreach ( $shapelms_recent as $shapelms_item ) : ?>
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
<?php elseif ( ! $shapelms_all ) : ?>
	<div class="shapelms-empty">
		<p><?php esc_html_e( "You haven't enrolled in any course yet.", 'shapelms' ); ?></p>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Browse courses', 'shapelms' ); ?></a>
	</div>
<?php endif; ?>

<?php
if ( shapelms()->get( \ShapeLMS\Core\Modules\ModuleRegistry::class )->is_active( 'announcements' ) ) {
	$shapelms_announcements = \ShapeLMS\Modules\Announcements\Module::for_user( 0, $shapelms_user_id );
	if ( $shapelms_announcements ) {
		echo '<h3>' . esc_html__( 'Latest announcements', 'shapelms' ) . '</h3>';
		shapelms_get_template( 'parts/announcements.php', array( 'items' => array_slice( $shapelms_announcements, 0, 3 ) ) );
	}
}
?>
