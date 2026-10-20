<?php
/**
 * Roles and capabilities.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

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
		return array(
			'administrator'  => array( self::MANAGE, self::EDIT_COURSES, 'upload_files' ),
			self::INSTRUCTOR => array( self::EDIT_COURSES, 'read', 'upload_files' ),
			self::STUDENT    => array( 'read' ),
		);
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
		if ( 'shapelms_edit_course' !== $cap ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( user_can( $user_id, self::MANAGE ) ) {
			return array( self::MANAGE );
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
