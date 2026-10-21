<?php
/**
 * Base repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin, prepared-statement-only data access for one custom table.
 */
abstract class Repository {

	/**
	 * Short table name (without prefix).
	 *
	 * @var string
	 */
	protected $table = '';

	/**
	 * Column formats for insert/update ( column => %d|%s|%f ).
	 *
	 * @var array<string, string>
	 */
	protected $formats = array();

	/**
	 * Columns that may be used in where/order clauses.
	 *
	 * @var string[]
	 */
	protected $columns = array();

	/**
	 * Returns the full table name.
	 *
	 * @return string
	 */
	public function table() {
		return shapelms_table( $this->table );
	}

	/**
	 * Finds a row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = esc_sql( $this->table() );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Finds the first row matching the conditions.
	 *
	 * @param array<string, mixed> $where Column => value (equality only).
	 * @return object|null
	 */
	public function find_by( array $where ) {
		$rows = $this->where( $where, array( 'limit' => 1 ) );

		return $rows ? $rows[0] : null;
	}

	/**
	 * Returns rows matching the conditions.
	 *
	 * @param array<string, mixed> $where Column => value. A value that is an array becomes IN(...).
	 * @param array<string, mixed> $args  { orderby, order, limit, offset }.
	 * @return object[]
	 */
	public function where( array $where, array $args = array() ) {
		global $wpdb;

		$sql = "SELECT * FROM {$this->table()}" . $this->build_where( $where ) . $this->build_order_limit( $args );

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
	}

	/**
	 * Counts rows matching the conditions.
	 *
	 * @param array<string, mixed> $where Column => value.
	 * @return int
	 */
	public function count( array $where = array() ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$this->table()}" . $this->build_where( $where );

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
	}

	/**
	 * Inserts a row.
	 *
	 * @param array<string, mixed> $data Column => value.
	 * @return int Inserted id, 0 on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$data = $this->only_known( $data );

		$result = $wpdb->insert( $this->table(), $data, $this->formats_for( $data ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Updates rows by id.
	 *
	 * @param int                  $id   Row id.
	 * @param array<string, mixed> $data Column => value.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data = $this->only_known( $data );

		if ( empty( $data ) ) {
			return false;
		}

		$result = $wpdb->update( $this->table(), $data, array( 'id' => (int) $id ), $this->formats_for( $data ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $result;
	}

	/**
	 * Deletes a row by id.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Deletes rows matching the conditions.
	 *
	 * @param array<string, mixed> $where Column => value.
	 * @return int Rows deleted.
	 */
	public function delete_where( array $where ) {
		global $wpdb;

		if ( empty( $where ) ) {
			return 0;
		}

		$sql = "DELETE FROM {$this->table()}" . $this->build_where( $where );

		return (int) $wpdb->query( $sql ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
	}

	/**
	 * Builds a prepared WHERE clause.
	 *
	 * @param array<string, mixed> $where Column => value, or column => array for IN, or column => [ 'op' => '>=', 'value' => x ].
	 * @return string
	 */
	protected function build_where( array $where ) {
		global $wpdb;

		$clauses = array();

		foreach ( $where as $column => $value ) {
			if ( ! in_array( $column, $this->columns, true ) ) {
				continue;
			}

			$format = isset( $this->formats[ $column ] ) ? $this->formats[ $column ] : '%s';

			if ( is_array( $value ) && isset( $value['op'] ) ) {
				$ops = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE' );
				$op  = in_array( $value['op'], $ops, true ) ? $value['op'] : '=';
				$val = array_key_exists( 'value', $value ) ? $value['value'] : '';

				if ( null === $val ) {
					$clauses[] = "{$column} IS " . ( '!=' === $op ? 'NOT ' : '' ) . 'NULL';
				} else {
					$clauses[] = $wpdb->prepare( "{$column} {$op} {$format}", $val ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				}
				continue;
			}

			if ( is_array( $value ) ) {
				if ( empty( $value ) ) {
					$clauses[] = '1=0';
					continue;
				}
				$placeholders = implode( ', ', array_fill( 0, count( $value ), $format ) );
				$clauses[]    = $wpdb->prepare( "{$column} IN ({$placeholders})", ...array_values( $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				continue;
			}

			if ( null === $value ) {
				$clauses[] = "{$column} IS NULL";
				continue;
			}

			$clauses[] = $wpdb->prepare( "{$column} = {$format}", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		return $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
	}

	/**
	 * Builds ORDER BY / LIMIT.
	 *
	 * @param array<string, mixed> $args { orderby, order, limit, offset }.
	 * @return string
	 */
	protected function build_order_limit( array $args ) {
		global $wpdb;

		$sql = '';

		if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], $this->columns, true ) ) {
			$order = ( isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';
			$sql  .= " ORDER BY {$args['orderby']} {$order}";
		}

		if ( isset( $args['limit'] ) ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', max( 1, (int) $args['limit'] ) );
			if ( ! empty( $args['offset'] ) ) {
				$sql .= $wpdb->prepare( ' OFFSET %d', max( 0, (int) $args['offset'] ) );
			}
		}

		return $sql;
	}

	/**
	 * Keeps only known columns.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>
	 */
	protected function only_known( array $data ) {
		return array_intersect_key( $data, array_flip( $this->columns ) );
	}

	/**
	 * Returns the formats for the given data in order.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return string[]
	 */
	protected function formats_for( array $data ) {
		$formats = array();

		foreach ( $data as $column => $value ) {
			if ( null === $value ) {
				$formats[] = '%s';
				continue;
			}
			$formats[] = isset( $this->formats[ $column ] ) ? $this->formats[ $column ] : '%s';
		}

		return $formats;
	}
}
