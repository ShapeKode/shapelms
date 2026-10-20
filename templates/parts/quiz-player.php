<?php
/**
 * Quiz player root. The JS loads the quiz over REST and renders it here.
 *
 * @var array $args { quiz_id, enrolled }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_quiz_id  = isset( $args['quiz_id'] ) ? (int) $args['quiz_id'] : get_the_ID();
$shapelms_enrolled = ! empty( $args['enrolled'] );
$shapelms_settings = \ShapeLMS\Services\QuizService::settings( $shapelms_quiz_id );
$shapelms_count    = count( \ShapeLMS\Services\QuizService::questions( $shapelms_quiz_id ) );
?>
<?php if ( get_the_content() ) : ?>
	<div class="shapelms-prose"><?php the_content(); ?></div>
<?php endif; ?>

<div class="shapelms-quiz" id="shapelms-quiz" data-quiz-id="<?php echo esc_attr( (string) $shapelms_quiz_id ); ?>">
	<div class="shapelms-quiz__intro" data-shapelms-quiz-intro>
		<ul class="shapelms-quiz__facts">
			<li><span><?php esc_html_e( 'Questions', 'shapelms' ); ?></span><strong><?php echo esc_html( (string) $shapelms_count ); ?></strong></li>
			<li><span><?php esc_html_e( 'Pass mark', 'shapelms' ); ?></span><strong><?php echo esc_html( $shapelms_settings['pass_mark'] . '%' ); ?></strong></li>
			<li><span><?php esc_html_e( 'Time limit', 'shapelms' ); ?></span><strong><?php echo $shapelms_settings['time_limit'] ? esc_html( sprintf( /* translators: %d: minutes */ _n( '%d minute', '%d minutes', $shapelms_settings['time_limit'], 'shapelms' ), $shapelms_settings['time_limit'] ) ) : esc_html__( 'None', 'shapelms' ); ?></strong></li>
			<li><span><?php esc_html_e( 'Attempts', 'shapelms' ); ?></span><strong data-shapelms-quiz-attempts><?php echo $shapelms_settings['attempts_allowed'] ? esc_html( (string) $shapelms_settings['attempts_allowed'] ) : esc_html__( 'Unlimited', 'shapelms' ); ?></strong></li>
		</ul>

		<?php if ( ! $shapelms_enrolled ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'Enroll in the course to take this quiz.', 'shapelms' ); ?></p>
		<?php elseif ( ! $shapelms_count ) : ?>
			<p class="shapelms-notice"><?php esc_html_e( 'This quiz has no questions yet.', 'shapelms' ); ?></p>
		<?php else : ?>
			<button type="button" class="shapelms-btn shapelms-btn--primary" data-shapelms-quiz-start><?php esc_html_e( 'Start quiz', 'shapelms' ); ?></button>
		<?php endif; ?>

		<div class="shapelms-quiz__history" data-shapelms-quiz-history hidden>
			<h3><?php esc_html_e( 'Previous attempts', 'shapelms' ); ?></h3>
			<table class="shapelms-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attempt', 'shapelms' ); ?></th>
						<th><?php esc_html_e( 'Score', 'shapelms' ); ?></th>
						<th><?php esc_html_e( 'Result', 'shapelms' ); ?></th>
						<th><?php esc_html_e( 'Date', 'shapelms' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<div class="shapelms-quiz__player" data-shapelms-quiz-player hidden>
		<div class="shapelms-quiz__header">
			<span class="shapelms-quiz__counter" data-shapelms-quiz-counter></span>
			<span class="shapelms-quiz__timer" data-shapelms-quiz-timer hidden></span>
		</div>
		<form class="shapelms-quiz__form" data-shapelms-quiz-form novalidate></form>
		<div class="shapelms-quiz__nav">
			<button type="button" class="shapelms-btn shapelms-btn--secondary" data-shapelms-quiz-prev><?php esc_html_e( 'Previous', 'shapelms' ); ?></button>
			<button type="button" class="shapelms-btn shapelms-btn--secondary" data-shapelms-quiz-next><?php esc_html_e( 'Next', 'shapelms' ); ?></button>
			<button type="button" class="shapelms-btn shapelms-btn--primary" data-shapelms-quiz-submit><?php esc_html_e( 'Submit quiz', 'shapelms' ); ?></button>
		</div>
	</div>

	<div class="shapelms-quiz__result" data-shapelms-quiz-result hidden></div>

	<p class="shapelms-quiz__message" data-shapelms-quiz-message aria-live="polite"></p>
</div>
