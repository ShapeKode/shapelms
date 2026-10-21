<?php
/**
 * Base migration.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A migration creates or alters tables in up() and reverts them in down().
 */
abstract class Migration {

	/**
	 * Applies the migration.
	 *
	 * @return void
	 */
	abstract public function up();

	/**
	 * Reverts the migration.
	 *
	 * @return void
	 */
	abstract public function down();

	/**
	 * Returns the charset collate string.
	 *
	 * @return string
	 */
	protected function charset_collate() {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * Creates or updates a table with dbDelta.
	 *
	 * @param string $table Short table name.
	 * @param string $body  Column and index definitions.
	 * @return void
	 */
	protected function create_table( $table, $body ) {
		$name = shapelms_table( $table );

		dbDelta( "CREATE TABLE {$name} (\n{$body}\n) {$this->charset_collate()};" );
	}

	/**
	 * Drops a table.
	 *
	 * @param string $table Short table name.
	 * @return void
	 */
	protected function drop_table( $table ) {
		global $wpdb;

		$name = shapelms_table( $table );

		$wpdb->query( "DROP TABLE IF EXISTS {$name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
