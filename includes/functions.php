<?php
/**
 * Global helper functions.
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a plugin setting.
 *
 * @param string $section Settings section id.
 * @param string $key     Field key.
 * @param mixed  $fallback Default value when the field is not set.
 * @return mixed
 */
function shapelms_get_setting( $section, $key, $fallback = null ) {
	return \ShapeLMS\Core\Settings::get( $section, $key, $fallback );
}

/**
 * Returns the full table name for a ShapeLMS table.
 *
 * @param string $table Short table name, e.g. "enrollments".
 * @return string
 */
function shapelms_table( $table ) {
	global $wpdb;

	return esc_sql( $wpdb->prefix . 'shapelms_' . sanitize_key( $table ) );
}

/**
 * Checks whether the current user can manage the LMS.
 *
 * @return bool
 */
function shapelms_current_user_can_manage() {
	return current_user_can( 'manage_shapelms' );
}

/**
 * Returns the current time in MySQL format (UTC).
 *
 * @return string
 */
function shapelms_now() {
	return current_time( 'mysql', true );
}

/**
 * Loads an overridable template.
 *
 * Looks in theme/shapelms/{$template} first, then in the plugin templates folder.
 *
 * @param string $template Template path relative to the templates folder (e.g. "course/single.php").
 * @param array  $args     Variables made available to the template.
 * @param bool   $output   Whether to echo (true) or return (false) the template.
 * @return string
 */
function shapelms_get_template( $template, $args = array(), $output = true ) {
	$located = locate_template( 'shapelms/' . $template );

	if ( ! $located ) {
		$located = SHAPELMS_DIR . 'templates/' . $template;
	}

	/**
	 * Filters the located template path.
	 *
	 * @param string $located  Located template path.
	 * @param string $template Requested template.
	 * @param array  $args     Template arguments.
	 */
	$located = apply_filters( 'shapelms_locate_template', $located, $template, $args );

	if ( ! file_exists( $located ) ) {
		return '';
	}

	if ( ! $output ) {
		ob_start();
	}

	load_template( $located, false, $args );

	if ( ! $output ) {
		return (string) ob_get_clean();
	}

	return '';
}

/**
 * Formats a duration in seconds for display.
 *
 * @param int $seconds Duration in seconds.
 * @return string
 */
function shapelms_format_duration( $seconds ) {
	$seconds = absint( $seconds );
	$hours   = (int) floor( $seconds / 3600 );
	$minutes = (int) floor( ( $seconds % 3600 ) / 60 );

	if ( $hours > 0 ) {
		/* translators: 1: hours, 2: minutes */
		return sprintf( __( '%1$dh %2$dm', 'shapelms' ), $hours, $minutes );
	}

	/* translators: %d: minutes */
	return sprintf( __( '%dm', 'shapelms' ), $minutes );
}

/**
 * Formats a price for display using the plugin currency settings.
 *
 * @param float $amount Amount.
 * @return string
 */
function shapelms_format_price( $amount ) {
	return \ShapeLMS\Services\CurrencyService::format( (float) $amount );
}

/**
 * Sanitizes quiz text that is shown as plain text (answer options, accepted answers,
 * student answers). Unlike sanitize_text_field() it keeps "<" and ">", so a quiz can
 * ask about literal markup like "<h1>". The value is always escaped on output.
 *
 * @param mixed $value      Raw value.
 * @param int   $max_length Maximum length in characters.
 * @return string
 */
function shapelms_sanitize_plain_text( $value, $max_length = 1000 ) {
	$value = wp_check_invalid_utf8( (string) $value, true );
	$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
	$value = trim( (string) preg_replace( '/ {2,}/', ' ', $value ) );

	return mb_substr( $value, 0, max( 1, (int) $max_length ) );
}

/**
 * Embed HTML for a lesson's video (YouTube, Vimeo, self-hosted or raw embed), or empty.
 *
 * @param int  $lesson_id Lesson id.
 * @param bool $autoplay  Autoplay (YouTube/Vimeo only, used for preview modals).
 * @return string
 */
function shapelms_lesson_video_html( $lesson_id, $autoplay = false ) {
	$source = (string) get_post_meta( $lesson_id, '_shapelms_video_source', true );
	$url    = (string) get_post_meta( $lesson_id, '_shapelms_video_url', true );
	$embed  = (string) get_post_meta( $lesson_id, '_shapelms_video_embed', true );
	$html   = '';

	switch ( $source ) {
		case 'youtube':
		case 'vimeo':
			$html = $url ? \ShapeLMS\Frontend\VideoEmbed::html( $url, get_the_title( $lesson_id ), $autoplay ) : '';
			break;

		case 'html5':
			if ( $url ) {
				$html = wp_video_shortcode(
					array(
						'src'     => $url,
						'preload' => 'metadata',
					)
				);
			}
			break;

		case 'embed':
			$html = \ShapeLMS\Content\Meta::sanitize_embed( $embed );
			break;
	}

	/**
	 * Filters the lesson video HTML.
	 *
	 * @param string $html      Embed HTML.
	 * @param int    $lesson_id Lesson id.
	 * @param string $source    Video source.
	 */
	return (string) apply_filters( 'shapelms_lesson_video_html', $html, (int) $lesson_id, $source );
}

/**
 * Whether a lesson has a playable video.
 *
 * @param int $lesson_id Lesson id.
 * @return bool
 */
function shapelms_lesson_has_video( $lesson_id ) {
	$source = (string) get_post_meta( $lesson_id, '_shapelms_video_source', true );

	if ( ! $source || 'none' === $source ) {
		return false;
	}

	return 'embed' === $source ? '' !== (string) get_post_meta( $lesson_id, '_shapelms_video_embed', true ) : '' !== (string) get_post_meta( $lesson_id, '_shapelms_video_url', true );
}

/**
 * URL of the front-end Sign in page (falls back to wp-login.php when the page is missing).
 *
 * @param string $redirect Where to go after logging in.
 * @return string
 */
function shapelms_login_url( $redirect = '' ) {
	$page_id = \ShapeLMS\Core\Pages::get_id( 'login' );

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return wp_login_url( $redirect );
	}

	$url = (string) get_permalink( $page_id );

	return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;
}

/**
 * URL of the front-end Sign up page (falls back to wp-login.php?action=register).
 *
 * @param string $redirect Where to go after signing up.
 * @return string
 */
function shapelms_register_url( $redirect = '' ) {
	$page_id = \ShapeLMS\Core\Pages::get_id( 'register' );

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return wp_registration_url();
	}

	$url = (string) get_permalink( $page_id );

	return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;
}

/**
 * URL of the front-end lost-password step (on the Sign in page).
 *
 * @return string
 */
function shapelms_lostpassword_url() {
	$page_id = \ShapeLMS\Core\Pages::get_id( 'login' );

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		return wp_lostpassword_url();
	}

	return add_query_arg( 'step', 'lost', (string) get_permalink( $page_id ) );
}
