<?php
/**
 * Enrollment repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for the enrollments table.
 */
class EnrollmentRepository extends Repository {

	/**
	 * Table.
	 *
	 * @var string
	 */
	protected $table = 'enrollments';

	/**
	 * Columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'id', 'user_id', 'course_id', 'status', 'source', 'order_id', 'progress', 'started_at', 'completed_at', 'expires_at', 'last_activity_at' );

	/**
	 * Formats.
	 *
	 * @var array<string, string>
	 */
	protected $formats = array(
		'id'               => '%d',
		'user_id'          => '%d',
		'course_id'        => '%d',
		'status'           => '%s',
		'source'           => '%s',
		'order_id'         => '%d',
		'progress'         => '%d',
		'started_at'       => '%s',
		'completed_at'     => '%s',
		'expires_at'       => '%s',
		'last_activity_at' => '%s',
	);

	/**
	 * Returns the enrollment of a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return object|null
	 */
	public function get( $user_id, $course_id ) {
		return $this->find_by(
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
			)
		);
	}

	/**
	 * Returns the course ids a user is enrolled in.
	 *
	 * @param int      $user_id  User id.
	 * @param string[] $statuses Statuses to include.
	 * @return int[]
	 */
	public function course_ids_for_user( $user_id, array $statuses = array( 'active', 'completed' ) ) {
		$rows = $this->where(
			array(
				'user_id' => (int) $user_id,
				'status'  => $statuses,
			),
			array(
				'orderby' => 'started_at',
				'order'   => 'DESC',
			)
		);

		return array_map( 'intval', wp_list_pluck( $rows, 'course_id' ) );
	}

	/**
	 * Counts active + completed enrollments per course for a list of course ids.
	 *
	 * @param int[] $course_ids Course ids.
	 * @return array<int, int> course_id => count.
	 */
	public function counts_for_courses( array $course_ids ) {
		global $wpdb;

		$course_ids = array_map( 'intval', array_filter( $course_ids ) );

		if ( empty( $course_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $course_ids ), '%d' ) );
		$table        = esc_sql( $this->table() );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT course_id, COUNT(*) AS total FROM {$table} WHERE status IN ('active','completed') AND course_id IN ({$placeholders}) GROUP BY course_id", ...$course_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->course_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Paginated list for the admin Students screen.
	 *
	 * @param array<string, mixed> $args { search, course_id, status, page, per_page, orderby, order }.
	 * @return array{items: object[], total: int}
	 */
	public function paginate( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'    => '',
				'course_id' => 0,
				'status'    => '',
				'page'      => 1,
				'per_page'  => 20,
				'orderby'   => 'started_at',
				'order'     => 'DESC',
			)
		);

		$table = esc_sql( $this->table() );
		$where = array( '1=1' );

		if ( $args['course_id'] ) {
			$where[] = $wpdb->prepare( 'e.course_id = %d', (int) $args['course_id'] );
		}
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'e.status = %s', $args['status'] );
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)', $like, $like, $like );
		}

		$orderby_map = array(
			'started_at' => 'e.started_at',
			'progress'   => 'e.progress',
			'name'       => 'u.display_name',
			'status'     => 'e.status',
		);
		$orderby     = isset( $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : 'e.started_at';
		$order       = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page    = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset      = max( 0, ( (int) $args['page'] - 1 ) * $per_page );
		$where_sql   = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} e INNER JOIN {$wpdb->users} u ON u.ID = e.user_id WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.

		$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT e.*, u.display_name, u.user_email FROM {$table} e INNER JOIN {$wpdb->users} u ON u.ID = e.user_id WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}
}
