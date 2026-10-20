<?php
/**
 * Instructor service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Events;

/**
 * Instructor profiles, applications and course ownership.
 */
class InstructorService {

	const META_STATUS   = 'shapelms_instructor_status';
	const META_BIO      = 'shapelms_bio';
	const META_HEADLINE = 'shapelms_headline';
	const META_SOCIAL   = 'shapelms_social';

	/**
	 * Whether a user teaches a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public static function is_course_instructor( $user_id, $course_id ) {
		$course_id = \ShapeLMS\Content\Curriculum::course_id_for( $course_id );

		if ( ! $course_id ) {
			return false;
		}

		return in_array( (int) $user_id, CourseService::instructor_ids( $course_id ), true );
	}

	/**
	 * Public profile data.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|null
	 */
	public static function public_profile( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		$social = get_user_meta( $user_id, self::META_SOCIAL, true );

		return array(
			'id'       => (int) $user->ID,
			'name'     => $user->display_name,
			'slug'     => $user->user_nicename,
			'avatar'   => get_avatar_url( $user->ID, array( 'size' => 160 ) ),
			'headline' => (string) get_user_meta( $user_id, self::META_HEADLINE, true ),
			'bio'      => (string) get_user_meta( $user_id, self::META_BIO, true ),
			'social'   => is_array( $social ) ? $social : array(),
			'url'      => get_author_posts_url( $user->ID ),
		);
	}

	/**
	 * Application status: '', 'pending', 'approved', 'rejected', 'blocked'.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function status( $user_id ) {
		if ( user_can( $user_id, Capabilities::EDIT_COURSES ) ) {
			return 'approved';
		}

		return (string) get_user_meta( $user_id, self::META_STATUS, true );
	}

	/**
	 * Submits an instructor application for an existing user.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $profile { headline, bio }.
	 * @return true|\WP_Error
	 */
	public static function apply( $user_id, array $profile = array() ) {
		if ( ! shapelms_get_setting( 'instructor', 'allow_registration', true ) ) {
			return new \WP_Error( 'shapelms_registration_closed', __( 'Instructor registration is closed.', 'shapelms' ) );
		}

		$status = self::status( $user_id );

		if ( in_array( $status, array( 'approved', 'pending', 'blocked' ), true ) ) {
			return new \WP_Error( 'shapelms_already_applied', __( 'You have already applied or are already an instructor.', 'shapelms' ) );
		}

		self::save_profile( $user_id, $profile );

		if ( shapelms_get_setting( 'instructor', 'require_approval', true ) ) {
			update_user_meta( $user_id, self::META_STATUS, 'pending' );
			Events::fire( Events::INSTRUCTOR_APPLIED, $user_id );
			return true;
		}

		self::approve( $user_id );

		return true;
	}

	/**
	 * Approves an instructor.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function approve( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		if ( ! user_can( $user_id, Capabilities::MANAGE ) ) {
			$user->add_role( Capabilities::INSTRUCTOR );
		}

		update_user_meta( $user_id, self::META_STATUS, 'approved' );
		Events::fire( Events::INSTRUCTOR_APPROVED, $user_id );
	}

	/**
	 * Rejects or blocks an instructor.
	 *
	 * @param int    $user_id User id.
	 * @param string $status  rejected|blocked.
	 * @return void
	 */
	public static function reject( $user_id, $status = 'rejected' ) {
		$user = get_userdata( $user_id );

		if ( $user ) {
			$user->remove_role( Capabilities::INSTRUCTOR );
		}

		update_user_meta( $user_id, self::META_STATUS, 'blocked' === $status ? 'blocked' : 'rejected' );
	}

	/**
	 * Saves profile fields.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $profile { headline, bio, social }.
	 * @return void
	 */
	public static function save_profile( $user_id, array $profile ) {
		if ( isset( $profile['headline'] ) ) {
			update_user_meta( $user_id, self::META_HEADLINE, sanitize_text_field( (string) $profile['headline'] ) );
		}
		if ( isset( $profile['bio'] ) ) {
			update_user_meta( $user_id, self::META_BIO, wp_kses_post( (string) $profile['bio'] ) );
		}
		if ( isset( $profile['social'] ) && is_array( $profile['social'] ) ) {
			$social = array();
			foreach ( array( 'website', 'twitter', 'linkedin', 'youtube', 'facebook', 'github' ) as $network ) {
				if ( ! empty( $profile['social'][ $network ] ) ) {
					$social[ $network ] = esc_url_raw( (string) $profile['social'][ $network ] );
				}
			}
			update_user_meta( $user_id, self::META_SOCIAL, $social );
		}
	}

	/**
	 * Course ids taught by a user.
	 *
	 * @param int      $user_id User id.
	 * @param string[] $status  Statuses.
	 * @return int[]
	 */
	public static function course_ids( $user_id, array $status = array( 'publish', 'draft', 'pending', 'private' ) ) {
		$own = get_posts(
			array(
				'post_type'     => PostTypes::COURSE,
				'author'        => (int) $user_id,
				'post_status'   => $status,
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);

		$co = get_posts(
			array(
				'post_type'     => PostTypes::COURSE,
				'post_status'   => $status,
				'numberposts'   => -1,
				'fields'        => 'ids',
				'no_found_rows' => true,
				'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_shapelms_instructors',
						'value'   => sprintf( ':%d;', (int) $user_id ),
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_values( array_unique( array_map( 'intval', array_merge( $own, $co ) ) ) );
	}

	/**
	 * Lists users by instructor status for the admin screen.
	 *
	 * @param string $status pending|approved|rejected|blocked|all.
	 * @param int    $page   Page.
	 * @param int    $per_page Per page.
	 * @param string $search Search.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function list( $status, $page = 1, $per_page = 20, $search = '' ) {
		$args = array(
			'number'  => max( 1, min( 100, (int) $per_page ) ),
			'paged'   => max( 1, (int) $page ),
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( $search ) {
			$args['search']         = '*' . sanitize_text_field( $search ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( 'approved' === $status ) {
			$args['role__in'] = array( Capabilities::INSTRUCTOR, 'administrator' );
		} elseif ( 'all' !== $status ) {
			$args['meta_key']   = self::META_STATUS; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = sanitize_key( $status ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		} else {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => self::META_STATUS,
					'compare' => 'EXISTS',
				),
			);
			$args['role__in']   = array( Capabilities::INSTRUCTOR );
		}

		$query = new \WP_User_Query( $args );
		$items = array();

		foreach ( $query->get_results() as $user ) {
			$items[] = array_merge(
				(array) self::public_profile( $user->ID ),
				array(
					'email'      => $user->user_email,
					'status'     => self::status( $user->ID ),
					'registered' => $user->user_registered,
					'courses'    => count( self::course_ids( $user->ID ) ),
				)
			);
		}

		return array(
			'items' => $items,
			'total' => (int) $query->get_total(),
		);
	}
}
