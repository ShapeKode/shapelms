<?php
/**
 * Post types.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Content;

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Settings;

/**
 * Registers course, topic, lesson, quiz and question post types.
 */
class PostTypes {

	const COURSE   = 'shapelms_course';
	const TOPIC    = 'shapelms_topic';
	const LESSON   = 'shapelms_lesson';
	const QUIZ     = 'shapelms_quiz';
	const QUESTION = 'shapelms_question';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		self::register_static();

		add_filter( 'post_type_link', array( $this, 'item_permalink' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'cascade_delete' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 99 );
	}

	/**
	 * Registers the post types. Static so activation can call it.
	 *
	 * @return void
	 */
	public static function register_static() {
		$slug = sanitize_title( (string) Settings::get( 'general', 'course_slug', 'courses' ) );
		$slug = $slug ? $slug : 'courses';

		$caps = array(
			'edit_post'              => Capabilities::EDIT_COURSES,
			'read_post'              => 'read',
			'delete_post'            => Capabilities::EDIT_COURSES,
			'edit_posts'             => Capabilities::EDIT_COURSES,
			'edit_others_posts'      => Capabilities::MANAGE,
			'delete_posts'           => Capabilities::EDIT_COURSES,
			'delete_others_posts'    => Capabilities::MANAGE,
			'publish_posts'          => Capabilities::EDIT_COURSES,
			'read_private_posts'     => Capabilities::EDIT_COURSES,
			'create_posts'           => Capabilities::EDIT_COURSES,
			'delete_private_posts'   => Capabilities::MANAGE,
			'delete_published_posts' => Capabilities::EDIT_COURSES,
			'edit_private_posts'     => Capabilities::MANAGE,
			'edit_published_posts'   => Capabilities::EDIT_COURSES,
		);

		register_post_type(
			self::COURSE,
			array(
				'labels'          => array(
					'name'               => _x( 'Courses', 'post type general name', 'shapelms' ),
					'singular_name'      => _x( 'Course', 'post type singular name', 'shapelms' ),
					'add_new'            => __( 'Add New', 'shapelms' ),
					'add_new_item'       => __( 'Add New Course', 'shapelms' ),
					'edit_item'          => __( 'Edit Course', 'shapelms' ),
					'new_item'           => __( 'New Course', 'shapelms' ),
					'view_item'          => __( 'View Course', 'shapelms' ),
					'search_items'       => __( 'Search Courses', 'shapelms' ),
					'not_found'          => __( 'No courses found', 'shapelms' ),
					'not_found_in_trash' => __( 'No courses found in Trash', 'shapelms' ),
					'all_items'          => __( 'All Courses', 'shapelms' ),
					'archives'           => __( 'Course Archives', 'shapelms' ),
				),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => true,
				'rest_base'       => 'shapelms-courses',
				'menu_icon'       => 'dashicons-welcome-learn-more',
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
				'has_archive'     => $slug,
				'rewrite'         => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capability_type' => array( 'shapelms_course', 'shapelms_courses' ),
				'capabilities'    => $caps,
				'map_meta_cap'    => false,
				'taxonomies'      => array( Taxonomies::CATEGORY, Taxonomies::TAG ),
			)
		);

		$child_args = array(
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
			'hierarchical'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => array( 'shapelms_course', 'shapelms_courses' ),
			'capabilities'        => $caps,
			'map_meta_cap'        => false,
		);

		register_post_type(
			self::TOPIC,
			array_merge(
				$child_args,
				array(
					'labels'             => array(
						'name'          => __( 'Topics', 'shapelms' ),
						'singular_name' => __( 'Topic', 'shapelms' ),
					),
					'publicly_queryable' => false,
					'show_in_rest'       => true,
					'rest_base'          => 'shapelms-topics',
				)
			)
		);

		register_post_type(
			self::LESSON,
			array_merge(
				$child_args,
				array(
					'labels'    => array(
						'name'          => __( 'Lessons', 'shapelms' ),
						'singular_name' => __( 'Lesson', 'shapelms' ),
					),
					'rest_base' => 'shapelms-lessons',
					'rewrite'   => array(
						'slug'       => $slug . '/%shapelms_course%/lessons',
						'with_front' => false,
					),
					'query_var' => 'shapelms_lesson',
				)
			)
		);

		register_post_type(
			self::QUIZ,
			array_merge(
				$child_args,
				array(
					'labels'    => array(
						'name'          => __( 'Quizzes', 'shapelms' ),
						'singular_name' => __( 'Quiz', 'shapelms' ),
					),
					'rest_base' => 'shapelms-quizzes',
					'rewrite'   => array(
						'slug'       => $slug . '/%shapelms_course%/quizzes',
						'with_front' => false,
					),
					'query_var' => 'shapelms_quiz',
				)
			)
		);

		register_post_type(
			self::QUESTION,
			array_merge(
				$child_args,
				array(
					'labels'             => array(
						'name'          => __( 'Questions', 'shapelms' ),
						'singular_name' => __( 'Question', 'shapelms' ),
					),
					'publicly_queryable' => false,
					'rest_base'          => 'shapelms-questions',
				)
			)
		);
	}

	/**
	 * Replaces %shapelms_course% in lesson and quiz permalinks.
	 *
	 * @param string   $permalink Permalink.
	 * @param \WP_Post $post      Post.
	 * @return string
	 */
	public function item_permalink( $permalink, $post ) {
		if ( ! in_array( $post->post_type, array( self::LESSON, self::QUIZ ), true ) ) {
			return $permalink;
		}

		$course_id = Curriculum::course_id_for( $post->ID );
		$course    = $course_id ? get_post( $course_id ) : null;
		$slug      = $course ? $course->post_name : 'course';

		return str_replace( '%shapelms_course%', $slug, $permalink );
	}

	/**
	 * Deletes topics, lessons, quizzes and questions when their parent is deleted permanently.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function cascade_delete( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, array( self::COURSE, self::TOPIC, self::QUIZ ), true ) ) {
			return;
		}

		$children = get_posts(
			array(
				'post_parent'   => $post_id,
				'post_type'     => array( self::TOPIC, self::LESSON, self::QUIZ, self::QUESTION ),
				'post_status'   => 'any',
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);

		foreach ( $children as $child_id ) {
			wp_delete_post( (int) $child_id, true );
		}
	}

	/**
	 * Uses the classic container for courses so the React builder can take over the screen.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type.
	 * @return bool
	 */
	public function block_editor( $use_block_editor, $post_type ) {
		if ( self::COURSE === $post_type ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Keeps the course edit screen clean; the builder handles everything.
	 *
	 * @return void
	 */
	public function remove_meta_boxes() {
		foreach ( array( 'postexcerpt', 'postcustom', 'commentstatusdiv', 'commentsdiv', 'slugdiv', 'authordiv', 'revisionsdiv', 'trackbacksdiv' ) as $box ) {
			remove_meta_box( $box, self::COURSE, 'normal' );
		}
	}

	/**
	 * All LMS post types.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::COURSE, self::TOPIC, self::LESSON, self::QUIZ, self::QUESTION );
	}
}
