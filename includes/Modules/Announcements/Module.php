<?php
/**
 * Announcements module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Announcements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Services\EnrollmentService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Instructors post announcements to a course; enrolled students see them and get an email.
 */
class Module extends AbstractModule {

	const POST_TYPE = 'shapelms_announce';

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'announcements';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Announcements', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Instructors post course announcements that enrolled students see and receive by email.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'post_type' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'shapelms_single_course_after', array( $this, 'render' ), 10, 2 );
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tab' ) );
	}

	/**
	 * Registers the private post type.
	 *
	 * @return void
	 */
	public function post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Announcements', 'shapelms' ),
					'singular_name' => __( 'Announcement', 'shapelms' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'editor', 'author' ),
				'capability_type' => array( 'shapelms_course', 'shapelms_courses' ),
				'map_meta_cap'    => false,
			)
		);
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			'shapelms/v1',
			'/announcements',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'title'     => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'   => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'wp_kses_post',
						),
						'notify'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			'shapelms/v1',
			'/announcements/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => array( $this, 'can_edit_existing' ),
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
	 * Permission: course editor.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit( WP_REST_Request $request ) {
		return current_user_can( 'shapelms_edit_course', (int) $request->get_param( 'course_id' ) );
	}

	/**
	 * Permission: editor of the announcement's course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_existing( WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );

		return $post && self::POST_TYPE === $post->post_type && current_user_can( 'shapelms_edit_course', (int) $post->post_parent );
	}

	/**
	 * Announcements for a course (or all the user's enrolled courses).
	 *
	 * @param int $course_id Course id (0 = all enrolled).
	 * @param int $user_id   User id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_user( $course_id, $user_id ) {
		$enrollment = shapelms()->get( EnrollmentService::class );

		if ( $course_id ) {
			if ( ! $enrollment->can_access( $user_id, $course_id ) ) {
				return array();
			}
			$course_ids = array( $course_id );
		} else {
			$course_ids = shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class )->course_ids_for_user( $user_id );
			$course_ids = array_merge( $course_ids, \ShapeLMS\Services\InstructorService::course_ids( $user_id ) );
		}

		if ( empty( $course_ids ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'       => self::POST_TYPE,
				'post_parent__in' => array_map( 'intval', $course_ids ),
				'post_status'     => 'publish',
				'numberposts'     => 50,
				'no_found_rows'   => true,
			)
		);

		return array_map( array( __CLASS__, 'to_array' ), $posts );
	}

	/**
	 * Post to array.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function to_array( $post ) {
		return array(
			'id'           => (int) $post->ID,
			'course_id'    => (int) $post->post_parent,
			'course_title' => get_the_title( (int) $post->post_parent ),
			'title'        => $post->post_title,
			'content'      => wpautop( wp_kses_post( $post->post_content ) ),
			'author'       => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'         => get_the_date( '', $post ),
			'can_delete'   => current_user_can( 'shapelms_edit_course', (int) $post->post_parent ),
		);
	}

	/**
	 * REST list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( WP_REST_Request $request ) {
		return self::for_user( (int) $request->get_param( 'course_id' ), get_current_user_id() );
	}

	/**
	 * Creates an announcement.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );

		if ( PostTypes::COURSE !== get_post_type( $course_id ) ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Course not found.', 'shapelms' ), array( 'status' => 404 ) );
		}

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::POST_TYPE,
					'post_title'   => $request->get_param( 'title' ),
					'post_content' => $request->get_param( 'content' ),
					'post_status'  => 'publish',
					'post_parent'  => $course_id,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		/**
		 * Fires when an announcement is published.
		 *
		 * @param int  $id        Announcement id.
		 * @param int  $course_id Course id.
		 * @param bool $notify    Whether to email students.
		 */
		do_action( 'shapelms_announcement_published', (int) $id, $course_id, (bool) $request->get_param( 'notify' ) );

		return self::to_array( get_post( (int) $id ) );
	}

	/**
	 * Deletes an announcement.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}
	 */
	public function delete( WP_REST_Request $request ) {
		return array( 'deleted' => (bool) wp_delete_post( (int) $request->get_param( 'id' ), true ) );
	}

	/**
	 * Renders announcements on the course page for enrolled students.
	 *
	 * @param int   $course_id Course id.
	 * @param array $course    Course.
	 * @return void
	 */
	public function render( $course_id, $course ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$items = self::for_user( $course_id, get_current_user_id() );

		if ( ! $items ) {
			return;
		}

		shapelms_get_template( 'parts/announcements.php', array( 'items' => $items ) );
	}

	/**
	 * Dashboard tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tab( $tabs ) {
		$tabs['announcements'] = array(
			'label'    => __( 'Announcements', 'shapelms' ),
			'template' => 'dashboard/announcements.php',
			'section'  => 'instructor',
			'order'    => 40,
		);

		return $tabs;
	}
}
