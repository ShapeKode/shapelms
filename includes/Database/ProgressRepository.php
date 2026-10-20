<?php
/**
 * Progress repository.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Database;

/**
 * Data access for the progress table.
 */
class ProgressRepository extends Repository {

	/**
	 * Table.
	 *
	 * @var string
	 */
	protected $table = 'progress';

	/**
	 * Columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'id', 'user_id', 'course_id', 'item_id', 'item_type', 'status', 'time_spent', 'started_at', 'completed_at' );

	/**
	 * Formats.
	 *
	 * @var array<string, string>
	 */
	protected $formats = array(
		'id'           => '%d',
		'user_id'      => '%d',
		'course_id'    => '%d',
		'item_id'      => '%d',
		'item_type'    => '%s',
		'status'       => '%s',
		'time_spent'   => '%d',
		'started_at'   => '%s',
		'completed_at' => '%s',
	);

	/**
	 * Returns the progress row for an item.
	 *
	 * @param int $user_id User id.
	 * @param int $item_id Item id.
	 * @return object|null
	 */
	public function get( $user_id, $item_id ) {
		return $this->find_by(
			array(
				'user_id' => (int) $user_id,
				'item_id' => (int) $item_id,
			)
		);
	}

	/**
	 * Returns item_id => status for a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return array<int, string>
	 */
	public function statuses_for_course( $user_id, $course_id ) {
		$rows = $this->where(
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->item_id ] = (string) $row->status;
		}

		return $map;
	}

	/**
	 * Returns completed item ids for a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	public function completed_item_ids( $user_id, $course_id ) {
		$rows = $this->where(
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
				'status'    => 'completed',
			)
		);

		return array_map( 'intval', wp_list_pluck( $rows, 'item_id' ) );
	}

	/**
	 * Returns the most recently touched item for a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return int Item id or 0.
	 */
	public function last_item( $user_id, $course_id ) {
		$rows = $this->where(
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
			),
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
				'limit'   => 1,
			)
		);

		return $rows ? (int) $rows[0]->item_id : 0;
	}

	/**
	 * Deletes all progress for a user in a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return int
	 */
	public function reset( $user_id, $course_id ) {
		return $this->delete_where(
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
			)
		);
	}
}
