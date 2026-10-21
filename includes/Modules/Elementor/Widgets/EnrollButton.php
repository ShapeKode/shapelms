<?php
/**
 * Elementor: enroll button widget.
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
 * Renders the enroll box for a course.
 */
class EnrollButton extends Widget_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'shapelms-enroll-button';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Course Enroll Box', 'shapelms' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-button';
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

		echo \ShapeLMS\Blocks\Blocks::render_enroll_button( array( 'courseId' => (int) $settings['course_id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
	}
}
