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
$shapelms_html      = shapelms_lesson_video_html( $shapelms_lesson_id );

if ( ! $shapelms_html ) {
	return;
}
?>
<div class="shapelms-video shapelms-video--<?php echo esc_attr( $shapelms_source ); ?>">
	<?php echo $shapelms_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iframe built with esc_url/esc_attr, wp_video_shortcode, or kses-sanitized embed. ?>
</div>
