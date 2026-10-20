<?php
/**
 * Dashboard: instructor earnings (gross sales per course).
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_ids     = \ShapeLMS\Services\InstructorService::course_ids( (int) $args['user']->ID );
$shapelms_revenue = $shapelms_ids ? shapelms()->get( \ShapeLMS\Database\OrderRepository::class )->revenue_for_courses( $shapelms_ids ) : array();
$shapelms_total   = 0.0;
$shapelms_sales   = 0;

foreach ( $shapelms_revenue as $shapelms_row ) {
	$shapelms_total += $shapelms_row['revenue'];
	$shapelms_sales += $shapelms_row['sales'];
}

/**
 * Filters the earnings data shown to an instructor (commission add-ons adjust this).
 *
 * @param array $data    { total, sales, courses: course_id => { revenue, sales } }.
 * @param int   $user_id Instructor id.
 */
$shapelms_data = apply_filters(
	'shapelms_instructor_earnings',
	array(
		'total'   => $shapelms_total,
		'sales'   => $shapelms_sales,
		'courses' => $shapelms_revenue,
	),
	(int) $args['user']->ID
);
?>
<h2><?php esc_html_e( 'Earnings', 'shapelms' ); ?></h2>

<div class="shapelms-stats">
	<div class="shapelms-stat"><span><?php esc_html_e( 'Total sales', 'shapelms' ); ?></span><strong><?php echo esc_html( shapelms_format_price( $shapelms_data['total'] ) ); ?></strong></div>
	<div class="shapelms-stat"><span><?php esc_html_e( 'Orders', 'shapelms' ); ?></span><strong><?php echo esc_html( number_format_i18n( $shapelms_data['sales'] ) ); ?></strong></div>
</div>

<?php if ( $shapelms_data['courses'] ) : ?>
	<table class="shapelms-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Course', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Sales', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Revenue', 'shapelms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shapelms_data['courses'] as $shapelms_course_id => $shapelms_row ) : ?>
				<tr>
					<td><?php echo esc_html( get_the_title( (int) $shapelms_course_id ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $shapelms_row['sales'] ) ); ?></td>
					<td><?php echo esc_html( shapelms_format_price( $shapelms_row['revenue'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No sales yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
