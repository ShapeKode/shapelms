<?php
/**
 * Dashboard: account settings.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_status = \ShapeLMS\Services\InstructorService::status( (int) $args['user']->ID );
?>
<h2><?php esc_html_e( 'Settings', 'shapelms' ); ?></h2>

<div class="shapelms-box">
	<h3><?php esc_html_e( 'Account', 'shapelms' ); ?></h3>
	<p><?php esc_html_e( 'Update your email, password and personal details in your WordPress profile.', 'shapelms' ); ?></p>
	<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Edit account', 'shapelms' ); ?></a>
</div>

<?php if ( shapelms_get_setting( 'instructor', 'allow_registration', true ) && ! current_user_can( \ShapeLMS\Core\Capabilities::EDIT_COURSES ) ) : ?>
	<div class="shapelms-box">
		<h3><?php esc_html_e( 'Become an instructor', 'shapelms' ); ?></h3>
		<?php if ( 'pending' === $shapelms_status ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Your application is waiting for approval.', 'shapelms' ); ?></p>
		<?php elseif ( 'blocked' === $shapelms_status ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Instructor access is not available for this account.', 'shapelms' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Share your knowledge by creating courses on this site.', 'shapelms' ); ?></p>
			<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( \ShapeLMS\Core\Pages::get_url( 'instructor_register' ) ); ?>"><?php esc_html_e( 'Apply now', 'shapelms' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="shapelms-box">
	<h3><?php esc_html_e( 'Your data', 'shapelms' ); ?></h3>
	<p><?php esc_html_e( 'You can request an export or deletion of your personal data from the site administrator.', 'shapelms' ); ?></p>
	<?php if ( get_option( 'wp_page_for_privacy_policy' ) ) : ?>
		<a href="<?php echo esc_url( (string) get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'shapelms' ); ?></a>
	<?php endif; ?>
</div>
