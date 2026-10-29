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

global $wp_query;

$shapelms_title = post_type_archive_title( '', false );
if ( is_tax() ) {
	$shapelms_title = single_term_title( '', false );
}

$shapelms_layout  = 'top' === shapelms_get_setting( 'general', 'archive_layout', 'sidebar' ) ? 'top' : 'sidebar';
$shapelms_columns = (int) shapelms_get_setting( 'general', 'archive_columns', 3 );
$shapelms_columns = in_array( $shapelms_columns, array( 2, 3, 4 ), true ) ? $shapelms_columns : 3;
$shapelms_found   = (int) $wp_query->found_posts;
$shapelms_per     = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
$shapelms_paged   = max( 1, (int) $wp_query->get( 'paged' ) );
$shapelms_from    = $shapelms_found ? ( $shapelms_paged - 1 ) * $shapelms_per + 1 : 0;
$shapelms_to      = min( $shapelms_found, $shapelms_paged * $shapelms_per );
$shapelms_active  = \ShapeLMS\Frontend\Frontend::active_filters();
?>
<div class="shapelms-wrap shapelms-archive shapelms-archive--<?php echo esc_attr( $shapelms_layout ); ?> shapelms-archive--cols-<?php echo esc_attr( (string) $shapelms_columns ); ?>">
	<header class="shapelms-archive__header">
		<h1 class="shapelms-archive__title"><?php echo esc_html( $shapelms_title ? $shapelms_title : __( 'Courses', 'shapelms' ) ); ?></h1>
		<?php if ( is_tax() && term_description() ) : ?>
			<div class="shapelms-archive__description"><?php echo wp_kses_post( term_description() ); ?></div>
		<?php else : ?>
			<p class="shapelms-archive__description shapelms-muted"><?php esc_html_e( 'Browse the catalogue, pick a course and start learning today.', 'shapelms' ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( 'top' === $shapelms_layout ) : ?>
		<?php shapelms_get_template( 'parts/filters.php', array( 'layout' => 'top' ) ); ?>
	<?php endif; ?>

	<div class="shapelms-archive__layout">
		<?php if ( 'sidebar' === $shapelms_layout ) : ?>
			<aside class="shapelms-archive__filters" id="shapelms-archive-filters" aria-label="<?php esc_attr_e( 'Filter courses', 'shapelms' ); ?>">
				<?php shapelms_get_template( 'parts/filters.php', array( 'layout' => 'sidebar' ) ); ?>
			</aside>
		<?php endif; ?>

		<main class="shapelms-archive__main">
			<div class="shapelms-archive__toolbar">
				<p class="shapelms-archive__count">
					<?php
					if ( $shapelms_found ) {
						echo esc_html(
							sprintf(
								/* translators: 1: first item, 2: last item, 3: total */
								_n( 'Showing %1$d–%2$d of %3$d course', 'Showing %1$d–%2$d of %3$d courses', $shapelms_found, 'shapelms' ),
								$shapelms_from,
								$shapelms_to,
								$shapelms_found
							)
						);
					} else {
						esc_html_e( 'No courses match your filters', 'shapelms' );
					}
					?>
				</p>

				<div class="shapelms-archive__tools">
					<?php if ( $shapelms_active ) : ?>
						<a class="shapelms-archive__clear" href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Clear filters', 'shapelms' ); ?></a>
					<?php endif; ?>

					<?php if ( 'sidebar' === $shapelms_layout ) : ?>
						<button type="button" class="shapelms-btn shapelms-btn--secondary shapelms-archive__toggle" data-shapelms-filters-toggle aria-controls="shapelms-archive-filters" aria-expanded="false">
							<?php esc_html_e( 'Filters', 'shapelms' ); ?>
							<?php if ( $shapelms_active ) : ?>
								<span class="shapelms-archive__toggle-count"><?php echo esc_html( (string) count( $shapelms_active ) ); ?></span>
							<?php endif; ?>
						</button>
						<?php shapelms_get_template( 'parts/filters.php', array( 'layout' => 'sort' ) ); ?>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( have_posts() ) : ?>
				<div class="shapelms-grid">
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
					<span class="shapelms-empty__icon" aria-hidden="true"></span>
					<h2><?php esc_html_e( 'No courses found', 'shapelms' ); ?></h2>
					<p class="shapelms-muted"><?php esc_html_e( 'Try a different search or remove some filters.', 'shapelms' ); ?></p>
					<?php if ( $shapelms_active ) : ?>
						<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( (string) get_post_type_archive_link( \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Clear filters', 'shapelms' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</main>
	</div>
</div>
<?php
get_footer();
