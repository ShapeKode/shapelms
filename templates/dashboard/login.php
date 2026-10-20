<?php
/**
 * Dashboard: logged-out view.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="shapelms-box shapelms-dashboard-login">
	<h2><?php esc_html_e( 'Log in to see your dashboard', 'shapelms' ); ?></h2>
	<p class="shapelms-muted"><?php esc_html_e( 'Track your courses, quiz results and certificates in one place.', 'shapelms' ); ?></p>
	<?php
	wp_login_form(
		array(
			'redirect'       => (string) get_permalink(),
			'label_log_in'   => __( 'Log in', 'shapelms' ),
			'label_username' => __( 'Username or email', 'shapelms' ),
		)
	);
	?>
	<?php if ( get_option( 'users_can_register' ) ) : ?>
		<p><a href="<?php echo esc_url( wp_registration_url() ); ?>"><?php esc_html_e( 'Create an account', 'shapelms' ); ?></a></p>
	<?php endif; ?>
	<p><a href="<?php echo esc_url( wp_lostpassword_url( (string) get_permalink() ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'shapelms' ); ?></a></p>
</div>
