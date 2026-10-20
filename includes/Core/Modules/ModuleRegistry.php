<?php
/**
 * Module registry.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core\Modules;

/**
 * Collects modules from the shapelms_register_modules filter and boots the active ones.
 */
class ModuleRegistry {

	const OPTION = 'shapelms_modules';

	/**
	 * Registered modules keyed by id.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private $modules = array();

	/**
	 * Ids of booted modules.
	 *
	 * @var string[]
	 */
	private $booted = array();

	/**
	 * Collects and boots modules.
	 *
	 * @return void
	 */
	public function boot() {
		/**
		 * Filters the list of module instances.
		 *
		 * @param ModuleInterface[] $modules Modules.
		 */
		$modules = apply_filters( 'shapelms_register_modules', $this->core_modules() );

		foreach ( $modules as $module ) {
			if ( $module instanceof ModuleInterface ) {
				$this->modules[ $module->id() ] = $module;
			}
		}

		foreach ( array_keys( $this->modules ) as $id ) {
			$this->maybe_boot( $id );
		}
	}

	/**
	 * Core (free) modules.
	 *
	 * @return ModuleInterface[]
	 */
	private function core_modules() {
		return array(
			new \ShapeLMS\Modules\Reviews\Module(),
			new \ShapeLMS\Modules\QA\Module(),
			new \ShapeLMS\Modules\Wishlist\Module(),
			new \ShapeLMS\Modules\Announcements\Module(),
			new \ShapeLMS\Modules\Emails\Module(),
			new \ShapeLMS\Modules\Checkout\Module(),
			new \ShapeLMS\Modules\WooCommerce\Module(),
			new \ShapeLMS\Modules\Instructors\Module(),
			new \ShapeLMS\Modules\Elementor\Module(),
			new \ShapeLMS\Modules\Privacy\Module(),
		);
	}

	/**
	 * Boots one module if it is active and its dependencies are satisfied.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	private function maybe_boot( $id ) {
		if ( in_array( $id, $this->booted, true ) ) {
			return true;
		}

		if ( ! isset( $this->modules[ $id ] ) || ! $this->is_enabled( $id ) ) {
			return false;
		}

		foreach ( $this->modules[ $id ]->dependencies() as $dependency ) {
			if ( ! $this->maybe_boot( $dependency ) ) {
				return false;
			}
		}

		$this->modules[ $id ]->boot();
		$this->booted[] = $id;

		return true;
	}

	/**
	 * Whether a module is switched on.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}

		$state = get_option( self::OPTION, array() );

		if ( is_array( $state ) && array_key_exists( $id, $state ) ) {
			return (bool) $state[ $id ];
		}

		return $this->modules[ $id ]->default_enabled();
	}

	/**
	 * Whether a module is booted (enabled and dependencies met).
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_active( $id ) {
		return in_array( $id, $this->booted, true );
	}

	/**
	 * Switches a module on or off.
	 *
	 * @param string $id      Module id.
	 * @param bool   $enabled State.
	 * @return bool False when the module is unknown.
	 */
	public function set_enabled( $id, $enabled ) {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}

		$state        = get_option( self::OPTION, array() );
		$state        = is_array( $state ) ? $state : array();
		$state[ $id ] = (bool) $enabled;

		update_option( self::OPTION, $state );

		return true;
	}

	/**
	 * Returns module data for the admin screen.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_array() {
		$list = array();

		foreach ( $this->modules as $id => $module ) {
			$list[] = array(
				'id'           => $id,
				'name'         => $module->name(),
				'description'  => $module->description(),
				'is_pro'       => $module->is_pro(),
				'dependencies' => $module->dependencies(),
				'enabled'      => $this->is_enabled( $id ),
				'active'       => $this->is_active( $id ),
			);
		}

		return $list;
	}

	/**
	 * Returns a module.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface|null
	 */
	public function get( $id ) {
		return isset( $this->modules[ $id ] ) ? $this->modules[ $id ] : null;
	}
}
