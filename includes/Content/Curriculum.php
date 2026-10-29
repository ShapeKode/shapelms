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
	 * Cached item types (after init).
	 *
	 * @var array<string, array{post_type: string, label: string, icon: string, add_label: string, edit_label: string}>|null
	 */
	private static $types = null;

	/**
	 * Curriculum item types: type => { post_type, label, icon }. Lessons and quizzes are built in;
	 * add-ons can register more (for example assignments) on the shapelms_curriculum_item_types filter.
	 *
	 * @return array<string, array{post_type: string, label: string, icon: string, add_label: string, edit_label: string}>
	 */
	public static function item_types() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$builtin = array(
			'lesson' => array(
				'post_type'  => PostTypes::LESSON,
				'label'      => __( 'Lesson', 'shapelms' ),
				'icon'       => 'L',
				'add_label'  => __( '+ Lesson', 'shapelms' ),
				'edit_label' => __( 'Edit lesson', 'shapelms' ),
			),
			'quiz'   => array(
				'post_type'  => PostTypes::QUIZ,
				'label'      => __( 'Quiz', 'shapelms' ),
				'icon'       => 'Q',
				'add_label'  => __( '+ Quiz', 'shapelms' ),
				'edit_label' => __( 'Edit quiz', 'shapelms' ),
			),
		);

		/**
		 * Filters the curriculum item types. Each entry needs a registered post_type; the
		 * built-in lesson and quiz entries cannot be changed.
		 *
		 * @param array $types type => { post_type, label, icon, add_label, edit_label }.
		 */
		$types = (array) apply_filters( 'shapelms_curriculum_item_types', $builtin );
		$clean = $builtin;

		foreach ( $types as $type => $config ) {
			$type = sanitize_key( (string) $type );
			if ( isset( $builtin[ $type ] ) || ! is_array( $config ) || empty( $config['post_type'] ) || ! post_type_exists( (string) $config['post_type'] ) ) {
				continue;
			}
			$label          = isset( $config['label'] ) ? (string) $config['label'] : $type;
			$clean[ $type ] = array(
				'post_type'  => (string) $config['post_type'],
				'label'      => $label,
				'icon'       => isset( $config['icon'] ) ? mb_substr( (string) $config['icon'], 0, 2 ) : mb_substr( $label, 0, 1 ),
				'add_label'  => isset( $config['add_label'] ) ? (string) $config['add_label'] : '+ ' . $label,
				'edit_label' => isset( $config['edit_label'] ) ? (string) $config['edit_label'] : $label,
			);
		}

		// Post types are registered on init; only remember the list once they all exist.
		if ( did_action( 'init' ) ) {
			self::$types = $clean;
		}

		return $clean;
	}

	/**
	 * Post types of all curriculum items.
	 *
	 * @return string[]
	 */
	public static function item_post_types() {
		return array_values( wp_list_pluck( self::item_types(), 'post_type' ) );
	}

	/**
	 * Item type ("lesson", "quiz", ...) of a post type, or '' if it is not a curriculum item.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function type_of( $post_type ) {
		foreach ( self::item_types() as $type => $config ) {
			if ( $config['post_type'] === $post_type ) {
				return (string) $type;
			}
		}

		return '';
	}

	/**
	 * Post type for an item type, or '' if unknown.
	 *
	 * @param string $type Item type.
	 * @return string
	 */
	public static function post_type_for( $type ) {
		$types = self::item_types();

		return isset( $types[ $type ] ) ? $types[ $type ]['post_type'] : '';
	}

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
					'post_type'       => self::item_post_types(),
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
				} elseif ( 'lesson' === $item['type'] ) {
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
		$type      = self::type_of( $post->post_type );
		$type      = $type ? $type : 'lesson';
		$is_lesson = 'lesson' === $type;

		$data = array(
			'id'       => (int) $post->ID,
			'type'     => $type,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'url'      => get_permalink( $post ),
			'preview'  => $is_lesson && (bool) get_post_meta( $post->ID, '_shapelms_preview', true ),
			'duration' => $is_lesson ? (int) get_post_meta( $post->ID, '_shapelms_video_duration', true ) : 0,
		);

		if ( $is_lesson ) {
			$data['video_source'] = (string) get_post_meta( $post->ID, '_shapelms_video_source', true );
		}

		/**
		 * Filters the data of a lesson or quiz in the curriculum (course page, learn page, builder list).
		 *
		 * @param array    $data Item data.
		 * @param \WP_Post $post Lesson or quiz post.
		 */
		return (array) apply_filters( 'shapelms_curriculum_item', $data, $post );
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
