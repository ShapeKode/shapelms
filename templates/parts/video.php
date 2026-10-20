<?php
/**
 * Lesson video.
 *
 * @var array $args { lesson_id }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_lesson_id = isset( $args['lesson_id'] ) ? (int) $args['lesson_id'] : get_the_ID();
$shapelms_source    = (string) get_post_meta( $shapelms_lesson_id, '_shapelms_video_source', true );
$shapelms_url       = (string) get_post_meta( $shapelms_lesson_id, '_shapelms_video_url', true );
$shapelms_embed     = (string) get_post_meta( $shapelms_lesson_id, '_shapelms_video_embed', true );

if ( 'none' === $shapelms_source || ! $shapelms_source ) {
	return;
}

$shapelms_html = '';

switch ( $shapelms_source ) {
	case 'youtube':
	case 'vimeo':
		$shapelms_html = $shapelms_url ? (string) wp_oembed_get( $shapelms_url ) : '';
		break;

	case 'html5':
		if ( $shapelms_url ) {
			$shapelms_html = wp_video_shortcode(
				array(
					'src'     => $shapelms_url,
					'preload' => 'metadata',
				)
			);
		}
		break;

	case 'embed':
		$shapelms_html = \ShapeLMS\Content\Meta::sanitize_embed( $shapelms_embed );
		break;
}

if ( ! $shapelms_html ) {
	return;
}
?>
<div class="shapelms-video shapelms-video--<?php echo esc_attr( $shapelms_source ); ?>">
	<?php echo $shapelms_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oEmbed / wp_video_shortcode / kses-sanitized iframe. ?>
</div>
