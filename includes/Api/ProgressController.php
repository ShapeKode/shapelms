<?php
/**
 * Progress endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Services\ProgressService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Mark complete and record activity.
 */
class ProgressController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'progress';

	/**
	 * Service.
	 *
	 * @var ProgressService
	 */
	private $progress;

	/**
	 * Constructor.
	 *
	 * @param ProgressService $progress Service.
	 */
	public function __construct( ProgressService $progress ) {
		$this->progress = $progress;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array( 'lesson_id' => $this->id_arg() ),
			)
		);

		$this->route(
			'/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
				'args'                => array( 'item_id' => $this->id_arg() ),
			)
		);
	}

	/**
	 * Marks a lesson complete and returns the course summary plus next item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function complete( WP_REST_Request $request ) {
		$lesson_id = (int) $request->get_param( 'lesson_id' );
		$result    = $this->progress->complete_lesson( get_current_user_id(), $lesson_id );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$course_id = Curriculum::course_id_for( $lesson_id );
		$neighbors = Curriculum::neighbors( $course_id, $lesson_id );

		$result['next_url']   = $neighbors['next'] ? get_permalink( $neighbors['next'] ) : '';
		$result['course_url'] = get_permalink( $course_id );

		return $result;
	}

	/**
	 * Records an item open.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{ok: bool}
	 */
	public function start( WP_REST_Request $request ) {
		$this->progress->start( get_current_user_id(), (int) $request->get_param( 'item_id' ) );

		return array( 'ok' => true );
	}
}
