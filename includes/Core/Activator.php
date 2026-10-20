<?php
/**
 * Plugin activation.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

use ShapeLMS\Database\Migrator;

/**
 * Runs on activation: tables, roles, pages, rewrite rules.
 */
class Activator {

	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide Whether the plugin is activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_single_site();
	}

	/**
	 * Activation for one site.
	 *
	 * @return void
	 */
	public static function activate_single_site() {
		Migrator::install();

		$capabilities = new Capabilities();
		$capabilities->register();
		$capabilities->add_caps();

		Pages::create_pages();

		\ShapeLMS\Content\PostTypes::register_static();
		flush_rewrite_rules();

		if ( ! get_option( 'shapelms_version' ) ) {
			set_transient( 'shapelms_activation_redirect', 1, 60 );
		}

		update_option( 'shapelms_version', SHAPELMS_VERSION );
	}

	/**
	 * Creates tables when a new site is added to a multisite network.
	 *
	 * @param \WP_Site $new_site New site object.
	 * @return void
	 */
	public static function on_new_site( $new_site ) {
		if ( ! is_plugin_active_for_network( SHAPELMS_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::activate_single_site();
		restore_current_blog();
	}
}
