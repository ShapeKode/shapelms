<?php
/**
 * Reset password form (from the email link).
 *
 * @var array $args { attempt, valid }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_attempt = $args['attempt'];
?>
<div class="shapelms-auth">
	<h1 class="shapelms-auth__title"><?php esc_html_e( 'Set a new password', 'shapelms' ); ?></h1>

	<div class="shapelms-auth__card">
		<?php if ( ! $args['valid'] ) : ?>
			<p class="shapelms-notice shapelms-notice--error"><?php esc_html_e( 'This reset link is invalid or has expired.', 'shapelms' ); ?></p>
			<a class="shapelms-btn shapelms-btn--auth shapelms-btn--block" href="<?php echo esc_url( shapelms_lostpassword_url() ); ?>"><?php esc_html_e( 'Request a new link', 'shapelms' ); ?></a>
		<?php else : ?>
			<?php if ( ! empty( $shapelms_attempt['errors'] ) ) : ?>
				<div class="shapelms-notice shapelms-notice--error" role="alert">
					<?php foreach ( $shapelms_attempt['errors'] as $shapelms_error ) : ?>
						<p><?php echo esc_html( $shapelms_error ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( \ShapeLMS\Frontend\Auth::reset_step_url() ); ?>" class="shapelms-auth__form">
				<?php wp_nonce_field( 'shapelms_resetpass', 'shapelms_nonce' ); ?>
				<input type="hidden" name="shapelms_resetpass" value="1">

				<div class="shapelms-field">
					<label for="shapelms-reset-pass1"><?php esc_html_e( 'New password', 'shapelms' ); ?></label>
					<input id="shapelms-reset-pass1" class="shapelms-input" type="password" name="pass1" required minlength="8" autocomplete="new-password" autofocus>
				</div>
				<div class="shapelms-field">
					<label for="shapelms-reset-pass2"><?php esc_html_e( 'Confirm new password', 'shapelms' ); ?></label>
					<input id="shapelms-reset-pass2" class="shapelms-input" type="password" name="pass2" required minlength="8" autocomplete="new-password">
				</div>

				<button type="submit" class="shapelms-btn shapelms-btn--auth shapelms-btn--block"><?php esc_html_e( 'Save password', 'shapelms' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<p class="shapelms-auth__switch"><a href="<?php echo esc_url( shapelms_login_url() ); ?>"><?php esc_html_e( 'Back to sign in', 'shapelms' ); ?></a></p>
</div>
