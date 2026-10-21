<?php
/**
 * Reports.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Content\PostTypes;
use ShapeLMS\Core\Capabilities;
use ShapeLMS\Core\Events;
use ShapeLMS\Database\ActivityLogRepository;
use ShapeLMS\Database\EnrollmentRepository;
use ShapeLMS\Database\OrderRepository;

/**
 * Basic reports built from the activity log, enrollments and orders.
 */
class ReportService {

	/**
	 * Activity.
	 *
	 * @var ActivityLogRepository
	 */
	private $activity;

	/**
	 * Enrollments.
	 *
	 * @var EnrollmentRepository
	 */
	private $enrollments;

	/**
	 * Orders.
	 *
	 * @var OrderRepository
	 */
	private $orders;

	/**
	 * Constructor.
	 *
	 * @param ActivityLogRepository $activity    Activity.
	 * @param EnrollmentRepository  $enrollments Enrollments.
	 * @param OrderRepository       $orders      Orders.
	 */
	public function __construct( ActivityLogRepository $activity, EnrollmentRepository $enrollments, OrderRepository $orders ) {
		$this->activity    = $activity;
		$this->enrollments = $enrollments;
		$this->orders      = $orders;
	}

	/**
	 * Overview for the dashboard and reports screen.
	 *
	 * @param string $from      Y-m-d.
	 * @param string $to        Y-m-d.
	 * @param int    $course_id Optional course.
	 * @param int    $user_id   Viewer (instructors see only their courses).
	 * @return array<string, mixed>
	 */
	public function overview( $from, $to, $course_id, $user_id ) {
		$is_manager = user_can( $user_id, Capabilities::MANAGE );
		$course_ids = $is_manager ? array() : InstructorService::course_ids( $user_id, array( 'publish' ) );

		if ( ! $is_manager && $course_id && ! in_array( $course_id, $course_ids, true ) ) {
			$course_id = -1;
		}

		$enroll_series   = $this->series( Events::COURSE_ENROLLED, $from, $to, $course_id, $course_ids );
		$complete_series = $this->series( Events::COURSE_COMPLETED, $from, $to, $course_id, $course_ids );

		$counts = $is_manager
			? array(
				'courses'   => (int) wp_count_posts( PostTypes::COURSE )->publish,
				'students'  => $this->enrollments->count( array( 'status' => array( 'active', 'completed' ) ) ),
				'active'    => $this->enrollments->count( array( 'status' => 'active' ) ),
				'completed' => $this->enrollments->count( array( 'status' => 'completed' ) ),
			)
			: array(
				'courses'   => count( $course_ids ),
				'students'  => $course_ids ? $this->enrollments->count(
					array(
						'course_id' => $course_ids,
						'status'    => array( 'active', 'completed' ),
					)
				) : 0,
				'active'    => $course_ids ? $this->enrollments->count(
					array(
						'course_id' => $course_ids,
						'status'    => 'active',
					)
				) : 0,
				'completed' => $course_ids ? $this->enrollments->count(
					array(
						'course_id' => $course_ids,
						'status'    => 'completed',
					)
				) : 0,
			);

		$data = array(
			'range'  => array(
				'from' => $from,
				'to'   => $to,
			),
			'totals' => $counts,
			'period' => array(
				'enrollments' => array_sum( $enroll_series ),
				'completions' => array_sum( $complete_series ),
			),
			'series' => array(
				'enrollments' => $this->fill_days( $enroll_series, $from, $to ),
				'completions' => $this->fill_days( $complete_series, $from, $to ),
			),
			'recent' => array(),
		);

		if ( $is_manager ) {
			$data['revenue']             = $this->orders->revenue();
			$data['period_revenue']      = $this->orders->revenue( $from . ' 00:00:00', $to . ' 23:59:59' );
			$data['revenue_html']        = shapelms_format_price( $data['revenue'] );
			$data['period_revenue_html'] = shapelms_format_price( $data['period_revenue'] );
			$data['orders']              = $this->orders->count( array( 'status' => 'completed' ) );
			$data['recent']              = array_map( array( $this, 'format_activity' ), $this->activity->recent( 15, array( Events::COURSE_ENROLLED, Events::COURSE_COMPLETED, Events::QUIZ_FINISHED, Events::ORDER_COMPLETED, Events::REVIEW_SUBMITTED, Events::INSTRUCTOR_APPLIED ) ) );
		}

		return $data;
	}

	/**
	 * Per-course numbers.
	 *
	 * @param int $user_id Viewer.
	 * @return array<int, array<string, mixed>>
	 */
	public function courses( $user_id ) {
		$is_manager = user_can( $user_id, Capabilities::MANAGE );

		$ids = $is_manager
			? get_posts(
				array(
					'post_type'     => PostTypes::COURSE,
					'post_status'   => 'publish',
					'numberposts'   => -1,
					'fields'        => 'ids',
					'no_found_rows' => true,
				)
			)
			: InstructorService::course_ids( $user_id, array( 'publish' ) );

		$ids = array_map( 'intval', $ids );
		if ( empty( $ids ) ) {
			return array();
		}

		$counts = $this->enrollments->counts_for_courses( $ids );
		$rows   = array();

		foreach ( $ids as $id ) {
			$total     = isset( $counts[ $id ] ) ? $counts[ $id ] : 0;
			$completed = $this->enrollments->count(
				array(
					'course_id' => $id,
					'status'    => 'completed',
				)
			);
			$rating    = CourseService::rating( $id );

			$rows[] = array(
				'id'              => $id,
				'title'           => get_the_title( $id ),
				'url'             => get_permalink( $id ),
				'students'        => $total,
				'completed'       => $completed,
				'completion_rate' => $total > 0 ? round( ( $completed / $total ) * 100 ) : 0,
				'rating'          => $rating['average'],
				'reviews'         => $rating['count'],
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['students'] <=> $a['students'];
			}
		);

		return $rows;
	}

	/**
	 * Daily series, restricted to the instructor's courses when needed.
	 *
	 * @param string $event      Event.
	 * @param string $from       From.
	 * @param string $to         To.
	 * @param int    $course_id  Course filter (-1 = none allowed).
	 * @param int[]  $course_ids Allowed courses for instructors (empty = all).
	 * @return array<string, int>
	 */
	private function series( $event, $from, $to, $course_id, array $course_ids ) {
		if ( -1 === $course_id ) {
			return array();
		}

		if ( $course_id || empty( $course_ids ) ) {
			return $this->activity->daily_counts( $event, $from, $to, $course_id );
		}

		$merged = array();
		foreach ( $course_ids as $id ) {
			foreach ( $this->activity->daily_counts( $event, $from, $to, $id ) as $day => $count ) {
				$merged[ $day ] = ( $merged[ $day ] ?? 0 ) + $count;
			}
		}
		ksort( $merged );

		return $merged;
	}

	/**
	 * Fills missing days with 0 for charts.
	 *
	 * @param array<string, int> $series Series.
	 * @param string             $from   From.
	 * @param string             $to     To.
	 * @return array<int, array{date: string, value: int}>
	 */
	private function fill_days( array $series, $from, $to ) {
		$out   = array();
		$start = strtotime( $from );
		$end   = strtotime( $to );

		if ( ! $start || ! $end || $end < $start || ( $end - $start ) > YEAR_IN_SECONDS * 2 ) {
			return $out;
		}

		for ( $t = $start; $t <= $end; $t += DAY_IN_SECONDS ) {
			$day   = gmdate( 'Y-m-d', $t );
			$out[] = array(
				'date'  => $day,
				'value' => isset( $series[ $day ] ) ? (int) $series[ $day ] : 0,
			);
		}

		return $out;
	}

	/**
	 * Formats an activity row.
	 *
	 * @param object $row Row.
	 * @return array<string, mixed>
	 */
	private function format_activity( $row ) {
		return array(
			'id'           => (int) $row->id,
			'event'        => $row->event,
			'user_id'      => (int) $row->user_id,
			'user_name'    => $row->display_name ? $row->display_name : __( 'Unknown', 'shapelms' ),
			'course_id'    => (int) $row->course_id,
			'course_title' => $row->course_title,
			'value'        => null === $row->value ? null : (float) $row->value,
			'created_at'   => $row->created_at,
		);
	}
}
