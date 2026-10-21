<?php
/**
 * Base REST controller.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Services\InstructorService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Shared helpers: namespace, permission callbacks, responses.
 */
abstract class RestController {

	const NAMESPACE_V1 = 'shapelms/v1';

	/**
	 * Route base, e.g. "courses".
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Registers a route under the namespace.
	 *
	 * @param string $route Route pattern relative to the base.
	 * @param array  $args  Route args (one or several endpoint arrays).
	 * @return void
	 */
	protected function route( $route, array $args ) {
		register_rest_route( self::NAMESPACE_V1, '/' . $this->rest_base . $route, $args );
	}

	/**
	 * Anyone, including guests.
	 *
	 * @return bool
	 */
	public function allow_public() {
		return true;
	}

	/**
	 * Logged-in users.
	 *
	 * @return bool|WP_Error
	 */
	public function allow_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'shapelms_login_required', __( 'You must be logged in.', 'shapelms' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * LMS managers (admins).
	 *
	 * @return bool|WP_Error
	 */
	public function allow_manager() {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return $this->forbidden();
		}

		return true;
	}

	/**
	 * Instructors and managers.
	 *
	 * @return bool|WP_Error
	 */
	public function allow_instructor() {
		if ( ! current_user_can( Capabilities::EDIT_COURSES ) ) {
			return $this->forbidden();
		}

		return true;
	}

	/**
	 * Users who can edit the course referenced by the request (id, course_id, or an item's course).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function allow_course_editor( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::EDIT_COURSES ) ) {
			return $this->forbidden();
		}

		if ( current_user_can( Capabilities::MANAGE ) ) {
			return true;
		}

		foreach ( array( 'course_id', 'id', 'topic_id', 'quiz_id', 'lesson_id', 'item_id' ) as $key ) {
			$post_id = (int) $request->get_param( $key );

			if ( $post_id && ! InstructorService::is_course_instructor( get_current_user_id(), $post_id ) ) {
				return $this->forbidden();
			}
		}

		return true;
	}

	/**
	 * 403 error.
	 *
	 * @return WP_Error
	 */
	protected function forbidden() {
		return new WP_Error( 'shapelms_forbidden', __( 'You are not allowed to do that.', 'shapelms' ), array( 'status' => 403 ) );
	}

	/**
	 * 404 error.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	protected function not_found( $message = '' ) {
		return new WP_Error( 'shapelms_not_found', $message ? $message : __( 'Not found.', 'shapelms' ), array( 'status' => 404 ) );
	}

	/**
	 * Turns a WP_Error without a status into a 400 response.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	protected function error( WP_Error $error ) {
		$data = $error->get_error_data();

		if ( ! is_array( $data ) || empty( $data['status'] ) ) {
			$error->add_data( array( 'status' => 400 ) );
		}

		return $error;
	}

	/**
	 * Paginated response with headers.
	 *
	 * @param array $items    Items.
	 * @param int   $total    Total count.
	 * @param int   $per_page Per page.
	 * @return WP_REST_Response
	 */
	protected function paginated( array $items, $total, $per_page ) {
		$response = new WP_REST_Response(
			array(
				'items' => $items,
				'total' => (int) $total,
				'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ( $per_page > 0 ? ceil( $total / $per_page ) : 1 ) );

		return $response;
	}

	/**
	 * Common pagination args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function pagination_args() {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Integer id arg.
	 *
	 * @param bool $required Required.
	 * @return array<string, mixed>
	 */
	protected function id_arg( $required = true ) {
		return array(
			'type'              => 'integer',
			'required'          => $required,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);
	}
}
