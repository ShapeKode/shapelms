<?php
/**
 * Quiz attempt endpoints (student side).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Database\AttemptRepository;
use ShapeLMS\Services\QuizService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Start, answer, submit and review attempts.
 */
class QuizzesController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'quizzes';

	/**
	 * Quiz service.
	 *
	 * @var QuizService
	 */
	private $quiz;

	/**
	 * Attempts.
	 *
	 * @var AttemptRepository
	 */
	private $attempts;

	/**
	 * Constructor.
	 *
	 * @param QuizService       $quiz     Quiz service.
	 * @param AttemptRepository $attempts Attempts.
	 */
	public function __construct( QuizService $quiz, AttemptRepository $attempts ) {
		$this->quiz     = $quiz;
		$this->attempts = $attempts;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array( 'id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/(?P<id>\d+)/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array( 'id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/attempts/(?P<id>\d+)/answer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'answer' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array(
					'id'          => $this->id_arg(),
					'question_id' => $this->id_arg(),
					'answer'      => array( 'required' => true ),
				),
			)
		);

		$this->route(
			'/attempts/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array(
					'id'      => $this->id_arg(),
					'answers' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		$this->route(
			'/attempts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'result' ),
					'permission_callback' => array( $this, 'allow_logged_in' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_attempt' ),
					'permission_callback' => array( $this, 'allow_instructor' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);

		$this->route(
			'/attempts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_attempts' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => array_merge(
					$this->pagination_args(),
					array(
						'quiz_id'   => $this->id_arg( false ),
						'course_id' => $this->id_arg( false ),
						'user_id'   => $this->id_arg( false ),
					)
				),
			)
		);
	}

	/**
	 * Quiz overview for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function overview( WP_REST_Request $request ) {
		$id        = (int) $request->get_param( 'id' );
		$course_id = \ShapeLMS\Content\Curriculum::course_id_for( $id );

		if ( \ShapeLMS\Content\PostTypes::QUIZ !== get_post_type( $id ) || ! $course_id || 'publish' !== get_post_status( $id ) ) {
			return $this->not_found();
		}

		if ( ! shapelms()->get( \ShapeLMS\Services\EnrollmentService::class )->can_access( get_current_user_id(), $course_id ) ) {
			return $this->forbidden();
		}

		return $this->quiz->overview( $id, get_current_user_id() );
	}

	/**
	 * Starts an attempt.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$result = $this->quiz->start( get_current_user_id(), (int) $request->get_param( 'id' ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $result;
	}

	/**
	 * Saves one answer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function answer( WP_REST_Request $request ) {
		$result = $this->quiz->answer(
			get_current_user_id(),
			(int) $request->get_param( 'id' ),
			(int) $request->get_param( 'question_id' ),
			$request->get_param( 'answer' )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $result;
	}

	/**
	 * Submits the attempt.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( WP_REST_Request $request ) {
		$answers = $request->get_param( 'answers' );
		$result  = $this->quiz->submit( get_current_user_id(), (int) $request->get_param( 'id' ), is_array( $answers ) ? $answers : array() );

		return is_wp_error( $result ) ? $this->error( $result ) : $result;
	}

	/**
	 * Attempt result.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function result( WP_REST_Request $request ) {
		$result = $this->quiz->result( get_current_user_id(), (int) $request->get_param( 'id' ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $result;
	}

	/**
	 * Deletes an attempt (instructor of the course or manager).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}|\WP_Error
	 */
	public function delete_attempt( WP_REST_Request $request ) {
		$attempt = $this->attempts->find( (int) $request->get_param( 'id' ) );

		if ( ! $attempt ) {
			return $this->not_found();
		}

		if ( ! current_user_can( 'shapelms_edit_course', (int) $attempt->course_id ) ) {
			return $this->forbidden();
		}

		return array( 'deleted' => $this->attempts->delete( (int) $attempt->id ) );
	}

	/**
	 * Lists attempts for instructors/admins.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_attempts( WP_REST_Request $request ) {
		$course_id = (int) $request->get_param( 'course_id' );
		$quiz_id   = (int) $request->get_param( 'quiz_id' );

		if ( ! current_user_can( 'manage_shapelms' ) ) {
			$check = $course_id ? $course_id : $quiz_id;
			if ( ! $check || ! current_user_can( 'shapelms_edit_course', $check ) ) {
				return $this->forbidden();
			}
		}

		$result = $this->attempts->paginate(
			array(
				'quiz_id'   => $quiz_id,
				'course_id' => $course_id,
				'user_id'   => (int) $request->get_param( 'user_id' ),
				'page'      => $request->get_param( 'page' ),
				'per_page'  => $request->get_param( 'per_page' ),
			)
		);

		$items = array();
		foreach ( $result['items'] as $row ) {
			$item               = $this->quiz->attempt_to_array( $row );
			$item['user_name']  = $row->display_name;
			$item['user_email'] = $row->user_email;
			$item['quiz_title'] = $row->quiz_title;
			$items[]            = $item;
		}

		return $this->paginated( $items, $result['total'], (int) $request->get_param( 'per_page' ) );
	}
}
