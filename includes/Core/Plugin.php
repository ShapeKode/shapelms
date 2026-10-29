<?php
/**
 * Main plugin class.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Modules\ModuleRegistry;
use ShapeLMS\Database\Migrator;

/**
 * Boots the plugin and holds the service container.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Returns the container.
	 *
	 * @return Container
	 */
	public function container() {
		return $this->container;
	}

	/**
	 * Shortcut to resolve a service from the container.
	 *
	 * @param string $id Service id (class name).
	 * @return mixed
	 */
	public function get( $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 5 );
		add_action( 'init', array( $this, 'on_init' ), 0 );
		add_action( 'rest_api_init', array( $this, 'on_rest_api_init' ) );
	}

	/**
	 * Runs on plugins_loaded: upgrades and module boot.
	 *
	 * @return void
	 */
	public function on_plugins_loaded() {
		Migrator::maybe_upgrade();

		$this->get( Capabilities::class )->register();
		$this->get( Capabilities::class )->maybe_sync();
		$this->get( ModuleRegistry::class )->boot();

		if ( is_admin() ) {
			$this->get( \ShapeLMS\Admin\Menu::class )->register();
			$this->get( \ShapeLMS\Admin\Assets::class )->register();
		}

		/**
		 * Fires after ShapeLMS core has loaded.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'shapelms_loaded', $this );
	}

	/**
	 * Runs on init: post types, blocks and roles.
	 *
	 * @return void
	 */
	public function on_init() {
		$this->get( \ShapeLMS\Content\PostTypes::class )->register();
		$this->get( \ShapeLMS\Content\Taxonomies::class )->register();
		$this->get( \ShapeLMS\Content\Meta::class )->register();
		$this->get( \ShapeLMS\Frontend\Frontend::class )->register();
		$this->get( \ShapeLMS\Frontend\Auth::class )->register();
		$this->get( \ShapeLMS\Blocks\Blocks::class )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'shapelms', \ShapeLMS\Cli\Commands::class );
		}
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function on_rest_api_init() {
		$controllers = array(
			\ShapeLMS\Api\SettingsController::class,
			\ShapeLMS\Api\ModulesController::class,
			\ShapeLMS\Api\CoursesController::class,
			\ShapeLMS\Api\CurriculumController::class,
			\ShapeLMS\Api\QuizzesController::class,
			\ShapeLMS\Api\EnrollmentsController::class,
			\ShapeLMS\Api\ProgressController::class,
			\ShapeLMS\Api\StudentsController::class,
			\ShapeLMS\Api\OrdersController::class,
			\ShapeLMS\Api\ReportsController::class,
			\ShapeLMS\Api\InstructorsController::class,
			\ShapeLMS\Api\ReviewsController::class,
			\ShapeLMS\Api\QuestionsController::class,
			\ShapeLMS\Api\ToolsController::class,
		);

		/**
		 * Filters the list of REST controllers to register.
		 *
		 * @param string[] $controllers Fully qualified controller class names.
		 */
		$controllers = apply_filters( 'shapelms_rest_controllers', $controllers );

		foreach ( $controllers as $controller ) {
			if ( class_exists( $controller ) ) {
				$this->get( $controller )->register_routes();
			}
		}
	}
}
