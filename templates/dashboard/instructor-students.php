<?php
/**
 * Dashboard: instructor students.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_ids = \ShapeLMS\Services\InstructorService::course_ids( (int) $args['user']->ID, array( 'publish' ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging.
$shapelms_page  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
$shapelms_rows  = array();
$shapelms_total = 0;

if ( $shapelms_ids ) {
	$shapelms_repo  = shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class );
	$shapelms_where = array(
		'course_id' => $shapelms_ids,
		'status'    => array( 'active', 'completed' ),
	);
	$shapelms_total = $shapelms_repo->count( $shapelms_where );
	$shapelms_rows  = $shapelms_repo->where(
		$shapelms_where,
		array(
			'orderby' => 'started_at',
			'order'   => 'DESC',
			'limit'   => 25,
			'offset'  => ( $shapelms_page - 1 ) * 25,
		)
	);
}
?>
<h2><?php esc_html_e( 'Students', 'shapelms' ); ?></h2>
<p class="shapelms-muted"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d enrollment', '%d enrollments', $shapelms_total, 'shapelms' ), $shapelms_total ) ); ?></p>

<?php if ( $shapelms_rows ) : ?>
	<table class="shapelms-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Student', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Course', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Progress', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Enrolled', 'shapelms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shapelms_rows as $shapelms_row ) : ?>
				<?php $shapelms_student = get_userdata( (int) $shapelms_row->user_id ); ?>
				<tr>
					<td>
						<?php if ( $shapelms_student ) : ?>
							<img src="<?php echo esc_url( get_avatar_url( $shapelms_student->ID, array( 'size' => 48 ) ) ); ?>" alt="" width="24" height="24" class="shapelms-avatar-inline">
							<?php echo esc_html( $shapelms_student->display_name ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Deleted user', 'shapelms' ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( get_the_title( (int) $shapelms_row->course_id ) ); ?></td>
					<td>
						<?php if ( 'completed' === $shapelms_row->status ) : ?>
							<span class="shapelms-tag shapelms-tag--success"><?php esc_html_e( 'Completed', 'shapelms' ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $shapelms_row->progress . '%' ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $shapelms_row->started_at ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $shapelms_total > 25 ) : ?>
		<nav class="shapelms-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', \ShapeLMS\Frontend\Dashboard::url( 'instructor-students' ) ),
						'format'  => '',
						'current' => $shapelms_page,
						'total'   => (int) ceil( $shapelms_total / 25 ),
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No students yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
