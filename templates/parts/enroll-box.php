<?php
/**
 * Enroll / buy box (single course sidebar).
 *
 * @var array $args { course }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_course  = isset( $args['course'] ) ? $args['course'] : array();
$shapelms_id      = (int) $shapelms_course['id'];
$shapelms_user_id = get_current_user_id();
$shapelms_enroll  = shapelms()->get( \ShapeLMS\Services\EnrollmentService::class );
$shapelms_access  = $shapelms_user_id && $shapelms_enroll->can_access( $shapelms_user_id, $shapelms_id );
$shapelms_full    = shapelms()->get( \ShapeLMS\Services\CourseService::class )->is_full( $shapelms_id );
$shapelms_pricing = $shapelms_course['pricing'];
$shapelms_resume  = $shapelms_access ? shapelms()->get( \ShapeLMS\Services\ProgressService::class )->resume_item( $shapelms_user_id, $shapelms_id ) : 0;
$shapelms_summary = $shapelms_access ? shapelms()->get( \ShapeLMS\Services\ProgressService::class )->summary( $shapelms_user_id, $shapelms_id ) : null;
$shapelms_missing = ( $shapelms_user_id && ! $shapelms_access ) ? $shapelms_enroll->missing_prerequisites( $shapelms_user_id, $shapelms_id ) : array();

/**
 * Filters the buy URL for a paid course (WooCommerce / checkout module fill this in).
 *
 * @param string $url       Buy URL.
 * @param int    $course_id Course id.
 */
$shapelms_buy_url = apply_filters( 'shapelms_course_buy_url', add_query_arg( 'course', $shapelms_id, \ShapeLMS\Core\Pages::get_url( 'checkout' ) ), $shapelms_id );
?>
<div class="shapelms-enroll-box" id="shapelms-enroll" <?php echo \ShapeLMS\Frontend\Frontend::data_attributes( array( 'course-id' => (string) $shapelms_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<?php
	$shapelms_intro_html = $shapelms_course['intro_video'] ? \ShapeLMS\Frontend\VideoEmbed::html( $shapelms_course['intro_video'], $shapelms_course['title'], true ) : '';
	?>
	<?php if ( $shapelms_intro_html ) : ?>
		<button type="button" class="shapelms-enroll-box__preview" data-shapelms-video="shapelms-intro-video" data-shapelms-video-title="<?php echo esc_attr( $shapelms_course['title'] ); ?>">
			<?php if ( $shapelms_course['image'] ) : ?>
				<img src="<?php echo esc_url( $shapelms_course['image'] ); ?>" alt="">
			<?php else : ?>
				<span class="shapelms-enroll-box__placeholder" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="shapelms-play" aria-hidden="true"></span>
			<span class="shapelms-enroll-box__preview-label"><?php esc_html_e( 'Preview this course', 'shapelms' ); ?></span>
		</button>
		<template id="shapelms-intro-video"><?php echo $shapelms_intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iframe built with esc_url/esc_attr or oEmbed. ?></template>
	<?php elseif ( $shapelms_course['image'] ) : ?>
		<img class="shapelms-enroll-box__image" src="<?php echo esc_url( $shapelms_course['image'] ); ?>" alt="">
	<?php endif; ?>

	<div class="shapelms-enroll-box__body">
		<?php if ( $shapelms_access && $shapelms_summary ) : ?>
			<div class="shapelms-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $shapelms_summary['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100">
				<span class="shapelms-progress__bar" data-percent="<?php echo esc_attr( (string) $shapelms_summary['percent'] ); ?>"></span>
			</div>
			<p class="shapelms-muted">
				<?php echo esc_html( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d completed', 'shapelms' ), $shapelms_summary['done'], $shapelms_summary['total'] ) ); ?>
			</p>
			<?php if ( $shapelms_summary['completed'] ) : ?>
				<p class="shapelms-notice shapelms-notice--success"><?php esc_html_e( 'You have completed this course.', 'shapelms' ); ?></p>
			<?php endif; ?>
			<?php if ( $shapelms_resume ) : ?>
				<a class="shapelms-btn shapelms-btn--primary shapelms-btn--block" href="<?php echo esc_url( (string) get_permalink( $shapelms_resume ) ); ?>">
					<?php echo $shapelms_summary['done'] > 0 ? esc_html__( 'Continue learning', 'shapelms' ) : esc_html__( 'Start learning', 'shapelms' ); ?>
				</a>
			<?php endif; ?>
		<?php else : ?>
			<div class="shapelms-enroll-box__price">
				<strong><?php echo esc_html( $shapelms_course['price_html'] ); ?></strong>
				<?php if ( $shapelms_pricing['on_sale'] ) : ?>
					<del><?php echo esc_html( shapelms_format_price( $shapelms_pricing['price'] ) ); ?></del>
					<span class="shapelms-enroll-box__discount"><?php echo esc_html( sprintf( /* translators: %d: percent */ __( '%d%% off', 'shapelms' ), (int) round( 100 - ( $shapelms_pricing['current'] / $shapelms_pricing['price'] * 100 ) ) ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $shapelms_full ) : ?>
				<p class="shapelms-notice"><?php esc_html_e( 'This course is full.', 'shapelms' ); ?></p>
			<?php elseif ( $shapelms_missing ) : ?>
				<div class="shapelms-notice shapelms-enroll-box__prereq">
					<p><?php esc_html_e( 'Complete these courses first:', 'shapelms' ); ?></p>
					<ul>
						<?php foreach ( $shapelms_missing as $shapelms_pre ) : ?>
							<li><a href="<?php echo esc_url( (string) get_permalink( $shapelms_pre ) ); ?>"><?php echo esc_html( get_the_title( $shapelms_pre ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php elseif ( $shapelms_pricing['is_free'] ) : ?>
				<?php if ( $shapelms_user_id ) : ?>
					<button type="button" class="shapelms-btn shapelms-btn--primary shapelms-btn--block" data-shapelms-enroll="<?php echo esc_attr( (string) $shapelms_id ); ?>">
						<?php esc_html_e( 'Enroll for free', 'shapelms' ); ?>
					</button>
				<?php else : ?>
					<a class="shapelms-btn shapelms-btn--primary shapelms-btn--block" href="<?php echo esc_url( shapelms_login_url( add_query_arg( 'shapelms_enroll', '1', (string) get_permalink( $shapelms_id ) ) ) ); ?>">
						<?php esc_html_e( 'Log in to enroll', 'shapelms' ); ?>
					</a>
				<?php endif; ?>
			<?php else : ?>
				<a class="shapelms-btn shapelms-btn--primary shapelms-btn--block" href="<?php echo esc_url( $shapelms_buy_url ); ?>">
					<?php esc_html_e( 'Buy now', 'shapelms' ); ?>
				</a>
			<?php endif; ?>
			<p class="shapelms-enroll-box__message" aria-live="polite"></p>
		<?php endif; ?>

		<?php
		/**
		 * Fires inside the enroll box after the buttons (wishlist hooks here).
		 *
		 * @param int  $course_id Course id.
		 * @param bool $enrolled  Whether the user can access.
		 */
		do_action( 'shapelms_enroll_box_actions', $shapelms_id, $shapelms_access );
		?>

		<h3 class="shapelms-enroll-box__heading"><?php esc_html_e( 'This course includes', 'shapelms' ); ?></h3>
		<ul class="shapelms-enroll-box__facts">
			<li><span><?php esc_html_e( 'Lessons', 'shapelms' ); ?></span><strong><?php echo esc_html( (string) $shapelms_course['lessons'] ); ?></strong></li>
			<li><span><?php esc_html_e( 'Quizzes', 'shapelms' ); ?></span><strong><?php echo esc_html( (string) $shapelms_course['quizzes'] ); ?></strong></li>
			<?php if ( $shapelms_course['duration'] ) : ?>
				<li><span><?php esc_html_e( 'Duration', 'shapelms' ); ?></span><strong><?php echo esc_html( shapelms_format_duration( $shapelms_course['duration'] ) ); ?></strong></li>
			<?php endif; ?>
			<li><span><?php esc_html_e( 'Students', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $shapelms_course['students'] ) ); ?></strong></li>
			<?php
			/**
			 * Filters the "Access" value in the enroll box (time-limited access can show its end date).
			 *
			 * @param string $label     Label.
			 * @param int    $course_id Course id.
			 * @param int    $user_id   Viewer.
			 */
			$shapelms_access_label = (string) apply_filters( 'shapelms_course_access_label', __( 'Lifetime', 'shapelms' ), $shapelms_id, $shapelms_user_id );
			?>
			<li><span><?php esc_html_e( 'Access', 'shapelms' ); ?></span><strong><?php echo esc_html( $shapelms_access_label ); ?></strong></li>
			<?php if ( ! empty( $shapelms_course['sequential'] ) ) : ?>
				<li><span><?php esc_html_e( 'Progression', 'shapelms' ); ?></span><strong><?php esc_html_e( 'Step by step', 'shapelms' ); ?></strong></li>
			<?php endif; ?>
		</ul>
	</div>
</div>
