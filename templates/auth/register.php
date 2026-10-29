<?php
/**
 * Sign up form.
 *
 * @var array $args { redirect_to, attempt, flags }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_attempt  = $args['attempt'];
$shapelms_redirect = $args['redirect_to'];
$shapelms_open     = \ShapeLMS\Frontend\Auth::is_open();
?>
<div class="shapelms-auth">
	<h1 class="shapelms-auth__title"><?php esc_html_e( 'Sign up', 'shapelms' ); ?></h1>

	<div class="shapelms-auth__card">
		<?php if ( ! $shapelms_open ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Registration is closed at the moment.', 'shapelms' ); ?></p>
		<?php else : ?>
			<?php if ( ! empty( $shapelms_attempt['errors'] ) ) : ?>
				<div class="shapelms-notice shapelms-notice--error" role="alert">
					<?php foreach ( $shapelms_attempt['errors'] as $shapelms_error ) : ?>
						<p><?php echo esc_html( $shapelms_error ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( shapelms_register_url( $shapelms_redirect ) ); ?>" class="shapelms-auth__form">
				<?php wp_nonce_field( 'shapelms_student_register', 'shapelms_nonce' ); ?>
				<input type="hidden" name="shapelms_student_register" value="1">
				<?php if ( $shapelms_redirect ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $shapelms_redirect ); ?>">
				<?php endif; ?>
				<div class="shapelms-auth__hp" aria-hidden="true">
					<label for="shapelms-signup-website"><?php esc_html_e( 'Leave this field empty', 'shapelms' ); ?></label>
					<input id="shapelms-signup-website" type="text" name="shapelms_website" value="" tabindex="-1" autocomplete="off">
				</div>

				<div class="shapelms-field">
					<label for="shapelms-signup-username"><?php esc_html_e( 'Username', 'shapelms' ); ?></label>
					<input id="shapelms-signup-username" class="shapelms-input" type="text" name="username" value="<?php echo esc_attr( $shapelms_attempt['username'] ); ?>" required autocomplete="username" autofocus>
				</div>
				<div class="shapelms-field">
					<label for="shapelms-signup-email"><?php esc_html_e( 'Email', 'shapelms' ); ?></label>
					<input id="shapelms-signup-email" class="shapelms-input" type="email" name="email" value="<?php echo esc_attr( $shapelms_attempt['email'] ); ?>" required autocomplete="email">
				</div>
				<div class="shapelms-field">
					<label for="shapelms-signup-password"><?php esc_html_e( 'Password', 'shapelms' ); ?></label>
					<input id="shapelms-signup-password" class="shapelms-input" type="password" name="password" required minlength="8" autocomplete="new-password">
				</div>
				<div class="shapelms-field">
					<label for="shapelms-signup-password-confirm"><?php esc_html_e( 'Confirm password', 'shapelms' ); ?></label>
					<input id="shapelms-signup-password-confirm" class="shapelms-input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
				</div>

				<button type="submit" class="shapelms-btn shapelms-btn--auth shapelms-btn--block"><?php esc_html_e( 'Register', 'shapelms' ); ?></button>
			</form>

			<?php shapelms_get_template( 'auth/parts/after-form.php', array( 'view' => 'register' ) ); ?>
		<?php endif; ?>
	</div>

	<p class="shapelms-auth__switch"><?php esc_html_e( 'Already have an account?', 'shapelms' ); ?> <a href="<?php echo esc_url( shapelms_login_url( $shapelms_redirect ) ); ?>"><?php esc_html_e( 'Sign in', 'shapelms' ); ?></a></p>
</div>
