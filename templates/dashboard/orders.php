<?php
/**
 * Dashboard: purchases.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_service = shapelms()->get( \ShapeLMS\Services\OrderService::class );
$shapelms_orders  = shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->paginate(
	array(
		'user_id'  => (int) $args['user']->ID,
		'per_page' => 100,
	)
);
$shapelms_labels  = array(
	'pending'   => __( 'Pending', 'shapelms' ),
	'completed' => __( 'Paid', 'shapelms' ),
	'refunded'  => __( 'Refunded', 'shapelms' ),
	'cancelled' => __( 'Cancelled', 'shapelms' ),
	'failed'    => __( 'Failed', 'shapelms' ),
);
?>
<h2><?php esc_html_e( 'Purchases', 'shapelms' ); ?></h2>

<?php if ( $shapelms_orders['items'] ) : ?>
	<table class="shapelms-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Courses', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Total', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Date', 'shapelms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shapelms_orders['items'] as $shapelms_row ) : ?>
				<?php $shapelms_order = $shapelms_service->to_array( $shapelms_row ); ?>
				<tr>
					<td>#<?php echo esc_html( (string) $shapelms_order['id'] ); ?></td>
					<td>
						<?php foreach ( $shapelms_order['items'] as $shapelms_line ) : ?>
							<a href="<?php echo esc_url( $shapelms_line['url'] ); ?>"><?php echo esc_html( $shapelms_line['title'] ); ?></a><br>
						<?php endforeach; ?>
					</td>
					<td><?php echo esc_html( $shapelms_order['total_html'] ); ?></td>
					<td><span class="shapelms-tag"><?php echo esc_html( isset( $shapelms_labels[ $shapelms_order['status'] ] ) ? $shapelms_labels[ $shapelms_order['status'] ] : $shapelms_order['status'] ); ?></span></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $shapelms_order['created_at'] ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No purchases yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
