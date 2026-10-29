<?php
/**
 * Order repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for orders and order items.
 */
class OrderRepository extends Repository {

	/**
	 * Table.
	 *
	 * @var string
	 */
	protected $table = 'orders';

	/**
	 * Columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'id', 'order_key', 'user_id', 'status', 'currency', 'subtotal', 'discount', 'total', 'gateway', 'transaction_id', 'billing_email', 'billing_name', 'ip_address', 'notes', 'created_at', 'paid_at', 'refunded_at' );

	/**
	 * Formats.
	 *
	 * @var array<string, string>
	 */
	protected $formats = array(
		'id'             => '%d',
		'order_key'      => '%s',
		'user_id'        => '%d',
		'status'         => '%s',
		'currency'       => '%s',
		'subtotal'       => '%f',
		'discount'       => '%f',
		'total'          => '%f',
		'gateway'        => '%s',
		'transaction_id' => '%s',
		'billing_email'  => '%s',
		'billing_name'   => '%s',
		'ip_address'     => '%s',
		'notes'          => '%s',
		'created_at'     => '%s',
		'paid_at'        => '%s',
		'refunded_at'    => '%s',
	);

	/**
	 * Finds an order by its key.
	 *
	 * @param string $key Order key.
	 * @return object|null
	 */
	public function find_by_key( $key ) {
		return $this->find_by( array( 'order_key' => sanitize_text_field( $key ) ) );
	}

	/**
	 * Finds an order by gateway transaction id.
	 *
	 * @param string $transaction_id Transaction id.
	 * @return object|null
	 */
	public function find_by_transaction( $transaction_id ) {
		return $this->find_by( array( 'transaction_id' => sanitize_text_field( $transaction_id ) ) );
	}

	/**
	 * Returns the items of an order.
	 *
	 * @param int $order_id Order id.
	 * @return object[]
	 */
	public function items( $order_id ) {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'order_items' ) );

		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC", (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Adds an item to an order.
	 *
	 * @param int    $order_id  Order id.
	 * @param int    $course_id Course id.
	 * @param string $title     Course title at purchase time.
	 * @param float  $price     Price paid.
	 * @return int Item id.
	 */
	public function add_item( $order_id, $course_id, $title, $price ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			shapelms_table( 'order_items' ),
			array(
				'order_id'  => (int) $order_id,
				'course_id' => (int) $course_id,
				'title'     => sanitize_text_field( $title ),
				'price'     => (float) $price,
			),
			array( '%d', '%d', '%s', '%f' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Deletes an order and its items.
	 *
	 * @param int $id Order id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$wpdb->delete( shapelms_table( 'order_items' ), array( 'order_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return parent::delete( $id );
	}

	/**
	 * Paginated orders for the admin screen.
	 *
	 * @param array<string, mixed> $args { search, status, user_id, page, per_page }.
	 * @return array{items: object[], total: int}
	 */
	public function paginate( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'user_id'  => 0,
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$table = esc_sql( $this->table() );
		$where = array( '1=1' );

		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'o.status = %s', $args['status'] );
		}
		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'o.user_id = %d', (int) $args['user_id'] );
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(o.billing_email LIKE %s OR o.billing_name LIKE %s OR o.order_key LIKE %s OR o.transaction_id LIKE %s)', $like, $like, $like, $like );
			if ( is_numeric( $args['search'] ) ) {
				$where[ count( $where ) - 1 ] = '(' . $where[ count( $where ) - 1 ] . $wpdb->prepare( ' OR o.id = %d)', (int) $args['search'] );
			}
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset    = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} o WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT o.* FROM {$table} o WHERE {$where_sql} ORDER BY o.id DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Revenue per course for completed orders.
	 *
	 * @param int[] $course_ids Course ids.
	 * @return array<int, array{revenue: float, sales: int}> course_id => totals.
	 */
	public function revenue_for_courses( array $course_ids ) {
		global $wpdb;

		$course_ids = array_map( 'intval', array_filter( $course_ids ) );

		if ( empty( $course_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $course_ids ), '%d' ) );
		$orders       = esc_sql( $this->table() );
		$items        = esc_sql( shapelms_table( 'order_items' ) );

		$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fragments are individually prepared; table and column names come from fixed whitelists.
			$wpdb->prepare(
				"SELECT i.course_id, COALESCE(SUM(i.price),0) AS revenue, COUNT(*) AS sales FROM {$items} i INNER JOIN {$orders} o ON o.id = i.order_id WHERE o.status = 'completed' AND i.course_id IN ({$placeholders}) GROUP BY i.course_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				...$course_ids
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->course_id ] = array(
				'revenue' => (float) $row->revenue,
				'sales'   => (int) $row->sales,
			);
		}

		return $out;
	}

	/**
	 * Sums paid totals, optionally within a date range.
	 *
	 * @param string $from Start date (Y-m-d H:i:s) or empty.
	 * @param string $to   End date or empty.
	 * @return float
	 */
	public function revenue( $from = '', $to = '' ) {
		global $wpdb;

		$table = esc_sql( $this->table() );
		$sql   = "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE status = 'completed'";

		if ( $from ) {
			$sql .= $wpdb->prepare( ' AND paid_at >= %s', $from );
		}
		if ( $to ) {
			$sql .= $wpdb->prepare( ' AND paid_at <= %s', $to );
		}

		return (float) $wpdb->get_var( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
	}
}
