<?php
/**
 * Elementor: course grid widget.
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
 * Renders the course grid block through Elementor.
 */
class CourseGrid extends Widget_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'shapelms-course-grid';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Course Grid', 'shapelms' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
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
		$this->start_controls_section( 'content', array( 'label' => __( 'Courses', 'shapelms' ) ) );

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Number of courses', 'shapelms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 1,
				'max'     => 48,
			)
		);
		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'shapelms' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
			)
		);
		$this->add_control(
			'category',
			array(
				'label'       => __( 'Category slug', 'shapelms' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Leave empty for all categories.', 'shapelms' ),
			)
		);
		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'shapelms' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'    => __( 'Newest', 'shapelms' ),
					'title'   => __( 'Title', 'shapelms' ),
					'price'   => __( 'Price', 'shapelms' ),
					'popular' => __( 'Popular', 'shapelms' ),
				),
			)
		);
		$this->add_control(
			'featured',
			array(
				'label'        => __( 'Featured only', 'shapelms' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
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

		$html = \ShapeLMS\Blocks\Blocks::render_course_grid(
			array(
				'perPage'  => (int) $settings['per_page'],
				'columns'  => (int) $settings['columns'],
				'category' => (string) $settings['category'],
				'orderBy'  => (string) $settings['orderby'],
				'featured' => 'yes' === $settings['featured'],
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped.
	}
}
