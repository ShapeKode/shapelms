<?php
/**
 * Q&A admin screen: list, filters, counts, thread view, replies and bulk delete.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\QA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Services\InstructorService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST routes under shapelms/v1/qa/admin. Managers see every course; instructors see
 * the courses they can edit.
 */
class Admin {

	/**
	 * Sortable columns => SQL.
	 */
	const SORTABLE = array(
		'date'    => 'q.comment_date_gmt',
		'replies' => 'replies',
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'shapelms_admin_screens', array( $this, 'screen' ) );
		add_filter( 'shapelms_admin_preload', array( $this, 'preload' ) );
	}

	/**
	 * First page and the course filter rendered into the page, so the screen shows at once.
	 * The queries must match the screen's initial ones in QA.js.
	 *
	 * @param array $lists Preloaded lists by screen.
	 * @return array
	 */
	public function preload( $lists ) {
		$lists['qa'] = array(
			array(
				'qa/admin',
				array(
					'course_id' => 0,
					'status'    => '',
					'search'    => '',
				),
			),
			array(
				'courses',
				array(
					'status'   => 'any',
					'per_page' => 100,
				),
				false,
			),
		);

		return $lists;
	}

	/**
	 * Adds the Q&A screen to the ShapeLMS menu.
	 *
	 * @param array $screens Screens.
	 * @return array
	 */
	public function screen( $screens ) {
		$screens['qa'] = array( __( 'Q&A', 'shapelms' ), Capabilities::EDIT_COURSES );

		return $screens;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function routes() {
		$permission = static function () {
			return current_user_can( Capabilities::EDIT_COURSES );
		};
		$id_arg     = array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		);

		register_rest_route(
			'shapelms/v1',
			'/qa/admin',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_questions' ),
				'permission_callback' => $permission,
				'args'                => array(
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'course_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'status'    => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'answered', 'unanswered' ),
					),
					'search'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'   => array(
						'type'    => 'string',
						'default' => 'date',
						'enum'    => array_keys( self::SORTABLE ),
					),
					'order'     => array(
						'type'    => 'string',
						'default' => 'DESC',
						'enum'    => array( 'ASC', 'DESC' ),
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/qa/admin/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_thread' ),
				'permission_callback' => $permission,
				'args'                => array( 'id' => $id_arg ),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/qa/admin/(?P<id>\d+)/reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reply' ),
				'permission_callback' => $permission,
				'args'                => array(
					'id'      => $id_arg,
					'content' => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 5000,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/qa/admin/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => $permission,
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'delete' ),
					),
					// Question or reply ids.
					'ids'    => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
						'minItems' => 1,
						'maxItems' => 100,
					),
				),
			)
		);
	}

	/**
	 * Courses the current user may see here; null = all (managers).
	 *
	 * @return int[]|null
	 */
	private static function visible_courses() {
		if ( current_user_can( Capabilities::MANAGE ) ) {
			return null;
		}

		return array_map( 'intval', InstructorService::course_ids( get_current_user_id() ) );
	}

	/**
	 * WHERE clauses for top-level questions (alias q), without the status filter.
	 *
	 * @param int    $course_id Course filter (0 = all).
	 * @param string $search    Search text.
	 * @return string[]|null Null when the user can see no course at all.
	 */
	private static function base_where( $course_id, $search ) {
		global $wpdb;

		$where = array(
			$wpdb->prepare( 'q.comment_type = %s', Module::TYPE ),
			'q.comment_parent = 0',
			"q.comment_approved = '1'",
		);

		$courses = self::visible_courses();

		if ( $course_id ) {
			if ( null !== $courses && ! in_array( (int) $course_id, $courses, true ) ) {
				return null;
			}
			$where[] = $wpdb->prepare( 'q.comment_post_ID = %d', $course_id );
		} elseif ( null !== $courses ) {
			if ( ! $courses ) {
				return null;
			}
			$where[] = 'q.comment_post_ID IN (' . implode( ',', array_map( 'intval', $courses ) ) . ')';
		}

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = $wpdb->prepare( '(q.comment_content LIKE %s OR q.comment_author LIKE %s OR q.comment_author_email LIKE %s)', $like, $like, $like );
		}

		return $where;
	}

	/**
	 * SQL: whether someone other than the asker replied to question q.
	 *
	 * @return string
	 */
	private static function answered_sql() {
		global $wpdb;

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$wpdb->comments} r WHERE r.comment_parent = q.comment_ID AND r.comment_type = %s AND r.comment_approved = '1' AND r.user_id != q.user_id)",
			Module::TYPE
		);
	}

	/**
	 * Paginated questions with counts per status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_questions( WP_REST_Request $request ) {
		global $wpdb;

		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$where    = self::base_where( (int) $request->get_param( 'course_id' ), (string) $request->get_param( 'search' ) );
		$counts   = array(
			'all'        => 0,
			'unanswered' => 0,
			'answered'   => 0,
		);

		if ( null === $where ) {
			return self::response( array(), 0, $per_page, $counts );
		}

		$answered  = self::answered_sql();
		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fragments are prepared above.
		$row                    = $wpdb->get_row( "SELECT COUNT(*) AS all_count, COALESCE(SUM({$answered}),0) AS answered FROM {$wpdb->comments} q WHERE {$where_sql}" );
		$counts['all']          = (int) ( $row->all_count ?? 0 );
		$counts['answered']     = (int) ( $row->answered ?? 0 );
		$counts['unanswered']   = $counts['all'] - $counts['answered'];
		$status                 = (string) $request->get_param( 'status' );

		if ( 'answered' === $status ) {
			$where[] = $answered;
		} elseif ( 'unanswered' === $status ) {
			$where[] = "NOT {$answered}";
		}

		$where_sql = implode( ' AND ', $where );
		$orderby   = self::SORTABLE[ $request->get_param( 'orderby' ) ] ?? self::SORTABLE['date'];
		$order     = 'ASC' === $request->get_param( 'order' ) ? 'ASC' : 'DESC';
		$replies   = $wpdb->prepare( "(SELECT COUNT(*) FROM {$wpdb->comments} r WHERE r.comment_parent = q.comment_ID AND r.comment_type = %s AND r.comment_approved = '1')", Module::TYPE );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fragments are prepared above.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} q WHERE {$where_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fragments are prepared above; sort column and direction come from whitelists.
		$sql   = $wpdb->prepare( "SELECT q.*, {$replies} AS replies, {$answered} AS answered FROM {$wpdb->comments} q WHERE {$where_sql} ORDER BY {$orderby} {$order}, q.comment_ID {$order} LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared on the line above; a live admin list, not cached.
		$rows  = $wpdb->get_results( $sql );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::question_row( $row );
		}

		return self::response( $items, $total, $per_page, $counts );
	}

	/**
	 * List response with counts.
	 *
	 * @param array $items    Items.
	 * @param int   $total    Total.
	 * @param int   $per_page Per page.
	 * @param array $counts   Counts.
	 * @return \WP_REST_Response
	 */
	private static function response( array $items, $total, $per_page, array $counts ) {
		$pages    = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
		$response = new \WP_REST_Response(
			array(
				'items'  => $items,
				'total'  => (int) $total,
				'pages'  => max( 1, $pages ),
				'counts' => $counts,
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, $pages ) );

		return $response;
	}

	/**
	 * A question for the list.
	 *
	 * @param object $row Comment row with replies and answered.
	 * @return array<string, mixed>
	 */
	private static function question_row( $row ) {
		$course_id = (int) $row->comment_post_ID;
		$item_id   = (int) get_comment_meta( (int) $row->comment_ID, 'shapelms_item_id', true );

		return array(
			'id'           => (int) $row->comment_ID,
			'user_id'      => (int) $row->user_id,
			'name'         => $row->comment_author,
			'email'        => $row->comment_author_email,
			'avatar'       => get_avatar_url( (int) $row->user_id, array( 'size' => 64 ) ),
			'content'      => $row->comment_content,
			'date'         => $row->comment_date_gmt,
			'course_id'    => $course_id,
			'course_title' => self::title( $course_id ),
			'item_id'      => $item_id,
			'item_title'   => $item_id ? self::title( $item_id ) : '',
			'item_url'     => $item_id ? get_permalink( $item_id ) : '',
			'replies'      => (int) $row->replies,
			'answered'     => (bool) (int) $row->answered,
		);
	}

	/**
	 * Plain-text post title (get_the_title() returns HTML entities such as &#8217;).
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function title( $post_id ) {
		return html_entity_decode( get_the_title( (int) $post_id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Loads a question the current user may manage.
	 *
	 * @param int $id Comment id.
	 * @return \WP_Comment|\WP_Error
	 */
	private static function own_question( $id ) {
		$comment = get_comment( (int) $id );

		if ( ! $comment || Module::TYPE !== $comment->comment_type ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Question not found.', 'shapelms' ), array( 'status' => 404 ) );
		}
		if ( (int) $comment->comment_parent ) {
			$comment = get_comment( (int) $comment->comment_parent );
		}
		if ( ! $comment || ! current_user_can( 'shapelms_edit_course', (int) $comment->comment_post_ID ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not allowed to do that.', 'shapelms' ), array( 'status' => 403 ) );
		}

		return $comment;
	}

	/**
	 * A question with its replies, oldest reply first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_thread( WP_REST_Request $request ) {
		$question = self::own_question( (int) $request->get_param( 'id' ) );

		return is_wp_error( $question ) ? $question : self::thread( $question );
	}

	/**
	 * Thread payload.
	 *
	 * @param \WP_Comment $question Question.
	 * @return array<string, mixed>
	 */
	private static function thread( $question ) {
		$course_id = (int) $question->comment_post_ID;
		$replies   = get_comments(
			array(
				'parent'  => (int) $question->comment_ID,
				'type'    => Module::TYPE,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);
		$message   = static function ( $comment ) use ( $course_id ) {
			$user_id = (int) $comment->user_id;
			return array(
				'id'            => (int) $comment->comment_ID,
				'name'          => $comment->comment_author,
				'avatar'        => get_avatar_url( $user_id, array( 'size' => 64 ) ),
				'content'       => $comment->comment_content,
				'date'          => $comment->comment_date_gmt,
				'is_instructor' => InstructorService::is_course_instructor( $user_id, $course_id ) || user_can( $user_id, Capabilities::MANAGE ),
			);
		};
		$item_id   = (int) get_comment_meta( (int) $question->comment_ID, 'shapelms_item_id', true );

		return array_merge(
			$message( $question ),
			array(
				'email'        => $question->comment_author_email,
				'course_id'    => $course_id,
				'course_title' => self::title( $course_id ),
				'item_title'   => $item_id ? self::title( $item_id ) : '',
				'item_url'     => $item_id ? get_permalink( $item_id ) . '#qa' : '',
				'replies'      => array_map( $message, (array) $replies ),
			)
		);
	}

	/**
	 * Replies to a question as the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reply( WP_REST_Request $request ) {
		$question = self::own_question( (int) $request->get_param( 'id' ) );
		$content  = trim( (string) $request->get_param( 'content' ) );

		if ( is_wp_error( $question ) ) {
			return $question;
		}
		if ( '' === $content ) {
			return new \WP_Error( 'shapelms_empty_reply', __( 'Write a reply first.', 'shapelms' ), array( 'status' => 400 ) );
		}

		$user       = wp_get_current_user();
		$course_id  = (int) $question->comment_post_ID;
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $course_id,
				'comment_parent'       => (int) $question->comment_ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => $content,
				'comment_type'         => Module::TYPE,
				'user_id'              => (int) $user->ID,
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			return new \WP_Error( 'shapelms_qa_failed', __( 'Could not post your reply.', 'shapelms' ) );
		}

		$item_id = (int) get_comment_meta( (int) $question->comment_ID, 'shapelms_item_id', true );
		if ( $item_id ) {
			update_comment_meta( $comment_id, 'shapelms_item_id', $item_id );
		}

		/** This action is documented in includes/Modules/QA/Module.php */
		do_action( 'shapelms_qa_posted', $comment_id, $course_id, (int) $question->comment_ID );

		return self::thread( get_comment( (int) $question->comment_ID ) );
	}

	/**
	 * Bulk delete. A question goes with its replies; a reply goes alone.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{done: int, skipped: int}
	 */
	public function bulk( WP_REST_Request $request ) {
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) ) );
		$done = 0;

		foreach ( $ids as $id ) {
			$comment = get_comment( $id );
			if ( ! $comment || Module::TYPE !== $comment->comment_type || is_wp_error( self::own_question( $id ) ) ) {
				continue;
			}
			if ( Module::delete_message( (int) $comment->comment_ID ) ) {
				++$done;
			}
		}

		return array(
			'done'    => $done,
			'skipped' => count( $ids ) - $done,
		);
	}
}
