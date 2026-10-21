<?php
/**
 * Base module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensible defaults for modules.
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Whether this module is provided by an add-on plugin.
	 *
	 * @return bool
	 */
	public function is_addon() {
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
