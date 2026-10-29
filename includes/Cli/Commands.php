<?php
/**
 * WP-CLI commands.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\ImportExportService;
use ShapeLMS\Database\Migrator;
use WP_CLI;

/**
 * WP-CLI commands: wp shapelms <command>.
 */
class Commands {

	/**
	 * Enrolls a user in a course.
	 *
	 * ## OPTIONS
	 *
	 * --student=<id|email|login>
	 * : User id, email or username. (WP-CLI reserves --user for the acting user.)
	 *
	 * --course=<id>
	 * : Course id.
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public function enroll( $args, $assoc_args ) {
		$who  = isset( $assoc_args['student'] ) ? (string) $assoc_args['student'] : (string) get_current_user_id();
		$user = is_numeric( $who ) ? get_userdata( (int) $who ) : get_user_by( 'email', $who );

		if ( ! $user && ! is_numeric( $who ) ) {
			$user = get_user_by( 'login', $who );
		}

		if ( ! $user ) {
			WP_CLI::error( 'User not found.' );
		}

		$result = shapelms()->get( EnrollmentService::class )->enroll( $user->ID, (int) $assoc_args['course'], 'manual' );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Enrolled user %d in course %d (enrollment #%d).', $user->ID, (int) $assoc_args['course'], $result ) );
	}

	/**
	 * Exports courses to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Comma-separated course ids (default: all).
	 *
	 * [--file=<path>]
	 * : Output file (default: stdout).
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$ids  = ! empty( $assoc_args['ids'] ) ? array_map( 'intval', explode( ',', $assoc_args['ids'] ) ) : array();
		$json = wp_json_encode( shapelms()->get( ImportExportService::class )->export( $ids ), JSON_PRETTY_PRINT );

		if ( ! empty( $assoc_args['file'] ) ) {
			file_put_contents( $assoc_args['file'], $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::success( 'Exported to ' . $assoc_args['file'] );
			return;
		}

		WP_CLI::line( $json );
	}

	/**
	 * Imports courses from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file.
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public function import( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$file = $args[0];

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File not found.' );
		}

		$data   = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = shapelms()->get( ImportExportService::class )->import( is_array( $data ) ? $data : array() );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Imported %d course(s).', count( $result ) ) );
	}

	/**
	 * Runs database migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--rollback]
	 * : Roll back all migrations (drops the tables).
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public function migrate( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['rollback'] ) ) {
			WP_CLI::confirm( 'This drops all ShapeLMS tables. Continue?' );
			Migrator::rollback( 0 );
			WP_CLI::success( 'Rolled back.' );
			return;
		}

		Migrator::run( (int) get_option( Migrator::OPTION, 0 ) );
		WP_CLI::success( 'Database is at version ' . get_option( Migrator::OPTION ) );
	}

	/**
	 * Installs demo content.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Install the full sample pack (six published courses across categories, levels and prices) instead of the single draft demo course.
	 *
	 * ## EXAMPLES
	 *
	 *     wp shapelms demo
	 *     wp shapelms demo --all
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	public function demo( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$pack   = ! empty( $assoc_args['all'] ) ? 'courses' : 'course';
		$result = shapelms()->get( ImportExportService::class )->install_demo( $pack );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		foreach ( (array) $result as $id ) {
			WP_CLI::log( get_the_title( $id ) . ': ' . get_permalink( $id ) );
		}

		WP_CLI::success( count( (array) $result ) . ' course(s) created. Archive: ' . get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) );
	}
}
