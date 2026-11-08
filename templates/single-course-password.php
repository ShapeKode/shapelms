<?php
/**
 * Password form of a protected course.
 *
 * Override: theme/shapelms/single-course-password.php
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

the_post();

$shapelms_course_id = get_the_ID();
$shapelms_url       = (string) get_permalink( $shapelms_course_id );

// Core sends a wrong password back to the page it came from, with the cookie set.
$shapelms_invalid = isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ) && wp_get_raw_referer() === $shapelms_url;
?>
<div class="shapelms-wrap shapelms-course-lock">
	<div class="shapelms-course-lock__card">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'class' => 'shapelms-course-lock__image' ) ); ?>
		<?php endif; ?>

		<h1 class="shapelms-course-lock__title"><?php the_title(); ?></h1>
		<p class="shapelms-muted"><?php esc_html_e( 'This course is password protected. Enter the password to see its details.', 'shapelms' ); ?></p>

		<?php if ( $shapelms_invalid ) : ?>
			<div class="shapelms-notice shapelms-notice--error" role="alert"><?php esc_html_e( 'That password is not correct. Please try again.', 'shapelms' ); ?></div>
		<?php endif; ?>

		<form action="<?php echo esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ); ?>" method="post">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $shapelms_url ); ?>">
			<div class="shapelms-field">
				<label for="shapelms-course-password"><?php esc_html_e( 'Password', 'shapelms' ); ?></label>
				<input class="shapelms-input" id="shapelms-course-password" type="password" name="post_password" maxlength="255" autocomplete="off" required>
			</div>
			<button type="submit" class="shapelms-btn shapelms-btn--primary shapelms-btn--block"><?php esc_html_e( 'Unlock course', 'shapelms' ); ?></button>
		</form>
	</div>
</div>
<?php
get_footer();
