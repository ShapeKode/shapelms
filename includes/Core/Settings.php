<?php
/**
 * Schema-based settings.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores settings in a single option, validated against a schema modules can extend.
 */
class Settings {

	const OPTION = 'shapelms_settings';

	/**
	 * Cached settings.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $cache = null;

	/**
	 * Returns the settings schema.
	 *
	 * Each section: id => [ label, description, fields => [ key => [ label, type, default, options?, description? ] ] ].
	 * Types: text, textarea, number, checkbox, select, color, page, email.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema() {
		$schema = array(
			'general'    => array(
				'label'       => __( 'General', 'shapelms' ),
				'description' => __( 'Pages, course archive and data handling.', 'shapelms' ),
				'fields'      => array(
					'courses_per_page'         => array(
						'label'   => __( 'Courses per page', 'shapelms' ),
						'type'    => 'number',
						'default' => 12,
						'min'     => 1,
						'max'     => 100,
					),
					'archive_layout'           => array(
						'label'       => __( 'Course archive layout', 'shapelms' ),
						'type'        => 'select',
						'default'     => 'sidebar',
						'options'     => array(
							'sidebar' => __( 'With filter sidebar (left)', 'shapelms' ),
							'top'     => __( 'No sidebar, filter bar above the courses', 'shapelms' ),
						),
						'description' => __( 'Where the search and filter controls appear on the course archive.', 'shapelms' ),
					),
					'archive_columns'          => array(
						'label'       => __( 'Courses per row', 'shapelms' ),
						'type'        => 'select',
						'default'     => '3',
						'options'     => array(
							'2' => __( '2 columns', 'shapelms' ),
							'3' => __( '3 columns', 'shapelms' ),
							'4' => __( '4 columns', 'shapelms' ),
						),
						'description' => __( 'On large screens. Smaller screens show fewer columns automatically.', 'shapelms' ),
					),
					'course_slug'              => array(
						'label'       => __( 'Course URL slug', 'shapelms' ),
						'type'        => 'text',
						'default'     => 'courses',
						'description' => __( 'Save permalinks after changing this.', 'shapelms' ),
					),
					'dashboard_page'           => array(
						'label'   => __( 'Dashboard page', 'shapelms' ),
						'type'    => 'page',
						'default' => 0,
					),
					'checkout_page'            => array(
						'label'   => __( 'Checkout page', 'shapelms' ),
						'type'    => 'page',
						'default' => 0,
					),
					'instructor_register_page' => array(
						'label'   => __( 'Instructor registration page', 'shapelms' ),
						'type'    => 'page',
						'default' => 0,
					),
					'login_page'               => array(
						'label'   => __( 'Sign in page', 'shapelms' ),
						'type'    => 'page',
						'default' => 0,
					),
					'register_page'            => array(
						'label'   => __( 'Sign up page', 'shapelms' ),
						'type'    => 'page',
						'default' => 0,
					),
					'student_registration'     => array(
						'label'       => __( 'Allow student registration', 'shapelms' ),
						'type'        => 'checkbox',
						'default'     => true,
						'description' => __( 'Lets visitors create a student account on the Sign up page. WordPress\'s own "Anyone can register" option (Settings → General) must also be on.', 'shapelms' ),
					),
					'auth_layout'              => array(
						'label'       => __( 'Sign in / Sign up page layout', 'shapelms' ),
						'type'        => 'select',
						'default'     => 'standalone',
						'options'     => array(
							'standalone' => __( 'Standalone (site logo, form, no theme header)', 'shapelms' ),
							'theme'      => __( 'Inside the theme layout', 'shapelms' ),
						),
						'description' => __( 'Standalone gives a clean, focused page on any theme. Choose the theme layout to keep your header and footer.', 'shapelms' ),
					),
					'front_login'              => array(
						'label'       => __( 'Use the Sign in page for all login links', 'shapelms' ),
						'type'        => 'checkbox',
						'default'     => true,
						'description' => __( 'Theme and WordPress login, registration and lost-password links point to the ShapeLMS pages instead of wp-login.php. Administrators can still open wp-login.php directly.', 'shapelms' ),
					),
					'delete_data_on_uninstall' => array(
						'label'       => __( 'Delete all data on uninstall', 'shapelms' ),
						'type'        => 'checkbox',
						'default'     => false,
						'description' => __( 'Removes courses, enrollments, progress and orders when the plugin is deleted.', 'shapelms' ),
					),
				),
			),
			'course'     => array(
				'label'       => __( 'Courses & learning', 'shapelms' ),
				'description' => __( 'How students learn and complete courses.', 'shapelms' ),
				'fields'      => array(
					'enable_reviews'              => array(
						'label'   => __( 'Enable course reviews', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'enable_qa'                   => array(
						'label'   => __( 'Enable course Q&A', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'enable_wishlist'             => array(
						'label'   => __( 'Enable wishlist', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'autoplay_next'               => array(
						'label'   => __( 'Go to the next lesson after completing one', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'completion_requires_quizzes' => array(
						'label'   => __( 'Require passing all quizzes to complete a course', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'sequential_learning'         => array(
						'label'       => __( 'Sequential learning', 'shapelms' ),
						'type'        => 'checkbox',
						'default'     => false,
						'description' => __( 'Lock each lesson and quiz until the previous one is completed. Each course can override this in its builder settings.', 'shapelms' ),
					),
				),
			),
			'instructor' => array(
				'label'       => __( 'Instructors', 'shapelms' ),
				'description' => __( 'Instructor registration and permissions.', 'shapelms' ),
				'fields'      => array(
					'allow_registration' => array(
						'label'   => __( 'Allow instructor registration', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'require_approval'   => array(
						'label'   => __( 'Require admin approval for new instructors', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'can_publish'        => array(
						'label'       => __( 'Instructors can publish courses without review', 'shapelms' ),
						'type'        => 'checkbox',
						'default'     => false,
						'description' => __( 'When off, instructor courses are submitted as pending for an admin to publish.', 'shapelms' ),
					),
				),
			),
			'commerce'   => array(
				'label'       => __( 'Selling', 'shapelms' ),
				'description' => __( 'Choose how paid courses are sold.', 'shapelms' ),
				'fields'      => array(
					'engine'                 => array(
						'label'   => __( 'Commerce engine', 'shapelms' ),
						'type'    => 'select',
						'default' => 'native',
						'options' => array(
							'native'      => __( 'ShapeLMS checkout (Stripe, PayPal)', 'shapelms' ),
							'woocommerce' => __( 'WooCommerce', 'shapelms' ),
						),
					),
					'currency'               => array(
						'label'   => __( 'Currency', 'shapelms' ),
						'type'    => 'select',
						'default' => 'USD',
						'options' => \ShapeLMS\Services\CurrencyService::currency_options(),
					),
					'currency_position'      => array(
						'label'   => __( 'Currency symbol position', 'shapelms' ),
						'type'    => 'select',
						'default' => 'left',
						'options' => array(
							'left'        => __( 'Left ($99)', 'shapelms' ),
							'right'       => __( 'Right (99$)', 'shapelms' ),
							'left_space'  => __( 'Left with space ($ 99)', 'shapelms' ),
							'right_space' => __( 'Right with space (99 $)', 'shapelms' ),
						),
					),
					'decimals'               => array(
						'label'   => __( 'Decimals', 'shapelms' ),
						'type'    => 'number',
						'default' => 2,
						'min'     => 0,
						'max'     => 4,
					),
					'stripe_enabled'         => array(
						'label'   => __( 'Enable Stripe', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => false,
					),
					'stripe_test_mode'       => array(
						'label'   => __( 'Stripe test mode', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'stripe_publishable_key' => array(
						'label'   => __( 'Stripe publishable key', 'shapelms' ),
						'type'    => 'text',
						'default' => '',
					),
					'stripe_secret_key'      => array(
						'label'   => __( 'Stripe secret key', 'shapelms' ),
						'type'    => 'secret',
						'default' => '',
					),
					'stripe_webhook_secret'  => array(
						'label'       => __( 'Stripe webhook signing secret', 'shapelms' ),
						'type'        => 'secret',
						'default'     => '',
						'description' => __( 'Webhook URL: /wp-json/shapelms/v1/webhooks/stripe', 'shapelms' ),
					),
					'paypal_enabled'         => array(
						'label'   => __( 'Enable PayPal', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => false,
					),
					'paypal_sandbox'         => array(
						'label'   => __( 'PayPal sandbox mode', 'shapelms' ),
						'type'    => 'checkbox',
						'default' => true,
					),
					'paypal_client_id'       => array(
						'label'   => __( 'PayPal client ID', 'shapelms' ),
						'type'    => 'text',
						'default' => '',
					),
					'paypal_secret'          => array(
						'label'   => __( 'PayPal secret', 'shapelms' ),
						'type'    => 'secret',
						'default' => '',
					),
					'paypal_webhook_id'      => array(
						'label'       => __( 'PayPal webhook ID', 'shapelms' ),
						'type'        => 'text',
						'default'     => '',
						'description' => __( 'Webhook URL: /wp-json/shapelms/v1/webhooks/paypal. Add it in your PayPal app, then paste the webhook ID PayPal shows. Refunds are only processed when this is set.', 'shapelms' ),
					),
				),
			),
			'emails'     => array(
				'label'       => __( 'Emails', 'shapelms' ),
				'description' => __( 'Sender details and which notifications are sent.', 'shapelms' ),
				'fields'      => array(
					'from_name'   => array(
						'label'   => __( 'From name', 'shapelms' ),
						'type'    => 'text',
						'default' => get_bloginfo( 'name' ),
					),
					'from_email'  => array(
						'label'   => __( 'From email', 'shapelms' ),
						'type'    => 'email',
						'default' => get_bloginfo( 'admin_email' ),
					),
					'footer_text' => array(
						'label'   => __( 'Footer text', 'shapelms' ),
						'type'    => 'textarea',
						'default' => get_bloginfo( 'name' ),
					),
				),
			),
			'design'     => array(
				'label'       => __( 'Design', 'shapelms' ),
				'description' => __( 'Colors used by the course pages, player and dashboard.', 'shapelms' ),
				'fields'      => array(
					'primary_color' => array(
						'label'   => __( 'Primary color', 'shapelms' ),
						'type'    => 'color',
						'default' => '#3b5bdb',
					),
					'radius'        => array(
						'label'   => __( 'Corner radius (px)', 'shapelms' ),
						'type'    => 'number',
						'default' => 8,
						'min'     => 0,
						'max'     => 32,
					),
				),
			),
		);

		/**
		 * Filters the settings schema so modules can add sections and fields.
		 *
		 * @param array $schema Settings schema.
		 */
		return apply_filters( 'shapelms_settings_schema', $schema );
	}

	/**
	 * Returns all settings merged with defaults.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$all    = array();

		$pages = get_option( 'shapelms_pages', array() );
		$pages = is_array( $pages ) ? $pages : array();

		foreach ( self::schema() as $section_id => $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$all[ $section_id ][ $key ] = isset( $stored[ $section_id ][ $key ] )
					? $stored[ $section_id ][ $key ]
					: $field['default'];

				// Page fields fall back to the pages created by the setup wizard.
				if ( 'page' === $field['type'] && empty( $all[ $section_id ][ $key ] ) ) {
					$page_key                   = str_replace( '_page', '', $key );
					$all[ $section_id ][ $key ] = isset( $pages[ $page_key ] ) ? (int) $pages[ $page_key ] : 0;
				}
			}
		}

		self::$cache = $all;

		return $all;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $section Section id.
	 * @param string $key     Field key.
	 * @param mixed  $fallback Default when the field is unknown.
	 * @return mixed
	 */
	public static function get( $section, $key, $fallback = null ) {
		$all = self::all();

		return isset( $all[ $section ][ $key ] ) ? $all[ $section ][ $key ] : $fallback;
	}

	/**
	 * Returns settings safe to send to the admin app (secrets masked).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function for_client() {
		$all = self::all();

		foreach ( self::schema() as $section_id => $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				if ( 'secret' === $field['type'] && ! empty( $all[ $section_id ][ $key ] ) ) {
					$all[ $section_id ][ $key ] = '********';
				}
			}
		}

		return $all;
	}

	/**
	 * Validates and saves settings.
	 *
	 * @param array<string, mixed> $input Raw input keyed by section then field.
	 * @return array<string, array<string, mixed>> Saved settings.
	 */
	public static function save( array $input ) {
		$current = self::all();
		$clean   = $current;

		foreach ( self::schema() as $section_id => $section ) {
			if ( ! isset( $input[ $section_id ] ) || ! is_array( $input[ $section_id ] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $key => $field ) {
				if ( ! array_key_exists( $key, $input[ $section_id ] ) ) {
					continue;
				}

				$value = $input[ $section_id ][ $key ];

				if ( 'secret' === $field['type'] && '********' === $value ) {
					continue;
				}

				$clean[ $section_id ][ $key ] = self::sanitize_field( $field, $value );
			}
		}

		/**
		 * Filters settings before they are saved.
		 *
		 * @param array $clean Sanitized settings.
		 * @param array $input Raw input.
		 */
		$clean = apply_filters( 'shapelms_save_settings', $clean, $input );

		update_option( self::OPTION, $clean );
		self::$cache = null;

		return self::all();
	}

	/**
	 * Sanitizes a value against its field definition.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function sanitize_field( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return rest_sanitize_boolean( $value );

			case 'number':
			case 'page':
				$number = is_numeric( $value ) ? $value + 0 : $field['default'];
				if ( isset( $field['min'] ) ) {
					$number = max( $field['min'], $number );
				}
				if ( isset( $field['max'] ) ) {
					$number = min( $field['max'], $number );
				}
				return $number;

			case 'select':
				$value = sanitize_text_field( (string) $value );
				return isset( $field['options'][ $value ] ) ? $value : $field['default'];

			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return $color ? $color : $field['default'];

			case 'email':
				return sanitize_email( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'secret':
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Clears the cache (for tests).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}
}
