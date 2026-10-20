<?php
/**
 * Base module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core\Modules;

/**
 * Sensible defaults for modules.
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Whether this module is from a Pro plugin or add-on.
	 *
	 * @return bool
	 */
	public function is_pro() {
		return false;
	}

	/**
	 * Dependencies.
	 *
	 * @return string[]
	 */
	public function dependencies() {
		return array();
	}

	/**
	 * On by default.
	 *
	 * @return bool
	 */
	public function default_enabled() {
		return true;
	}
}
