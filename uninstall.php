<?php
/**
 * Uninstall ShapeLMS.
 *
 * Removes plugin data only when the "Delete data on uninstall" setting is enabled.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$shapelms_settings = get_option( 'shapelms_settings', array() );

if ( empty( $shapelms_settings['general']['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$shapelms_tables = array(
	'shapelms_enrollments',
	'shapelms_progress',
	'shapelms_quiz_attempts',
	'shapelms_attempt_answers',
	'shapelms_orders',
	'shapelms_order_items',
	'shapelms_activity_log',
);

foreach ( $shapelms_tables as $shapelms_table ) {
	$shapelms_table_name = $wpdb->prefix . $shapelms_table;
	$wpdb->query( "DROP TABLE IF EXISTS {$shapelms_table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$shapelms_post_types = array( 'shapelms_course', 'shapelms_topic', 'shapelms_lesson', 'shapelms_quiz', 'shapelms_question' );

$shapelms_post_ids = get_posts(
	array(
		'post_type'     => $shapelms_post_types,
		'post_status'   => 'any',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'no_found_rows' => true,
	)
);

foreach ( $shapelms_post_ids as $shapelms_post_id ) {
	wp_delete_post( $shapelms_post_id, true );
}

$shapelms_options = array(
	'shapelms_settings',
	'shapelms_modules',
	'shapelms_db_version',
	'shapelms_version',
	'shapelms_pages',
	'shapelms_setup_complete',
);

foreach ( $shapelms_options as $shapelms_option ) {
	delete_option( $shapelms_option );
}

remove_role( 'shapelms_instructor' );
remove_role( 'shapelms_student' );
