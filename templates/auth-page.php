<?php
/**
 * Standalone Sign in / Sign up page (no theme header or footer).
 *
 * Override: theme/shapelms/auth-page.php
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Pages;
use ShapeLMS\Frontend\Auth;

the_post();

$shapelms_view    = Pages::is_page( 'register' ) ? 'register' : 'login';
$shapelms_logo_id = (int) get_theme_mod( 'custom_logo' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'shapelms-auth-body' ); ?>>
<?php wp_body_open(); ?>
<div class="shapelms shapelms-auth-page__wrap" <?php echo \ShapeLMS\Frontend\Frontend::data_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<a class="shapelms-auth-page__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( $shapelms_logo_id ) : ?>
			<?php echo wp_kses_post( wp_get_attachment_image( $shapelms_logo_id, 'medium', false, array( 'alt' => get_bloginfo( 'name' ) ) ) ); ?>
		<?php else : ?>
			<?php bloginfo( 'name' ); ?>
		<?php endif; ?>
	</a>

	<?php echo Auth::render( $shapelms_view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output, escaped inside. ?>

	<p class="shapelms-auth-page__back"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Back to %s', 'shapelms' ), get_bloginfo( 'name' ) ) ); ?></a></p>
</div>
<?php wp_footer(); ?>
</body>
</html>
