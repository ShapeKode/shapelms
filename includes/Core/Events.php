<?php
/**
 * Event bus.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Database\ActivityLogRepository;

/**
 * Fires typed actions and writes each event to the activity log.
 *
 * Events (all receive the same $context array as the second argument):
 *
 * - shapelms_course_enrolled    ( int $user_id, array { course_id, source } )
 * - shapelms_course_unenrolled  ( int $user_id, array { course_id } )
 * - shapelms_lesson_completed   ( int $user_id, array { course_id, lesson_id } )
 * - shapelms_course_completed   ( int $user_id, array { course_id } )
 * - shapelms_quiz_started       ( int $user_id, array { course_id, quiz_id, attempt_id } )
 * - shapelms_quiz_finished      ( int $user_id, array { course_id, quiz_id, attempt_id, score, passed } )
 * - shapelms_order_completed    ( int $user_id, array { order_id, total } )
 * - shapelms_order_refunded     ( int $user_id, array { order_id } )
 * - shapelms_review_submitted   ( int $user_id, array { course_id, rating } )
 * - shapelms_instructor_applied ( int $user_id, array {} )
 * - shapelms_instructor_approved( int $user_id, array {} )
 */
class Events {

	const COURSE_ENROLLED     = 'shapelms_course_enrolled';
	const COURSE_UNENROLLED   = 'shapelms_course_unenrolled';
	const LESSON_COMPLETED    = 'shapelms_lesson_completed';
	const COURSE_COMPLETED    = 'shapelms_course_completed';
	const QUIZ_STARTED        = 'shapelms_quiz_started';
	const QUIZ_FINISHED       = 'shapelms_quiz_finished';
	const QUIZ_PENDING_REVIEW = 'shapelms_quiz_pending_review';
	const ORDER_COMPLETED     = 'shapelms_order_completed';
	const ORDER_REFUNDED      = 'shapelms_order_refunded';
	const REVIEW_SUBMITTED    = 'shapelms_review_submitted';
	const INSTRUCTOR_APPLIED  = 'shapelms_instructor_applied';
	const INSTRUCTOR_APPROVED = 'shapelms_instructor_approved';

	/**
	 * Fires an event.
	 *
	 * @param string $event   Event name (one of the class constants).
	 * @param int    $user_id User the event belongs to.
	 * @param array  $context Event context. Keys course_id, object_id and value are indexed in the log.
	 * @return void
	 */
	public static function fire( $event, $user_id, array $context = array() ) {
		$user_id = (int) $user_id;

		/**
		 * Filters whether an event should be logged to the activity table.
		 *
		 * @param bool   $log     Whether to log.
		 * @param string $event   Event name.
		 * @param int    $user_id User id.
		 * @param array  $context Context.
		 */
		if ( apply_filters( 'shapelms_log_event', true, $event, $user_id, $context ) ) {
			$object_id = 0;
			foreach ( array( 'lesson_id', 'quiz_id', 'order_id', 'object_id' ) as $key ) {
				if ( ! empty( $context[ $key ] ) ) {
					$object_id = (int) $context[ $key ];
					break;
				}
			}

			ActivityLogRepository::log(
				$event,
				$user_id,
				isset( $context['course_id'] ) ? (int) $context['course_id'] : 0,
				$object_id,
				isset( $context['value'] ) ? (float) $context['value'] : ( isset( $context['score'] ) ? (float) $context['score'] : null ),
				$context
			);
		}

		/**
		 * Fires the named ShapeLMS event, e.g. shapelms_lesson_completed.
		 *
		 * @param int   $user_id User id.
		 * @param array $context Context.
		 */
		do_action( $event, $user_id, $context ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase, WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- dynamic, always shapelms_-prefixed.

		/**
		 * Fires for every ShapeLMS event.
		 *
		 * @param string $event   Event name.
		 * @param int    $user_id User id.
		 * @param array  $context Context.
		 */
		do_action( 'shapelms_event', $event, $user_id, $context );
	}
}
