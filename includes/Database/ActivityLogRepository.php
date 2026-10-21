<?php
/**
 * Activity log repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for the activity log. Feeds reports and the dashboard.
 */
class ActivityLogRepository extends Repository {

	/**
	 * Table.
	 *
	 * @var string
	 */
	protected $table = 'activity_log';

	/**
	 * Columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'id', 'event', 'user_id', 'course_id', 'object_id', 'value', 'context', 'created_at' );

	/**
	 * Formats.
	 *
	 * @var array<string, string>
	 */
	protected $formats = array(
		'id'         => '%d',
		'event'      => '%s',
		'user_id'    => '%d',
		'course_id'  => '%d',
		'object_id'  => '%d',
		'value'      => '%f',
		'context'    => '%s',
		'created_at' => '%s',
	);

	/**
	 * Writes a log row.
	 *
	 * @param string     $event     Event name.
	 * @param int        $user_id   User id.
	 * @param int        $course_id Course id.
	 * @param int        $object_id Object id.
	 * @param float|null $value     Numeric value (score, amount).
	 * @param array      $context   Extra context.
	 * @return int
	 */
	public static function log( $event, $user_id, $course_id = 0, $object_id = 0, $value = null, array $context = array() ) {
		$repo = new self();

		return $repo->insert(
			array(
				'event'      => sanitize_key( $event ),
				'user_id'    => (int) $user_id,
				'course_id'  => (int) $course_id,
				'object_id'  => (int) $object_id,
				'value'      => $value,
				'context'    => $context ? wp_json_encode( $context ) : null,
				'created_at' => shapelms_now(),
			)
		);
	}

	/**
	 * Counts events per day in a range.
	 *
	 * @param string $event     Event name.
	 * @param string $from      Start (Y-m-d).
	 * @param string $to        End (Y-m-d).
	 * @param int    $course_id Optional course filter.
	 * @return array<string, int> date => count.
	 */
	public function daily_counts( $event, $from, $to, $course_id = 0 ) {
		global $wpdb;

		$table = esc_sql( $this->table() );
		$sql   = $wpdb->prepare(
			"SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$table} WHERE event = %s AND created_at >= %s AND created_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			sanitize_key( $event ),
			$from . ' 00:00:00',
			$to . ' 23:59:59'
		);

		if ( $course_id ) {
			$sql .= $wpdb->prepare( ' AND course_id = %d', (int) $course_id );
		}

		$sql .= ' GROUP BY DATE(created_at) ORDER BY day ASC';

		$rows   = (array) $wpdb->get_results( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (string) $row->day ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Returns the latest events with user and course titles.
	 *
	 * @param int   $limit  Number of rows.
	 * @param array $events Event names to include (empty = all).
	 * @return object[]
	 */
	public function recent( $limit = 20, array $events = array() ) {
		global $wpdb;

		$table = esc_sql( $this->table() );
		$where = '1=1';

		if ( $events ) {
			$placeholders = implode( ', ', array_fill( 0, count( $events ), '%s' ) );
			$where        = $wpdb->prepare( "l.event IN ({$placeholders})", ...array_map( 'sanitize_key', $events ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT l.*, u.display_name, p.post_title AS course_title FROM {$table} l LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id LEFT JOIN {$wpdb->posts} p ON p.ID = l.course_id WHERE {$where} ORDER BY l.id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				max( 1, min( 100, (int) $limit ) )
			)
		);
	}

	/**
	 * Events for one user, latest first.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Number of rows.
	 * @return object[]
	 */
	public function for_user( $user_id, $limit = 50 ) {
		global $wpdb;

		$table = esc_sql( $this->table() );

		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT l.*, p.post_title AS course_title FROM {$table} l LEFT JOIN {$wpdb->posts} p ON p.ID = l.course_id WHERE l.user_id = %d ORDER BY l.id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id,
				max( 1, min( 200, (int) $limit ) )
			)
		);
	}
}
