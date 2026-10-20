<?php
/**
 * Q&A module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\QA;

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\InstructorService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Per-course questions and answers stored as comments (type shapelms_qa) on the course.
 */
class Module extends AbstractModule {

	const TYPE = 'shapelms_qa';

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'qa';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Course Q&A', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Students ask questions on lessons; instructors and other students answer.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! shapelms_get_setting( 'course', 'enable_qa', true ) ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'shapelms_learn_after', array( $this, 'render' ), 10, 2 );
		add_filter( 'comments_clauses', array( $this, 'hide_from_comment_queries' ), 10, 2 );
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tab' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			'shapelms/v1',
			'/qa',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'item_id'   => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'item_id'   => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'parent'    => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'content'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/qa/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Lists questions and answers for a course or item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function list( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );
		$item_id   = (int) $request->get_param( 'item_id' );

		if ( ! shapelms()->get( EnrollmentService::class )->can_access( get_current_user_id(), $course_id ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not enrolled in this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		return self::threads( $course_id, $item_id );
	}

	/**
	 * Threads for a course (optionally one item).
	 *
	 * @param int $course_id Course id.
	 * @param int $item_id   Item id (0 = all).
	 * @return array<int, array<string, mixed>>
	 */
	public static function threads( $course_id, $item_id = 0 ) {
		$args = array(
			'post_id' => $course_id,
			'type'    => self::TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		if ( $item_id ) {
			$args['meta_key']   = 'shapelms_item_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $item_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		$all     = get_comments( $args );
		$threads = array();
		$answers = array();

		foreach ( (array) $all as $comment ) {
			$data = self::to_array( $comment, $course_id );
			if ( (int) $comment->comment_parent ) {
				$answers[ (int) $comment->comment_parent ][] = $data;
			} else {
				$threads[ (int) $comment->comment_ID ] = $data;
			}
		}

		$list = array();
		foreach ( $threads as $id => $thread ) {
			$thread['answers'] = isset( $answers[ $id ] ) ? array_reverse( $answers[ $id ] ) : array();
			$list[]            = $thread;
		}

		return $list;
	}

	/**
	 * Comment to array.
	 *
	 * @param \WP_Comment $comment   Comment.
	 * @param int         $course_id Course id.
	 * @return array<string, mixed>
	 */
	private static function to_array( $comment, $course_id ) {
		$user_id = (int) $comment->user_id;
		$item_id = (int) get_comment_meta( (int) $comment->comment_ID, 'shapelms_item_id', true );

		return array(
			'id'            => (int) $comment->comment_ID,
			'user_id'       => $user_id,
			'name'          => $comment->comment_author,
			'avatar'        => get_avatar_url( $user_id, array( 'size' => 72 ) ),
			'is_instructor' => InstructorService::is_course_instructor( $user_id, $course_id ),
			'content'       => $comment->comment_content,
			'date'          => get_comment_date( '', $comment ),
			'item_id'       => $item_id,
			'item_title'    => $item_id ? get_the_title( $item_id ) : '',
			'item_url'      => $item_id ? get_permalink( $item_id ) : '',
			'can_delete'    => get_current_user_id() === $user_id || current_user_can( 'shapelms_edit_course', $course_id ),
		);
	}

	/**
	 * Creates a question or answer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );
		$item_id   = (int) $request->get_param( 'item_id' );
		$parent    = (int) $request->get_param( 'parent' );
		$user_id   = get_current_user_id();

		if ( ! shapelms()->get( EnrollmentService::class )->can_access( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not enrolled in this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		if ( $item_id && Curriculum::course_id_for( $item_id ) !== $course_id ) {
			$item_id = 0;
		}

		if ( $parent ) {
			$parent_comment = get_comment( $parent );
			if ( ! $parent_comment || self::TYPE !== $parent_comment->comment_type || (int) $parent_comment->comment_post_ID !== $course_id ) {
				return new \WP_Error( 'shapelms_invalid_parent', __( 'Question not found.', 'shapelms' ), array( 'status' => 404 ) );
			}
			$item_id = (int) get_comment_meta( $parent, 'shapelms_item_id', true );
		}

		$user       = get_userdata( $user_id );
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $course_id,
				'comment_parent'       => $parent,
				'comment_author'       => $user ? $user->display_name : '',
				'comment_author_email' => $user ? $user->user_email : '',
				'comment_content'      => $request->get_param( 'content' ),
				'comment_type'         => self::TYPE,
				'user_id'              => $user_id,
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			return new \WP_Error( 'shapelms_qa_failed', __( 'Could not post your message.', 'shapelms' ) );
		}

		if ( $item_id ) {
			update_comment_meta( $comment_id, 'shapelms_item_id', $item_id );
		}

		/**
		 * Fires when a Q&A message is posted.
		 *
		 * @param int $comment_id Comment id.
		 * @param int $course_id  Course id.
		 * @param int $parent     Parent id (0 = new question).
		 */
		do_action( 'shapelms_qa_posted', $comment_id, $course_id, $parent );

		return self::to_array( get_comment( $comment_id ), $course_id );
	}

	/**
	 * Deletes a message (author or instructor).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}|\WP_Error
	 */
	public function delete( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if ( ! $comment || self::TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		$course_id = (int) $comment->comment_post_ID;

		if ( get_current_user_id() !== (int) $comment->user_id && ! current_user_can( 'shapelms_edit_course', $course_id ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not allowed to do that.', 'shapelms' ), array( 'status' => 403 ) );
		}

		return array( 'deleted' => (bool) wp_delete_comment( (int) $comment->comment_ID, true ) );
	}

	/**
	 * Renders the Q&A panel under a lesson.
	 *
	 * @param int $item_id   Item id.
	 * @param int $course_id Course id.
	 * @return void
	 */
	public function render( $item_id, $course_id ) {
		if ( ! is_user_logged_in() || ! shapelms()->get( EnrollmentService::class )->can_access( get_current_user_id(), $course_id ) ) {
			return;
		}

		shapelms_get_template(
			'parts/qa.php',
			array(
				'item_id'   => $item_id,
				'course_id' => $course_id,
				'threads'   => self::threads( $course_id, $item_id ),
			)
		);
	}

	/**
	 * Keeps Q&A out of normal comment queries.
	 *
	 * @param array             $clauses Clauses.
	 * @param \WP_Comment_Query $query   Query.
	 * @return array
	 */
	public function hide_from_comment_queries( $clauses, $query ) {
		$types = array_filter( (array) ( $query->query_vars['type'] ?? '' ) );

		if ( in_array( self::TYPE, $types, true ) ) {
			return $clauses;
		}

		if ( empty( $types ) || in_array( 'all', $types, true ) ) {
			global $wpdb;
			$clauses['where'] .= $wpdb->prepare( ' AND comment_type != %s', self::TYPE );
		}

		return $clauses;
	}

	/**
	 * Dashboard tab for instructors.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tab( $tabs ) {
		$tabs['qa'] = array(
			'label'    => __( 'Q&A', 'shapelms' ),
			'template' => 'dashboard/qa.php',
			'section'  => 'instructor',
			'order'    => 30,
		);

		return $tabs;
	}
}
