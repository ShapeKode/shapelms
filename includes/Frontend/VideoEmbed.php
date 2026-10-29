<?php
/**
 * Video embed helper.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds privacy-friendly iframes for YouTube and Vimeo without a network call; falls back to oEmbed.
 */
class VideoEmbed {

	/**
	 * YouTube video id from any common URL form.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function youtube_id( $url ) {
		$patterns = array(
			'#youtu\.be/([A-Za-z0-9_-]{6,})#',
			'#youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)([A-Za-z0-9_-]{6,})#',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $match ) ) {
				return $match[1];
			}
		}

		return '';
	}

	/**
	 * Vimeo video id.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function vimeo_id( $url ) {
		if ( preg_match( '#vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)#', $url, $match ) ) {
			return $match[1];
		}

		return '';
	}

	/**
	 * Iframe HTML for a YouTube or Vimeo URL, or empty when the URL is not recognised.
	 *
	 * @param string $url      URL.
	 * @param string $title    Accessible title.
	 * @param bool   $autoplay Start playing as soon as the player loads (for modals opened by a click).
	 * @return string
	 */
	public static function iframe( $url, $title = '', $autoplay = false ) {
		$src = '';

		$youtube = self::youtube_id( $url );
		if ( $youtube ) {
			$src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $youtube ) . '?rel=0&modestbranding=1' . ( $autoplay ? '&autoplay=1' : '' );
		}

		$vimeo = self::vimeo_id( $url );
		if ( ! $src && $vimeo ) {
			$src = 'https://player.vimeo.com/video/' . rawurlencode( $vimeo ) . '?dnt=1' . ( $autoplay ? '&autoplay=1' : '' );
		}

		if ( ! $src ) {
			return '';
		}

		return sprintf(
			'<iframe src="%s" title="%s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_url( $src ),
			esc_attr( $title ? $title : __( 'Video', 'shapelms' ) )
		);
	}

	/**
	 * Best-effort embed HTML: direct iframe for YouTube/Vimeo, otherwise oEmbed.
	 *
	 * @param string $url      URL.
	 * @param string $title    Accessible title.
	 * @param bool   $autoplay Autoplay (YouTube/Vimeo only).
	 * @return string
	 */
	public static function html( $url, $title = '', $autoplay = false ) {
		$iframe = self::iframe( $url, $title, $autoplay );

		if ( $iframe ) {
			return $iframe;
		}

		// Cache the provider lookup so the server does not call the provider on every page view.
		$cache_key = 'shapelms_oembed_' . md5( $url );
		$oembed    = get_transient( $cache_key );

		if ( false === $oembed ) {
			$oembed = (string) wp_oembed_get( $url );
			set_transient( $cache_key, $oembed, $oembed ? DAY_IN_SECONDS : HOUR_IN_SECONDS );
		}

		return (string) $oembed;
	}
}
