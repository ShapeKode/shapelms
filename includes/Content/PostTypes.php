<?php
/**
 * Post types.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		$slug = self::course_slug();

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
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-welcome-learn-more',
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
				'has_archive'     => $slug,
				'rewrite'         => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'capability_type' => array( 'shapelms_course', 'shapelms_courses' ),
				'map_meta_cap'    => true,
				'taxonomies'      => array( Taxonomies::CATEGORY, Taxonomies::TAG ),
			)
		);

		$child_args = array(
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
			'hierarchical'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => array( 'shapelms_course', 'shapelms_courses' ),
			'map_meta_cap'        => true,
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
		if ( ! in_array( $post->post_type, Curriculum::item_post_types(), true ) ) {
			return $permalink;
		}

		$course_id = Curriculum::course_id_for( $post->ID );
		$course    = $course_id ? get_post( $course_id ) : null;
		$slug      = $course ? $course->post_name : '';

		if ( ! $slug && $course ) {
			$slug = sanitize_title( $course->post_title );
		}

		return str_replace( '%shapelms_course%', $slug ? $slug : 'course', $permalink );
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

		if ( self::COURSE === $post->post_type ) {
			self::purge_course_data( (int) $post_id );
		}

		$children = get_posts(
			array(
				'post_parent'   => $post_id,
				'post_type'     => array_merge( array( self::TOPIC, self::QUESTION ), Curriculum::item_post_types() ),
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
	 * Removes enrollments, progress and quiz attempts of a permanently deleted course.
	 *
	 * @param int $course_id Course id.
	 * @return void
	 */
	public static function purge_course_data( $course_id ) {
		$where = array( 'course_id' => (int) $course_id );

		shapelms()->get( \ShapeLMS\Database\ProgressRepository::class )->delete_where( $where );
		shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class )->delete_where( $where );

		$attempts = shapelms()->get( \ShapeLMS\Database\AttemptRepository::class );
		foreach ( $attempts->where( $where ) as $attempt ) {
			$attempts->delete( (int) $attempt->id );
		}

		/**
		 * Fires after a course's learning data has been purged.
		 *
		 * @param int $course_id Course id.
		 */
		do_action( 'shapelms_course_data_purged', (int) $course_id );
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
		return array_values( array_unique( array_merge( array( self::COURSE, self::TOPIC, self::QUESTION ), Curriculum::item_post_types() ) ) );
	}

	/**
	 * Course URL base ("courses" by default). Item URLs live under it: /courses/{course}/{items}/{slug}/.
	 *
	 * @return string
	 */
	public static function course_slug() {
		$slug = sanitize_title( (string) Settings::get( 'general', 'course_slug', 'courses' ) );

		return $slug ? $slug : 'courses';
	}
}
