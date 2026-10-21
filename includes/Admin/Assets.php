<?php
/**
 * Admin assets.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Settings;

/**
 * Enqueues the admin app and builder only on ShapeLMS screens.
 */
class Assets {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the current screen belongs to ShapeLMS.
	 *
	 * @return string '' | 'app' | 'builder'
	 */
	private function screen_kind() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return '';
		}

		if ( PostTypes::COURSE === $screen->post_type && 'post' === $screen->base ) {
			return 'builder';
		}

		if ( false !== strpos( (string) $screen->id, 'shapelms' ) && 'post' !== $screen->base && 'edit' !== $screen->base && 'edit-tags' !== $screen->base && 'term' !== $screen->base ) {
			return 'app';
		}

		return '';
	}

	/**
	 * Enqueues built assets.
	 *
	 * @return void
	 */
	public function enqueue() {
		$kind = $this->screen_kind();

		if ( ! $kind ) {
			return;
		}

		$handle = 'builder' === $kind ? 'shapelms-builder' : 'shapelms-admin';
		$entry  = 'builder' === $kind ? 'builder' : 'admin';
		$asset  = $this->asset_file( $entry );

		wp_enqueue_media();

		if ( 'builder' === $kind ) {
			wp_enqueue_editor();
		}

		wp_enqueue_script(
			$handle,
			SHAPELMS_URL . 'assets/build/' . $entry . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'shapelms', SHAPELMS_DIR . 'languages' );

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			$handle,
			SHAPELMS_URL . 'assets/build/style-admin.css',
			array( 'wp-components' ),
			$asset['version']
		);
		wp_style_add_data( $handle, 'rtl', 'replace' );
	}

	/**
	 * Reads the asset manifest produced by wp-scripts.
	 *
	 * @param string $entry Entry name.
	 * @return array{dependencies: string[], version: string}
	 */
	private function asset_file( $entry ) {
		$file = SHAPELMS_DIR . 'assets/build/' . $entry . '.asset.php';

		if ( file_exists( $file ) ) {
			$asset = include $file;
			return array(
				'dependencies' => isset( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
				'version'      => isset( $asset['version'] ) ? $asset['version'] : SHAPELMS_VERSION,
			);
		}

		return array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data', 'wp-url' ),
			'version'      => SHAPELMS_VERSION,
		);
	}

	/**
	 * Data attributes for the React root (escaped). Replaces wp_localize_script.
	 *
	 * @return string
	 */
	public static function data_attributes() {
		$data = array(
			'rest-url'    => esc_url_raw( rest_url( 'shapelms/v1/' ) ),
			'rest-nonce'  => wp_create_nonce( 'wp_rest' ),
			'admin-url'   => admin_url(),
			'site-url'    => home_url( '/' ),
			'can-manage'  => current_user_can( Capabilities::MANAGE ) ? '1' : '0',
			'user-id'     => (string) get_current_user_id(),
			'currency'    => \ShapeLMS\Services\CurrencyService::symbol(),
			'version'     => SHAPELMS_VERSION,
			'course-type' => PostTypes::COURSE,
			'new-course'  => admin_url( 'post-new.php?post_type=' . PostTypes::COURSE ),
			'setup-done'  => get_option( 'shapelms_setup_complete' ) ? '1' : '0',
			'primary'     => (string) Settings::get( 'design', 'primary_color', '#3b5bdb' ),
		);

		/**
		 * Filters the data attributes passed to the admin app.
		 *
		 * @param array $data Attributes without the data- prefix.
		 */
		$data = apply_filters( 'shapelms_admin_data', $data );

		$out = '';
		foreach ( $data as $key => $value ) {
			$out .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}

		return $out;
	}
}
