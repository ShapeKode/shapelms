<?php
/**
 * Announcements admin screen: list, filters, sorting, add/edit and bulk delete.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Announcements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Services\InstructorService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST routes under shapelms/v1/announcements/admin. Managers see every course;
 * instructors see the courses they can edit. Creating uses POST /announcements.
 */
class Admin {

	/**
	 * Sortable columns => WP_Query orderby.
	 */
	const SORTABLE = array(
		'date'  => 'date',
		'title' => 'title',
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
	 * Adds the Announcements screen to the ShapeLMS menu.
	 *
	 * @param array $screens Screens.
	 * @return array
	 */
	public function screen( $screens ) {
		$screens['announcements'] = array( __( 'Announcements', 'shapelms' ), Capabilities::EDIT_COURSES );

		return $screens;
	}

	/**
	 * First page and the course list rendered into the page, so the screen shows at once.
	 * The queries must match the screen's initial ones in Announcements.js.
	 *
	 * @param array $lists Preloaded lists by screen.
	 * @return array
	 */
	public function preload( $lists ) {
		$lists['announcements'] = array(
			array(
				'announcements/admin',
				array(
					'course_id' => 0,
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
	 * Routes.
	 *
	 * @return void
	 */
	public function routes() {
		$permission = static function () {
			return current_user_can( Capabilities::EDIT_COURSES );
		};

		register_rest_route(
			'shapelms/v1',
			'/announcements/admin',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
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
			'/announcements/admin/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update' ),
				'permission_callback' => $permission,
				'args'                => array(
					'id'        => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'course_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'title'     => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content'   => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/announcements/admin/bulk',
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
	 * Paginated announcements.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ) {
		$per_page  = (int) $request->get_param( 'per_page' );
		$course_id = (int) $request->get_param( 'course_id' );
		$courses   = self::visible_courses();
		$orderby   = self::SORTABLE[ $request->get_param( 'orderby' ) ] ?? 'date';
		$order     = 'ASC' === $request->get_param( 'order' ) ? 'ASC' : 'DESC';
		$args      = array(
			'post_type'      => Module::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => (int) $request->get_param( 'page' ),
			// Id breaks ties so paging stays stable.
			'orderby'        => array(
				$orderby => $order,
				'ID'     => $order,
			),
		);

		if ( $course_id ) {
			if ( null !== $courses && ! in_array( $course_id, $courses, true ) ) {
				return self::response( array(), 0, $per_page );
			}
			$args['post_parent'] = $course_id;
		} elseif ( null !== $courses ) {
			if ( ! $courses ) {
				return self::response( array(), 0, $per_page );
			}
			$args['post_parent__in'] = $courses;
		}

		$search = (string) $request->get_param( 'search' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );

		return self::response( array_map( array( __CLASS__, 'row' ), $query->posts ), (int) $query->found_posts, $per_page );
	}

	/**
	 * List response.
	 *
	 * @param array $items    Items.
	 * @param int   $total    Total.
	 * @param int   $per_page Per page.
	 * @return \WP_REST_Response
	 */
	private static function response( array $items, $total, $per_page ) {
		$pages    = max( 1, $per_page > 0 ? (int) ceil( $total / $per_page ) : 1 );
		$response = new \WP_REST_Response(
			array(
				'items' => $items,
				'total' => (int) $total,
				'pages' => $pages,
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * An announcement for the list and the edit form.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function row( $post ) {
		$course_id = (int) $post->post_parent;
		$author_id = (int) $post->post_author;

		return array(
			'id'           => (int) $post->ID,
			'title'        => $post->post_title,
			'content'      => $post->post_content,
			'excerpt'      => wp_trim_words( wp_strip_all_tags( $post->post_content ), 22, '…' ),
			'course_id'    => $course_id,
			'course_title' => html_entity_decode( get_the_title( $course_id ), ENT_QUOTES, 'UTF-8' ),
			'course_url'   => get_permalink( $course_id ),
			'author'       => get_the_author_meta( 'display_name', $author_id ),
			'avatar'       => get_avatar_url( $author_id, array( 'size' => 64 ) ),
			'date'         => $post->post_date_gmt,
		);
	}

	/**
	 * Loads an announcement the current user may manage.
	 *
	 * @param int $id Post id.
	 * @return \WP_Post|\WP_Error
	 */
	private static function own( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || Module::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Announcement not found.', 'shapelms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'shapelms_edit_course', (int) $post->post_parent ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not allowed to do that.', 'shapelms' ), array( 'status' => 403 ) );
		}

		return $post;
	}

	/**
	 * Edits an announcement. Students are not emailed again.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$post      = self::own( (int) $request->get_param( 'id' ) );
		$course_id = (int) $request->get_param( 'course_id' );

		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( PostTypes::COURSE !== get_post_type( $course_id ) ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Course not found.', 'shapelms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'shapelms_edit_course', $course_id ) ) {
			return new \WP_Error( 'shapelms_forbidden', __( 'You are not allowed to do that.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $post->ID,
					'post_title'   => trim( (string) $request->get_param( 'title' ) ),
					'post_content' => (string) $request->get_param( 'content' ),
					'post_parent'  => $course_id,
				)
			),
			true
		);

		return is_wp_error( $result ) ? $result : self::row( get_post( (int) $post->ID ) );
	}

	/**
	 * Bulk delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{done: int, skipped: int}
	 */
	public function bulk( WP_REST_Request $request ) {
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) ) );
		$done = 0;

		foreach ( $ids as $id ) {
			if ( ! is_wp_error( self::own( $id ) ) && wp_delete_post( $id, true ) ) {
				++$done;
			}
		}

		return array(
			'done'    => $done,
			'skipped' => count( $ids ) - $done,
		);
	}
}
