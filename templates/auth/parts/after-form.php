<?php
/**
 * Area below the sign in / sign up form. Social login add-ons hook here.
 *
 * @var array $args { view }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_view = isset( $args['view'] ) ? $args['view'] : 'login';

if ( ! has_action( 'shapelms_auth_after_form' ) ) {
	return;
}
?>
<div class="shapelms-auth__divider"><span><?php esc_html_e( 'Or', 'shapelms' ); ?></span></div>
<div class="shapelms-auth__alternatives">
	<?php
	/**
	 * Fires below the sign in / sign up form (social login buttons hook here).
	 *
	 * @param string $view login|register.
	 */
	do_action( 'shapelms_auth_after_form', $shapelms_view );
	?>
</div>
