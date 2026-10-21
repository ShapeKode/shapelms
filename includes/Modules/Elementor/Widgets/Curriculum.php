<?php
/**
 * Elementor: curriculum widget.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Renders the curriculum accordion for a course.
 */
class Curriculum extends Widget_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'shapelms-curriculum';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Course Curriculum', 'shapelms' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-accordion';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'shapelms' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Course', 'shapelms' ) ) );
		$this->add_control(
			'course_id',
			array(
				'label'       => __( 'Course ID', 'shapelms' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 0,
				'description' => __( 'Leave 0 to use the current course.', 'shapelms' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		echo \ShapeLMS\Blocks\Blocks::render_curriculum( array( 'courseId' => (int) $settings['course_id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
	}
}
