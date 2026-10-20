<?php
/**
 * Course archive template.
 *
 * Override: theme/shapelms/archive-course.php
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$shapelms_title = post_type_archive_title( '', false );
if ( is_tax() ) {
	$shapelms_title = single_term_title( '', false );
}
?>
<div class="shapelms-wrap shapelms-archive">
	<header class="shapelms-archive__header">
		<h1 class="shapelms-archive__title"><?php echo esc_html( $shapelms_title ? $shapelms_title : __( 'Courses', 'shapelms' ) ); ?></h1>
		<?php if ( is_tax() && term_description() ) : ?>
			<div class="shapelms-archive__description"><?php echo wp_kses_post( term_description() ); ?></div>
		<?php endif; ?>
	</header>

	<?php shapelms_get_template( 'parts/filters.php' ); ?>

	<?php if ( have_posts() ) : ?>
		<div class="shapelms-grid shapelms-grid--3">
			<?php
			while ( have_posts() ) :
				the_post();
				shapelms_get_template( 'parts/course-card.php', array( 'course_id' => get_the_ID() ) );
			endwhile;
			?>
		</div>

		<nav class="shapelms-pagination">
			<?php
			the_posts_pagination(
				array(
					'prev_text' => '&larr; ' . esc_html__( 'Previous', 'shapelms' ),
					'next_text' => esc_html__( 'Next', 'shapelms' ) . ' &rarr;',
				)
			);
			?>
		</nav>
	<?php else : ?>
		<div class="shapelms-empty">
			<p><?php esc_html_e( 'No courses found.', 'shapelms' ); ?></p>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
