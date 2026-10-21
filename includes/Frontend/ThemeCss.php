<?php
/**
 * Generated theme stylesheet.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Settings;

/**
 * Writes the design settings (colors, radius) to a static CSS file in uploads so nothing is inlined.
 */
class ThemeCss {

	const FILE   = 'shapelms/theme.css';
	const OPTION = 'shapelms_theme_css_version';

	/**
	 * Absolute path of the generated file.
	 *
	 * @return string
	 */
	private static function path() {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::FILE;
	}

	/**
	 * Public URL of the generated file, or empty when it does not exist.
	 *
	 * @return string
	 */
	public static function url() {
		if ( ! file_exists( self::path() ) ) {
			self::write();
		}

		if ( ! file_exists( self::path() ) ) {
			return '';
		}

		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['baseurl'] ) . self::FILE;
	}

	/**
	 * Cache-busting version.
	 *
	 * @return string
	 */
	public static function version() {
		return (string) get_option( self::OPTION, SHAPELMS_VERSION );
	}

	/**
	 * Regenerates the file after settings are saved. Used on the shapelms_save_settings filter.
	 *
	 * @param array $settings Settings being saved.
	 * @return array
	 */
	public static function regenerate( $settings ) {
		self::write( $settings );

		return $settings;
	}

	/**
	 * Writes the CSS file.
	 *
	 * @param array|null $settings Settings (defaults to the saved ones).
	 * @return void
	 */
	public static function write( $settings = null ) {
		$design  = is_array( $settings ) && isset( $settings['design'] ) ? $settings['design'] : Settings::all()['design'];
		$primary = isset( $design['primary_color'] ) ? sanitize_hex_color( (string) $design['primary_color'] ) : '';
		$primary = $primary ? $primary : '#3b5bdb';
		$radius  = isset( $design['radius'] ) ? absint( $design['radius'] ) : 8;

		$rgb = sscanf( ltrim( $primary, '#' ), '%02x%02x%02x' );
		$rgb = is_array( $rgb ) && 3 === count( $rgb ) ? implode( ', ', $rgb ) : '59, 91, 219';

		$css = sprintf(
			":root{--shapelms-primary:%s;--shapelms-primary-rgb:%s;--shapelms-radius:%dpx;}\n",
			$primary,
			$rgb,
			$radius
		);

		/**
		 * Filters the generated theme CSS.
		 *
		 * @param string $css    CSS.
		 * @param array  $design Design settings.
		 */
		$css = apply_filters( 'shapelms_theme_css', $css, $design );

		$path = self::path();

		wp_mkdir_p( dirname( $path ) );

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem && $wp_filesystem->put_contents( $path, $css, FS_CHMOD_FILE ) ) {
			update_option( self::OPTION, (string) time() );
		}
	}
}
