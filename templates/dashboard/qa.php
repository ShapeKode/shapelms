<?php
/**
 * Dashboard: instructor Q&A inbox.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_ids     = \ShapeLMS\Services\InstructorService::course_ids( (int) $args['user']->ID );
$shapelms_threads = array();

foreach ( $shapelms_ids as $shapelms_course_id ) {
	foreach ( \ShapeLMS\Modules\QA\Module::threads( $shapelms_course_id ) as $shapelms_thread ) {
		$shapelms_thread['course_id']    = $shapelms_course_id;
		$shapelms_thread['course_title'] = get_the_title( $shapelms_course_id );
		$shapelms_threads[]              = $shapelms_thread;
	}
}

usort(
	$shapelms_threads,
	static function ( $a, $b ) {
		return $b['id'] <=> $a['id'];
	}
);
/**
 * Filters how many questions the instructor inbox shows per page.
 *
 * @param int $per_page Questions per page.
 */
$shapelms_per_page = max( 1, (int) apply_filters( 'shapelms_dashboard_qa_per_page', 20 ) );
$shapelms_total    = count( $shapelms_threads );
$shapelms_pages    = (int) ceil( $shapelms_total / $shapelms_per_page );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
$shapelms_paged   = isset( $_GET['qa_page'] ) ? max( 1, absint( wp_unslash( $_GET['qa_page'] ) ) ) : 1;
$shapelms_paged   = min( $shapelms_paged, max( 1, $shapelms_pages ) );
$shapelms_threads = array_slice( $shapelms_threads, ( $shapelms_paged - 1 ) * $shapelms_per_page, $shapelms_per_page );
?>
<h2><?php esc_html_e( 'Questions from students', 'shapelms' ); ?></h2>

<?php if ( $shapelms_threads ) : ?>
	<div class="shapelms-qa__list">
		<?php foreach ( $shapelms_threads as $shapelms_thread ) : ?>
			<article class="shapelms-qa__item<?php echo empty( $shapelms_thread['answers'] ) ? ' is-unanswered' : ''; ?>">
				<div class="shapelms-qa__head">
					<img src="<?php echo esc_url( $shapelms_thread['avatar'] ); ?>" alt="" width="36" height="36" loading="lazy">
					<div>
						<span class="shapelms-qa__name"><?php echo esc_html( $shapelms_thread['name'] ); ?></span>
						<div class="shapelms-qa__date">
							<?php echo esc_html( $shapelms_thread['date'] ); ?> ·
							<?php echo esc_html( $shapelms_thread['course_title'] ); ?>
							<?php if ( $shapelms_thread['item_title'] ) : ?>
								· <a href="<?php echo esc_url( $shapelms_thread['item_url'] . '#qa' ); ?>"><?php echo esc_html( $shapelms_thread['item_title'] ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( empty( $shapelms_thread['answers'] ) ) : ?>
						<span class="shapelms-tag shapelms-tag--warning"><?php esc_html_e( 'Unanswered', 'shapelms' ); ?></span>
					<?php endif; ?>
				</div>
				<p><?php echo esc_html( $shapelms_thread['content'] ); ?></p>

				<div class="shapelms-qa__answers">
					<?php foreach ( $shapelms_thread['answers'] as $shapelms_answer ) : ?>
						<div class="shapelms-qa__answer">
							<span class="shapelms-qa__name"><?php echo esc_html( $shapelms_answer['name'] ); ?></span>
							<span class="shapelms-qa__date"><?php echo esc_html( $shapelms_answer['date'] ); ?></span>
							<p><?php echo esc_html( $shapelms_answer['content'] ); ?></p>
						</div>
					<?php endforeach; ?>
					<form data-shapelms-form="qa" data-reload="1">
						<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $shapelms_thread['course_id'] ); ?>">
						<input type="hidden" name="parent" value="<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>">
						<div class="shapelms-field">
							<label class="screen-reader-text" for="shapelms-qa-answer-<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>"><?php esc_html_e( 'Your answer', 'shapelms' ); ?></label>
							<textarea id="shapelms-qa-answer-<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>" class="shapelms-textarea" name="content" rows="2" required maxlength="3000" placeholder="<?php esc_attr_e( 'Write an answer…', 'shapelms' ); ?>"></textarea>
						</div>
						<button type="submit" class="shapelms-btn shapelms-btn--secondary"><?php esc_html_e( 'Answer', 'shapelms' ); ?></button>
						<p data-shapelms-form-message aria-live="polite"></p>
					</form>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( $shapelms_pages > 1 ) : ?>
		<nav class="shapelms-pagination" aria-label="<?php esc_attr_e( 'Questions pages', 'shapelms' ); ?>">
			<div class="nav-links">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => add_query_arg( 'qa_page', '%#%', \ShapeLMS\Frontend\Dashboard::url( 'qa' ) ),
							'format'    => '',
							'current'   => $shapelms_paged,
							'total'     => $shapelms_pages,
							'prev_text' => '&larr; ' . esc_html__( 'Previous', 'shapelms' ),
							'next_text' => esc_html__( 'Next', 'shapelms' ) . ' &rarr;',
						)
					)
				);
				?>
			</div>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No questions yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
