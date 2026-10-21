<?php
/**
 * Enrollment endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Services\EnrollmentService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Enroll (free), my enrollments, and admin enroll/unenroll.
 */
class EnrollmentsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'enrollments';

	/**
	 * Service.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentService $enrollment Service.
	 */
	public function __construct( EnrollmentService $enrollment ) {
		$this->enrollment = $enrollment;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'enroll' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array(
					'course_id' => $this->id_arg(),
					'user_id'   => $this->id_arg( false ),
				),
			)
		);

		$this->route(
			'/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'mine' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array(
					'status' => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'active', 'completed' ),
					),
				),
			)
		);

		$this->route(
			'/(?P<course_id>\d+)/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unenroll' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'course_id' => $this->id_arg(),
					'user_id'   => $this->id_arg(),
					'reset'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		$this->route(
			'/(?P<course_id>\d+)/(?P<user_id>\d+)/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset' ),
				'permission_callback' => array( $this, 'allow_course_editor' ),
				'args'                => array(
					'course_id' => $this->id_arg(),
					'user_id'   => $this->id_arg(),
				),
			)
		);
	}

	/**
	 * Enrolls the current user (free course) or, for course editors, another user (manual).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function enroll( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );
		$user_id   = (int) $request->get_param( 'user_id' );

		if ( $user_id && get_current_user_id() !== $user_id ) {
			if ( ! current_user_can( 'shapelms_edit_course', $course_id ) ) {
				return $this->forbidden();
			}
			$result = $this->enrollment->enroll( $user_id, $course_id, 'manual' );
		} else {
			$result = $this->enrollment->enroll_free( get_current_user_id(), $course_id );
		}

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$row = $this->enrollment->get( $user_id ? $user_id : get_current_user_id(), $course_id );

		return array(
			'enrollment' => $row ? $this->enrollment->row_to_array( $row ) : null,
			'redirect'   => get_permalink( $course_id ),
		);
	}

	/**
	 * Current user's enrollments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public function mine( WP_REST_Request $request ) {
		return $this->enrollment->for_user( get_current_user_id(), $request->get_param( 'status' ) );
	}

	/**
	 * Unenrolls a user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{removed: bool}
	 */
	public function unenroll( WP_REST_Request $request ) {
		return array(
			'removed' => $this->enrollment->unenroll(
				(int) $request->get_param( 'user_id' ),
				(int) $request->get_param( 'course_id' ),
				'cancelled',
				(bool) $request->get_param( 'reset' )
			),
		);
	}

	/**
	 * Resets progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{reset: bool}
	 */
	public function reset( WP_REST_Request $request ) {
		return array(
			'reset' => $this->enrollment->reset_progress( (int) $request->get_param( 'user_id' ), (int) $request->get_param( 'course_id' ) ),
		);
	}
}
