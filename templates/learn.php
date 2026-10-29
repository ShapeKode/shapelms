<?php
/**
 * Learning page (lesson or quiz) with curriculum sidebar.
 *
 * Override: theme/shapelms/learn.php
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();

$shapelms_item_id   = get_the_ID();
$shapelms_item_type = \ShapeLMS\Content\Curriculum::type_of( (string) get_post_type() );
$shapelms_item_type = $shapelms_item_type ? $shapelms_item_type : 'lesson';
$shapelms_is_quiz   = 'quiz' === $shapelms_item_type;
$shapelms_is_lesson = 'lesson' === $shapelms_item_type;
$shapelms_course_id = \ShapeLMS\Content\Curriculum::course_id_for( $shapelms_item_id );
$shapelms_tree      = \ShapeLMS\Content\Curriculum::tree( $shapelms_course_id );
$shapelms_neighbors = \ShapeLMS\Content\Curriculum::neighbors( $shapelms_course_id, $shapelms_item_id );
$shapelms_user_id   = get_current_user_id();
$shapelms_enrolled  = $shapelms_user_id && shapelms()->get( \ShapeLMS\Services\EnrollmentService::class )->is_enrolled( $shapelms_user_id, $shapelms_course_id );
$shapelms_summary   = $shapelms_user_id ? shapelms()->get( \ShapeLMS\Services\ProgressService::class )->summary( $shapelms_user_id, $shapelms_course_id ) : null;
$shapelms_statuses  = $shapelms_summary ? $shapelms_summary['statuses'] : array();
$shapelms_done      = isset( $shapelms_statuses[ $shapelms_item_id ] ) && 'completed' === $shapelms_statuses[ $shapelms_item_id ];
$shapelms_locked    = $shapelms_summary ? $shapelms_summary['locked'] : array();
$shapelms_lock_msgs = $shapelms_summary ? $shapelms_summary['lock_messages'] : array();
$shapelms_next_lock = $shapelms_neighbors['next'] && in_array( (int) $shapelms_neighbors['next'], $shapelms_locked, true );
$shapelms_autoplay  = (bool) shapelms_get_setting( 'course', 'autoplay_next', true );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by the lock redirect.
$shapelms_was_lock = isset( $_GET['shapelms_locked'] );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'shapelms-learn-body' ); ?>>
<?php wp_body_open(); ?>

<?php
$shapelms_data_attributes = \ShapeLMS\Frontend\Frontend::data_attributes(
	array(
		'course-id' => (string) $shapelms_course_id,
		'item-id'   => (string) $shapelms_item_id,
		'item-type' => $shapelms_item_type,
		'next-url'  => $shapelms_neighbors['next'] ? (string) get_permalink( $shapelms_neighbors['next'] ) : '',
		'autoplay'  => $shapelms_autoplay ? '1' : '0',
	)
);
?>
<div class="shapelms-learn" id="shapelms-learn" <?php echo $shapelms_data_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<header class="shapelms-learn__bar">
		<button type="button" class="shapelms-learn__toggle" aria-expanded="true" aria-controls="shapelms-learn-sidebar" data-shapelms-toggle-sidebar>
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle course content', 'shapelms' ); ?></span>
			<span class="shapelms-learn__toggle-icon" aria-hidden="true"></span>
		</button>
		<a class="shapelms-learn__course" href="<?php echo esc_url( (string) get_permalink( $shapelms_course_id ) ); ?>">
			<span class="shapelms-learn__back" aria-hidden="true">&larr;</span>
			<?php echo esc_html( get_the_title( $shapelms_course_id ) ); ?>
		</a>
		<?php if ( $shapelms_summary ) : ?>
			<div class="shapelms-learn__progress">
				<div class="shapelms-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $shapelms_summary['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100">
					<span class="shapelms-progress__bar" data-percent="<?php echo esc_attr( (string) $shapelms_summary['percent'] ); ?>"></span>
				</div>
				<span class="shapelms-learn__progress-text" data-shapelms-progress-text><?php echo esc_html( sprintf( /* translators: %d: percent */ __( '%d%% complete', 'shapelms' ), $shapelms_summary['percent'] ) ); ?></span>
			</div>
		<?php endif; ?>
		<a class="shapelms-learn__exit" href="<?php echo esc_url( \ShapeLMS\Core\Pages::get_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Dashboard', 'shapelms' ); ?></a>
	</header>

	<div class="shapelms-learn__body">
		<aside class="shapelms-learn__sidebar" id="shapelms-learn-sidebar">
			<nav aria-label="<?php esc_attr_e( 'Course content', 'shapelms' ); ?>">
				<?php foreach ( $shapelms_tree as $shapelms_topic ) : ?>
					<?php
					$shapelms_open_topic = false;
					foreach ( $shapelms_topic['items'] as $shapelms_check ) {
						if ( (int) $shapelms_check['id'] === $shapelms_item_id ) {
							$shapelms_open_topic = true;
						}
					}
					?>
					<details class="shapelms-learn__topic" <?php echo $shapelms_open_topic ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $shapelms_topic['title'] ); ?></summary>
						<ul>
							<?php foreach ( $shapelms_topic['items'] as $shapelms_item ) : ?>
								<?php
								$shapelms_item_done = isset( $shapelms_statuses[ $shapelms_item['id'] ] ) && 'completed' === $shapelms_statuses[ $shapelms_item['id'] ];
								$shapelms_item_lock = in_array( (int) $shapelms_item['id'], $shapelms_locked, true );
								$shapelms_can_open  = ! $shapelms_item_lock && ( $shapelms_enrolled || ! empty( $shapelms_item['preview'] ) || current_user_can( 'shapelms_edit_course', $shapelms_course_id ) );
								?>
								<li class="shapelms-learn__item shapelms-learn__item--<?php echo esc_attr( $shapelms_item['type'] ); ?><?php echo $shapelms_item_done ? ' is-done' : ''; ?><?php echo $shapelms_item_lock ? ' is-locked' : ''; ?><?php echo (int) $shapelms_item['id'] === $shapelms_item_id ? ' is-current' : ''; ?>" data-item-id="<?php echo esc_attr( (string) $shapelms_item['id'] ); ?>"<?php echo $shapelms_item_lock && $shapelms_enrolled ? ' data-item-url="' . esc_url( $shapelms_item['url'] ) . '"' : ''; ?>>
									<?php if ( $shapelms_can_open ) : ?>
										<a href="<?php echo esc_url( $shapelms_item['url'] ); ?>" <?php echo (int) $shapelms_item['id'] === $shapelms_item_id ? 'aria-current="page"' : ''; ?>>
											<span class="shapelms-learn__check" aria-hidden="true"></span>
											<span class="shapelms-learn__item-title"><?php echo esc_html( $shapelms_item['title'] ); ?></span>
											<?php if ( ! empty( $shapelms_item['duration'] ) ) : ?>
												<span class="shapelms-muted"><?php echo esc_html( shapelms_format_duration( $shapelms_item['duration'] ) ); ?></span>
											<?php endif; ?>
										</a>
									<?php else : ?>
										<span class="shapelms-learn__locked" title="<?php echo $shapelms_item_lock ? esc_attr( $shapelms_lock_msgs[ (int) $shapelms_item['id'] ] ?? '' ) : esc_attr__( 'Enroll to unlock', 'shapelms' ); ?>">
											<span class="shapelms-learn__lock-icon" aria-hidden="true"></span>
											<span class="shapelms-learn__item-title"><?php echo esc_html( $shapelms_item['title'] ); ?></span>
										</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
			</nav>
		</aside>

		<main class="shapelms-learn__main">
			<article class="shapelms-learn__content">
				<?php if ( $shapelms_was_lock ) : ?>
					<p class="shapelms-notice"><?php esc_html_e( 'That lesson is not available yet.', 'shapelms' ); ?></p>
				<?php endif; ?>
				<h1 class="shapelms-learn__title"><?php the_title(); ?></h1>

				<?php if ( $shapelms_is_quiz ) : ?>
					<?php
					shapelms_get_template(
						'parts/quiz-player.php',
						array(
							'quiz_id'  => $shapelms_item_id,
							'enrolled' => $shapelms_enrolled,
						)
					);
					?>
				<?php elseif ( $shapelms_is_lesson ) : ?>
					<?php shapelms_get_template( 'parts/video.php', array( 'lesson_id' => $shapelms_item_id ) ); ?>

					<div class="shapelms-prose"><?php the_content(); ?></div>

					<?php shapelms_get_template( 'parts/attachments.php', array( 'lesson_id' => $shapelms_item_id ) ); ?>
				<?php else : ?>
					<?php
					/**
					 * Renders the content of an add-on item type (for example an assignment).
					 *
					 * @param int    $item_id   Item id.
					 * @param string $type      Item type.
					 * @param int    $course_id Course id.
					 * @param bool   $enrolled  Whether the viewer is enrolled.
					 */
					do_action( 'shapelms_learn_item_content', $shapelms_item_id, $shapelms_item_type, $shapelms_course_id, $shapelms_enrolled );
					?>
				<?php endif; ?>

				<?php
				/**
				 * Fires at the end of the lesson / quiz content, above the Previous / Next bar.
				 *
				 * @param int $item_id   Item id.
				 * @param int $course_id Course id.
				 */
				do_action( 'shapelms_learn_content_after', $shapelms_item_id, $shapelms_course_id );
				?>
			</article>

			<footer class="shapelms-learn__footer">
				<?php if ( $shapelms_neighbors['prev'] ) : ?>
					<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( (string) get_permalink( $shapelms_neighbors['prev'] ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'shapelms' ); ?></a>
				<?php else : ?>
					<span></span>
				<?php endif; ?>

				<?php if ( $shapelms_is_lesson && $shapelms_enrolled ) : ?>
					<?php
					$shapelms_can_complete = $shapelms_done ? true : shapelms()->get( \ShapeLMS\Services\ProgressService::class )->can_complete_lesson( $shapelms_user_id, $shapelms_item_id );
					$shapelms_blocked      = is_wp_error( $shapelms_can_complete );
					?>
					<button type="button" class="shapelms-btn shapelms-btn--primary<?php echo $shapelms_done ? ' is-done' : ''; ?>" data-shapelms-complete="<?php echo esc_attr( (string) $shapelms_item_id ); ?>" <?php disabled( $shapelms_done || $shapelms_blocked ); ?><?php echo $shapelms_blocked ? ' title="' . esc_attr( $shapelms_can_complete->get_error_message() ) . '"' : ''; ?>>
						<?php echo $shapelms_done ? esc_html__( 'Completed', 'shapelms' ) : esc_html__( 'Mark as complete', 'shapelms' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $shapelms_next_lock ) : ?>
					<a class="shapelms-btn shapelms-btn--secondary is-disabled" href="<?php echo esc_url( (string) get_permalink( $shapelms_neighbors['next'] ) ); ?>" aria-disabled="true" data-shapelms-next-locked title="<?php echo esc_attr( $shapelms_lock_msgs[ (int) $shapelms_neighbors['next'] ] ?? __( 'Complete this lesson to continue', 'shapelms' ) ); ?>"><?php esc_html_e( 'Next', 'shapelms' ); ?> &rarr;</a>
				<?php elseif ( $shapelms_neighbors['next'] ) : ?>
					<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( (string) get_permalink( $shapelms_neighbors['next'] ) ); ?>"><?php esc_html_e( 'Next', 'shapelms' ); ?> &rarr;</a>
				<?php else : ?>
					<a class="shapelms-btn shapelms-btn--secondary" href="<?php echo esc_url( (string) get_permalink( $shapelms_course_id ) ); ?>"><?php esc_html_e( 'Back to course', 'shapelms' ); ?></a>
				<?php endif; ?>
			</footer>

			<?php
			/**
			 * Fires below the lesson/quiz content (Q&A hooks here).
			 *
			 * @param int $item_id   Item id.
			 * @param int $course_id Course id.
			 */
			do_action( 'shapelms_learn_after', $shapelms_item_id, $shapelms_course_id );
			?>
		</main>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
