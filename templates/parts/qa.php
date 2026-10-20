<?php
/**
 * Q&A panel under a lesson.
 *
 * @var array $args { item_id, course_id, threads }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_item_id   = (int) $args['item_id'];
$shapelms_course_id = (int) $args['course_id'];
$shapelms_threads   = isset( $args['threads'] ) ? $args['threads'] : array();
?>
<section class="shapelms-learn__content shapelms-qa" id="qa">
	<h2 class="shapelms-box__title"><?php esc_html_e( 'Questions & answers', 'shapelms' ); ?></h2>

	<form data-shapelms-form="qa" data-reload="1" data-success="<?php esc_attr_e( 'Posted.', 'shapelms' ); ?>">
		<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $shapelms_course_id ); ?>">
		<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $shapelms_item_id ); ?>">
		<div class="shapelms-field">
			<label for="shapelms-qa-content"><?php esc_html_e( 'Ask a question about this lesson', 'shapelms' ); ?></label>
			<textarea id="shapelms-qa-content" class="shapelms-textarea" name="content" required maxlength="3000"></textarea>
		</div>
		<button type="submit" class="shapelms-btn shapelms-btn--primary"><?php esc_html_e( 'Post question', 'shapelms' ); ?></button>
		<p data-shapelms-form-message aria-live="polite"></p>
	</form>

	<?php if ( $shapelms_threads ) : ?>
		<div class="shapelms-qa__list">
			<?php foreach ( $shapelms_threads as $shapelms_thread ) : ?>
				<article class="shapelms-qa__item" id="qa-<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>">
					<div class="shapelms-qa__head">
						<img src="<?php echo esc_url( $shapelms_thread['avatar'] ); ?>" alt="" width="36" height="36" loading="lazy">
						<div>
							<span class="shapelms-qa__name"><?php echo esc_html( $shapelms_thread['name'] ); ?></span>
							<?php if ( $shapelms_thread['is_instructor'] ) : ?>
								<span class="shapelms-tag"><?php esc_html_e( 'Instructor', 'shapelms' ); ?></span>
							<?php endif; ?>
							<div class="shapelms-qa__date"><?php echo esc_html( $shapelms_thread['date'] ); ?></div>
						</div>
						<?php if ( $shapelms_thread['can_delete'] ) : ?>
							<button type="button" class="shapelms-link" data-shapelms-action="qa/<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>" data-method="DELETE" data-remove=".shapelms-qa__item" data-confirm="<?php esc_attr_e( 'Delete this question and its answers?', 'shapelms' ); ?>"><?php esc_html_e( 'Delete', 'shapelms' ); ?></button>
						<?php endif; ?>
					</div>
					<p><?php echo esc_html( $shapelms_thread['content'] ); ?></p>

					<div class="shapelms-qa__answers">
						<?php foreach ( $shapelms_thread['answers'] as $shapelms_answer ) : ?>
							<div class="shapelms-qa__answer">
								<div class="shapelms-qa__head">
									<img src="<?php echo esc_url( $shapelms_answer['avatar'] ); ?>" alt="" width="28" height="28" loading="lazy">
									<span class="shapelms-qa__name"><?php echo esc_html( $shapelms_answer['name'] ); ?></span>
									<?php if ( $shapelms_answer['is_instructor'] ) : ?>
										<span class="shapelms-tag"><?php esc_html_e( 'Instructor', 'shapelms' ); ?></span>
									<?php endif; ?>
									<span class="shapelms-qa__date"><?php echo esc_html( $shapelms_answer['date'] ); ?></span>
								</div>
								<p><?php echo esc_html( $shapelms_answer['content'] ); ?></p>
							</div>
						<?php endforeach; ?>

						<form data-shapelms-form="qa" data-reload="1">
							<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $shapelms_course_id ); ?>">
							<input type="hidden" name="parent" value="<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>">
							<div class="shapelms-field">
								<label class="screen-reader-text" for="shapelms-qa-reply-<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>"><?php esc_html_e( 'Your answer', 'shapelms' ); ?></label>
								<textarea id="shapelms-qa-reply-<?php echo esc_attr( (string) $shapelms_thread['id'] ); ?>" class="shapelms-textarea" name="content" rows="2" required maxlength="3000" placeholder="<?php esc_attr_e( 'Write an answer…', 'shapelms' ); ?>"></textarea>
							</div>
							<button type="submit" class="shapelms-btn shapelms-btn--secondary"><?php esc_html_e( 'Reply', 'shapelms' ); ?></button>
							<p data-shapelms-form-message aria-live="polite"></p>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="shapelms-muted"><?php esc_html_e( 'No questions yet. Be the first to ask.', 'shapelms' ); ?></p>
	<?php endif; ?>
</section>
