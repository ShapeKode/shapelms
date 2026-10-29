<?php
/**
 * Instructor registration form.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Services\InstructorService;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag.
$shapelms_registered = isset( $_GET['registered'] ) ? sanitize_key( wp_unslash( $_GET['registered'] ) ) : '';
$shapelms_errors     = \ShapeLMS\Modules\Instructors\Module::errors();
$shapelms_user_id    = get_current_user_id();
$shapelms_status     = $shapelms_user_id ? InstructorService::status( $shapelms_user_id ) : '';
?>
<div class="shapelms-box shapelms-instructor-register">
	<h1><?php esc_html_e( 'Become an instructor', 'shapelms' ); ?></h1>

	<?php if ( ! shapelms_get_setting( 'instructor', 'allow_registration', true ) ) : ?>
		<p class="shapelms-notice"><?php esc_html_e( 'Instructor registration is closed at the moment.', 'shapelms' ); ?></p>
	<?php elseif ( '1' === $shapelms_registered || 'pending' === $shapelms_status ) : ?>
		<p class="shapelms-notice shapelms-notice--success">
			<?php echo shapelms_get_setting( 'instructor', 'require_approval', true ) ? esc_html__( 'Thanks! Your application was received. We will email you when it is reviewed.', 'shapelms' ) : esc_html__( 'You are now an instructor.', 'shapelms' ); ?>
		</p>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( \ShapeLMS\Core\Pages::get_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'shapelms' ); ?></a>
	<?php elseif ( 'approved' === $shapelms_status ) : ?>
		<p class="shapelms-notice shapelms-notice--success"><?php esc_html_e( 'You are already an instructor.', 'shapelms' ); ?></p>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( \ShapeLMS\Frontend\Dashboard::url( 'instructor-courses' ) ); ?>"><?php esc_html_e( 'My courses', 'shapelms' ); ?></a>
	<?php elseif ( 'blocked' === $shapelms_status ) : ?>
		<p class="shapelms-notice"><?php esc_html_e( 'Instructor access is not available for this account.', 'shapelms' ); ?></p>
	<?php elseif ( ! $shapelms_user_id && ! get_option( 'users_can_register' ) && ! apply_filters( 'shapelms_allow_instructor_signup', false ) ) : ?>
		<p class="shapelms-notice"><?php esc_html_e( 'Please log in with your existing account to apply as an instructor.', 'shapelms' ); ?></p>
		<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( shapelms_login_url( (string) get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'shapelms' ); ?></a>
	<?php else : ?>
		<?php if ( $shapelms_errors ) : ?>
			<div class="shapelms-notice shapelms-notice--error">
				<?php foreach ( $shapelms_errors as $shapelms_error ) : ?>
					<p><?php echo esc_html( $shapelms_error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'shapelms_instructor_register', 'shapelms_nonce' ); ?>
			<input type="hidden" name="shapelms_instructor_register" value="1">

			<?php if ( ! $shapelms_user_id ) : ?>
				<div class="shapelms-auth__hp" aria-hidden="true">
					<label for="shapelms-reg-website"><?php esc_html_e( 'Leave this field empty', 'shapelms' ); ?></label>
					<input id="shapelms-reg-website" type="text" name="shapelms_website" value="" tabindex="-1" autocomplete="off">
				</div>
				<div class="shapelms-field">
					<label for="shapelms-reg-name"><?php esc_html_e( 'Your name', 'shapelms' ); ?></label>
					<input id="shapelms-reg-name" class="shapelms-input" type="text" name="name" required autocomplete="name">
				</div>
				<div class="shapelms-field">
					<label for="shapelms-reg-email"><?php esc_html_e( 'Email', 'shapelms' ); ?></label>
					<input id="shapelms-reg-email" class="shapelms-input" type="email" name="email" required autocomplete="email">
				</div>
				<div class="shapelms-field">
					<label for="shapelms-reg-password"><?php esc_html_e( 'Password', 'shapelms' ); ?></label>
					<input id="shapelms-reg-password" class="shapelms-input" type="password" name="password" required minlength="8" autocomplete="new-password">
				</div>
				<p class="shapelms-muted"><?php echo wp_kses_post( sprintf( /* translators: %s: login url */ __( 'Already have an account? <a href="%s">Log in</a> first.', 'shapelms' ), esc_url( shapelms_login_url( (string) get_permalink() ) ) ) ); ?></p>
			<?php endif; ?>

			<div class="shapelms-field">
				<label for="shapelms-reg-headline"><?php esc_html_e( 'Headline', 'shapelms' ); ?></label>
				<input id="shapelms-reg-headline" class="shapelms-input" type="text" name="headline" maxlength="120" placeholder="<?php esc_attr_e( 'What do you teach?', 'shapelms' ); ?>">
			</div>
			<div class="shapelms-field">
				<label for="shapelms-reg-bio"><?php esc_html_e( 'Tell us about yourself', 'shapelms' ); ?></label>
				<textarea id="shapelms-reg-bio" class="shapelms-textarea" name="bio" maxlength="3000"></textarea>
			</div>

			<button type="submit" class="shapelms-btn shapelms-btn--primary"><?php esc_html_e( 'Apply', 'shapelms' ); ?></button>
		</form>
	<?php endif; ?>
</div>
