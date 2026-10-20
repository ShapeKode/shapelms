<?php
/**
 * Dashboard: instructor announcements.
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
$shapelms_items   = \ShapeLMS\Modules\Announcements\Module::for_user( 0, $shapelms_user_id );
$shapelms_items   = array_filter(
	$shapelms_items,
	static function ( $item ) use ( $shapelms_ids ) {
		return in_array( (int) $item['course_id'], $shapelms_ids, true );
	}
);
?>
<h2><?php esc_html_e( 'Announcements', 'shapelms' ); ?></h2>

<?php if ( $shapelms_ids ) : ?>
	<form class="shapelms-box" data-shapelms-form="announcements" data-reload="1" data-success="<?php esc_attr_e( 'Announcement posted.', 'shapelms' ); ?>">
		<h3><?php esc_html_e( 'New announcement', 'shapelms' ); ?></h3>
		<div class="shapelms-field">
			<label for="shapelms-ann-course"><?php esc_html_e( 'Course', 'shapelms' ); ?></label>
			<select id="shapelms-ann-course" class="shapelms-select" name="course_id" required>
				<?php foreach ( $shapelms_ids as $shapelms_id ) : ?>
					<option value="<?php echo esc_attr( (string) $shapelms_id ); ?>"><?php echo esc_html( get_the_title( $shapelms_id ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="shapelms-field">
			<label for="shapelms-ann-title"><?php esc_html_e( 'Title', 'shapelms' ); ?></label>
			<input id="shapelms-ann-title" class="shapelms-input" type="text" name="title" required maxlength="200">
		</div>
		<div class="shapelms-field">
			<label for="shapelms-ann-content"><?php esc_html_e( 'Message', 'shapelms' ); ?></label>
			<textarea id="shapelms-ann-content" class="shapelms-textarea" name="content" required maxlength="5000"></textarea>
		</div>
		<div class="shapelms-field">
			<label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Email enrolled students', 'shapelms' ); ?></label>
		</div>
		<button type="submit" class="shapelms-btn shapelms-btn--primary"><?php esc_html_e( 'Post announcement', 'shapelms' ); ?></button>
		<p data-shapelms-form-message aria-live="polite"></p>
	</form>
<?php endif; ?>

<?php if ( $shapelms_items ) : ?>
	<?php foreach ( $shapelms_items as $shapelms_item ) : ?>
		<article class="shapelms-box shapelms-announcement">
			<div class="shapelms-dashboard__head">
				<h3 class="shapelms-announcement__title"><?php echo esc_html( $shapelms_item['title'] ); ?></h3>
				<button type="button" class="shapelms-link" data-shapelms-action="announcements/<?php echo esc_attr( (string) $shapelms_item['id'] ); ?>" data-method="DELETE" data-remove=".shapelms-announcement" data-confirm="<?php esc_attr_e( 'Delete this announcement?', 'shapelms' ); ?>"><?php esc_html_e( 'Delete', 'shapelms' ); ?></button>
			</div>
			<p class="shapelms-muted"><?php echo esc_html( $shapelms_item['course_title'] . ' · ' . $shapelms_item['date'] ); ?></p>
			<div class="shapelms-prose"><?php echo wp_kses_post( $shapelms_item['content'] ); ?></div>
		</article>
	<?php endforeach; ?>
<?php else : ?>
	<div class="shapelms-empty"><p><?php esc_html_e( 'No announcements yet.', 'shapelms' ); ?></p></div>
<?php endif; ?>
