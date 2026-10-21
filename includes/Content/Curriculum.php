<?php
/**
 * Curriculum helpers.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the course → topic → item tree. Results are cached per request.
 */
class Curriculum {

	/**
	 * Per-request cache of course trees.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private static $trees = array();

	/**
	 * Returns the course id for any item (topic, lesson, quiz, question).
	 *
	 * @param int $post_id Post id.
	 * @return int
	 */
	public static function course_id_for( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return 0;
		}

		if ( PostTypes::COURSE === $post->post_type ) {
			return (int) $post->ID;
		}

		$depth = 0;
		while ( $post && PostTypes::COURSE !== $post->post_type && $depth < 5 ) {
			$post = $post->post_parent ? get_post( $post->post_parent ) : null;
			++$depth;
		}

		return ( $post && PostTypes::COURSE === $post->post_type ) ? (int) $post->ID : 0;
	}

	/**
	 * Returns the curriculum tree: list of topics, each with ordered items.
	 *
	 * @param int  $course_id        Course id.
	 * @param bool $include_drafts   Include non-published items (for the builder).
	 * @return array<int, array<string, mixed>>
	 */
	public static function tree( $course_id, $include_drafts = false ) {
		$course_id = (int) $course_id;
		$cache_key = $course_id . ( $include_drafts ? ':all' : ':pub' );

		if ( isset( self::$trees[ $cache_key ] ) ) {
			return self::$trees[ $cache_key ];
		}

		$status = $include_drafts ? array( 'publish', 'draft', 'pending', 'private' ) : array( 'publish' );

		$topics = get_posts(
			array(
				'post_type'     => PostTypes::TOPIC,
				'post_parent'   => $course_id,
				'post_status'   => $status,
				'numberposts'   => -1,
				'orderby'       => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'no_found_rows' => true,
			)
		);

		$topic_ids = wp_list_pluck( $topics, 'ID' );
		$items     = array();

		if ( $topic_ids ) {
			$items = get_posts(
				array(
					'post_type'       => array( PostTypes::LESSON, PostTypes::QUIZ ),
					'post_parent__in' => array_map( 'intval', $topic_ids ),
					'post_status'     => $status,
					'numberposts'     => -1,
					'orderby'         => array(
						'menu_order' => 'ASC',
						'ID'         => 'ASC',
					),
					'no_found_rows'   => true,
				)
			);
		}

		$by_topic = array();
		foreach ( $items as $item ) {
			$by_topic[ (int) $item->post_parent ][] = self::item_to_array( $item );
		}

		$tree = array();
		foreach ( $topics as $topic ) {
			$tree[] = array(
				'id'      => (int) $topic->ID,
				'title'   => $topic->post_title,
				'summary' => $topic->post_content,
				'status'  => $topic->post_status,
				'items'   => isset( $by_topic[ (int) $topic->ID ] ) ? $by_topic[ (int) $topic->ID ] : array(),
			);
		}

		self::$trees[ $cache_key ] = $tree;

		return $tree;
	}

	/**
	 * Flat ordered list of items (lessons and quizzes).
	 *
	 * @param int  $course_id      Course id.
	 * @param bool $include_drafts Include drafts.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items( $course_id, $include_drafts = false ) {
		$flat = array();

		foreach ( self::tree( $course_id, $include_drafts ) as $topic ) {
			foreach ( $topic['items'] as $item ) {
				$item['topic_id'] = $topic['id'];
				$flat[]           = $item;
			}
		}

		return $flat;
	}

	/**
	 * Returns item ids in order.
	 *
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	public static function item_ids( $course_id ) {
		return array_map( 'intval', wp_list_pluck( self::items( $course_id ), 'id' ) );
	}

	/**
	 * Previous and next item ids around the given one.
	 *
	 * @param int $course_id Course id.
	 * @param int $item_id   Current item.
	 * @return array{prev: int, next: int}
	 */
	public static function neighbors( $course_id, $item_id ) {
		$ids   = self::item_ids( $course_id );
		$index = array_search( (int) $item_id, $ids, true );

		if ( false === $index ) {
			return array(
				'prev' => 0,
				'next' => 0,
			);
		}

		return array(
			'prev' => $index > 0 ? $ids[ $index - 1 ] : 0,
			'next' => isset( $ids[ $index + 1 ] ) ? $ids[ $index + 1 ] : 0,
		);
	}

	/**
	 * First item id of a course.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	public static function first_item( $course_id ) {
		$ids = self::item_ids( $course_id );

		return $ids ? $ids[0] : 0;
	}

	/**
	 * Counts lessons and quizzes.
	 *
	 * @param int $course_id Course id.
	 * @return array{lessons: int, quizzes: int, topics: int}
	 */
	public static function counts( $course_id ) {
		$tree    = self::tree( $course_id );
		$lessons = 0;
		$quizzes = 0;

		foreach ( $tree as $topic ) {
			foreach ( $topic['items'] as $item ) {
				if ( 'quiz' === $item['type'] ) {
					++$quizzes;
				} else {
					++$lessons;
				}
			}
		}

		return array(
			'lessons' => $lessons,
			'quizzes' => $quizzes,
			'topics'  => count( $tree ),
		);
	}

	/**
	 * Total video duration in seconds.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	public static function duration( $course_id ) {
		$explicit = (int) get_post_meta( $course_id, '_shapelms_duration', true );

		if ( $explicit > 0 ) {
			return $explicit;
		}

		$total = 0;
		foreach ( self::items( $course_id ) as $item ) {
			$total += isset( $item['duration'] ) ? (int) $item['duration'] : 0;
		}

		return $total;
	}

	/**
	 * Converts an item post to an array.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function item_to_array( $post ) {
		$is_quiz = PostTypes::QUIZ === $post->post_type;

		$data = array(
			'id'       => (int) $post->ID,
			'type'     => $is_quiz ? 'quiz' : 'lesson',
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'url'      => get_permalink( $post ),
			'preview'  => ! $is_quiz && (bool) get_post_meta( $post->ID, '_shapelms_preview', true ),
			'duration' => $is_quiz ? 0 : (int) get_post_meta( $post->ID, '_shapelms_video_duration', true ),
		);

		if ( ! $is_quiz ) {
			$data['video_source'] = (string) get_post_meta( $post->ID, '_shapelms_video_source', true );
		}

		return $data;
	}

	/**
	 * Clears the per-request cache for a course.
	 *
	 * @param int $course_id Course id (0 = all).
	 * @return void
	 */
	public static function flush( $course_id = 0 ) {
		if ( ! $course_id ) {
			self::$trees = array();
			return;
		}

		unset( self::$trees[ $course_id . ':all' ], self::$trees[ $course_id . ':pub' ] );
	}
}
