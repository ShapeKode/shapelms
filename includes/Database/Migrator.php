<?php
/**
 * Database migrator.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

/**
 * Runs versioned migrations and tracks the installed database version.
 */
class Migrator {

	const OPTION = 'shapelms_db_version';

	/**
	 * Returns the migrations in order.
	 *
	 * @return array<int, class-string<Migrations\Migration>>
	 */
	public static function migrations() {
		$migrations = array(
			1 => Migrations\Migration_0001_Initial::class,
		);

		/**
		 * Filters the list of migrations (version => class).
		 *
		 * @param array $migrations Migrations.
		 */
		return apply_filters( 'shapelms_migrations', $migrations );
	}

	/**
	 * Installs or upgrades the database on activation.
	 *
	 * @return void
	 */
	public static function install() {
		self::run();
	}

	/**
	 * Upgrades the database when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (int) get_option( self::OPTION, 0 );

		if ( $installed < (int) SHAPELMS_DB_VERSION ) {
			self::run( $installed );
		}
	}

	/**
	 * Runs all migrations above the given version.
	 *
	 * @param int $from Installed version.
	 * @return void
	 */
	public static function run( $from = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$latest = $from;

		foreach ( self::migrations() as $version => $class_name ) {
			if ( $version <= $from ) {
				continue;
			}

			$migration = new $class_name();
			$migration->up();

			$latest = $version;
			update_option( self::OPTION, $latest );
		}

		/**
		 * Fires after the database has been migrated.
		 *
		 * @param int $from   Previous version.
		 * @param int $latest Current version.
		 */
		do_action( 'shapelms_db_migrated', $from, $latest );
	}

	/**
	 * Rolls back migrations down to the given version. Used by tests and WP-CLI.
	 *
	 * @param int $to Target version.
	 * @return void
	 */
	public static function rollback( $to = 0 ) {
		$installed  = (int) get_option( self::OPTION, 0 );
		$migrations = self::migrations();
		krsort( $migrations );

		foreach ( $migrations as $version => $class_name ) {
			if ( $version > $installed || $version <= $to ) {
				continue;
			}

			$migration = new $class_name();
			$migration->down();

			update_option( self::OPTION, $version - 1 );
		}
	}
}
