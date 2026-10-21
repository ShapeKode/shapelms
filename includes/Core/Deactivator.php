<?php
/**
 * Plugin deactivation.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on deactivation. Data is kept; only scheduled events and rewrite rules are cleared.
 */
class Deactivator {

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'shapelms_daily_maintenance' );
		wp_clear_scheduled_hook( 'shapelms_process_queue' );
		flush_rewrite_rules();
	}
}
