<?php
/**
 * Module interface.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A module is a feature that can be switched on or off from the Modules screen.
 */
interface ModuleInterface {

	/**
	 * Unique id (slug).
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human name.
	 *
	 * @return string
	 */
	public function name();

	/**
	 * One-line description.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * Whether the module is provided by an add-on plugin.
	 *
	 * @return bool
	 */
	public function is_addon();

	/**
	 * Ids of modules that must be active first.
	 *
	 * @return string[]
	 */
	public function dependencies();

	/**
	 * Whether the module is on by default.
	 *
	 * @return bool
	 */
	public function default_enabled();

	/**
	 * Boots the module (hooks, routes, assets).
	 *
	 * @return void
	 */
	public function boot();
}
