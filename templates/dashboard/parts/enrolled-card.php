<?php
/**
 * Enrolled course card (dashboard).
 *
 * @var array $args { item: { enrollment, course }, resume }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_course     = $args['item']['course'];
$shapelms_enrollment = $args['item']['enrollment'];
$shapelms_resume     = isset( $args['resume'] ) ? (int) $args['resume'] : 0;
$shapelms_completed  = 'completed' === $shapelms_enrollment['status'];
?>
<article class="shapelms-enrolled-card">
	<a href="<?php echo esc_url( $shapelms_course['url'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $shapelms_course['image'] ) : ?>
			<img src="<?php echo esc_url( $shapelms_course['image'] ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<span class="shapelms-card__placeholder"></span>
		<?php endif; ?>
	</a>
	<div>
		<h3><a href="<?php echo esc_url( $shapelms_course['url'] ); ?>"><?php echo esc_html( $shapelms_course['title'] ); ?></a></h3>
		<?php if ( ! empty( $shapelms_course['instructors'][0] ) ) : ?>
			<p class="shapelms-muted"><?php echo esc_html( $shapelms_course['instructors'][0]['name'] ); ?></p>
		<?php endif; ?>
		<div class="shapelms-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $shapelms_enrollment['progress'] ); ?>" aria-valuemin="0" aria-valuemax="100">
			<span class="shapelms-progress__bar" data-percent="<?php echo esc_attr( (string) $shapelms_enrollment['progress'] ); ?>"></span>
		</div>
		<div class="shapelms-enrolled-card__footer">
			<span class="shapelms-muted">
				<?php if ( $shapelms_completed ) : ?>
					<span class="shapelms-tag shapelms-tag--success"><?php esc_html_e( 'Completed', 'shapelms' ); ?></span>
				<?php else : ?>
					<?php echo esc_html( sprintf( /* translators: %d: percent */ __( '%d%% complete', 'shapelms' ), $shapelms_enrollment['progress'] ) ); ?>
				<?php endif; ?>
			</span>
			<?php if ( $shapelms_resume && ! $shapelms_completed ) : ?>
				<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( (string) get_permalink( $shapelms_resume ) ); ?>"><?php echo $shapelms_enrollment['progress'] > 0 ? esc_html__( 'Continue', 'shapelms' ) : esc_html__( 'Start', 'shapelms' ); ?></a>
			<?php else : ?>
				<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( $shapelms_course['url'] ); ?>"><?php esc_html_e( 'View course', 'shapelms' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</article>
