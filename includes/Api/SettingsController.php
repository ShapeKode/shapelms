<?php
/**
 * Settings endpoints.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

use ShapeLMS\Core\Settings;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET/POST /settings.
 */
class SettingsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'allow_manager' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'allow_manager' ),
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);

		$this->route(
			'/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages' ),
				'permission_callback' => array( $this, 'allow_manager' ),
			)
		);
	}

	/**
	 * Returns schema + values.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		return array(
			'schema' => Settings::schema(),
			'values' => Settings::for_client(),
		);
	}

	/**
	 * Saves settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function save_settings( WP_REST_Request $request ) {
		$input = $request->get_param( 'settings' );
		$saved = Settings::save( is_array( $input ) ? $input : array() );

		\ShapeLMS\Content\PostTypes::register_static();
		flush_rewrite_rules();

		return array(
			'values' => Settings::for_client(),
			'saved'  => (bool) $saved,
		);
	}

	/**
	 * Page list for page selectors.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public function get_pages() {
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		$list  = array();

		foreach ( (array) $pages as $page ) {
			$list[] = array(
				'id'    => (int) $page->ID,
				'title' => $page->post_title,
			);
		}

		return $list;
	}
}
