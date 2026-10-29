<?php
/**
 * Plugin Name: ShapeLMS
 * Description: A fast, modern learning management system for WordPress. Build courses, lessons and quizzes, enroll students and sell courses.
 * Plugin URI:  https://shapekode.com/
 * Author:      ShapeKode
 * Author URI:  https://profiles.wordpress.org/shapekode22/
 * Version:     1.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shapelms
 * Domain Path: /languages
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'SHAPELMS_VERSION', '1.0.0' );
define( 'SHAPELMS_DB_VERSION', '1' );
define( 'SHAPELMS_FILE', __FILE__ );
define( 'SHAPELMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHAPELMS_URL', plugin_dir_url( __FILE__ ) );
define( 'SHAPELMS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Composer autoload (preferred) with PSR-4 fallback.
 */
if ( file_exists( SHAPELMS_DIR . 'vendor/autoload.php' ) ) {
	require_once SHAPELMS_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix   = 'ShapeLMS\\';
			$base_dir = SHAPELMS_DIR . 'includes/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
			}

			$relative = substr( $class_name, $len );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

require_once SHAPELMS_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'ShapeLMS\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ShapeLMS\\Core\\Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return \ShapeLMS\Core\Plugin
 */
function shapelms() {
	return \ShapeLMS\Core\Plugin::instance();
}

shapelms()->boot();
