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
	protected $columns = array( 'id', 'order_key', 'user_id', 'status', 'currency', 'subtotal', 'discount', 'total', 'refunded_amount', 'gateway', 'transaction_id', 'billing_email', 'billing_name', 'billing', 'ip_address', 'notes', 'created_at', 'paid_at', 'refunded_at', 'trashed_at' );

	/**
	 * Admin list tabs => the order statuses they hold. Trash is separate (trashed_at).
	 *
	 * @var array<string, string[]>
	 */
	const VIEWS = array(
		'incomplete' => array( 'pending', 'failed' ),
		'pending'    => array( 'on-hold' ),
		'completed'  => array( 'completed' ),
		'cancelled'  => array( 'cancelled', 'refunded' ),
	);

	/**
	 * Payment status => SQL condition on the order row (alias o). Mirrors OrderService::payment_status().
	 *
	 * @var array<string, string>
	 */
	const PAYMENT_SQL = array(
		'paid'               => "((o.status = 'completed' AND o.refunded_amount <= 0) OR (o.status = 'cancelled' AND o.paid_at IS NOT NULL))",
		'partially-refunded' => "(o.status = 'completed' AND o.refunded_amount > 0)",
		'unpaid'             => "(o.status = 'pending' OR (o.status = 'cancelled' AND o.paid_at IS NULL))",
		'pending'            => "(o.status = 'on-hold')",
		'failed'             => "(o.status = 'failed')",
		'refunded'           => "(o.status = 'refunded')",
	);

	/**
	 * Sortable columns of the admin list.
	 *
	 * @var string[]
	 */
	const SORTABLE = array( 'id', 'created_at', 'total' );

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
		'refunded_amount' => '%f',
		'gateway'        => '%s',
		'transaction_id' => '%s',
		'billing_email'  => '%s',
		'billing_name'   => '%s',
		'billing'        => '%s',
		'ip_address'     => '%s',
		'notes'          => '%s',
		'created_at'     => '%s',
		'paid_at'        => '%s',
		'refunded_at'    => '%s',
		'trashed_at'     => '%s',
	);

	/**
	 * The user's on-hold order that contains a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return object|null
	 */
	public function find_on_hold( $user_id, $course_id ) {
		global $wpdb;

		$table = esc_sql( $this->table() );
		$items = esc_sql( shapelms_table( 'order_items' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are internal and escaped.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT o.* FROM {$table} o INNER JOIN {$items} i ON i.order_id = o.id WHERE o.user_id = %d AND o.status = 'on-hold' AND o.trashed_at IS NULL AND i.course_id = %d ORDER BY o.id DESC LIMIT 1", $user_id, $course_id ) );

		return $row ? $row : null;
	}

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
	 * Trashed orders are left out unless the trash view is asked for.
	 *
	 * @param array<string, mixed> $args { search, view, status, payment, user_id, orderby, order, page, per_page }.
	 * @return array{items: object[], total: int}
	 */
	public function paginate( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'view'     => '',
				'status'   => '',
				'payment'  => '',
				'user_id'  => 0,
				'orderby'  => 'id',
				'order'    => 'DESC',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$table = esc_sql( $this->table() );
		$where = array( 'trash' === $args['view'] ? 'o.trashed_at IS NOT NULL' : 'o.trashed_at IS NULL' );

		if ( isset( self::VIEWS[ $args['view'] ] ) ) {
			$where[] = "o.status IN ('" . implode( "','", array_map( 'esc_sql', self::VIEWS[ $args['view'] ] ) ) . "')";
		}
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'o.status = %s', $args['status'] );
		}
		if ( isset( self::PAYMENT_SQL[ $args['payment'] ] ) ) {
			$where[] = self::PAYMENT_SQL[ $args['payment'] ];
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

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} o WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fragments are individually prepared; table and column names come from fixed whitelists.
		// Whitelisted column and direction; id breaks ties so paging stays stable.
		$orderby = in_array( $args['orderby'], self::SORTABLE, true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$sort    = 'id' === $orderby ? "o.id {$order}" : "o.{$orderby} {$order}, o.id {$order}";

		$items = $wpdb->get_results( $wpdb->prepare( "SELECT o.* FROM {$table} o WHERE {$where_sql} ORDER BY {$sort} LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- sort column and direction come from whitelists.

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Number of orders in each admin tab.
	 *
	 * @return array<string, int> all, incomplete, pending, completed, cancelled, trash.
	 */
	public function counts() {
		global $wpdb;

		$table = esc_sql( $this->table() );
		$rows  = $wpdb->get_results( "SELECT status, (trashed_at IS NOT NULL) AS trashed, COUNT(*) AS n FROM {$table} GROUP BY status, trashed" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table, no input.

		$counts = array_fill_keys( array_merge( array( 'all' ), array_keys( self::VIEWS ), array( 'trash' ) ), 0 );

		foreach ( (array) $rows as $row ) {
			if ( (int) $row->trashed ) {
				$counts['trash'] += (int) $row->n;
				continue;
			}
			$counts['all'] += (int) $row->n;
			foreach ( self::VIEWS as $view => $statuses ) {
				if ( in_array( $row->status, $statuses, true ) ) {
					$counts[ $view ] += (int) $row->n;
				}
			}
		}

		return $counts;
	}

	/**
	 * Moves orders to the trash. Their status, items and enrollments stay as they are.
	 *
	 * @param int[] $ids Order ids.
	 * @return int Orders moved.
	 */
	public function trash( array $ids ) {
		return $this->set_trashed( $ids, true );
	}

	/**
	 * Takes orders out of the trash.
	 *
	 * @param int[] $ids Order ids.
	 * @return int Orders restored.
	 */
	public function restore( array $ids ) {
		return $this->set_trashed( $ids, false );
	}

	/**
	 * Sets or clears trashed_at.
	 *
	 * @param int[] $ids     Order ids.
	 * @param bool  $trashed Trash or restore.
	 * @return int Rows changed.
	 */
	private function set_trashed( array $ids, $trashed ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = esc_sql( $this->table() );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$set          = $trashed ? $wpdb->prepare( 'trashed_at = %s', shapelms_now() ) : 'trashed_at = NULL';
		$only         = $trashed ? 'trashed_at IS NULL' : 'trashed_at IS NOT NULL';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fragments are prepared; table name is internal.
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$set} WHERE {$only} AND id IN ({$placeholders})", ...$ids ) );
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
				"SELECT i.course_id, COALESCE(SUM(i.price),0) AS revenue, COUNT(*) AS sales FROM {$items} i INNER JOIN {$orders} o ON o.id = i.order_id WHERE o.status = 'completed' AND o.trashed_at IS NULL AND i.course_id IN ({$placeholders}) GROUP BY i.course_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
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
		// Partial refunds are taken off; trashed orders are left out.
		$sql = "SELECT COALESCE(SUM(total - refunded_amount),0) FROM {$table} WHERE status = 'completed' AND trashed_at IS NULL";

		if ( $from ) {
			$sql .= $wpdb->prepare( ' AND paid_at >= %s', $from );
		}
		if ( $to ) {
			$sql .= $wpdb->prepare( ' AND paid_at <= %s', $to );
		}

		return (float) $wpdb->get_var( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
	}
}
