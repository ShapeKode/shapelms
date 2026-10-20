<?php
/**
 * Reviews section (single course).
 *
 * @var array $args { course_id, course }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Modules\Reviews\ReviewService;

$shapelms_course_id = (int) $args['course_id'];
$shapelms_user_id   = get_current_user_id();
$shapelms_list      = ReviewService::list( $shapelms_course_id, 1, 10 );
$shapelms_aggregate = $shapelms_list['aggregate'];
$shapelms_mine      = ReviewService::user_review( $shapelms_user_id, $shapelms_course_id );
$shapelms_enrolled  = $shapelms_user_id && shapelms()->get( \ShapeLMS\Services\EnrollmentService::class )->is_enrolled( $shapelms_user_id, $shapelms_course_id );
?>
<section class="shapelms-box shapelms-reviews" id="reviews" <?php echo \ShapeLMS\Frontend\Frontend::data_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<h2 class="shapelms-box__title"><?php esc_html_e( 'Student reviews', 'shapelms' ); ?></h2>

	<?php if ( $shapelms_aggregate['count'] > 0 ) : ?>
		<div class="shapelms-rating-summary">
			<div>
				<div class="shapelms-rating-summary__big"><?php echo esc_html( number_format_i18n( $shapelms_aggregate['average'], 1 ) ); ?></div>
				<div class="shapelms-rating"><span class="shapelms-rating__star">★★★★★</span></div>
				<div class="shapelms-muted"><?php echo esc_html( sprintf( /* translators: %d: reviews */ _n( '%d review', '%d reviews', $shapelms_aggregate['count'], 'shapelms' ), $shapelms_aggregate['count'] ) ); ?></div>
			</div>
			<div class="shapelms-rating-summary__bars">
				<?php for ( $shapelms_star = 5; $shapelms_star >= 1; $shapelms_star-- ) : ?>
					<?php $shapelms_pct = $shapelms_aggregate['count'] ? round( ( $shapelms_aggregate['breakdown'][ $shapelms_star ] / $shapelms_aggregate['count'] ) * 100 ) : 0; ?>
					<div class="shapelms-rating-summary__row">
						<span><?php echo esc_html( sprintf( /* translators: %d: stars */ __( '%d star', 'shapelms' ), $shapelms_star ) ); ?></span>
						<div class="shapelms-progress"><span class="shapelms-progress__bar" data-percent="<?php echo esc_attr( (string) $shapelms_pct ); ?>"></span></div>
						<span class="shapelms-muted"><?php echo esc_html( $shapelms_pct . '%' ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $shapelms_enrolled ) : ?>
		<form class="shapelms-review-form" data-shapelms-form="reviews" data-reload="1" data-success="<?php esc_attr_e( 'Thanks for your review!', 'shapelms' ); ?>">
			<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $shapelms_course_id ); ?>">
			<h3><?php echo $shapelms_mine ? esc_html__( 'Update your review', 'shapelms' ) : esc_html__( 'Leave a review', 'shapelms' ); ?></h3>
			<div class="shapelms-field">
				<span class="shapelms-stars" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'shapelms' ); ?>">
					<?php for ( $shapelms_star = 5; $shapelms_star >= 1; $shapelms_star-- ) : ?>
						<input type="radio" id="shapelms-star-<?php echo esc_attr( (string) $shapelms_star ); ?>" name="rating" value="<?php echo esc_attr( (string) $shapelms_star ); ?>" <?php checked( $shapelms_mine ? $shapelms_mine['rating'] : 5, $shapelms_star ); ?> required>
						<label for="shapelms-star-<?php echo esc_attr( (string) $shapelms_star ); ?>" data-value="<?php echo esc_attr( (string) $shapelms_star ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ __( '%d stars', 'shapelms' ), $shapelms_star ) ); ?>">★</label>
					<?php endfor; ?>
				</span>
			</div>
			<div class="shapelms-field">
				<label for="shapelms-review-content"><?php esc_html_e( 'Your review', 'shapelms' ); ?></label>
				<textarea id="shapelms-review-content" class="shapelms-textarea" name="content" maxlength="2000"><?php echo esc_textarea( $shapelms_mine ? $shapelms_mine['content'] : '' ); ?></textarea>
			</div>
			<button type="submit" class="shapelms-btn shapelms-btn--primary"><?php esc_html_e( 'Submit review', 'shapelms' ); ?></button>
			<p data-shapelms-form-message aria-live="polite"></p>
		</form>
	<?php endif; ?>

	<?php if ( $shapelms_list['items'] ) : ?>
		<div class="shapelms-reviews__list">
			<?php foreach ( $shapelms_list['items'] as $shapelms_review ) : ?>
				<article class="shapelms-review-card">
					<div class="shapelms-review-card__head">
						<img src="<?php echo esc_url( $shapelms_review['avatar'] ); ?>" alt="" width="36" height="36" loading="lazy">
						<div>
							<div class="shapelms-review-card__name"><?php echo esc_html( $shapelms_review['name'] ); ?></div>
							<div class="shapelms-review-card__date"><?php echo esc_html( $shapelms_review['date'] ); ?></div>
						</div>
						<span class="shapelms-rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ __( '%d out of 5', 'shapelms' ), $shapelms_review['rating'] ) ); ?>"><span class="shapelms-rating__star"><?php echo esc_html( str_repeat( '★', $shapelms_review['rating'] ) ); ?></span></span>
					</div>
					<?php if ( $shapelms_review['content'] ) : ?>
						<p><?php echo esc_html( $shapelms_review['content'] ); ?></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php elseif ( ! $shapelms_enrolled ) : ?>
		<p class="shapelms-muted"><?php esc_html_e( 'No reviews yet.', 'shapelms' ); ?></p>
	<?php endif; ?>
</section>
