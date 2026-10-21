<?php
/**
 * Admin menu.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Content\Taxonomies;
use ShapeLMS\Core\Capabilities;

/**
 * One top-level menu with React screens, plus the course builder on the course edit screen.
 */
class Menu {

	const SLUG = 'shapelms';

	/**
	 * React app screens: slug suffix => [ title, capability ].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function screens() {
		$screens = array(
			''            => array( __( 'Dashboard', 'shapelms' ), Capabilities::EDIT_COURSES ),
			'courses'     => array( __( 'Courses', 'shapelms' ), Capabilities::EDIT_COURSES ),
			'students'    => array( __( 'Students', 'shapelms' ), Capabilities::EDIT_COURSES ),
			'quizzes'     => array( __( 'Quiz Attempts', 'shapelms' ), Capabilities::EDIT_COURSES ),
			'instructors' => array( __( 'Instructors', 'shapelms' ), Capabilities::MANAGE ),
			'orders'      => array( __( 'Orders', 'shapelms' ), Capabilities::MANAGE ),
			'reports'     => array( __( 'Reports', 'shapelms' ), Capabilities::EDIT_COURSES ),
			'modules'     => array( __( 'Modules', 'shapelms' ), Capabilities::MANAGE ),
			'settings'    => array( __( 'Settings', 'shapelms' ), Capabilities::MANAGE ),
			'tools'       => array( __( 'Tools', 'shapelms' ), Capabilities::MANAGE ),
		);

		/**
		 * Filters the admin screens.
		 *
		 * @param array $screens Screens.
		 */
		return apply_filters( 'shapelms_admin_screens', $screens );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'activation_redirect' ) );
		add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
		add_action( 'edit_form_after_title', array( $this, 'builder_root' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Adds the menu and submenus.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'ShapeLMS', 'shapelms' ),
			__( 'ShapeLMS', 'shapelms' ),
			Capabilities::EDIT_COURSES,
			self::SLUG,
			array( $this, 'render_app' ),
			'dashicons-welcome-learn-more',
			30
		);

		foreach ( self::screens() as $suffix => $screen ) {
			$slug = $suffix ? self::SLUG . '-' . $suffix : self::SLUG;

			add_submenu_page( self::SLUG, $screen[0], $screen[0], $screen[1], $slug, array( $this, 'render_app' ) );
		}

		add_submenu_page( self::SLUG, __( 'Add New Course', 'shapelms' ), __( 'Add New Course', 'shapelms' ), Capabilities::EDIT_COURSES, 'post-new.php?post_type=' . PostTypes::COURSE );
		add_submenu_page( self::SLUG, __( 'Categories', 'shapelms' ), __( 'Categories', 'shapelms' ), Capabilities::MANAGE, 'edit-tags.php?taxonomy=' . Taxonomies::CATEGORY . '&post_type=' . PostTypes::COURSE );
		add_submenu_page( self::SLUG, __( 'Tags', 'shapelms' ), __( 'Tags', 'shapelms' ), Capabilities::MANAGE, 'edit-tags.php?taxonomy=' . Taxonomies::TAG . '&post_type=' . PostTypes::COURSE );
	}

	/**
	 * Renders the React root. Data goes through data attributes, never inline JS.
	 *
	 * @return void
	 */
	public function render_app() {
		$screen = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = str_replace( self::SLUG . '-', '', $screen );
		$screen = self::SLUG === $screen ? 'dashboard' : $screen;

		printf(
			'<div id="shapelms-admin" class="shapelms-admin-root" data-screen="%s" %s></div>',
			esc_attr( $screen ),
			Assets::data_attributes() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method.
		);
	}

	/**
	 * Renders the course builder root on the course edit screen.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function builder_root( $post ) {
		if ( PostTypes::COURSE !== $post->post_type ) {
			return;
		}

		printf(
			'<div id="shapelms-builder" class="shapelms-builder-root" data-course-id="%d" %s></div>',
			(int) $post->ID,
			Assets::data_attributes() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method.
		);
	}

	/**
	 * Adds a body class on builder screens so the classic editor chrome can be hidden with CSS.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$screen = get_current_screen();

		if ( $screen && PostTypes::COURSE === $screen->post_type && 'post' === $screen->base ) {
			$classes .= ' shapelms-builder-screen';
		}

		return $classes;
	}

	/**
	 * Keeps the ShapeLMS menu open on course and taxonomy screens.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public function highlight_menu( $parent_file ) {
		$screen = get_current_screen();

		if ( $screen && ( PostTypes::COURSE === $screen->post_type || in_array( $screen->taxonomy, array( Taxonomies::CATEGORY, Taxonomies::TAG ), true ) ) ) {
			return self::SLUG;
		}

		return $parent_file;
	}

	/**
	 * Highlights the right submenu.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $submenu_file;
		}

		if ( PostTypes::COURSE === $screen->post_type && 'post' === $screen->base ) {
			return self::SLUG . '-courses';
		}

		if ( in_array( $screen->taxonomy, array( Taxonomies::CATEGORY, Taxonomies::TAG ), true ) ) {
			return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=' . PostTypes::COURSE;
		}

		return $submenu_file;
	}

	/**
	 * Redirects to the setup wizard after the first activation.
	 *
	 * @return void
	 */
	public function activation_redirect() {
		if ( ! get_transient( 'shapelms_activation_redirect' ) || wp_doing_ajax() || is_network_admin() ) {
			return;
		}

		delete_transient( 'shapelms_activation_redirect' );

		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by core.
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE ) || get_option( 'shapelms_setup_complete' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-tools&tab=setup' ) );
		exit;
	}
}
