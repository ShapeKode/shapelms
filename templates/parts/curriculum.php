<?php
/**
 * Curriculum accordion (single course).
 *
 * @var array $args { course }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_course = isset( $args['course'] ) ? $args['course'] : array();
$shapelms_tree   = isset( $shapelms_course['curriculum'] ) ? $shapelms_course['curriculum'] : array();

if ( empty( $shapelms_tree ) ) {
	echo '<p class="shapelms-muted">' . esc_html__( 'The curriculum will be published soon.', 'shapelms' ) . '</p>';
	return;
}

$shapelms_user_id    = get_current_user_id();
$shapelms_can_access = $shapelms_user_id && shapelms()->get( \ShapeLMS\Services\EnrollmentService::class )->can_access( $shapelms_user_id, (int) $shapelms_course['id'] );
$shapelms_statuses   = $shapelms_can_access ? shapelms()->get( \ShapeLMS\Services\ProgressService::class )->summary( $shapelms_user_id, (int) $shapelms_course['id'] )['statuses'] : array();
?>
<div class="shapelms-curriculum">
	<?php foreach ( $shapelms_tree as $shapelms_index => $shapelms_topic ) : ?>
		<details class="shapelms-curriculum__topic" <?php echo 0 === $shapelms_index ? 'open' : ''; ?>>
			<summary class="shapelms-curriculum__summary">
				<span class="shapelms-curriculum__topic-title"><?php echo esc_html( $shapelms_topic['title'] ); ?></span>
				<span class="shapelms-curriculum__count"><?php echo esc_html( sprintf( /* translators: %d: number of items */ _n( '%d item', '%d items', count( $shapelms_topic['items'] ), 'shapelms' ), count( $shapelms_topic['items'] ) ) ); ?></span>
			</summary>

			<?php if ( ! empty( $shapelms_topic['summary'] ) ) : ?>
				<p class="shapelms-curriculum__topic-summary"><?php echo wp_kses_post( $shapelms_topic['summary'] ); ?></p>
			<?php endif; ?>

			<ul class="shapelms-curriculum__items">
				<?php foreach ( $shapelms_topic['items'] as $shapelms_item ) : ?>
					<?php
					$shapelms_open = $shapelms_can_access || ! empty( $shapelms_item['preview'] );
					$shapelms_done = isset( $shapelms_statuses[ $shapelms_item['id'] ] ) && 'completed' === $shapelms_statuses[ $shapelms_item['id'] ];
					?>
					<li class="shapelms-curriculum__item shapelms-curriculum__item--<?php echo esc_attr( $shapelms_item['type'] ); ?><?php echo $shapelms_done ? ' is-done' : ''; ?>">
						<span class="shapelms-curriculum__icon" aria-hidden="true"></span>
						<?php if ( $shapelms_open ) : ?>
							<a href="<?php echo esc_url( $shapelms_item['url'] ); ?>"><?php echo esc_html( $shapelms_item['title'] ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( $shapelms_item['title'] ); ?></span>
						<?php endif; ?>
						<span class="shapelms-curriculum__right">
							<?php if ( ! empty( $shapelms_item['preview'] ) && ! $shapelms_can_access ) : ?>
								<span class="shapelms-tag"><?php esc_html_e( 'Preview', 'shapelms' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $shapelms_item['duration'] ) ) : ?>
								<span class="shapelms-muted"><?php echo esc_html( shapelms_format_duration( $shapelms_item['duration'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( ! $shapelms_open ) : ?>
								<span class="shapelms-curriculum__lock" aria-label="<?php esc_attr_e( 'Locked', 'shapelms' ); ?>"></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endforeach; ?>
</div>
