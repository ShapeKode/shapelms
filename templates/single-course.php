<?php
/**
 * Single course template.
 *
 * Override: theme/shapelms/single-course.php
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

the_post();

$shapelms_course_id = get_the_ID();
$shapelms_courses   = shapelms()->get( \ShapeLMS\Services\CourseService::class );
$shapelms_course    = $shapelms_courses->to_array( $shapelms_course_id, true );
$shapelms_level_map = array(
	'all'          => __( 'All levels', 'shapelms' ),
	'beginner'     => __( 'Beginner', 'shapelms' ),
	'intermediate' => __( 'Intermediate', 'shapelms' ),
	'advanced'     => __( 'Advanced', 'shapelms' ),
);
?>
<div class="shapelms-wrap shapelms-course" data-course-id="<?php echo esc_attr( (string) $shapelms_course_id ); ?>">

	<header class="shapelms-course__hero">
		<div class="shapelms-course__hero-inner">
			<?php if ( ! empty( $shapelms_course['categories'] ) ) : ?>
				<div class="shapelms-course__cats">
					<?php foreach ( $shapelms_course['categories'] as $shapelms_cat ) : ?>
						<a href="<?php echo esc_url( $shapelms_cat['url'] ); ?>"><?php echo esc_html( $shapelms_cat['name'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<h1 class="shapelms-course__title"><?php the_title(); ?></h1>

			<?php if ( $shapelms_course['excerpt'] ) : ?>
				<p class="shapelms-course__excerpt"><?php echo esc_html( $shapelms_course['excerpt'] ); ?></p>
			<?php endif; ?>

			<ul class="shapelms-course__facts">
				<?php if ( $shapelms_course['rating']['count'] > 0 ) : ?>
					<li class="shapelms-rating">
						<span class="shapelms-rating__star">★</span>
						<?php echo esc_html( number_format_i18n( $shapelms_course['rating']['average'], 1 ) ); ?>
						<small>(<?php echo esc_html( sprintf( /* translators: %d: reviews */ _n( '%d review', '%d reviews', $shapelms_course['rating']['count'], 'shapelms' ), $shapelms_course['rating']['count'] ) ); ?>)</small>
					</li>
				<?php endif; ?>
				<li><?php echo esc_html( sprintf( /* translators: %d: students */ _n( '%d student', '%d students', $shapelms_course['students'], 'shapelms' ), $shapelms_course['students'] ) ); ?></li>
				<li><?php echo esc_html( isset( $shapelms_level_map[ $shapelms_course['level'] ] ) ? $shapelms_level_map[ $shapelms_course['level'] ] : $shapelms_level_map['all'] ); ?></li>
				<li><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Updated %s', 'shapelms' ), get_the_modified_date() ) ); ?></li>
			</ul>

			<?php if ( ! empty( $shapelms_course['instructors'] ) ) : ?>
				<div class="shapelms-course__instructors">
					<?php foreach ( $shapelms_course['instructors'] as $shapelms_instructor ) : ?>
						<?php
						if ( ! $shapelms_instructor ) {
							continue;
						}
						?>
						<a class="shapelms-avatar-chip" href="<?php echo esc_url( $shapelms_instructor['url'] ); ?>">
							<img src="<?php echo esc_url( $shapelms_instructor['avatar'] ); ?>" alt="" width="32" height="32">
							<span><?php echo esc_html( $shapelms_instructor['name'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</header>

	<div class="shapelms-course__layout">
		<main class="shapelms-course__main">

			<?php if ( ! empty( $shapelms_course['outcomes'] ) ) : ?>
				<section class="shapelms-box">
					<h2 class="shapelms-box__title"><?php esc_html_e( "What you'll learn", 'shapelms' ); ?></h2>
					<ul class="shapelms-checklist">
						<?php foreach ( $shapelms_course['outcomes'] as $shapelms_item ) : ?>
							<li><?php echo esc_html( $shapelms_item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<section class="shapelms-box" id="curriculum">
				<h2 class="shapelms-box__title"><?php esc_html_e( 'Course content', 'shapelms' ); ?></h2>
				<p class="shapelms-muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: topics, 2: lessons, 3: quizzes */
							__( '%1$d sections • %2$d lessons • %3$d quizzes', 'shapelms' ),
							$shapelms_course['topics'],
							$shapelms_course['lessons'],
							$shapelms_course['quizzes']
						)
					);
					?>
				</p>
				<?php shapelms_get_template( 'parts/curriculum.php', array( 'course' => $shapelms_course ) ); ?>
			</section>

			<?php if ( ! empty( $shapelms_course['requirements'] ) ) : ?>
				<section class="shapelms-box">
					<h2 class="shapelms-box__title"><?php esc_html_e( 'Requirements', 'shapelms' ); ?></h2>
					<ul class="shapelms-list">
						<?php foreach ( $shapelms_course['requirements'] as $shapelms_item ) : ?>
							<li><?php echo esc_html( $shapelms_item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( get_the_content() ) : ?>
				<section class="shapelms-box shapelms-course__description">
					<h2 class="shapelms-box__title"><?php esc_html_e( 'Description', 'shapelms' ); ?></h2>
					<div class="shapelms-prose"><?php the_content(); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $shapelms_course['audience'] ) ) : ?>
				<section class="shapelms-box">
					<h2 class="shapelms-box__title"><?php esc_html_e( 'Who this course is for', 'shapelms' ); ?></h2>
					<ul class="shapelms-list">
						<?php foreach ( $shapelms_course['audience'] as $shapelms_item ) : ?>
							<li><?php echo esc_html( $shapelms_item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php
			/**
			 * Fires after the main course sections (reviews, Q&A, announcements hook here).
			 *
			 * @param int   $course_id Course id.
			 * @param array $course    Course data.
			 */
			do_action( 'shapelms_single_course_after', $shapelms_course_id, $shapelms_course );
			?>
		</main>

		<aside class="shapelms-course__sidebar">
			<?php shapelms_get_template( 'parts/enroll-box.php', array( 'course' => $shapelms_course ) ); ?>
		</aside>
	</div>
</div>
<?php
get_footer();
