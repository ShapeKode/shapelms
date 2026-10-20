<?php
/**
 * Minimal Elementor stubs for static analysis.
 *
 * @package ShapeLMS
 */

// phpcs:ignoreFile

namespace Elementor;

class Controls_Manager {
	const NUMBER   = 'number';
	const SELECT   = 'select';
	const TEXT     = 'text';
	const SWITCHER = 'switcher';
}

abstract class Widget_Base {
	/** @return string */
	abstract public function get_name();
	/** @return string */
	abstract public function get_title();
	/** @param string $id @param array<string, mixed> $args @return void */
	protected function start_controls_section( $id, $args ) {}
	/** @return void */
	protected function end_controls_section() {}
	/** @param string $id @param array<string, mixed> $args @return void */
	protected function add_control( $id, $args ) {}
	/** @return array<string, mixed> */
	protected function get_settings_for_display() {}
}

class Elements_Manager {
	/** @param string $id @param array<string, mixed> $args @return void */
	public function add_category( $id, $args ) {}
}

class Widgets_Manager {
	/** @param Widget_Base $widget @return void */
	public function register( $widget ) {}
}
