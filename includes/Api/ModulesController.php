<?php
/**
 * Modules endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

use ShapeLMS\Core\Modules\ModuleRegistry;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET /modules, POST /modules/{id}/toggle.
 */
class ModulesController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'modules';

	/**
	 * Registry.
	 *
	 * @var ModuleRegistry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param ModuleRegistry $registry Registry.
	 */
	public function __construct( ModuleRegistry $registry ) {
		$this->registry = $registry;
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
				'callback'            => array( $this, 'list_modules' ),
				'permission_callback' => array( $this, 'allow_manager' ),
			)
		);

		$this->route(
			'/(?P<id>[a-z0-9_-]+)/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'id'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Lists modules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_modules() {
		return $this->registry->to_array();
	}

	/**
	 * Toggles a module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function toggle( WP_REST_Request $request ) {
		$ok = $this->registry->set_enabled( $request->get_param( 'id' ), (bool) $request->get_param( 'enabled' ) );

		if ( ! $ok ) {
			return $this->not_found( __( 'Module not found.', 'shapelms' ) );
		}

		return $this->registry->to_array();
	}
}
