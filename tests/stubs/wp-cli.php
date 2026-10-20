<?php
/**
 * WP-CLI stub for static analysis.
 *
 * @package ShapeLMS
 */

// phpcs:ignoreFile

class WP_CLI {
	/**
	 * @param string $message
	 * @return void
	 */
	public static function error( $message ) {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function success( $message ) {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function line( $message ) {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function confirm( $message ) {}

	/**
	 * @param string          $name
	 * @param callable|string $callable
	 * @return void
	 */
	public static function add_command( $name, $callable ) {}
}
