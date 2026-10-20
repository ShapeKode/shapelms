<?php
/**
 * Wishlist module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Wishlist;

use ShapeLMS\Core\Modules\AbstractModule;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Lets students save courses for later (user meta).
 */
class Module extends AbstractModule {

	const META = 'shapelms_wishlist';

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'wishlist';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Wishlist', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Students can save courses to a wishlist on their dashboard.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! shapelms_get_setting( 'course', 'enable_wishlist', true ) ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'shapelms_enroll_box_actions', array( $this, 'button' ), 10, 2 );
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tab' ) );
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			'shapelms/v1',
			'/wishlist',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Course ids in the wishlist.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public static function ids( $user_id ) {
		$ids = get_user_meta( $user_id, self::META, true );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
	}

	/**
	 * Whether a course is wishlisted.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function has( $user_id, $course_id ) {
		return in_array( (int) $course_id, self::ids( $user_id ), true );
	}

	/**
	 * Toggles a course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{active: bool, ids: int[]}
	 */
	public function toggle( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$course_id = (int) $request->get_param( 'course_id' );
		$ids       = self::ids( $user_id );

		if ( in_array( $course_id, $ids, true ) ) {
			$ids    = array_values( array_diff( $ids, array( $course_id ) ) );
			$active = false;
		} else {
			$ids[]  = $course_id;
			$active = true;
		}

		update_user_meta( $user_id, self::META, $ids );

		return array(
			'active' => $active,
			'ids'    => $ids,
		);
	}

	/**
	 * Lists wishlisted courses.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list() {
		$ids = self::ids( get_current_user_id() );

		if ( ! $ids ) {
			return array();
		}

		return shapelms()->get( \ShapeLMS\Services\CourseService::class )->query(
			array(
				'ids'      => $ids,
				'per_page' => 100,
			)
		)['items'];
	}

	/**
	 * Wishlist button in the enroll box.
	 *
	 * @param int  $course_id Course id.
	 * @param bool $enrolled  Enrolled.
	 * @return void
	 */
	public function button( $course_id, $enrolled ) {
		if ( $enrolled ) {
			return;
		}

		$active = is_user_logged_in() && self::has( get_current_user_id(), $course_id );

		printf(
			'<button type="button" class="shapelms-wishlist-btn%s" data-shapelms-action="wishlist" data-body="%s" data-toggle-class="is-active" data-on-text="%s" data-off-text="%s" aria-pressed="%s"><span aria-hidden="true">♡</span> <span class="shapelms-action__label">%s</span></button>',
			$active ? ' is-active' : '',
			esc_attr( (string) wp_json_encode( array( 'course_id' => (int) $course_id ) ) ),
			esc_attr__( 'Saved to wishlist', 'shapelms' ),
			esc_attr__( 'Add to wishlist', 'shapelms' ),
			$active ? 'true' : 'false',
			$active ? esc_html__( 'Saved to wishlist', 'shapelms' ) : esc_html__( 'Add to wishlist', 'shapelms' )
		);
	}

	/**
	 * Dashboard tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tab( $tabs ) {
		$tabs['wishlist'] = array(
			'label'    => __( 'Wishlist', 'shapelms' ),
			'template' => 'dashboard/wishlist.php',
			'section'  => 'student',
			'order'    => 30,
		);

		return $tabs;
	}
}
