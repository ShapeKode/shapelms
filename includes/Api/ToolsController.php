<?php
/**
 * Tools endpoints: setup wizard, import/export, demo content.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Pages;
use ShapeLMS\Core\Settings;
use ShapeLMS\Services\ImportExportService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Admin-only maintenance endpoints.
 */
class ToolsController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'tools';

	/**
	 * Import/export.
	 *
	 * @var ImportExportService
	 */
	private $io;

	/**
	 * Constructor.
	 *
	 * @param ImportExportService $io Import/export.
	 */
	public function __construct( ImportExportService $io ) {
		$this->io = $io;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'allow_manager' ),
			)
		);

		$this->route(
			'/setup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'setup' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'settings'     => array(
						'type'    => 'object',
						'default' => array(),
					),
					'create_pages' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'import_demo'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		$this->route(
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'ids' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		$this->route(
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'data' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		$this->route(
			'/demo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'demo' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'pack' => array(
						'type'              => 'string',
						'default'           => 'course',
						'enum'              => array( 'course', 'courses' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Site status for the Tools screen.
	 *
	 * @return array<string, mixed>
	 */
	public function status() {
		$pages = array();
		foreach ( array_keys( Pages::definitions() ) as $key ) {
			$id            = Pages::get_id( $key );
			$pages[ $key ] = array(
				'id'     => $id,
				'exists' => $id > 0 && 'publish' === get_post_status( $id ),
				'url'    => $id ? get_permalink( $id ) : '',
			);
		}

		return array(
			'version'          => SHAPELMS_VERSION,
			'db_version'       => (int) get_option( 'shapelms_db_version', 0 ),
			'setup_complete'   => (bool) get_option( 'shapelms_setup_complete' ),
			'pages'            => $pages,
			'php'              => PHP_VERSION,
			'wp'               => get_bloginfo( 'version' ),
			'woocommerce'      => class_exists( 'WooCommerce' ),
			'action_scheduler' => function_exists( 'as_enqueue_async_action' ),
			'permalinks'       => (bool) get_option( 'permalink_structure' ),
			'courses'          => (int) wp_count_posts( \ShapeLMS\Content\PostTypes::COURSE )->publish,
		);
	}

	/**
	 * Runs the setup wizard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function setup( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		if ( is_array( $settings ) && $settings ) {
			Settings::save( $settings );
		}

		if ( $request->get_param( 'create_pages' ) ) {
			Pages::create_pages( true );
		}

		$demo = 0;
		if ( $request->get_param( 'import_demo' ) ) {
			$result = $this->io->install_demo();
			$demo   = is_wp_error( $result ) ? 0 : (int) $result;
		}

		\ShapeLMS\Frontend\ThemeCss::write();
		update_option( 'shapelms_setup_complete', 1 );
		\ShapeLMS\Content\PostTypes::register_static();
		flush_rewrite_rules();

		return array(
			'done'        => true,
			'demo_course' => $demo,
			'status'      => $this->status(),
		);
	}

	/**
	 * Exports courses as JSON.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function export( WP_REST_Request $request ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );

		return $this->io->export( $ids );
	}

	/**
	 * Imports courses from JSON.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$result = $this->io->import( (array) $request->get_param( 'data' ) );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return array(
			'imported' => count( $result ),
			'ids'      => $result,
		);
	}

	/**
	 * Installs the demo course or the six-course sample pack.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function demo( WP_REST_Request $request ) {
		$pack   = 'courses' === $request->get_param( 'pack' ) ? 'courses' : 'course';
		$result = $this->io->install_demo( $pack );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$ids   = (array) $result;
		$first = (int) $ids[0];

		return array(
			'course_id'   => $first,
			'course_ids'  => array_map( 'intval', $ids ),
			'count'       => count( $ids ),
			'url'         => get_permalink( $first ),
			'edit_url'    => get_edit_post_link( $first, 'raw' ),
			'archive_url' => get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ),
		);
	}
}
