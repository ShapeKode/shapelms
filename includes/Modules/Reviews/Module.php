<?php
/**
 * Reviews module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Reviews;

use ShapeLMS\Core\Modules\AbstractModule;

/**
 * Course reviews and ratings stored as WordPress comments (type shapelms_review).
 */
class Module extends AbstractModule {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'reviews';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Reviews & ratings', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Students can rate and review courses they are enrolled in.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! shapelms_get_setting( 'course', 'enable_reviews', true ) ) {
			return;
		}

		add_filter( 'shapelms_course_rating', array( ReviewService::class, 'rating_filter' ), 10, 2 );
		add_action( 'shapelms_single_course_after', array( $this, 'render' ), 20, 2 );
		add_filter( 'comments_clauses', array( $this, 'hide_from_comment_queries' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'hide_from_feeds' ) );
		add_filter( 'wp_count_comments', array( $this, 'count_comments' ), 10, 2 );
	}

	/**
	 * Renders the reviews section on the single course page.
	 *
	 * @param int   $course_id Course id.
	 * @param array $course    Course data.
	 * @return void
	 */
	public function render( $course_id, $course ) {
		shapelms_get_template(
			'parts/reviews.php',
			array(
				'course_id' => $course_id,
				'course'    => $course,
			)
		);
	}

	/**
	 * Keeps reviews out of normal comment queries (recent comments widget, admin "all comments").
	 *
	 * @param array             $clauses Clauses.
	 * @param \WP_Comment_Query $query   Query.
	 * @return array
	 */
	public function hide_from_comment_queries( $clauses, $query ) {
		$types = array_filter( (array) ( $query->query_vars['type'] ?? '' ) );

		if ( in_array( ReviewService::TYPE, $types, true ) ) {
			return $clauses;
		}

		if ( empty( $types ) || in_array( 'all', $types, true ) ) {
			global $wpdb;
			$clauses['where'] .= $wpdb->prepare( ' AND comment_type != %s', ReviewService::TYPE );
		}

		return $clauses;
	}

	/**
	 * Hides reviews from comment feeds.
	 *
	 * @param string $where Where.
	 * @return string
	 */
	public function hide_from_feeds( $where ) {
		global $wpdb;

		return $where . $wpdb->prepare( ' AND comment_type != %s', ReviewService::TYPE );
	}

	/**
	 * Leaves comment counts alone (reviews have their own count).
	 *
	 * @param array|object $counts  Counts.
	 * @param int          $post_id Post id.
	 * @return array|object
	 */
	public function count_comments( $counts, $post_id ) {
		return $counts;
	}
}
