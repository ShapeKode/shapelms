<?php
/**
 * Dashboard: instructor courses.
 *
 * @var array $args { user }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_user_id = (int) $args['user']->ID;
$shapelms_ids     = \ShapeLMS\Services\InstructorService::course_ids( $shapelms_user_id );
$shapelms_courses = shapelms()->get( \ShapeLMS\Services\CourseService::class );
$shapelms_counts  = shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class )->counts_for_courses( $shapelms_ids );
$shapelms_labels  = array(
	'publish' => __( 'Published', 'shapelms' ),
	'draft'   => __( 'Draft', 'shapelms' ),
	'pending' => __( 'Pending review', 'shapelms' ),
	'private' => __( 'Private', 'shapelms' ),
);
?>
<div class="shapelms-dashboard__head">
	<h2><?php esc_html_e( 'My courses', 'shapelms' ); ?></h2>
	<a class="shapelms-btn shapelms-btn--primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . \ShapeLMS\Content\PostTypes::COURSE ) ); ?>"><?php esc_html_e( 'Create course', 'shapelms' ); ?></a>
</div>

<?php if ( $shapelms_ids ) : ?>
	<table class="shapelms-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Course', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Status', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Students', 'shapelms' ); ?></th>
				<th><?php esc_html_e( 'Rating', 'shapelms' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shapelms_ids as $shapelms_id ) : ?>
				<?php
				$shapelms_course = $shapelms_courses->to_array( $shapelms_id, false );
				if ( ! $shapelms_course ) {
					continue;
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $shapelms_course['title'] ); ?></strong></td>
					<td><span class="shapelms-tag"><?php echo esc_html( isset( $shapelms_labels[ $shapelms_course['status'] ] ) ? $shapelms_labels[ $shapelms_course['status'] ] : $shapelms_course['status'] ); ?></span></td>
					<td><?php echo esc_html( number_format_i18n( isset( $shapelms_counts[ $shapelms_id ] ) ? $shapelms_counts[ $shapelms_id ] : 0 ) ); ?></td>
					<td><?php echo $shapelms_course['rating']['count'] ? esc_html( number_format_i18n( $shapelms_course['rating']['average'], 1 ) . ' ★' ) : '—'; ?></td>
					<td>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $shapelms_id, 'raw' ) ); ?>"><?php esc_html_e( 'Edit', 'shapelms' ); ?></a>
						&middot;
						<a href="<?php echo esc_url( $shapelms_course['url'] ); ?>"><?php esc_html_e( 'View', 'shapelms' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( "You haven't created a course yet.", 'shapelms' ); ?></p></div>
<?php endif; ?>
