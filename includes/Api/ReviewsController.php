<?php
/**
 * Reviews endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Modules\Reviews\ReviewService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Submit and list course reviews.
 */
class ReviewsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reviews';

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		if ( ! shapelms()->get( \ShapeLMS\Core\Modules\ModuleRegistry::class )->is_active( 'reviews' ) ) {
			return;
		}

		$this->route(
			'',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_reviews' ),
					'permission_callback' => array( $this, 'allow_public' ),
					'args'                => array_merge(
						$this->pagination_args(),
						array( 'course_id' => $this->id_arg() )
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => array( $this, 'allow_logged_in' ),
					'args'                => array(
						'course_id' => $this->id_arg(),
						'rating'    => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
							'maximum'  => 5,
						),
						'content'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Lists approved reviews.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_reviews( WP_REST_Request $request ) {
		$result = ReviewService::list( (int) $request->get_param( 'course_id' ), (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );

		return $this->paginated( $result['items'], $result['total'], (int) $request->get_param( 'per_page' ) );
	}

	/**
	 * Submits or updates the current user's review.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( WP_REST_Request $request ) {
		$result = ReviewService::submit(
			get_current_user_id(),
			(int) $request->get_param( 'course_id' ),
			(int) $request->get_param( 'rating' ),
			(string) $request->get_param( 'content' )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $result;
	}
}
