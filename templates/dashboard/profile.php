<?php
/**
 * Dashboard: profile.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_user    = $args['user'];
$shapelms_profile = \ShapeLMS\Services\InstructorService::public_profile( $shapelms_user->ID );
$shapelms_social  = $shapelms_profile ? $shapelms_profile['social'] : array();
?>
<h2><?php esc_html_e( 'Profile', 'shapelms' ); ?></h2>

<form data-shapelms-form="instructors/me" data-success="<?php esc_attr_e( 'Profile saved.', 'shapelms' ); ?>">
	<div class="shapelms-field">
		<label><?php esc_html_e( 'Display name', 'shapelms' ); ?></label>
		<input class="shapelms-input" type="text" value="<?php echo esc_attr( $shapelms_user->display_name ); ?>" disabled>
		<p class="shapelms-muted"><?php echo wp_kses_post( sprintf( /* translators: %s: link */ __( 'Change your name, email and password in your <a href="%s">account settings</a>.', 'shapelms' ), esc_url( admin_url( 'profile.php' ) ) ) ); ?></p>
	</div>
	<div class="shapelms-field">
		<label for="shapelms-headline"><?php esc_html_e( 'Headline', 'shapelms' ); ?></label>
		<input id="shapelms-headline" class="shapelms-input" type="text" name="headline" maxlength="120" value="<?php echo esc_attr( $shapelms_profile ? $shapelms_profile['headline'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Front-end developer & teacher', 'shapelms' ); ?>">
	</div>
	<div class="shapelms-field">
		<label for="shapelms-bio"><?php esc_html_e( 'Bio', 'shapelms' ); ?></label>
		<textarea id="shapelms-bio" class="shapelms-textarea" name="bio" maxlength="3000"><?php echo esc_textarea( $shapelms_profile ? $shapelms_profile['bio'] : '' ); ?></textarea>
	</div>
	<?php
	foreach ( array(
		'website'  => __( 'Website', 'shapelms' ),
		'twitter'  => __( 'X / Twitter', 'shapelms' ),
		'linkedin' => __( 'LinkedIn', 'shapelms' ),
		'youtube'  => __( 'YouTube', 'shapelms' ),
	) as $shapelms_key => $shapelms_label ) :
		?>
		<div class="shapelms-field">
			<label for="shapelms-social-<?php echo esc_attr( $shapelms_key ); ?>"><?php echo esc_html( $shapelms_label ); ?></label>
			<input id="shapelms-social-<?php echo esc_attr( $shapelms_key ); ?>" class="shapelms-input" type="url" name="social[<?php echo esc_attr( $shapelms_key ); ?>]" value="<?php echo esc_attr( isset( $shapelms_social[ $shapelms_key ] ) ? $shapelms_social[ $shapelms_key ] : '' ); ?>" placeholder="https://">
		</div>
	<?php endforeach; ?>
	<button type="submit" class="shapelms-btn shapelms-btn--primary"><?php esc_html_e( 'Save profile', 'shapelms' ); ?></button>
	<p data-shapelms-form-message aria-live="polite"></p>
</form>
