<?php
/**
 * Elementor module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Elementor;

use ShapeLMS\Core\Modules\AbstractModule;

/**
 * Registers basic Elementor widgets when Elementor is active.
 */
class Module extends AbstractModule {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'elementor';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Elementor widgets', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Course grid, enroll button and curriculum widgets for Elementor.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'styles' ) );
	}

	/**
	 * Category.
	 *
	 * @param \Elementor\Elements_Manager $manager Manager.
	 * @return void
	 */
	public function category( $manager ) {
		$manager->add_category(
			'shapelms',
			array(
				'title' => __( 'ShapeLMS', 'shapelms' ),
				'icon'  => 'eicon-posts-grid',
			)
		);
	}

	/**
	 * Widgets.
	 *
	 * @param \Elementor\Widgets_Manager $manager Manager.
	 * @return void
	 */
	public function widgets( $manager ) {
		$manager->register( new Widgets\CourseGrid() );
		$manager->register( new Widgets\EnrollButton() );
		$manager->register( new Widgets\Curriculum() );
	}

	/**
	 * Front-end styles for Elementor pages that use the widgets.
	 *
	 * @return void
	 */
	public function styles() {
		wp_enqueue_style( 'shapelms-frontend', SHAPELMS_URL . 'assets/css/frontend.css', array(), SHAPELMS_VERSION );
		wp_enqueue_script( 'shapelms-frontend', SHAPELMS_URL . 'assets/js/frontend.js', array(), SHAPELMS_VERSION, true );
	}
}
