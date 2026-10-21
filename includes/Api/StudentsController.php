<?php
/**
 * Students endpoints (admin).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Database\ActivityLogRepository;
use ShapeLMS\Database\EnrollmentRepository;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\InstructorService;
use ShapeLMS\Services\ProgressService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Student list, single student timeline, user search.
 */
class StudentsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'students';

	/**
	 * Enrollments.
	 *
	 * @var EnrollmentRepository
	 */
	private $enrollments;

	/**
	 * Enrollment service.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment;

	/**
	 * Progress.
	 *
	 * @var ProgressService
	 */
	private $progress;

	/**
	 * Activity.
	 *
	 * @var ActivityLogRepository
	 */
	private $activity;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository  $enrollments Enrollments.
	 * @param EnrollmentService     $enrollment  Service.
	 * @param ProgressService       $progress    Progress.
	 * @param ActivityLogRepository $activity    Activity.
	 */
	public function __construct( EnrollmentRepository $enrollments, EnrollmentService $enrollment, ProgressService $progress, ActivityLogRepository $activity ) {
		$this->enrollments = $enrollments;
		$this->enrollment  = $enrollment;
		$this->progress    = $progress;
		$this->activity    = $activity;
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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_students' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => array_merge(
					$this->pagination_args(),
					array(
						'course_id' => $this->id_arg( false ),
						'status'    => array(
							'type'    => 'string',
							'default' => '',
							'enum'    => array( '', 'active', 'completed', 'cancelled', 'expired' ),
						),
						'orderby'   => array(
							'type'    => 'string',
							'default' => 'started_at',
							'enum'    => array( 'started_at', 'progress', 'name', 'status' ),
						),
						'order'     => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC' ),
						),
					)
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_student' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => array( 'id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/search-users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_users' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Paginated enrollments with user data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_students( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );

		if ( ! current_user_can( 'manage_shapelms' ) ) {
			if ( $course_id && ! current_user_can( 'shapelms_edit_course', $course_id ) ) {
				return $this->forbidden();
			}
			if ( ! $course_id ) {
				return $this->instructor_students( $request );
			}
		}

		$result = $this->enrollments->paginate(
			array(
				'search'    => $request->get_param( 'search' ),
				'course_id' => $course_id,
				'status'    => $request->get_param( 'status' ),
				'page'      => $request->get_param( 'page' ),
				'per_page'  => $request->get_param( 'per_page' ),
				'orderby'   => $request->get_param( 'orderby' ),
				'order'     => $request->get_param( 'order' ),
			)
		);

		return $this->paginated( array_map( array( $this, 'row' ), $result['items'] ), $result['total'], (int) $request->get_param( 'per_page' ) );
	}

	/**
	 * Enrollments across all of an instructor's courses.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	private function instructor_students( WP_REST_Request $request ) {
		$course_ids = InstructorService::course_ids( get_current_user_id() );

		if ( empty( $course_ids ) ) {
			return $this->paginated( array(), 0, (int) $request->get_param( 'per_page' ) );
		}

		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$where    = array( 'course_id' => $course_ids );

		if ( $request->get_param( 'status' ) ) {
			$where['status'] = $request->get_param( 'status' );
		}

		$total = $this->enrollments->count( $where );
		$rows  = $this->enrollments->where(
			$where,
			array(
				'orderby' => 'started_at',
				'order'   => 'DESC',
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
			)
		);

		foreach ( $rows as $row ) {
			$user              = get_userdata( (int) $row->user_id );
			$row->display_name = $user ? $user->display_name : '';
			$row->user_email   = $user ? $user->user_email : '';
		}

		return $this->paginated( array_map( array( $this, 'row' ), $rows ), $total, $per_page );
	}

	/**
	 * Formats a row.
	 *
	 * @param object $row Row.
	 * @return array<string, mixed>
	 */
	private function row( $row ) {
		$data                 = $this->enrollment->row_to_array( $row );
		$data['user_name']    = isset( $row->display_name ) ? $row->display_name : '';
		$data['user_email']   = isset( $row->user_email ) ? $row->user_email : '';
		$data['avatar']       = get_avatar_url( (int) $row->user_id, array( 'size' => 64 ) );
		$data['course_title'] = get_the_title( (int) $row->course_id );

		return $data;
	}

	/**
	 * One student: profile, enrollments, timeline.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_student( WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return $this->not_found();
		}

		$enrollments = $this->enrollment->for_user( $user_id, 'all' );

		if ( ! current_user_can( 'manage_shapelms' ) ) {
			$mine        = InstructorService::course_ids( get_current_user_id() );
			$enrollments = array_values(
				array_filter(
					$enrollments,
					static function ( $item ) use ( $mine ) {
						return in_array( (int) $item['course']['id'], $mine, true );
					}
				)
			);
			if ( empty( $enrollments ) ) {
				return $this->forbidden();
			}
		}

		foreach ( $enrollments as &$item ) {
			$item['summary'] = $this->progress->summary( $user_id, (int) $item['course']['id'] );
		}

		return array(
			'id'          => $user_id,
			'name'        => $user->display_name,
			'email'       => $user->user_email,
			'avatar'      => get_avatar_url( $user_id, array( 'size' => 128 ) ),
			'registered'  => $user->user_registered,
			'enrollments' => $enrollments,
			'activity'    => current_user_can( 'manage_shapelms' ) ? $this->activity->for_user( $user_id, 50 ) : array(),
		);
	}

	/**
	 * User search for the manual enroll dialog.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public function search_users( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_shapelms' ) ) {
			$user = get_user_by( 'email', sanitize_email( (string) $request->get_param( 'search' ) ) );

			if ( ! $user ) {
				return array();
			}

			return array(
				array(
					'id'    => (int) $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
				),
			);
		}

		$query = new \WP_User_Query(
			array(
				'search'         => '*' . $request->get_param( 'search' ) . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 10,
			)
		);

		$list = array();
		foreach ( $query->get_results() as $user ) {
			$list[] = array(
				'id'    => (int) $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			);
		}

		return $list;
	}
}
