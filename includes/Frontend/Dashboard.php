<?php
/**
 * Student / instructor dashboard.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Capabilities;
use ShapeLMS\Frontend\Auth;

/**
 * Collects dashboard tabs from modules and renders the dashboard shell.
 */
class Dashboard {

	/**
	 * Returns the tabs the current user can see, grouped by section and sorted.
	 *
	 * @return array<string, array<string, array<string, mixed>>> section => id => tab.
	 */
	public static function tabs() {
		$tabs = array(
			'overview' => array(
				'label'    => __( 'Overview', 'shapelms' ),
				'template' => 'dashboard/overview.php',
				'section'  => 'student',
				'order'    => 0,
			),
			'courses'  => array(
				'label'    => __( 'My learning', 'shapelms' ),
				'template' => 'dashboard/courses.php',
				'section'  => 'student',
				'order'    => 10,
			),
			'quizzes'  => array(
				'label'    => __( 'Quiz attempts', 'shapelms' ),
				'template' => 'dashboard/quizzes.php',
				'section'  => 'student',
				'order'    => 20,
			),
			'profile'  => array(
				'label'    => __( 'Profile', 'shapelms' ),
				'template' => 'dashboard/profile.php',
				'section'  => 'account',
				'order'    => 10,
			),
			'settings' => array(
				'label'    => __( 'Settings', 'shapelms' ),
				'template' => 'dashboard/settings.php',
				'section'  => 'account',
				'order'    => 20,
			),
		);

		/**
		 * Filters the dashboard tabs. Each tab: label, template, section (student|instructor|account), order.
		 *
		 * @param array $tabs Tabs.
		 */
		$tabs = apply_filters( 'shapelms_dashboard_tabs', $tabs );

		$is_instructor = current_user_can( Capabilities::EDIT_COURSES );
		$grouped       = array(
			'student'    => array(),
			'instructor' => array(),
			'account'    => array(),
		);

		foreach ( $tabs as $id => $tab ) {
			$section = isset( $tab['section'] ) ? $tab['section'] : 'student';
			if ( 'instructor' === $section && ! $is_instructor ) {
				continue;
			}
			$grouped[ $section ][ $id ] = $tab;
		}

		foreach ( $grouped as &$group ) {
			uasort(
				$group,
				static function ( $a, $b ) {
					return ( $a['order'] ?? 50 ) <=> ( $b['order'] ?? 50 );
				}
			);
		}

		return array_filter( $grouped );
	}

	/**
	 * Current tab id.
	 *
	 * @return string
	 */
	public static function current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		foreach ( self::tabs() as $group ) {
			if ( isset( $group[ $tab ] ) ) {
				return $tab;
			}
		}

		return 'overview';
	}

	/**
	 * Renders the dashboard.
	 *
	 * @return string
	 */
	public static function render() {
		if ( ! is_user_logged_in() ) {
			return Auth::render( 'login' );
		}

		return shapelms_get_template(
			'dashboard.php',
			array(
				'tabs'    => self::tabs(),
				'current' => self::current(),
				'user'    => wp_get_current_user(),
			),
			false
		);
	}

	/**
	 * Section labels.
	 *
	 * @param string $section Section id.
	 * @return string
	 */
	public static function section_label( $section ) {
		$labels = array(
			'student'    => __( 'Learning', 'shapelms' ),
			'instructor' => __( 'Teaching', 'shapelms' ),
			'account'    => __( 'Account', 'shapelms' ),
		);

		return isset( $labels[ $section ] ) ? $labels[ $section ] : ucfirst( $section );
	}

	/**
	 * URL for a tab.
	 *
	 * @param string $tab Tab id.
	 * @return string
	 */
	public static function url( $tab ) {
		return add_query_arg( 'tab', $tab, \ShapeLMS\Core\Pages::get_url( 'dashboard' ) );
	}
}
