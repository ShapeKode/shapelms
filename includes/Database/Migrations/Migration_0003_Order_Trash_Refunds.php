<?php
/**
 * Adds order trash and partial refund tracking.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * trashed_at: orders moved to the trash keep their status and can be restored.
 * refunded_amount: money given back so far, so a partial refund can be told apart from a full one.
 */
class Migration_0003_Order_Trash_Refunds extends Migration {

	/**
	 * Up.
	 *
	 * @return void
	 */
	public function up() {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'orders' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema check on an internal table.
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'refunded_amount'" ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema change on an internal table.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN refunded_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total" );
			// Orders refunded before this column existed were refunded in full.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
			$wpdb->query( "UPDATE {$table} SET refunded_amount = total WHERE status = 'refunded'" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema check on an internal table.
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'trashed_at'" ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuerySchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- schema change on an internal table.
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN trashed_at DATETIME NULL DEFAULT NULL AFTER refunded_at, ADD KEY trashed_at (trashed_at)" );
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
		$wpdb->query( "ALTER TABLE {$table} DROP KEY trashed_at, DROP COLUMN trashed_at, DROP COLUMN refunded_amount" );
	}
}
