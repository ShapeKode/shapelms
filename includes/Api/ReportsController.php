<?php
/**
 * Reports endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Services\ReportService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Dashboard KPIs and basic reports.
 */
class ReportsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports';

	/**
	 * Reports.
	 *
	 * @var ReportService
	 */
	private $reports;

	/**
	 * Constructor.
	 *
	 * @param ReportService $reports Reports.
	 */
	public function __construct( ReportService $reports ) {
		$this->reports = $reports;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$args = array(
			'from'      => array(
				'type'              => 'string',
				'default'           => gmdate( 'Y-m-d', strtotime( '-29 days' ) ),
				'sanitize_callback' => array( $this, 'sanitize_date' ),
			),
			'to'        => array(
				'type'              => 'string',
				'default'           => gmdate( 'Y-m-d' ),
				'sanitize_callback' => array( $this, 'sanitize_date' ),
			),
			'course_id' => $this->id_arg( false ),
		);

		$this->route(
			'/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => $args,
			)
		);

		$this->route(
			'/courses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'courses' ),
				'permission_callback' => array( $this, 'allow_instructor' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * Y-m-d or today.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : gmdate( 'Y-m-d' );
	}

	/**
	 * KPIs, series and recent activity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function overview( WP_REST_Request $request ) {
		return $this->reports->overview( $request->get_param( 'from' ), $request->get_param( 'to' ), (int) $request->get_param( 'course_id' ), get_current_user_id() );
	}

	/**
	 * Per-course table.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public function courses( WP_REST_Request $request ) {
		return $this->reports->courses( get_current_user_id() );
	}
}
