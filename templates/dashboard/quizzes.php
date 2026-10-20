<?php
/**
 * Dashboard: quiz attempts.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_attempts = shapelms()->get( \ShapeLMS\Database\AttemptRepository::class )->where(
	array(
		'user_id' => (int) $args['user']->ID,
		'status'  => array( 'completed', 'expired' ),
	),
	array(
		'orderby' => 'id',
		'order'   => 'DESC',
		'limit'   => 100,
	)
);
?>
<h2><?php esc_html_e( 'Quiz attempts', 'shapelms' ); ?></h2>

<?php if ( $shapelms_attempts ) : ?>
	<table class="shapelms-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Quiz', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Course', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Score', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Result', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'shapelms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shapelms_attempts as $shapelms_row ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( (string) get_permalink( (int) $shapelms_row->quiz_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $shapelms_row->quiz_id ) ); ?></a></td>
					<td><?php echo esc_html( get_the_title( (int) $shapelms_row->course_id ) ); ?></td>
					<td><?php echo esc_html( $shapelms_row->percentage . '%' ); ?></td>
					<td><span class="shapelms-tag <?php echo $shapelms_row->passed ? 'shapelms-tag--success' : 'shapelms-tag--danger'; ?>"><?php echo $shapelms_row->passed ? esc_html__( 'Passed', 'shapelms' ) : esc_html__( 'Failed', 'shapelms' ); ?></span></td>
					<td><?php echo esc_html( $shapelms_row->finished_at ? wp_date( get_option( 'date_format' ), strtotime( $shapelms_row->finished_at ) ) : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No quiz attempts yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
