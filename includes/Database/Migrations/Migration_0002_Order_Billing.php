<?php
/**
 * Adds billing details to orders.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address and terms consent given at checkout, kept with the order as JSON.
 */
class Migration_0002_Order_Billing extends Migration {

	/**
	 * Up.
	 *
	 * @return void
	 */
	public function up() {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'orders' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema change on an internal table.
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'billing'" ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema change on an internal table.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN billing TEXT NULL AFTER billing_name" );
		}
	}

	/**
	 * Down.
	 *
	 * @return void
	 */
	public function down() {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'orders' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema change on an internal table.
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN billing" );
	}
}
