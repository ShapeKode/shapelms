<?php
/**
 * Gutenberg blocks.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\Curriculum;
use ShapeLMS\Content\PostTypes;
use ShapeLMS\Frontend\Dashboard;
use ShapeLMS\Services\CourseService;

/**
 * Registers dynamic blocks rendered by PHP templates.
 */
class Blocks {

	/**
	 * Blocks and their render callbacks.
	 *
	 * @return array<string, callable>
	 */
	private function definitions() {
		return array(
			'course-grid'             => array( __CLASS__, 'render_course_grid' ),
			'enroll-button'           => array( __CLASS__, 'render_enroll_button' ),
			'curriculum'              => array( __CLASS__, 'render_curriculum' ),
			'dashboard'               => array( __CLASS__, 'render_dashboard' ),
			'checkout'                => array( __CLASS__, 'render_checkout' ),
			'instructor-registration' => array( __CLASS__, 'render_instructor_registration' ),
			'login'                   => array( __CLASS__, 'render_login' ),
			'register'                => array( __CLASS__, 'render_register' ),
		);
	}

	/**
	 * Registers blocks from block.json files and legacy shortcodes.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( $this->definitions() as $name => $callback ) {
			$dir = SHAPELMS_DIR . 'blocks/' . $name;

			if ( file_exists( $dir . '/block.json' ) ) {
				register_block_type( $dir, array( 'render_callback' => $callback ) );
			}
		}

		add_filter( 'block_categories_all', array( $this, 'category' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );

		add_shortcode( 'shapelms_courses', array( __CLASS__, 'shortcode_courses' ) );
		add_shortcode( 'shapelms_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_shortcode( 'shapelms_checkout', array( __CLASS__, 'render_checkout' ) );
		add_shortcode( 'shapelms_instructor_registration', array( __CLASS__, 'render_instructor_registration' ) );
		add_shortcode( 'shapelms_login', array( __CLASS__, 'render_login' ) );
		add_shortcode( 'shapelms_register', array( __CLASS__, 'render_register' ) );
	}

	/**
	 * Block category.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public function category( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'shapelms',
				'title' => __( 'ShapeLMS', 'shapelms' ),
			)
		);

		return $categories;
	}

	/**
	 * Editor script for block controls.
	 *
	 * @return void
	 */
	public function editor_assets() {
		$asset = SHAPELMS_DIR . 'assets/build/blocks.asset.php';
		$deps  = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' );
		$ver   = SHAPELMS_VERSION;

		if ( file_exists( $asset ) ) {
			$data = include $asset;
			$deps = isset( $data['dependencies'] ) ? $data['dependencies'] : $deps;
			$ver  = isset( $data['version'] ) ? $data['version'] : $ver;
		}

		if ( file_exists( SHAPELMS_DIR . 'assets/build/blocks.js' ) ) {
			wp_enqueue_script( 'shapelms-blocks', SHAPELMS_URL . 'assets/build/blocks.js', $deps, $ver, true );
			wp_set_script_translations( 'shapelms-blocks', 'shapelms', SHAPELMS_DIR . 'languages' );
		}

		wp_enqueue_style( 'shapelms-frontend', SHAPELMS_URL . 'assets/css/frontend.css', array(), SHAPELMS_VERSION );
	}

	/**
	 * Resolves the course id from attributes or the current post.
	 *
	 * @param array $attributes Attributes.
	 * @return int
	 */
	private static function course_id( array $attributes ) {
		$id = isset( $attributes['courseId'] ) ? (int) $attributes['courseId'] : 0;

		if ( ! $id ) {
			$id = Curriculum::course_id_for( get_the_ID() );
		}

		return PostTypes::COURSE === get_post_type( $id ) ? $id : 0;
	}

	/**
	 * Course grid.
	 *
	 * @param array $attributes { perPage, columns, category, tag, orderBy, featured, level, price }.
	 * @return string
	 */
	public static function render_course_grid( $attributes = array() ) {
		$attributes = wp_parse_args(
			(array) $attributes,
			array(
				'perPage'  => 6,
				'columns'  => 3,
				'category' => '',
				'tag'      => '',
				'orderBy'  => 'date',
				'featured' => false,
				'level'    => '',
				'price'    => '',
			)
		);

		$args = array(
			'per_page' => (int) $attributes['perPage'],
			'category' => (string) $attributes['category'],
			'tag'      => (string) $attributes['tag'],
			'orderby'  => (string) $attributes['orderBy'],
			'level'    => (string) $attributes['level'],
			'price'    => (string) $attributes['price'],
		);

		if ( ! empty( $attributes['featured'] ) ) {
			$featured = get_posts(
				array(
					'post_type'     => PostTypes::COURSE,
					'post_status'   => 'publish',
					'numberposts'   => (int) $attributes['perPage'],
					'fields'        => 'ids',
					'no_found_rows' => true,
					'meta_key'      => '_shapelms_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'    => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( ! $featured ) {
				return '';
			}
			$args['ids'] = $featured;
		}

		$result = shapelms()->get( CourseService::class )->query( $args );

		if ( ! $result['items'] ) {
			return '<div class="shapelms shapelms-empty">' . esc_html__( 'No courses found.', 'shapelms' ) . '</div>';
		}

		$columns = max( 1, min( 4, (int) $attributes['columns'] ) );
		$html    = sprintf( '<div class="shapelms shapelms-grid shapelms-grid--%d"%s>', $columns, \ShapeLMS\Frontend\Frontend::data_attributes() );

		foreach ( $result['items'] as $course ) {
			$html .= shapelms_get_template( 'parts/course-card.php', array( 'course_id' => $course['id'] ), false );
		}

		return $html . '</div>';
	}

	/**
	 * Courses shortcode: [shapelms_courses per_page="6" columns="3" category="" orderby="date" featured="0"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode_courses( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page' => 6,
				'columns'  => 3,
				'category' => '',
				'tag'      => '',
				'orderby'  => 'date',
				'featured' => '0',
				'level'    => '',
				'price'    => '',
			),
			$atts,
			'shapelms_courses'
		);

		return self::render_course_grid(
			array(
				'perPage'  => (int) $atts['per_page'],
				'columns'  => (int) $atts['columns'],
				'category' => $atts['category'],
				'tag'      => $atts['tag'],
				'orderBy'  => $atts['orderby'],
				'featured' => '1' === (string) $atts['featured'],
				'level'    => $atts['level'],
				'price'    => $atts['price'],
			)
		);
	}

	/**
	 * Enroll box.
	 *
	 * @param array $attributes { courseId }.
	 * @return string
	 */
	public static function render_enroll_button( $attributes = array() ) {
		$course_id = self::course_id( (array) $attributes );

		if ( ! $course_id ) {
			return '';
		}

		$course = shapelms()->get( CourseService::class )->to_array( $course_id, false );

		return '<div class="shapelms">' . shapelms_get_template( 'parts/enroll-box.php', array( 'course' => $course ), false ) . '</div>';
	}

	/**
	 * Curriculum accordion.
	 *
	 * @param array $attributes { courseId }.
	 * @return string
	 */
	public static function render_curriculum( $attributes = array() ) {
		$course_id = self::course_id( (array) $attributes );

		if ( ! $course_id ) {
			return '';
		}

		$course = shapelms()->get( CourseService::class )->to_array( $course_id, true );

		return '<div class="shapelms">' . shapelms_get_template( 'parts/curriculum.php', array( 'course' => $course ), false ) . '</div>';
	}

	/**
	 * Dashboard.
	 *
	 * @return string
	 */
	public static function render_dashboard() {
		return '<div class="shapelms shapelms-wrap"' . \ShapeLMS\Frontend\Frontend::data_attributes() . '>' . Dashboard::render() . '</div>';
	}

	/**
	 * Checkout.
	 *
	 * @return string
	 */
	public static function render_checkout() {
		return '<div class="shapelms shapelms-wrap">' . shapelms_get_template( 'checkout.php', array(), false ) . '</div>';
	}

	/**
	 * Instructor registration.
	 *
	 * @return string
	 */
	public static function render_instructor_registration() {
		return '<div class="shapelms shapelms-wrap">' . shapelms_get_template( 'instructor-register.php', array(), false ) . '</div>';
	}

	/**
	 * Sign in page (also hosts the lost-password and reset-password steps).
	 *
	 * @return string
	 */
	public static function render_login() {
		return '<div class="shapelms shapelms-wrap">' . \ShapeLMS\Frontend\Auth::render( 'login' ) . '</div>';
	}

	/**
	 * Sign up page.
	 *
	 * @return string
	 */
	public static function render_register() {
		return '<div class="shapelms shapelms-wrap">' . \ShapeLMS\Frontend\Auth::render( 'register' ) . '</div>';
	}
}
