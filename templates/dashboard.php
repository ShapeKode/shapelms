<?php
/**
 * Dashboard shell.
 *
 * @var array $args { tabs, current, user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Frontend\Dashboard;

$shapelms_tabs    = $args['tabs'];
$shapelms_current = $args['current'];
$shapelms_user    = $args['user'];
$shapelms_tab     = null;

foreach ( $shapelms_tabs as $shapelms_group ) {
	if ( isset( $shapelms_group[ $shapelms_current ] ) ) {
		$shapelms_tab = $shapelms_group[ $shapelms_current ];
	}
}
?>
<div class="shapelms-dashboard">
	<nav class="shapelms-dashboard__nav" aria-label="<?php esc_attr_e( 'Dashboard', 'shapelms' ); ?>">
		<div class="shapelms-dashboard__user">
			<img src="<?php echo esc_url( get_avatar_url( $shapelms_user->ID, array( 'size' => 88 ) ) ); ?>" alt="" width="44" height="44">
			<div>
				<strong><?php echo esc_html( $shapelms_user->display_name ); ?></strong>
				<span class="shapelms-muted"><?php echo esc_html( $shapelms_user->user_email ); ?></span>
			</div>
		</div>
		<ul class="shapelms-dashboard__menu">
			<?php foreach ( $shapelms_tabs as $shapelms_section => $shapelms_group ) : ?>
				<li class="is-section"><?php echo esc_html( Dashboard::section_label( $shapelms_section ) ); ?></li>
				<?php foreach ( $shapelms_group as $shapelms_id => $shapelms_item ) : ?>
					<li>
						<a href="<?php echo esc_url( Dashboard::url( $shapelms_id ) ); ?>" class="<?php echo $shapelms_id === $shapelms_current ? 'is-active' : ''; ?>" <?php echo $shapelms_id === $shapelms_current ? 'aria-current="page"' : ''; ?>>
							<?php echo esc_html( $shapelms_item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<li class="is-section"></li>
			<li><a href="<?php echo esc_url( wp_logout_url( shapelms_login_url() ) ); ?>"><?php esc_html_e( 'Log out', 'shapelms' ); ?></a></li>
		</ul>
	</nav>

	<main class="shapelms-dashboard__panel">
		<?php
		if ( $shapelms_tab && ! empty( $shapelms_tab['template'] ) ) {
			shapelms_get_template( $shapelms_tab['template'], array( 'user' => $shapelms_user ) );
		} elseif ( $shapelms_tab && ! empty( $shapelms_tab['callback'] ) && is_callable( $shapelms_tab['callback'] ) ) {
			call_user_func( $shapelms_tab['callback'], $shapelms_user );
		}
		?>
	</main>
</div>
