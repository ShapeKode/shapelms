<?php
/**
 * Review service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Events;
use ShapeLMS\Services\EnrollmentService;

/**
 * Stores reviews as comments of type shapelms_review with a rating meta.
 */
class ReviewService {

	const TYPE        = 'shapelms_review';
	const META_RATING = 'shapelms_rating';
	const META_CACHE  = '_shapelms_rating_cache';

	/**
	 * Fills the course rating from the cached aggregate.
	 *
	 * @param array $rating    Rating.
	 * @param int   $course_id Course id.
	 * @return array{average: float, count: int}
	 */
	public static function rating_filter( $rating, $course_id ) {
		$cache = get_post_meta( $course_id, self::META_CACHE, true );

		if ( is_array( $cache ) && isset( $cache['average'], $cache['count'] ) ) {
			return array(
				'average' => (float) $cache['average'],
				'count'   => (int) $cache['count'],
			);
		}

		return self::recalculate( $course_id );
	}

	/**
	 * Recalculates and caches the aggregate.
	 *
	 * @param int $course_id Course id.
	 * @return array{average: float, count: int, breakdown: array<int, int>}
	 */
	public static function recalculate( $course_id ) {
		$comments = get_comments(
			array(
				'post_id' => (int) $course_id,
				'type'    => self::TYPE,
				'status'  => 'approve',
				'fields'  => 'ids',
			)
		);

		$breakdown = array(
			5 => 0,
			4 => 0,
			3 => 0,
			2 => 0,
			1 => 0,
		);
		$sum       = 0;

		foreach ( (array) $comments as $comment_id ) {
			$rating = (int) get_comment_meta( (int) $comment_id, self::META_RATING, true );
			$rating = max( 1, min( 5, $rating ) );
			++$breakdown[ $rating ];
			$sum += $rating;
		}

		$count = count( $comments );
		$data  = array(
			'average'   => $count ? round( $sum / $count, 2 ) : 0.0,
			'count'     => $count,
			'breakdown' => $breakdown,
		);

		update_post_meta( $course_id, self::META_CACHE, $data );

		return $data;
	}

	/**
	 * Submits or updates the user's review.
	 *
	 * @param int    $user_id   User id.
	 * @param int    $course_id Course id.
	 * @param int    $rating    1-5.
	 * @param string $content   Review text.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function submit( $user_id, $course_id, $rating, $content ) {
		$enrollment = shapelms()->get( EnrollmentService::class );

		if ( ! $enrollment->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_not_enrolled', __( 'Only enrolled students can review this course.', 'shapelms' ), array( 'status' => 403 ) );
		}

		$rating  = max( 1, min( 5, (int) $rating ) );
		$content = sanitize_textarea_field( $content );
		$user    = get_userdata( $user_id );
		$status  = (bool) apply_filters( 'shapelms_review_requires_approval', false, $user_id, $course_id ) ? 0 : 1;

		$existing = get_comments(
			array(
				'post_id' => $course_id,
				'user_id' => $user_id,
				'type'    => self::TYPE,
				'status'  => 'all',
				'number'  => 1,
			)
		);

		if ( $existing ) {
			$comment_id = (int) $existing[0]->comment_ID;

			if ( in_array( wp_get_comment_status( $comment_id ), array( 'spam', 'trash' ), true ) ) {
				return new \WP_Error( 'shapelms_review_blocked', __( 'Your review cannot be updated.', 'shapelms' ), array( 'status' => 403 ) );
			}

			wp_update_comment(
				array(
					'comment_ID'       => $comment_id,
					'comment_content'  => $content,
					'comment_approved' => $status,
				)
			);
		} else {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $course_id,
					'comment_author'       => $user ? $user->display_name : '',
					'comment_author_email' => $user ? $user->user_email : '',
					'comment_content'      => $content,
					'comment_type'         => self::TYPE,
					'user_id'              => $user_id,
					'comment_approved'     => $status,
				)
			);
		}

		if ( ! $comment_id ) {
			return new \WP_Error( 'shapelms_review_failed', __( 'Could not save your review.', 'shapelms' ) );
		}

		update_comment_meta( $comment_id, self::META_RATING, $rating );
		$aggregate = self::recalculate( $course_id );

		Events::fire(
			Events::REVIEW_SUBMITTED,
			$user_id,
			array(
				'course_id' => $course_id,
				'object_id' => $comment_id,
				'rating'    => $rating,
				'value'     => $rating,
			)
		);

		return array(
			'id'        => $comment_id,
			'rating'    => $rating,
			'content'   => $content,
			'approved'  => (bool) $status,
			'aggregate' => $aggregate,
		);
	}

	/**
	 * The current user's review for a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return array{rating: int, content: string}|null
	 */
	public static function user_review( $user_id, $course_id ) {
		if ( ! $user_id ) {
			return null;
		}

		$existing = get_comments(
			array(
				'post_id' => $course_id,
				'user_id' => $user_id,
				'type'    => self::TYPE,
				'status'  => 'all',
				'number'  => 1,
			)
		);

		if ( ! $existing ) {
			return null;
		}

		return array(
			'rating'  => (int) get_comment_meta( (int) $existing[0]->comment_ID, self::META_RATING, true ),
			'content' => $existing[0]->comment_content,
		);
	}

	/**
	 * Lists approved reviews.
	 *
	 * @param int $course_id Course id.
	 * @param int $page      Page.
	 * @param int $per_page  Per page.
	 * @return array{items: array<int, array<string, mixed>>, total: int, aggregate: array}
	 */
	public static function list( $course_id, $page = 1, $per_page = 10 ) {
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$args     = array(
			'post_id' => (int) $course_id,
			'type'    => self::TYPE,
			'status'  => 'approve',
			'number'  => $per_page,
			'offset'  => ( max( 1, (int) $page ) - 1 ) * $per_page,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		$comments = get_comments( $args );
		$total    = (int) get_comments(
			array_merge(
				$args,
				array(
					'count'  => true,
					'number' => 0,
					'offset' => 0,
				)
			)
		);
		$items    = array();

		foreach ( (array) $comments as $comment ) {
			$items[] = array(
				'id'      => (int) $comment->comment_ID,
				'name'    => $comment->comment_author,
				'avatar'  => get_avatar_url( (int) $comment->user_id ? (int) $comment->user_id : $comment->comment_author_email, array( 'size' => 72 ) ),
				'rating'  => (int) get_comment_meta( (int) $comment->comment_ID, self::META_RATING, true ),
				'content' => $comment->comment_content,
				'date'    => get_comment_date( '', $comment ),
				'iso'     => mysql2date( 'c', $comment->comment_date_gmt, false ),
			);
		}

		$aggregate = get_post_meta( $course_id, self::META_CACHE, true );

		return array(
			'items'     => $items,
			'total'     => $total,
			'aggregate' => is_array( $aggregate ) ? $aggregate : self::recalculate( $course_id ),
		);
	}
}
