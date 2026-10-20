<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package ShapeLMS
 */

$shapelms_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $shapelms_tests_dir ) {
	$shapelms_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$shapelms_polyfills = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills';
if ( file_exists( $shapelms_polyfills . '/phpunitpolyfills-autoload.php' ) ) {
	require_once $shapelms_polyfills . '/phpunitpolyfills-autoload.php';
}

if ( ! file_exists( "{$shapelms_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$shapelms_tests_dir}/includes/functions.php. Run `npx wp-env start` and set WP_TESTS_DIR.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once "{$shapelms_tests_dir}/includes/functions.php";

/**
 * Loads the plugin under test.
 *
 * @return void
 */
function shapelms_tests_load_plugin() {
	require dirname( __DIR__, 2 ) . '/shapelms.php';
}
tests_add_filter( 'muplugins_loaded', 'shapelms_tests_load_plugin' );

require "{$shapelms_tests_dir}/includes/bootstrap.php";
