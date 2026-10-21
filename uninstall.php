<?php
/**
 * Uninstall ShapeLMS.
 *
 * Removes plugin data only when the "Delete all data on uninstall" setting is enabled on a site.
 * On multisite every site is processed.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes all ShapeLMS data from the current site.
 *
 * @return void
 */
function shapelms_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'shapelms_settings', array() );

	if ( empty( $settings['general']['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Custom tables.
	foreach ( array( 'enrollments', 'progress', 'quiz_attempts', 'attempt_answers', 'orders', 'order_items', 'activity_log' ) as $table ) {
		$table_name = esc_sql( $wpdb->prefix . 'shapelms_' . $table );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fixed, escaped table names.
	}

	// Pages created by the setup wizard.
	$pages = get_option( 'shapelms_pages', array() );
	foreach ( (array) $pages as $page_id ) {
		if ( $page_id && 'page' === get_post_type( (int) $page_id ) ) {
			wp_delete_post( (int) $page_id, true );
		}
	}

	// Posts (courses, topics, lessons, quizzes, questions, announcements) and their meta.
	$post_ids = get_posts(
		array(
			'post_type'     => array( 'shapelms_course', 'shapelms_topic', 'shapelms_lesson', 'shapelms_quiz', 'shapelms_question', 'shapelms_announce' ),
			'post_status'   => 'any',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	// Reviews and Q&A (stored as comments).
	$comment_ids = get_comments(
		array(
			'type__in' => array( 'shapelms_review', 'shapelms_qa' ),
			'status'   => 'all',
			'fields'   => 'ids',
		)
	);

	foreach ( (array) $comment_ids as $comment_id ) {
		wp_delete_comment( (int) $comment_id, true );
	}

	// Taxonomy terms.
	foreach ( array( 'shapelms_course_category', 'shapelms_course_tag' ) as $taxonomy ) {
		$term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $term_ids as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}

	// Meta left on other objects (WooCommerce products linked to courses).
	delete_post_meta_by_key( '_shapelms_course_id' );

	// Options.
	foreach ( array( 'shapelms_settings', 'shapelms_modules', 'shapelms_db_version', 'shapelms_version', 'shapelms_pages', 'shapelms_setup_complete', 'shapelms_theme_css_version', 'shapelms_paypal_webhook_id', 'shapelms_caps_version' ) as $option ) {
		delete_option( $option );
	}

	// Transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_shapelms\_%' OR option_name LIKE '\_transient\_timeout\_shapelms\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- literal pattern, no user input.

	// User meta.
	foreach ( array( 'shapelms_wishlist', 'shapelms_bio', 'shapelms_headline', 'shapelms_social', 'shapelms_instructor_status' ) as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}

	// Roles and capabilities.
	remove_role( 'shapelms_instructor' );
	remove_role( 'shapelms_student' );

	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		foreach ( array( 'manage_shapelms', 'edit_shapelms_courses', 'delete_shapelms_courses', 'edit_published_shapelms_courses', 'delete_published_shapelms_courses', 'edit_others_shapelms_courses', 'delete_others_shapelms_courses', 'publish_shapelms_courses', 'read_private_shapelms_courses', 'edit_private_shapelms_courses', 'delete_private_shapelms_courses' ) as $cap ) {
			$admin_role->remove_cap( $cap );
		}
	}

	// Generated stylesheet.
	$uploads = wp_upload_dir();
	$css_dir = trailingslashit( $uploads['basedir'] ) . 'shapelms';

	if ( is_dir( $css_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem ) {
			$wp_filesystem->delete( $css_dir, true );
		}
	}

	// Scheduled events.
	wp_clear_scheduled_hook( 'shapelms_daily_maintenance' );
	wp_clear_scheduled_hook( 'shapelms_send_queued_email' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $shapelms_site_id ) {
		switch_to_blog( (int) $shapelms_site_id );
		shapelms_uninstall_site();
		restore_current_blog();
	}
} else {
	shapelms_uninstall_site();
}
