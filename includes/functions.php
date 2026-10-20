<?php
/**
 * Global helper functions.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a plugin setting.
 *
 * @param string $section Settings section id.
 * @param string $key     Field key.
 * @param mixed  $fallback Default value when the field is not set.
 * @return mixed
 */
function shapelms_get_setting( $section, $key, $fallback = null ) {
	return \ShapeLMS\Core\Settings::get( $section, $key, $fallback );
}

/**
 * Returns the full table name for a ShapeLMS table.
 *
 * @param string $table Short table name, e.g. "enrollments".
 * @return string
 */
function shapelms_table( $table ) {
	global $wpdb;

	return $wpdb->prefix . 'shapelms_' . $table;
}

/**
 * Checks whether the current user can manage the LMS.
 *
 * @return bool
 */
function shapelms_current_user_can_manage() {
	return current_user_can( 'manage_shapelms' );
}

/**
 * Returns the current time in MySQL format (UTC).
 *
 * @return string
 */
function shapelms_now() {
	return current_time( 'mysql', true );
}

/**
 * Loads an overridable template.
 *
 * Looks in theme/shapelms/{$template} first, then in the plugin templates folder.
 *
 * @param string $template Template path relative to the templates folder (e.g. "course/single.php").
 * @param array  $args     Variables made available to the template.
 * @param bool   $output   Whether to echo (true) or return (false) the template.
 * @return string
 */
function shapelms_get_template( $template, $args = array(), $output = true ) {
	$located = locate_template( 'shapelms/' . $template );

	if ( ! $located ) {
		$located = SHAPELMS_DIR . 'templates/' . $template;
	}

	/**
	 * Filters the located template path.
	 *
	 * @param string $located  Located template path.
	 * @param string $template Requested template.
	 * @param array  $args     Template arguments.
	 */
	$located = apply_filters( 'shapelms_locate_template', $located, $template, $args );

	if ( ! file_exists( $located ) ) {
		return '';
	}

	if ( ! $output ) {
		ob_start();
	}

	load_template( $located, false, $args );

	if ( ! $output ) {
		return (string) ob_get_clean();
	}

	return '';
}

/**
 * Formats a duration in seconds for display.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
function shapelms_format_duration( $seconds ) {
	$seconds = absint( $seconds );
	$hours   = (int) floor( $seconds / 3600 );
	$minutes = (int) floor( ( $seconds % 3600 ) / 60 );

	if ( $hours > 0 ) {
		/* translators: 1: hours, 2: minutes */
		return sprintf( __( '%1$dh %2$dm', 'shapelms' ), $hours, $minutes );
	}

	/* translators: %d: minutes */
	return sprintf( __( '%dm', 'shapelms' ), $minutes );
}

/**
 * Formats a price for display using the plugin currency settings.
 *
 * @param float $amount Amount.
 * @return string
 */
function shapelms_format_price( $amount ) {
	return \ShapeLMS\Services\CurrencyService::format( (float) $amount );
}
