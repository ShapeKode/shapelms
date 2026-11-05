<?php
/**
 * LMS pages (dashboard, checkout, instructor registration).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and looks up the pages the plugin needs.
 */
class Pages {

	/**
	 * Returns the page definitions.
	 *
	 * @return array<string, array{title: string, content: string}>
	 */
	public static function definitions() {
		return array(
			'dashboard'           => array(
				'title'   => __( 'Dashboard', 'shapelms' ),
				'content' => '<!-- wp:shapelms/dashboard /-->',
			),
			'checkout'            => array(
				'title'   => __( 'Checkout', 'shapelms' ),
				'content' => '<!-- wp:shapelms/checkout /-->',
			),
			'cart'                => array(
				'title'   => __( 'Cart', 'shapelms' ),
				'content' => '<!-- wp:shapelms/cart /-->',
			),
			'instructor_register' => array(
				'title'   => __( 'Become an Instructor', 'shapelms' ),
				'content' => '<!-- wp:shapelms/instructor-registration /-->',
			),
			'login'               => array(
				'title'   => __( 'Sign in', 'shapelms' ),
				'content' => '<!-- wp:shapelms/login /-->',
			),
			'register'            => array(
				'title'   => __( 'Sign up', 'shapelms' ),
				'content' => '<!-- wp:shapelms/register /-->',
			),
		);
	}

	/**
	 * Creates missing pages and stores their ids.
	 *
	 * On activation only pages that were never created are added, so a page the site owner
	 * deleted on purpose does not come back. The setup wizard passes $recreate to restore them.
	 *
	 * @param bool $recreate Also recreate pages that were created before and later deleted.
	 * @return void
	 */
	public static function create_pages( $recreate = false ) {
		$pages = get_option( 'shapelms_pages', array() );
		$pages = is_array( $pages ) ? $pages : array();

		foreach ( self::definitions() as $key => $definition ) {
			if ( ! empty( $pages[ $key ] ) && get_post_status( (int) $pages[ $key ] ) ) {
				continue;
			}
			if ( array_key_exists( $key, $pages ) && ! $recreate ) {
				continue;
			}
			// The cart page is only needed once the "Buy Now" setting is off; turning it off creates the page.
			if ( 'cart' === $key && Settings::get( 'commerce', 'buy_now', true ) ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $definition['title'],
					'post_content'   => $definition['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
				),
				true
			);

			if ( ! is_wp_error( $page_id ) ) {
				$pages[ $key ] = (int) $page_id;
			}
		}

		update_option( 'shapelms_pages', $pages );
		Settings::flush();
	}

	/**
	 * Brings back one page: out of the trash, or as a new page when it is gone.
	 *
	 * @param string $key Page key.
	 * @return int Page id, 0 on failure.
	 */
	public static function restore( $key ) {
		$definitions = self::definitions();

		if ( ! isset( $definitions[ $key ] ) ) {
			return 0;
		}

		$pages   = get_option( 'shapelms_pages', array() );
		$pages   = is_array( $pages ) ? $pages : array();
		$page_id = isset( $pages[ $key ] ) ? (int) $pages[ $key ] : 0;

		if ( $page_id && get_post_status( $page_id ) ) {
			$page_id = (int) wp_update_post(
				array(
					'ID'          => $page_id,
					'post_status' => 'publish',
				)
			);
		} else {
			$page_id = (int) wp_insert_post(
				array(
					'post_title'     => $definitions[ $key ]['title'],
					'post_content'   => $definitions[ $key ]['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
				)
			);
		}

		if ( $page_id ) {
			$pages[ $key ] = $page_id;
			update_option( 'shapelms_pages', $pages );
		}

		return $page_id;
	}

	/**
	 * Returns a page id.
	 *
	 * @param string $key Page key.
	 * @return int
	 */
	public static function get_id( $key ) {
		$page_id = (int) Settings::get( 'general', $key . '_page', 0 );

		if ( ! $page_id ) {
			$pages   = get_option( 'shapelms_pages', array() );
			$page_id = isset( $pages[ $key ] ) ? (int) $pages[ $key ] : 0;
		}

		/**
		 * Filters an LMS page id.
		 *
		 * @param int    $page_id Page id.
		 * @param string $key     Page key.
		 */
		return (int) apply_filters( 'shapelms_page_id', $page_id, $key );
	}

	/**
	 * Returns a page URL.
	 *
	 * @param string $key Page key.
	 * @return string
	 */
	public static function get_url( $key ) {
		$page_id = self::get_id( $key );

		if ( ! $page_id ) {
			return home_url( '/' );
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * Whether the current request is one of the LMS pages.
	 *
	 * @param string $key Page key.
	 * @return bool
	 */
	public static function is_page( $key ) {
		$page_id = self::get_id( $key );

		return $page_id > 0 && is_page( $page_id );
	}
}
