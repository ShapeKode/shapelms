<?php
/**
 * Post meta registration.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Content;

/**
 * Registers typed, sanitized meta for courses, lessons, quizzes and questions.
 */
class Meta {

	/**
	 * Returns the meta definitions per post type: key => [ type, default, single, sanitize? ].
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function definitions() {
		$definitions = array(
			PostTypes::COURSE   => array(
				'_shapelms_level'          => array(
					'type'     => 'string',
					'default'  => 'all',
					'sanitize' => array( __CLASS__, 'sanitize_level' ),
				),
				'_shapelms_duration'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_requirements'   => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'string',
					'sanitize' => array( __CLASS__, 'sanitize_string_list' ),
				),
				'_shapelms_outcomes'       => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'string',
					'sanitize' => array( __CLASS__, 'sanitize_string_list' ),
				),
				'_shapelms_audience'       => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'string',
					'sanitize' => array( __CLASS__, 'sanitize_string_list' ),
				),
				'_shapelms_intro_video'    => array(
					'type'     => 'string',
					'default'  => '',
					'sanitize' => 'esc_url_raw',
				),
				'_shapelms_price_type'     => array(
					'type'     => 'string',
					'default'  => 'free',
					'sanitize' => array( __CLASS__, 'sanitize_price_type' ),
				),
				'_shapelms_price'          => array(
					'type'    => 'number',
					'default' => 0,
				),
				'_shapelms_sale_price'     => array(
					'type'    => 'number',
					'default' => 0,
				),
				'_shapelms_max_students'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_instructors'    => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'integer',
					'sanitize' => array( __CLASS__, 'sanitize_int_list' ),
				),
				'_shapelms_woo_product_id' => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_featured'       => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			PostTypes::LESSON   => array(
				'_shapelms_video_source'   => array(
					'type'     => 'string',
					'default'  => 'none',
					'sanitize' => array( __CLASS__, 'sanitize_video_source' ),
				),
				'_shapelms_video_url'      => array(
					'type'     => 'string',
					'default'  => '',
					'sanitize' => 'esc_url_raw',
				),
				'_shapelms_video_embed'    => array(
					'type'     => 'string',
					'default'  => '',
					'sanitize' => array( __CLASS__, 'sanitize_embed' ),
				),
				'_shapelms_video_duration' => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_attachments'    => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'integer',
					'sanitize' => array( __CLASS__, 'sanitize_int_list' ),
				),
				'_shapelms_preview'        => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			PostTypes::QUIZ     => array(
				'_shapelms_time_limit'         => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_attempts_allowed'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'_shapelms_pass_mark'          => array(
					'type'    => 'integer',
					'default' => 70,
				),
				'_shapelms_randomize'          => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'_shapelms_feedback_mode'      => array(
					'type'     => 'string',
					'default'  => 'end',
					'sanitize' => array( __CLASS__, 'sanitize_feedback_mode' ),
				),
				'_shapelms_show_answers'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'_shapelms_questions_per_page' => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
			PostTypes::QUESTION => array(
				'_shapelms_type'        => array(
					'type'     => 'string',
					'default'  => 'single_choice',
					'sanitize' => 'sanitize_key',
				),
				'_shapelms_points'      => array(
					'type'    => 'number',
					'default' => 1,
				),
				'_shapelms_options'     => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'object',
					'sanitize' => array( __CLASS__, 'sanitize_options' ),
				),
				'_shapelms_correct'     => array(
					'type'     => 'array',
					'default'  => array(),
					'items'    => 'string',
					'sanitize' => array( __CLASS__, 'sanitize_string_list' ),
				),
				'_shapelms_explanation' => array(
					'type'     => 'string',
					'default'  => '',
					'sanitize' => 'wp_kses_post',
				),
				'_shapelms_settings'    => array(
					'type'     => 'object',
					'default'  => array(),
					'sanitize' => array( __CLASS__, 'sanitize_settings' ),
				),
			),
		);

		/**
		 * Filters the meta definitions.
		 *
		 * @param array $definitions Definitions keyed by post type.
		 */
		return apply_filters( 'shapelms_meta_definitions', $definitions );
	}

	/**
	 * Registers meta.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( self::definitions() as $post_type => $fields ) {
			foreach ( $fields as $key => $field ) {
				$args = array(
					'type'          => $field['type'],
					'single'        => true,
					'default'       => $field['default'],
					'show_in_rest'  => $this->rest_schema( $field ),
					'auth_callback' => array( $this, 'auth' ),
				);

				if ( isset( $field['sanitize'] ) ) {
					$args['sanitize_callback'] = $field['sanitize'];
				}

				register_post_meta( $post_type, $key, $args );
			}
		}
	}

	/**
	 * Builds the REST schema for a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>|bool
	 */
	private function rest_schema( array $field ) {
		if ( 'array' === $field['type'] ) {
			$items = isset( $field['items'] ) ? $field['items'] : 'string';

			if ( 'object' === $items ) {
				return array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				);
			}

			return array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => $items ),
				),
			);
		}

		if ( 'object' === $field['type'] ) {
			return array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
			);
		}

		return true;
	}

	/**
	 * Only users who can edit the post may write its meta.
	 *
	 * @param bool   $allowed  Current value.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post id.
	 * @return bool
	 */
	public function auth( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitizes a list of strings.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	public static function sanitize_string_list( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', array_map( 'strval', $value ) ),
				static function ( $item ) {
					return '' !== $item;
				}
			)
		);
	}

	/**
	 * Sanitizes a list of ints.
	 *
	 * @param mixed $value Value.
	 * @return int[]
	 */
	public static function sanitize_int_list( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * Sanitizes the level.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_level( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'all', 'beginner', 'intermediate', 'advanced' ), true ) ? $value : 'all';
	}

	/**
	 * Sanitizes the price type.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_price_type( $value ) {
		return 'paid' === $value ? 'paid' : 'free';
	}

	/**
	 * Sanitizes the video source.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_video_source( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'none', 'youtube', 'vimeo', 'html5', 'embed' ), true ) ? $value : 'none';
	}

	/**
	 * Sanitizes the feedback mode.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_feedback_mode( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'instant', 'end', 'none' ), true ) ? $value : 'end';
	}

	/**
	 * Sanitizes embed code: only iframes from known video hosts are kept.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_embed( $value ) {
		$allowed = array(
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'frameborder'     => true,
				'title'           => true,
				'loading'         => true,
			),
		);

		$clean = wp_kses( (string) $value, $allowed );

		if ( ! preg_match( '#src=["\'](https?://[^"\']+)["\']#i', $clean, $match ) ) {
			return '';
		}

		$host = wp_parse_url( $match[1], PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		/**
		 * Filters the hosts allowed in lesson embed codes.
		 *
		 * @param string[] $hosts Allowed hosts (suffix match).
		 */
		$hosts = apply_filters( 'shapelms_allowed_embed_hosts', array( 'youtube.com', 'youtube-nocookie.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', 'wistia.com', 'wistia.net', 'loom.com', 'bunny.net', 'mediadelivery.net', 'dailymotion.com', 'vidyard.com' ) );

		foreach ( $hosts as $allowed_host ) {
			if ( $host === $allowed_host || substr( $host, -strlen( '.' . $allowed_host ) ) === '.' . $allowed_host ) {
				return $clean;
			}
		}

		return '';
	}

	/**
	 * Sanitizes answer options: [ { id, text, image? } ].
	 *
	 * @param mixed $value Value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_options( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$clean[] = array(
				'id'    => isset( $option['id'] ) ? sanitize_key( (string) $option['id'] ) : wp_generate_password( 8, false ),
				'text'  => isset( $option['text'] ) ? wp_kses_post( (string) $option['text'] ) : '',
				'image' => isset( $option['image'] ) ? absint( $option['image'] ) : 0,
			);
		}

		return $clean;
	}

	/**
	 * Sanitizes a free-form settings object (scalars only, one level deep).
	 *
	 * @param mixed $value Value.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $key => $item ) {
			$key = sanitize_key( (string) $key );
			if ( is_bool( $item ) ) {
				$clean[ $key ] = $item;
			} elseif ( is_numeric( $item ) ) {
				$clean[ $key ] = $item + 0;
			} elseif ( is_string( $item ) ) {
				$clean[ $key ] = sanitize_text_field( $item );
			} elseif ( is_array( $item ) ) {
				$clean[ $key ] = self::sanitize_string_list( $item );
			}
		}

		return $clean;
	}
}
