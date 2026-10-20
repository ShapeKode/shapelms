<?php
/**
 * Taxonomies.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Content;

use ShapeLMS\Core\Capabilities;

/**
 * Registers course categories and tags.
 */
class Taxonomies {

	const CATEGORY = 'shapelms_course_category';
	const TAG      = 'shapelms_course_tag';

	/**
	 * Registers the taxonomies.
	 *
	 * @return void
	 */
	public function register() {
		$caps = array(
			'manage_terms' => Capabilities::MANAGE,
			'edit_terms'   => Capabilities::MANAGE,
			'delete_terms' => Capabilities::MANAGE,
			'assign_terms' => Capabilities::EDIT_COURSES,
		);

		register_taxonomy(
			self::CATEGORY,
			PostTypes::COURSE,
			array(
				'labels'            => array(
					'name'          => __( 'Course Categories', 'shapelms' ),
					'singular_name' => __( 'Course Category', 'shapelms' ),
					'search_items'  => __( 'Search Categories', 'shapelms' ),
					'all_items'     => __( 'All Categories', 'shapelms' ),
					'edit_item'     => __( 'Edit Category', 'shapelms' ),
					'add_new_item'  => __( 'Add New Category', 'shapelms' ),
					'menu_name'     => __( 'Categories', 'shapelms' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'shapelms-course-categories',
				'rewrite'           => array(
					'slug'         => 'course-category',
					'hierarchical' => true,
				),
				'capabilities'      => $caps,
			)
		);

		register_taxonomy(
			self::TAG,
			PostTypes::COURSE,
			array(
				'labels'            => array(
					'name'          => __( 'Course Tags', 'shapelms' ),
					'singular_name' => __( 'Course Tag', 'shapelms' ),
					'search_items'  => __( 'Search Tags', 'shapelms' ),
					'all_items'     => __( 'All Tags', 'shapelms' ),
					'edit_item'     => __( 'Edit Tag', 'shapelms' ),
					'add_new_item'  => __( 'Add New Tag', 'shapelms' ),
					'menu_name'     => __( 'Tags', 'shapelms' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'shapelms-course-tags',
				'rewrite'           => array( 'slug' => 'course-tag' ),
				'capabilities'      => $caps,
			)
		);
	}
}
