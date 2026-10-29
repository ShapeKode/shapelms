<?php
/**
 * Sign in form.
 *
 * @var array $args { redirect_to, attempt, flags }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_attempt  = $args['attempt'];
$shapelms_flags    = $args['flags'];
$shapelms_redirect = $args['redirect_to'];
$shapelms_action   = shapelms_login_url( $shapelms_redirect );
?>
<div class="shapelms-auth">
	<h1 class="shapelms-auth__title"><?php esc_html_e( 'Login', 'shapelms' ); ?></h1>

	<div class="shapelms-auth__card">
		<?php if ( '1' === $shapelms_flags['reset'] ) : ?>
			<p class="shapelms-notice shapelms-notice--success"><?php esc_html_e( 'Your password has been changed. You can sign in now.', 'shapelms' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $shapelms_attempt['errors'] ) ) : ?>
			<div class="shapelms-notice shapelms-notice--error" role="alert">
				<?php foreach ( $shapelms_attempt['errors'] as $shapelms_error ) : ?>
					<p><?php echo esc_html( $shapelms_error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $shapelms_action ); ?>" class="shapelms-auth__form">
			<?php wp_nonce_field( 'shapelms_login', 'shapelms_nonce' ); ?>
			<input type="hidden" name="shapelms_login" value="1">
			<?php if ( $shapelms_redirect ) : ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $shapelms_redirect ); ?>">
			<?php endif; ?>

			<div class="shapelms-field">
				<label for="shapelms-login-user"><?php esc_html_e( 'Username or Email Address', 'shapelms' ); ?></label>
				<input id="shapelms-login-user" class="shapelms-input" type="text" name="log" value="<?php echo esc_attr( $shapelms_attempt['login'] ); ?>" required autocomplete="username" autofocus>
			</div>
			<div class="shapelms-field">
				<label for="shapelms-login-pass"><?php esc_html_e( 'Password', 'shapelms' ); ?></label>
				<input id="shapelms-login-pass" class="shapelms-input" type="password" name="pwd" required autocomplete="current-password">
			</div>

			<div class="shapelms-auth__row">
				<label class="shapelms-auth__remember">
					<input type="checkbox" name="rememberme" value="forever">
					<span><?php esc_html_e( 'Remember me', 'shapelms' ); ?></span>
				</label>
				<a class="shapelms-auth__link" href="<?php echo esc_url( shapelms_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'shapelms' ); ?></a>
			</div>

			<button type="submit" class="shapelms-btn shapelms-btn--auth shapelms-btn--block"><?php esc_html_e( 'Sign in', 'shapelms' ); ?></button>
		</form>

		<?php shapelms_get_template( 'auth/parts/after-form.php', array( 'view' => 'login' ) ); ?>
	</div>

	<?php if ( \ShapeLMS\Frontend\Auth::is_open() ) : ?>
		<p class="shapelms-auth__switch"><?php esc_html_e( "Don't have an account?", 'shapelms' ); ?> <a href="<?php echo esc_url( shapelms_register_url( $shapelms_redirect ) ); ?>"><?php esc_html_e( 'Sign up', 'shapelms' ); ?></a></p>
	<?php endif; ?>
</div>
