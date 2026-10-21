<?php
/**
 * Instructors module (front end).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Instructors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Services\InstructorService;

/**
 * Instructor registration page and instructor dashboard tabs.
 */
class Module extends AbstractModule {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'instructors';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Instructor registration', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Front-end instructor registration with admin approval, plus instructor dashboard tabs.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'shapelms_dashboard_tabs', array( $this, 'tabs' ) );
		add_action( 'template_redirect', array( $this, 'handle_registration' ) );
		add_filter( 'shapelms_admin_data', array( $this, 'admin_data' ) );
	}

	/**
	 * Instructor tabs.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tabs( $tabs ) {
		$tabs['instructor-courses']  = array(
			'label'    => __( 'My courses', 'shapelms' ),
			'template' => 'dashboard/instructor-courses.php',
			'section'  => 'instructor',
			'order'    => 10,
		);
		$tabs['instructor-students'] = array(
			'label'    => __( 'Students', 'shapelms' ),
			'template' => 'dashboard/instructor-students.php',
			'section'  => 'instructor',
			'order'    => 20,
		);
		$tabs['instructor-earnings'] = array(
			'label'    => __( 'Earnings', 'shapelms' ),
			'template' => 'dashboard/instructor-earnings.php',
			'section'  => 'instructor',
			'order'    => 50,
		);

		return $tabs;
	}

	/**
	 * Handles the registration form (classic POST with nonce) so it works without JS and creates the user.
	 *
	 * @return void
	 */
	public function handle_registration() {
		if ( ! isset( $_POST['shapelms_instructor_register'] ) ) {
			return;
		}

		if ( ! isset( $_POST['shapelms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shapelms_nonce'] ) ), 'shapelms_instructor_register' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'shapelms' ) );
		}

		if ( ! shapelms_get_setting( 'instructor', 'allow_registration', true ) ) {
			wp_die( esc_html__( 'Instructor registration is closed.', 'shapelms' ) );
		}

		$errors = new \WP_Error();

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			if ( ! get_option( 'users_can_register' ) && ! apply_filters( 'shapelms_allow_instructor_signup', false ) ) {
				$errors->add( 'closed', __( 'Registration is closed.', 'shapelms' ) );
			}

			$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.

			if ( ! is_email( $email ) ) {
				$errors->add( 'email', __( 'Please enter a valid email address.', 'shapelms' ) );
			} elseif ( email_exists( $email ) ) {
				$errors->add( 'email', __( 'An account with this email already exists. Please log in first.', 'shapelms' ) );
			}
			if ( strlen( $password ) < 8 ) {
				$errors->add( 'password', __( 'Password must be at least 8 characters.', 'shapelms' ) );
			}
			if ( ! $name ) {
				$errors->add( 'name', __( 'Please enter your name.', 'shapelms' ) );
			}

			if ( ! $errors->has_errors() ) {
				$username = sanitize_user( current( explode( '@', $email ) ), true );
				$base     = $username;
				$i        = 1;
				while ( username_exists( $username ) ) {
					$username = $base . ( $i++ );
				}

				$user_id = wp_insert_user(
					array(
						'user_login'   => $username,
						'user_email'   => $email,
						'user_pass'    => $password,
						'display_name' => $name,
						'first_name'   => $name,
						'role'         => 'subscriber',
					)
				);

				if ( is_wp_error( $user_id ) ) {
					$errors->add( 'create', $user_id->get_error_message() );
					$user_id = 0;
				} else {
					wp_set_current_user( $user_id );
					wp_set_auth_cookie( $user_id );
				}
			}
		}

		if ( ! $errors->has_errors() && $user_id ) {
			$result = InstructorService::apply(
				$user_id,
				array(
					'headline' => isset( $_POST['headline'] ) ? sanitize_text_field( wp_unslash( $_POST['headline'] ) ) : '',
					'bio'      => isset( $_POST['bio'] ) ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '',
				)
			);

			if ( is_wp_error( $result ) ) {
				$errors->add( $result->get_error_code(), $result->get_error_message() );
			}
		}

		if ( $errors->has_errors() ) {
			set_transient( 'shapelms_reg_errors_' . ( $user_id ? $user_id : md5( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ), $errors->get_error_messages(), 60 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_safe_redirect( add_query_arg( 'registered', 'error', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'registered', '1', \ShapeLMS\Core\Pages::get_url( 'instructor_register' ) ) );
		exit;
	}

	/**
	 * Registration errors for the current visitor.
	 *
	 * @return string[]
	 */
	public static function errors() {
		$key    = get_current_user_id() ? get_current_user_id() : md5( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$errors = get_transient( 'shapelms_reg_errors_' . $key );
		delete_transient( 'shapelms_reg_errors_' . $key );

		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Adds pending count to admin data.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function admin_data( $data ) {
		$pending                     = InstructorService::list( 'pending', 1, 1 );
		$data['pending-instructors'] = (string) $pending['total'];

		return $data;
	}
}
