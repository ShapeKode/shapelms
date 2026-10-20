<?php
/**
 * Lesson attachments.
 *
 * @var array $args { lesson_id }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_lesson_id   = isset( $args['lesson_id'] ) ? (int) $args['lesson_id'] : get_the_ID();
$shapelms_attachments = get_post_meta( $shapelms_lesson_id, '_shapelms_attachments', true );

if ( empty( $shapelms_attachments ) || ! is_array( $shapelms_attachments ) ) {
	return;
}
?>
<section class="shapelms-attachments">
	<h2 class="shapelms-attachments__title"><?php esc_html_e( 'Resources', 'shapelms' ); ?></h2>
	<ul class="shapelms-attachments__list">
		<?php foreach ( array_map( 'absint', $shapelms_attachments ) as $shapelms_attachment_id ) : ?>
			<?php
			$shapelms_file_url = wp_get_attachment_url( $shapelms_attachment_id );
			if ( ! $shapelms_file_url ) {
				continue;
			}
			$shapelms_file = get_attached_file( $shapelms_attachment_id );
			$shapelms_size = $shapelms_file && file_exists( $shapelms_file ) ? size_format( (int) filesize( $shapelms_file ) ) : '';
			?>
			<li>
				<a href="<?php echo esc_url( $shapelms_file_url ); ?>" download>
					<span class="shapelms-attachments__icon" aria-hidden="true"></span>
					<span class="shapelms-attachments__name"><?php echo esc_html( get_the_title( $shapelms_attachment_id ) ); ?></span>
					<?php if ( $shapelms_size ) : ?>
						<span class="shapelms-muted"><?php echo esc_html( $shapelms_size ); ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
