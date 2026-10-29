<?php
/**
 * Shown on the Sign in / Sign up pages when the visitor is already logged in.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_user = wp_get_current_user();
?>
<div class="shapelms-auth">
	<div class="shapelms-auth__card shapelms-auth__card--center">
		<img class="shapelms-auth__avatar" src="<?php echo esc_url( get_avatar_url( $shapelms_user->ID, array( 'size' => 128 ) ) ); ?>" alt="" width="64" height="64">
		<h2 class="shapelms-auth__subtitle"><?php echo esc_html( sprintf( /* translators: %s: display name */ __( 'You are signed in as %s', 'shapelms' ), $shapelms_user->display_name ) ); ?></h2>
		<a class="shapelms-btn shapelms-btn--auth shapelms-btn--block" href="<?php echo esc_url( \ShapeLMS\Core\Pages::get_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'shapelms' ); ?></a>
		<p class="shapelms-auth__switch"><a href="<?php echo esc_url( wp_logout_url( shapelms_login_url() ) ); ?>"><?php esc_html_e( 'Not you? Log out', 'shapelms' ); ?></a></p>
	</div>
</div>
