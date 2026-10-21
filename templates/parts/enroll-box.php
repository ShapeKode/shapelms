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

/**
 * Filters the buy URL for a paid course (WooCommerce / checkout module fill this in).
 *
 * @param string $url       Buy URL.
 * @param int    $course_id Course id.
 */
$shapelms_buy_url = apply_filters( 'shapelms_course_buy_url', add_query_arg( 'course', $shapelms_id, \ShapeLMS\Core\Pages::get_url( 'checkout' ) ), $shapelms_id );
?>
<div class="shapelms-enroll-box" id="shapelms-enroll" <?php echo \ShapeLMS\Frontend\Frontend::data_attributes( array( 'course-id' => (string) $shapelms_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the method. ?>>
	<?php if ( $shapelms_course['intro_video'] ) : ?>
		<div class="shapelms-enroll-box__video">
			<?php echo \ShapeLMS\Frontend\VideoEmbed::html( $shapelms_course['intro_video'], $shapelms_course['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iframe built with esc_url/esc_attr or oEmbed. ?>
		</div>
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
				<?php if ( $shapelms_pricing['on_sale'] ) : ?>
					<del><?php echo esc_html( shapelms_format_price( $shapelms_pricing['price'] ) ); ?></del>
				<?php endif; ?>
				<strong><?php echo esc_html( $shapelms_course['price_html'] ); ?></strong>
			</div>

			<?php if ( $shapelms_full ) : ?>
				<p class="shapelms-notice"><?php esc_html_e( 'This course is full.', 'shapelms' ); ?></p>
			<?php elseif ( $shapelms_pricing['is_free'] ) : ?>
				<?php if ( $shapelms_user_id ) : ?>
					<button type="button" class="shapelms-btn shapelms-btn--primary shapelms-btn--block" data-shapelms-enroll="<?php echo esc_attr( (string) $shapelms_id ); ?>">
						<?php esc_html_e( 'Enroll for free', 'shapelms' ); ?>
					</button>
				<?php else : ?>
					<a class="shapelms-btn shapelms-btn--primary shapelms-btn--block" href="<?php echo esc_url( wp_login_url( (string) get_permalink( $shapelms_id ) ) ); ?>">
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

		<ul class="shapelms-enroll-box__facts">
			<li><span><?php esc_html_e( 'Lessons', 'shapelms' ); ?></span><strong><?php echo esc_html( (string) $shapelms_course['lessons'] ); ?></strong></li>
			<li><span><?php esc_html_e( 'Quizzes', 'shapelms' ); ?></span><strong><?php echo esc_html( (string) $shapelms_course['quizzes'] ); ?></strong></li>
			<?php if ( $shapelms_course['duration'] ) : ?>
				<li><span><?php esc_html_e( 'Duration', 'shapelms' ); ?></span><strong><?php echo esc_html( shapelms_format_duration( $shapelms_course['duration'] ) ); ?></strong></li>
			<?php endif; ?>
			<li><span><?php esc_html_e( 'Students', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $shapelms_course['students'] ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Access', 'shapelms' ); ?></span><strong><?php esc_html_e( 'Lifetime', 'shapelms' ); ?></strong></li>
		</ul>
	</div>
</div>
