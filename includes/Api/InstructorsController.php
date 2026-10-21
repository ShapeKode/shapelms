<?php
/**
 * Instructor endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Services\InstructorService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Apply, approve, reject, list, profile.
 */
class InstructorsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'instructors';

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_instructors' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array_merge(
					$this->pagination_args(),
					array(
						'status' => array(
							'type'    => 'string',
							'default' => 'all',
							'enum'    => array( 'all', 'pending', 'approved', 'rejected', 'blocked' ),
						),
					)
				),
			)
		);

		$this->route(
			'/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array(
					'headline' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'bio'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_status' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'id'     => $this->id_arg(),
					'status' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'approved', 'rejected', 'blocked' ),
					),
				),
			)
		);

		$this->route(
			'/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'me' ),
					'permission_callback' => array( $this, 'allow_logged_in' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_profile' ),
					'permission_callback' => array( $this, 'allow_logged_in' ),
				),
			)
		);
	}

	/**
	 * Lists instructors by status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_instructors( WP_REST_Request $request ) {
		$result = InstructorService::list( $request->get_param( 'status' ), $request->get_param( 'page' ), $request->get_param( 'per_page' ), $request->get_param( 'search' ) );

		return $this->paginated( $result['items'], $result['total'], (int) $request->get_param( 'per_page' ) );
	}

	/**
	 * Current user applies.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$result = InstructorService::apply(
			get_current_user_id(),
			array(
				'headline' => $request->get_param( 'headline' ),
				'bio'      => $request->get_param( 'bio' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return array( 'status' => InstructorService::status( get_current_user_id() ) );
	}

	/**
	 * Approves/rejects/blocks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{status: string}
	 */
	public function set_status( WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$status  = $request->get_param( 'status' );

		if ( 'approved' === $status ) {
			InstructorService::approve( $user_id );
		} else {
			InstructorService::reject( $user_id, $status );
		}

		return array( 'status' => InstructorService::status( $user_id ) );
	}

	/**
	 * Current user's instructor data.
	 *
	 * @return array<string, mixed>
	 */
	public function me() {
		$user_id = get_current_user_id();

		return array_merge(
			(array) InstructorService::public_profile( $user_id ),
			array(
				'status'  => InstructorService::status( $user_id ),
				'courses' => InstructorService::course_ids( $user_id ),
			)
		);
	}

	/**
	 * Saves the current user's profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function save_profile( WP_REST_Request $request ) {
		InstructorService::save_profile(
			get_current_user_id(),
			array(
				'headline' => $request->get_param( 'headline' ),
				'bio'      => $request->get_param( 'bio' ),
				'social'   => $request->get_param( 'social' ),
			)
		);

		return $this->me();
	}
}
