<?php
/**
 * Lost password form.
 *
 * @var array $args { attempt, flags }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_attempt = $args['attempt'];
$shapelms_flags   = $args['flags'];
?>
<div class="shapelms-auth">
	<h1 class="shapelms-auth__title"><?php esc_html_e( 'Forgot password', 'shapelms' ); ?></h1>

	<div class="shapelms-auth__card">
		<?php if ( 'confirm' === $shapelms_flags['checkemail'] ) : ?>
			<p class="shapelms-notice shapelms-notice--success"><?php esc_html_e( 'If an account matches what you entered, a password reset link is on its way. Check your inbox.', 'shapelms' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $shapelms_attempt['errors'] ) ) : ?>
			<div class="shapelms-notice shapelms-notice--error" role="alert">
				<?php foreach ( $shapelms_attempt['errors'] as $shapelms_error ) : ?>
					<p><?php echo esc_html( $shapelms_error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p class="shapelms-auth__intro"><?php esc_html_e( 'Enter your username or email address and we will send you a link to set a new password.', 'shapelms' ); ?></p>

		<form method="post" action="<?php echo esc_url( shapelms_lostpassword_url() ); ?>" class="shapelms-auth__form">
			<?php wp_nonce_field( 'shapelms_lostpassword', 'shapelms_nonce' ); ?>
			<input type="hidden" name="shapelms_lostpassword" value="1">

			<div class="shapelms-field">
				<label for="shapelms-lost-user"><?php esc_html_e( 'Username or Email Address', 'shapelms' ); ?></label>
				<input id="shapelms-lost-user" class="shapelms-input" type="text" name="user_login" required autocomplete="username" autofocus>
			</div>

			<button type="submit" class="shapelms-btn shapelms-btn--auth shapelms-btn--block"><?php esc_html_e( 'Send reset link', 'shapelms' ); ?></button>
		</form>
	</div>

	<p class="shapelms-auth__switch"><a href="<?php echo esc_url( shapelms_login_url() ); ?>"><?php esc_html_e( 'Back to sign in', 'shapelms' ); ?></a></p>
</div>
