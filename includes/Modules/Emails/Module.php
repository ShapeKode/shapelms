<?php
/**
 * Emails module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Emails;

use ShapeLMS\Core\Events;
use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Core\Pages;
use ShapeLMS\Services\CourseService;

/**
 * Sends transactional emails on LMS events. Each email can be switched off and its subject/body edited in Settings.
 */
class Module extends AbstractModule {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'emails';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Email notifications', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Sends emails on enrollment, completion, quiz results, orders and instructor approval.', 'shapelms' );
	}

	/**
	 * Email definitions: id => [ label, to (student|admin|instructor), subject, body, enabled ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions() {
		$definitions = array(
			'student_enrolled'     => array(
				'label'   => __( 'Student: enrolled in a course', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( 'You are enrolled in {course_title}', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nYou are now enrolled in {course_title}. Start learning here:\n{course_url}\n\nHappy learning!", 'shapelms' ),
			),
			'student_completed'    => array(
				'label'   => __( 'Student: completed a course', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( 'Congratulations! You completed {course_title}', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nYou have completed {course_title}. Well done!\n\nSee your dashboard: {dashboard_url}", 'shapelms' ),
			),
			'student_quiz'         => array(
				'label'   => __( 'Student: quiz result', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( 'Your result for {quiz_title}: {quiz_score}%', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nYou scored {quiz_score}% on {quiz_title} in {course_title}. Result: {quiz_result}.\n\n{course_url}", 'shapelms' ),
			),
			'student_order'        => array(
				'label'   => __( 'Student: order receipt', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( 'Your order #{order_id} on {site_name}', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nThanks for your purchase. Order #{order_id} for {order_total} is complete.\n\n{order_items}\n\nGo to your dashboard: {dashboard_url}", 'shapelms' ),
			),
			'admin_order'          => array(
				'label'   => __( 'Admin: new order', 'shapelms' ),
				'to'      => 'admin',
				'subject' => __( 'New order #{order_id} ({order_total})', 'shapelms' ),
				'body'    => __( "A new order was completed on {site_name}.\n\nOrder #{order_id}\nStudent: {student_name} ({student_email})\nTotal: {order_total}\n\n{order_items}", 'shapelms' ),
			),
			'admin_instructor'     => array(
				'label'   => __( 'Admin: new instructor application', 'shapelms' ),
				'to'      => 'admin',
				'subject' => __( 'New instructor application from {student_name}', 'shapelms' ),
				'body'    => __( "{student_name} ({student_email}) applied to become an instructor on {site_name}.\n\nReview applications: {admin_instructors_url}", 'shapelms' ),
			),
			'instructor_approved'  => array(
				'label'   => __( 'Instructor: application approved', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( 'You are now an instructor on {site_name}', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nYour instructor application was approved. You can create your first course here:\n{dashboard_url}", 'shapelms' ),
			),
			'instructor_enrolled'  => array(
				'label'   => __( 'Instructor: new student enrolled', 'shapelms' ),
				'to'      => 'instructor',
				'subject' => __( 'New student in {course_title}', 'shapelms' ),
				'body'    => __( "{student_name} enrolled in {course_title}.\n\n{course_url}", 'shapelms' ),
			),
			'student_announcement' => array(
				'label'   => __( 'Student: course announcement', 'shapelms' ),
				'to'      => 'student',
				'subject' => __( '{course_title}: {announcement_title}', 'shapelms' ),
				'body'    => __( "Hi {student_name},\n\nA new announcement was posted in {course_title}:\n\n{announcement_title}\n\n{announcement_content}\n\n{course_url}", 'shapelms' ),
			),
		);

		/**
		 * Filters the email definitions.
		 *
		 * @param array $definitions Definitions.
		 */
		return apply_filters( 'shapelms_email_definitions', $definitions );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'shapelms_settings_schema', array( $this, 'settings' ) );

		add_action( Events::COURSE_ENROLLED, array( $this, 'on_enrolled' ), 10, 2 );
		add_action( Events::COURSE_COMPLETED, array( $this, 'on_completed' ), 10, 2 );
		add_action( Events::QUIZ_FINISHED, array( $this, 'on_quiz' ), 10, 2 );
		add_action( Events::ORDER_COMPLETED, array( $this, 'on_order' ), 10, 2 );
		add_action( Events::INSTRUCTOR_APPLIED, array( $this, 'on_applied' ), 10, 2 );
		add_action( Events::INSTRUCTOR_APPROVED, array( $this, 'on_approved' ), 10, 2 );
		add_action( 'shapelms_announcement_published', array( $this, 'on_announcement' ), 10, 3 );
		add_action( 'shapelms_send_queued_email', array( $this, 'send_queued' ), 10, 4 );
	}

	/**
	 * Adds per-email settings.
	 *
	 * @param array $schema Schema.
	 * @return array
	 */
	public function settings( $schema ) {
		foreach ( self::definitions() as $id => $definition ) {
			$schema['emails']['fields'][ $id . '_enabled' ] = array(
				'label'   => $definition['label'],
				'type'    => 'checkbox',
				'default' => true,
				'group'   => $id,
			);
			$schema['emails']['fields'][ $id . '_subject' ] = array(
				'label'   => __( 'Subject', 'shapelms' ),
				'type'    => 'text',
				'default' => $definition['subject'],
				'group'   => $id,
			);
			$schema['emails']['fields'][ $id . '_body' ]    = array(
				'label'       => __( 'Body', 'shapelms' ),
				'type'        => 'textarea',
				'default'     => $definition['body'],
				'group'       => $id,
				'description' => __( 'Tags: {student_name} {student_email} {course_title} {course_url} {quiz_title} {quiz_score} {quiz_result} {order_id} {order_total} {order_items} {dashboard_url} {site_name}', 'shapelms' ),
			);
		}

		return $schema;
	}

	/**
	 * Base tags for a user.
	 *
	 * @param int $user_id User id.
	 * @return array<string, string>
	 */
	private function user_tags( $user_id ) {
		$user = get_userdata( $user_id );

		return array(
			'student_name'          => $user ? $user->display_name : '',
			'student_email'         => $user ? $user->user_email : '',
			'site_name'             => get_bloginfo( 'name' ),
			'site_url'              => home_url( '/' ),
			'dashboard_url'         => Pages::get_url( 'dashboard' ),
			'admin_instructors_url' => admin_url( 'admin.php?page=shapelms-instructors' ),
		);
	}

	/**
	 * Course tags.
	 *
	 * @param int $course_id Course id.
	 * @return array<string, string>
	 */
	private function course_tags( $course_id ) {
		return array(
			'course_title' => get_the_title( $course_id ),
			'course_url'   => (string) get_permalink( $course_id ),
		);
	}

	/**
	 * Enrolled.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_enrolled( $user_id, $context ) {
		$course_id = (int) $context['course_id'];
		$tags      = array_merge( $this->user_tags( $user_id ), $this->course_tags( $course_id ) );

		Mailer::queue( 'student_enrolled', $tags['student_email'], $tags );

		foreach ( CourseService::instructor_ids( $course_id ) as $instructor_id ) {
			$instructor = get_userdata( $instructor_id );
			if ( $instructor && $instructor_id !== $user_id ) {
				Mailer::queue( 'instructor_enrolled', $instructor->user_email, $tags );
			}
		}
	}

	/**
	 * Completed.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_completed( $user_id, $context ) {
		$tags = array_merge( $this->user_tags( $user_id ), $this->course_tags( (int) $context['course_id'] ) );
		Mailer::queue( 'student_completed', $tags['student_email'], $tags );
	}

	/**
	 * Quiz finished.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_quiz( $user_id, $context ) {
		$tags                = array_merge( $this->user_tags( $user_id ), $this->course_tags( (int) $context['course_id'] ) );
		$tags['quiz_title']  = get_the_title( (int) $context['quiz_id'] );
		$tags['quiz_score']  = (string) $context['score'];
		$tags['quiz_result'] = ! empty( $context['passed'] ) ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' );
		Mailer::queue( 'student_quiz', $tags['student_email'], $tags );
	}

	/**
	 * Order completed.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_order( $user_id, $context ) {
		$orders = shapelms()->get( \ShapeLMS\Database\OrderRepository::class );
		$order  = $orders->find( (int) $context['order_id'] );

		if ( ! $order ) {
			return;
		}

		$lines = array();
		foreach ( $orders->items( (int) $order->id ) as $item ) {
			$lines[] = sprintf( '- %s: %s', $item->title, shapelms_format_price( (float) $item->price ) );
		}

		$tags                = $this->user_tags( $user_id );
		$tags['order_id']    = (string) $order->id;
		$tags['order_total'] = shapelms_format_price( (float) $order->total );
		$tags['order_items'] = implode( "\n", $lines );

		Mailer::queue( 'student_order', $order->billing_email ? $order->billing_email : $tags['student_email'], $tags );
		Mailer::queue( 'admin_order', get_option( 'admin_email' ), $tags );
	}

	/**
	 * Instructor applied.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_applied( $user_id, $context ) {
		Mailer::queue( 'admin_instructor', get_option( 'admin_email' ), $this->user_tags( $user_id ) );
	}

	/**
	 * Instructor approved.
	 *
	 * @param int   $user_id User id.
	 * @param array $context Context.
	 * @return void
	 */
	public function on_approved( $user_id, $context ) {
		$tags = $this->user_tags( $user_id );
		Mailer::queue( 'instructor_approved', $tags['student_email'], $tags );
	}

	/**
	 * Sends a queued email (Action Scheduler / WP-Cron callback).
	 *
	 * @param string $email_id Email id.
	 * @param string $to       Recipient.
	 * @param array  $tags     Merge tags.
	 * @param array  $extra    Extra args.
	 * @return void
	 */
	public function send_queued( $email_id, $to, $tags = array(), $extra = array() ) {
		Mailer::send( $email_id, $to, (array) $tags, (array) $extra );
	}

	/**
	 * Announcement published: email every enrolled student.
	 *
	 * @param int  $id        Announcement id.
	 * @param int  $course_id Course id.
	 * @param bool $notify    Notify.
	 * @return void
	 */
	public function on_announcement( $id, $course_id, $notify ) {
		if ( ! $notify ) {
			return;
		}

		$post = get_post( $id );
		$rows = shapelms()->get( \ShapeLMS\Database\EnrollmentRepository::class )->where(
			array(
				'course_id' => $course_id,
				'status'    => array( 'active', 'completed' ),
			)
		);

		foreach ( $rows as $row ) {
			$tags                         = array_merge( $this->user_tags( (int) $row->user_id ), $this->course_tags( $course_id ) );
			$tags['announcement_title']   = $post->post_title;
			$tags['announcement_content'] = wp_strip_all_tags( $post->post_content );
			Mailer::queue( 'student_announcement', $tags['student_email'], $tags );
		}
	}
}
