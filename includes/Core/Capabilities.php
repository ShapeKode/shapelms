<?php
/**
 * Roles and capabilities.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the instructor and student roles and the LMS capabilities.
 */
class Capabilities {

	const MANAGE       = 'manage_shapelms';
	const EDIT_COURSES = 'edit_shapelms_courses';
	const INSTRUCTOR   = 'shapelms_instructor';
	const STUDENT      = 'shapelms_student';

	/**
	 * Registers roles and hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Returns the capability map for each role.
	 *
	 * @return array<string, string[]>
	 */
	public static function role_caps() {
		$instructor = array(
			self::EDIT_COURSES,
			'read',
			'upload_files',
			'delete_shapelms_courses',
			'edit_published_shapelms_courses',
			'delete_published_shapelms_courses',
		);

		$admin = array_merge(
			$instructor,
			array(
				self::MANAGE,
				'edit_others_shapelms_courses',
				'delete_others_shapelms_courses',
				'publish_shapelms_courses',
				'read_private_shapelms_courses',
				'edit_private_shapelms_courses',
				'delete_private_shapelms_courses',
			)
		);

		return array(
			'administrator'  => $admin,
			self::INSTRUCTOR => $instructor,
			self::STUDENT    => array( 'read' ),
		);
	}

	/**
	 * Re-applies role capabilities once per plugin version (covers upgrades that add caps).
	 *
	 * @return void
	 */
	public function maybe_sync() {
		if ( get_option( 'shapelms_caps_version' ) === SHAPELMS_VERSION ) {
			return;
		}

		$this->add_caps();
		update_option( 'shapelms_caps_version', SHAPELMS_VERSION );
	}

	/**
	 * Creates the roles and adds the capabilities. Called on activation.
	 *
	 * @return void
	 */
	public function add_caps() {
		add_role( self::INSTRUCTOR, __( 'Instructor', 'shapelms' ), array( 'read' => true ) );
		add_role( self::STUDENT, __( 'Student', 'shapelms' ), array( 'read' => true ) );

		foreach ( self::role_caps() as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Maps course-level meta capabilities so instructors can edit only their own content.
	 *
	 * @param string[] $caps    Required capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User id.
	 * @param array    $args    Extra arguments (usually the post id).
	 * @return string[]
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		$lms_types = array( 'shapelms_course', 'shapelms_topic', 'shapelms_lesson', 'shapelms_quiz', 'shapelms_question', 'shapelms_announce' );

		if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) && ! empty( $args[0] ) ) {
			$post = get_post( (int) $args[0] );

			if ( ! $post || ! in_array( $post->post_type, $lms_types, true ) ) {
				return $caps;
			}

			if ( 'read_post' === $cap && 'publish' === $post->post_status ) {
				return array( 'read' );
			}

			$cap = 'shapelms_edit_course';
		}

		if ( 'shapelms_edit_course' !== $cap ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( user_can( $user_id, self::MANAGE ) ) {
			return array( self::MANAGE );
		}

		if ( ! user_can( $user_id, self::EDIT_COURSES ) ) {
			return array( 'do_not_allow' );
		}

		if ( ! $post_id ) {
			return array( self::EDIT_COURSES );
		}

		if ( \ShapeLMS\Services\InstructorService::is_course_instructor( $user_id, $post_id ) ) {
			return array( self::EDIT_COURSES );
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Whether the user is an instructor (has the role or can manage).
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public static function is_instructor( $user_id ) {
		return user_can( $user_id, self::EDIT_COURSES );
	}
}
