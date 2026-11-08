<?php
/**
 * Scheduled courses.
 *
 * @package ShapeLMS
 */

/**
 * Scheduling through the builder endpoint and the rules for a scheduled course.
 */
class ShapeLMS_CourseScheduleTest extends ShapeLMS_TestCase {

	/**
	 * Sends a course update as a user.
	 *
	 * @param int                  $user_id   User.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $body      Body.
	 * @return WP_REST_Response
	 */
	private function put( $user_id, $course_id, array $body ) {
		wp_set_current_user( $user_id );
		$request = new WP_REST_Request( 'PUT', '/shapelms/v1/courses/' . $course_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * A date some time from now in the site time zone.
	 *
	 * @param string $modify Date modifier.
	 * @return string
	 */
	private function site_date( $modify ) {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( $modify )->format( 'Y-m-d\TH:i' );
	}

	/**
	 * A future date schedules the course and queues core's publish event.
	 */
	public function test_schedule_sets_future_and_cron() {
		$course_id = $this->make_course( array( 'status' => 'draft' ) );
		$admin     = $this->make_instructor();
		$date      = $this->site_date( '+2 days' );

		$data = $this->put( $admin, $course_id, array( 'schedule' => $date ) )->get_data();

		$this->assertSame( 'future', $data['status'] );
		$this->assertSame( $date, $data['schedule'] );
		$this->assertNotFalse( wp_next_scheduled( 'publish_future_post', array( $course_id ) ) );

		// When the event fires after the date has passed, the course goes live on its own.
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			),
			array( 'ID' => $course_id )
		);
		clean_post_cache( $course_id );
		check_and_publish_future_post( $course_id );
		$this->assertSame( 'publish', get_post_status( $course_id ) );
	}

	/**
	 * Past or malformed dates are refused and change nothing.
	 */
	public function test_bad_dates_are_refused() {
		$course_id = $this->make_course( array( 'status' => 'draft' ) );
		$admin     = $this->make_instructor();

		$this->assertSame( 400, $this->put( $admin, $course_id, array( 'schedule' => $this->site_date( '-1 hour' ) ) )->get_status() );
		$this->assertSame( 400, $this->put( $admin, $course_id, array( 'schedule' => 'tomorrow' ) )->get_status() );
		$this->assertSame( 'draft', get_post_status( $course_id ) );
	}

	/**
	 * Cancelling gives a draft, and publishing that draft goes live now, not on the old date.
	 */
	public function test_cancel_then_publish_goes_live_now() {
		$course_id = $this->make_course( array( 'status' => 'draft' ) );
		$admin     = $this->make_instructor();

		$this->put( $admin, $course_id, array( 'schedule' => $this->site_date( '+2 days' ) ) );
		$this->assertSame( 'draft', $this->put( $admin, $course_id, array( 'schedule' => '' ) )->get_data()['status'] );

		$this->assertSame( 'publish', $this->put( $admin, $course_id, array( 'status' => 'publish' ) )->get_data()['status'] );
		$this->assertLessThanOrEqual( time(), get_post_timestamp( $course_id ) );
	}

	/**
	 * Publish now on a scheduled course skips the wait.
	 */
	public function test_publish_now() {
		$course_id = $this->make_course( array( 'status' => 'draft' ) );
		$admin     = $this->make_instructor();

		$this->put( $admin, $course_id, array( 'schedule' => $this->site_date( '+2 days' ) ) );

		$this->assertSame( 'publish', $this->put( $admin, $course_id, array( 'status' => 'publish' ) )->get_data()['status'] );
		$this->assertLessThanOrEqual( time(), get_post_timestamp( $course_id ) );
	}

	/**
	 * An instructor who cannot publish cannot schedule.
	 */
	public function test_instructor_without_publish_right() {
		$course_id  = $this->make_course( array( 'status' => 'draft' ) );
		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_author' => $instructor,
			)
		);

		$this->assertSame( 403, $this->put( $instructor, $course_id, array( 'schedule' => $this->site_date( '+2 days' ) ) )->get_status() );
		$this->assertSame( 'draft', get_post_status( $course_id ) );
	}

	/**
	 * A scheduled course cannot be read, enrolled in or bought.
	 */
	public function test_scheduled_course_stays_closed() {
		$course_id = $this->make_course( array( 'status' => 'draft' ) );
		$student   = $this->make_student();

		$this->put( $this->make_instructor(), $course_id, array( 'schedule' => $this->site_date( '+2 days' ) ) );

		wp_set_current_user( $student );
		$request = new WP_REST_Request( 'GET', '/shapelms/v1/courses/' . $course_id );
		$this->assertSame( 404, rest_do_request( $request )->get_status() );
		$this->assertWPError( $this->service( \ShapeLMS\Services\EnrollmentService::class )->enroll_free( $student, $course_id ) );
		$this->assertWPError( $this->service( \ShapeLMS\Services\OrderService::class )->can_purchase( $student, $course_id ) );
	}
}
