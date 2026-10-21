<?php
/**
 * Uninstall ShapeLMS.
 *
 * Removes plugin data only when the "Delete all data on uninstall" setting is enabled.
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

// Custom tables.
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
	$shapelms_table_name = esc_sql( $wpdb->prefix . $shapelms_table );
	$wpdb->query( "DROP TABLE IF EXISTS {$shapelms_table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table names.
}

// Posts (courses, topics, lessons, quizzes, questions, announcements) and their meta.
$shapelms_post_ids = get_posts(
	array(
		'post_type'     => array( 'shapelms_course', 'shapelms_topic', 'shapelms_lesson', 'shapelms_quiz', 'shapelms_question', 'shapelms_announce' ),
		'post_status'   => 'any',
		'numberposts'   => -1,
		'fields'        => 'ids',
		'no_found_rows' => true,
	)
);

foreach ( $shapelms_post_ids as $shapelms_post_id ) {
	wp_delete_post( $shapelms_post_id, true );
}

// Reviews and Q&A (stored as comments).
$shapelms_comment_ids = get_comments(
	array(
		'type__in' => array( 'shapelms_review', 'shapelms_qa' ),
		'status'   => 'all',
		'fields'   => 'ids',
	)
);

foreach ( (array) $shapelms_comment_ids as $shapelms_comment_id ) {
	wp_delete_comment( (int) $shapelms_comment_id, true );
}

// Taxonomy terms.
foreach ( array( 'shapelms_course_category', 'shapelms_course_tag' ) as $shapelms_taxonomy ) {
	$shapelms_terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $shapelms_taxonomy ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $shapelms_terms as $shapelms_term_id ) {
		wp_delete_term( (int) $shapelms_term_id, $shapelms_taxonomy );
	}
}

// Options.
foreach ( array( 'shapelms_settings', 'shapelms_modules', 'shapelms_db_version', 'shapelms_version', 'shapelms_pages', 'shapelms_setup_complete', 'shapelms_theme_css_version', 'shapelms_paypal_webhook_id', 'shapelms_caps_version' ) as $shapelms_option ) {
	delete_option( $shapelms_option );
}

// Transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_shapelms\_%' OR option_name LIKE '\_transient\_timeout\_shapelms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- literal pattern, no user input.

// User meta.
foreach ( array( 'shapelms_wishlist', 'shapelms_bio', 'shapelms_headline', 'shapelms_social', 'shapelms_instructor_status' ) as $shapelms_meta_key ) {
	delete_metadata( 'user', 0, $shapelms_meta_key, '', true );
}

// Roles and capabilities.
remove_role( 'shapelms_instructor' );
remove_role( 'shapelms_student' );

$shapelms_admin_role = get_role( 'administrator' );
if ( $shapelms_admin_role ) {
	foreach ( array( 'manage_shapelms', 'edit_shapelms_courses', 'delete_shapelms_courses', 'edit_published_shapelms_courses', 'delete_published_shapelms_courses', 'edit_others_shapelms_courses', 'delete_others_shapelms_courses', 'publish_shapelms_courses', 'read_private_shapelms_courses', 'edit_private_shapelms_courses', 'delete_private_shapelms_courses' ) as $shapelms_cap ) {
		$shapelms_admin_role->remove_cap( $shapelms_cap );
	}
}

// Generated stylesheet.
$shapelms_uploads = wp_upload_dir();
$shapelms_css_dir = trailingslashit( $shapelms_uploads['basedir'] ) . 'shapelms';

if ( is_dir( $shapelms_css_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $shapelms_css_dir, true );
	}
}

// Scheduled events.
wp_clear_scheduled_hook( 'shapelms_daily_maintenance' );
wp_clear_scheduled_hook( 'shapelms_send_queued_email' );
