<?php
/**
 * Quiz attempt repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for quiz attempts and their answers.
 */
class AttemptRepository extends Repository {

	/**
	 * Table.
	 *
	 * @var string
	 */
	protected $table = 'quiz_attempts';

	/**
	 * Columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'id', 'user_id', 'course_id', 'quiz_id', 'attempt_number', 'status', 'score', 'max_score', 'percentage', 'passed', 'question_order', 'started_at', 'expires_at', 'finished_at' );

	/**
	 * Formats.
	 *
	 * @var array<string, string>
	 */
	protected $formats = array(
		'id'             => '%d',
		'user_id'        => '%d',
		'course_id'      => '%d',
		'quiz_id'        => '%d',
		'attempt_number' => '%d',
		'status'         => '%s',
		'score'          => '%f',
		'max_score'      => '%f',
		'percentage'     => '%f',
		'passed'         => '%d',
		'question_order' => '%s',
		'started_at'     => '%s',
		'expires_at'     => '%s',
		'finished_at'    => '%s',
	);

	/**
	 * Returns the open (in progress) attempt for a user on a quiz.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return object|null
	 */
	public function open_attempt( $user_id, $quiz_id ) {
		return $this->find_by(
			array(
				'user_id' => (int) $user_id,
				'quiz_id' => (int) $quiz_id,
				'status'  => 'in_progress',
			)
		);
	}

	/**
	 * Returns all attempts for a user on a quiz, latest first.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return object[]
	 */
	public function for_user_quiz( $user_id, $quiz_id ) {
		return $this->where(
			array(
				'user_id' => (int) $user_id,
				'quiz_id' => (int) $quiz_id,
			),
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
			)
		);
	}

	/**
	 * Counts finished attempts.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return int
	 */
	public function count_finished( $user_id, $quiz_id ) {
		return $this->count(
			array(
				'user_id' => (int) $user_id,
				'quiz_id' => (int) $quiz_id,
				'status'  => array( 'completed', 'expired' ),
			)
		);
	}

	/**
	 * Whether the user has passed the quiz in any attempt.
	 *
	 * @param int $user_id User id.
	 * @param int $quiz_id Quiz id.
	 * @return bool
	 */
	public function has_passed( $user_id, $quiz_id ) {
		return $this->count(
			array(
				'user_id' => (int) $user_id,
				'quiz_id' => (int) $quiz_id,
				'passed'  => 1,
			)
		) > 0;
	}

	/**
	 * Returns answers of an attempt keyed by question id.
	 *
	 * @param int $attempt_id Attempt id.
	 * @return array<int, object>
	 */
	public function answers( $attempt_id ) {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'attempt_answers' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE attempt_id = %d", (int) $attempt_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->question_id ] = $row;
		}

		return $map;
	}

	/**
	 * Saves (upserts) an answer.
	 *
	 * @param int   $attempt_id  Attempt id.
	 * @param int   $question_id Question id.
	 * @param mixed $answer      Raw answer (stored as JSON).
	 * @param array $grade       { is_correct, points, max_points }.
	 * @return void
	 */
	public function save_answer( $attempt_id, $question_id, $answer, array $grade ) {
		global $wpdb;

		$table = esc_sql( shapelms_table( 'attempt_answers' ) );
		$data  = array(
			'attempt_id'  => (int) $attempt_id,
			'question_id' => (int) $question_id,
			'answer'      => wp_json_encode( $answer ),
			'is_correct'  => isset( $grade['is_correct'] ) ? (int) $grade['is_correct'] : null,
			'points'      => (float) ( $grade['points'] ?? 0 ),
			'max_points'  => (float) ( $grade['max_points'] ?? 0 ),
			'answered_at' => shapelms_now(),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE attempt_id = %d AND question_id = %d", $data['attempt_id'], $data['question_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$formats = array( '%d', '%d', '%s', '%d', '%f', '%f', '%s' );

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Deletes an attempt and its answers.
	 *
	 * @param int $id Attempt id.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$wpdb->delete( shapelms_table( 'attempt_answers' ), array( 'attempt_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return parent::delete( $id );
	}

	/**
	 * Paginated attempts for admin/instructor screens.
	 *
	 * @param array<string, mixed> $args { quiz_id, course_id, user_id, page, per_page }.
	 * @return array{items: object[], total: int}
	 */
	public function paginate( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'quiz_id'   => 0,
				'course_id' => 0,
				'user_id'   => 0,
				'page'      => 1,
				'per_page'  => 20,
			)
		);

		$table = esc_sql( $this->table() );
		$where = array( "a.status != 'in_progress'" );

		foreach ( array( 'quiz_id', 'course_id', 'user_id' ) as $column ) {
			if ( ! empty( $args[ $column ] ) ) {
				$where[] = $wpdb->prepare( "a.{$column} = %d", (int) $args[ $column ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column from a fixed whitelist.
			}
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset    = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} a WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments are individually prepared; table and column names come from fixed whitelists.
		$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email, p.post_title AS quiz_title FROM {$table} a INNER JOIN {$wpdb->users} u ON u.ID = a.user_id LEFT JOIN {$wpdb->posts} p ON p.ID = a.quiz_id WHERE {$where_sql} ORDER BY a.id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
