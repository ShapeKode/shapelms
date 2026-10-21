<?php
/**
 * Course card.
 *
 * @var array $args { course_id }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_course_id = isset( $args['course_id'] ) ? (int) $args['course_id'] : get_the_ID();
$shapelms_course    = shapelms()->get( \ShapeLMS\Services\CourseService::class )->to_array( $shapelms_course_id, false );

if ( ! $shapelms_course ) {
	return;
}

$shapelms_instructor = ! empty( $shapelms_course['instructors'][0] ) ? $shapelms_course['instructors'][0] : null;
$shapelms_level_map  = array(
	'all'          => __( 'All levels', 'shapelms' ),
	'beginner'     => __( 'Beginner', 'shapelms' ),
	'intermediate' => __( 'Intermediate', 'shapelms' ),
	'advanced'     => __( 'Advanced', 'shapelms' ),
);
?>
<article class="shapelms-card" data-course-id="<?php echo esc_attr( (string) $shapelms_course['id'] ); ?>">
	<a class="shapelms-card__media" href="<?php echo esc_url( $shapelms_course['url'] ); ?>" aria-hidden="true" tabindex="-1">
		<?php if ( $shapelms_course['image'] ) : ?>
			<img src="<?php echo esc_url( $shapelms_course['image'] ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<span class="shapelms-card__placeholder"></span>
		<?php endif; ?>
		<?php if ( ! empty( $shapelms_course['categories'][0] ) ) : ?>
			<span class="shapelms-card__badge"><?php echo esc_html( $shapelms_course['categories'][0]['name'] ); ?></span>
		<?php endif; ?>
	</a>

	<div class="shapelms-card__body">
		<h3 class="shapelms-card__title">
			<a href="<?php echo esc_url( $shapelms_course['url'] ); ?>"><?php echo esc_html( $shapelms_course['title'] ); ?></a>
		</h3>

		<?php if ( $shapelms_instructor ) : ?>
			<div class="shapelms-card__instructor">
				<img src="<?php echo esc_url( $shapelms_instructor['avatar'] ); ?>" alt="" width="24" height="24" loading="lazy">
				<span><?php echo esc_html( $shapelms_instructor['name'] ); ?></span>
			</div>
		<?php endif; ?>

		<ul class="shapelms-card__meta">
			<li><?php echo esc_html( sprintf( /* translators: %d: lessons */ _n( '%d lesson', '%d lessons', $shapelms_course['lessons'], 'shapelms' ), $shapelms_course['lessons'] ) ); ?></li>
			<?php if ( $shapelms_course['duration'] ) : ?>
				<li><?php echo esc_html( shapelms_format_duration( $shapelms_course['duration'] ) ); ?></li>
			<?php endif; ?>
			<li><?php echo esc_html( isset( $shapelms_level_map[ $shapelms_course['level'] ] ) ? $shapelms_level_map[ $shapelms_course['level'] ] : $shapelms_level_map['all'] ); ?></li>
		</ul>

		<div class="shapelms-card__footer">
			<?php if ( $shapelms_course['rating']['count'] > 0 ) : ?>
				<span class="shapelms-rating" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: rating, 2: number of students */ _n( 'Rated %1$s out of 5 by %2$d student', 'Rated %1$s out of 5 by %2$d students', $shapelms_course['rating']['count'], 'shapelms' ), number_format_i18n( $shapelms_course['rating']['average'], 1 ), $shapelms_course['rating']['count'] ) ); ?>">
					<span class="shapelms-rating__star">★</span>
					<?php echo esc_html( number_format_i18n( $shapelms_course['rating']['average'], 1 ) ); ?>
					<small>(<?php echo esc_html( number_format_i18n( $shapelms_course['rating']['count'] ) ); ?>)</small>
				</span>
			<?php else : ?>
				<span class="shapelms-card__students"><?php echo esc_html( sprintf( /* translators: %d: students */ _n( '%d student', '%d students', $shapelms_course['students'], 'shapelms' ), $shapelms_course['students'] ) ); ?></span>
			<?php endif; ?>

			<span class="shapelms-price">
				<?php if ( $shapelms_course['pricing']['on_sale'] ) : ?>
					<del><?php echo esc_html( shapelms_format_price( $shapelms_course['pricing']['price'] ) ); ?></del>
				<?php endif; ?>
				<strong><?php echo esc_html( $shapelms_course['price_html'] ); ?></strong>
			</span>
		</div>
	</div>
</article>
