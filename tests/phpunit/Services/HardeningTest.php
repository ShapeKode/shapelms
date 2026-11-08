<?php
/**
 * Access rules that protect reviews, reports, co-instructor lists, PayPal and enrolling.
 *
 * @package ShapeLMS
 */

use ShapeLMS\Frontend\Auth;
use ShapeLMS\Modules\Checkout\PayPalGateway;
use ShapeLMS\Services\EnrollmentService;
use ShapeLMS\Services\InstructorService;
use ShapeLMS\Services\ReportService;

/**
 * Each test pins one rule a visitor or instructor must not get around.
 */
class ShapeLMS_HardeningTest extends ShapeLMS_TestCase {

	/**
	 * Status of the public reviews list for a course.
	 *
	 * @param int $course_id Course or other post.
	 * @return int
	 */
	private function reviews_status( $course_id ) {
		$request = new WP_REST_Request( 'GET', '/shapelms/v1/reviews' );
		$request->set_param( 'course_id', $course_id );

		return rest_do_request( $request )->get_status();
	}

	/**
	 * Guests read reviews only for a course they could open.
	 */
	public function test_reviews_follow_course_visibility() {
		wp_set_current_user( 0 );

		$course_id = $this->make_course();
		$this->assertSame( 200, $this->reviews_status( $course_id ) );

		foreach ( array( 'draft', 'private' ) as $status ) {
			wp_update_post(
				array(
					'ID'          => $course_id,
					'post_status' => $status,
				)
			);
			$this->assertSame( 404, $this->reviews_status( $course_id ), $status );
		}

		wp_update_post(
			array(
				'ID'            => $course_id,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$this->assertSame( 404, $this->reviews_status( $course_id ) );
	}

	/**
	 * A student of a private course still sees its reviews.
	 */
	public function test_private_course_student_reads_reviews() {
		$course_id = $this->make_course();
		$student   = $this->make_student();
		$this->service( EnrollmentService::class )->enroll( $student, $course_id );
		wp_update_post(
			array(
				'ID'          => $course_id,
				'post_status' => 'private',
			)
		);

		wp_set_current_user( $student );
		$this->assertSame( 200, $this->reviews_status( $course_id ) );
	}

	/**
	 * Asking about any other post writes nothing to it.
	 */
	public function test_reviews_ignore_other_posts() {
		wp_set_current_user( 0 );
		$post_id = (int) self::factory()->post->create();

		$this->assertSame( 404, $this->reviews_status( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, \ShapeLMS\Modules\Reviews\ReviewService::META_CACHE, true ) );
	}

	/**
	 * Being at a position in the stored list does not make someone a co-instructor.
	 */
	public function test_co_instructor_list_ignores_array_keys() {
		$course_id = $this->make_course();
		$teachers  = self::factory()->user->create_many( 3 );
		update_post_meta( $course_id, '_shapelms_instructors', array_map( 'intval', $teachers ) );

		$this->assertContains( $course_id, InstructorService::course_ids( (int) $teachers[2] ) );

		foreach ( array( 1, 2 ) as $position ) {
			if ( ! in_array( $position, array_map( 'intval', $teachers ), true ) ) {
				$this->assertNotContains( $course_id, InstructorService::course_ids( $position ) );
			}
		}
	}

	/**
	 * An instructor without courses sees no site-wide numbers.
	 */
	public function test_report_is_empty_without_courses() {
		$course_id = $this->make_course();
		$this->service( EnrollmentService::class )->enroll_free( $this->make_student(), $course_id );

		$today = gmdate( 'Y-m-d' );
		$total = static function ( $report ) {
			$sum = 0;
			array_walk_recursive(
				$report,
				static function ( $value, $key ) use ( &$sum ) {
					if ( 'value' === $key ) {
						$sum += (int) $value;
					}
				}
			);

			return $sum;
		};

		$reports = $this->service( ReportService::class );
		$this->assertGreaterThan( 0, $total( $reports->overview( $today, $today, 0, $this->make_instructor() ) ), 'The data exists.' );

		$instructor = (int) self::factory()->user->create( array( 'role' => 'shapelms_instructor' ) );
		$this->assertSame( 0, $total( $reports->overview( $today, $today, 0, $instructor ) ) );
	}

	/**
	 * Answers PayPal's API calls.
	 *
	 * @param array<string, mixed> $by_path Response body by the end of the path.
	 * @return void
	 */
	private function fake_paypal( array $by_path ) {
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( $by_path ) {
				$body = array( 'access_token' => 'token' );
				foreach ( $by_path as $path => $response ) {
					if ( false !== strpos( $url, $path ) ) {
						$body = $response;
					}
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( $body ),
				);
			},
			10,
			3
		);
	}

	/**
	 * A capture response from PayPal.
	 *
	 * @param string $capture_status Capture status.
	 * @return array<string, mixed>
	 */
	private function paypal_order( $capture_status ) {
		return array(
			'status'         => 'COMPLETED',
			'purchase_units' => array(
				array(
					'payments' => array(
						'captures' => array(
							array(
								'id'     => 'CAP1',
								'status' => $capture_status,
								'amount' => array(
									'value'         => '10.00',
									'currency_code' => 'USD',
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * The course opens only once the money has been captured.
	 */
	public function test_paypal_waits_for_the_capture() {
		$this->set_setting( 'commerce', 'paypal_client_id', 'id' );
		$this->set_setting( 'commerce', 'paypal_secret', 'secret' );

		$order = (object) array(
			'transaction_id' => 'ORDER1',
			'total'          => 10,
			'currency'       => 'USD',
		);

		$this->fake_paypal( array( '/capture' => $this->paypal_order( 'PENDING' ) ) );
		$pending = ( new PayPalGateway() )->confirm( $order, array( 'paypal_order_id' => 'ORDER1' ) );
		$this->assertWPError( $pending );
		$this->assertSame( 'shapelms_paypal_pending', $pending->get_error_code() );

		remove_all_filters( 'pre_http_request' );
		$this->fake_paypal( array( '/capture' => $this->paypal_order( 'COMPLETED' ) ) );
		$this->assertSame( 'CAP1', ( new PayPalGateway() )->confirm( $order, array( 'paypal_order_id' => 'ORDER1' ) ) );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * A reversed payment takes access away like a refund, once PayPal has confirmed the event.
	 */
	public function test_paypal_reversal_counts_as_refund() {
		$this->set_setting( 'commerce', 'paypal_client_id', 'id' );
		$this->set_setting( 'commerce', 'paypal_secret', 'secret' );
		$this->set_setting( 'commerce', 'paypal_webhook_id', 'WH1' );
		$this->fake_paypal( array( 'verify-webhook-signature' => array( 'verification_status' => 'SUCCESS' ) ) );

		$request = new WP_REST_Request( 'POST', '/shapelms/v1/webhooks/paypal' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'event_type' => 'PAYMENT.CAPTURE.REVERSED',
					'resource'   => array( 'custom_id' => '42' ),
				)
			)
		);

		$result = ( new PayPalGateway() )->webhook( $request );
		remove_all_filters( 'pre_http_request' );

		$this->assertSame( 'refund', $result['action'] );
		$this->assertSame( 42, $result['order_id'] );
	}

	/**
	 * "Log in to enroll" enrolls on the sign in, then opens the course.
	 */
	public function test_sign_in_finishes_a_free_enrollment() {
		$course_id = $this->make_course();
		$student   = $this->make_student();
		$target    = add_query_arg( 'shapelms_enroll', $course_id, get_permalink( $course_id ) );

		wp_set_current_user( $student );
		$next = Auth::finish_enroll( $student, $target );

		$this->assertTrue( $this->service( EnrollmentService::class )->is_enrolled( $student, $course_id ) );
		$this->assertStringNotContainsString( 'shapelms_enroll', $next );
	}

	/**
	 * Paid and closed courses are not enrolled in; other destinations pass through.
	 */
	public function test_sign_in_never_enrolls_in_paid_or_closed_courses() {
		$student = $this->make_student();
		$enroll  = $this->service( EnrollmentService::class );
		wp_set_current_user( $student );

		$paid = $this->make_course();
		update_post_meta( $paid, '_shapelms_price_type', 'paid' );
		update_post_meta( $paid, '_shapelms_price', 49 );
		$target = add_query_arg( 'shapelms_enroll', $paid, get_permalink( $paid ) );
		$this->assertSame( $target, Auth::finish_enroll( $student, $target ) );
		$this->assertFalse( $enroll->is_enrolled( $student, $paid ) );

		$draft = $this->make_course( array( 'status' => 'draft' ) );
		$url   = add_query_arg( 'shapelms_enroll', $draft, home_url( '/x/' ) );
		$this->assertSame( home_url( '/x/' ), Auth::finish_enroll( $student, $url ) );
		$this->assertFalse( $enroll->is_enrolled( $student, $draft ) );

		$this->assertSame( home_url( '/y/' ), Auth::finish_enroll( $student, home_url( '/y/' ) ) );
	}
}
