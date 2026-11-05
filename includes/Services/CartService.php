<?php
/**
 * Native checkout cart.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Courses a student picked before checkout. Guests log in first, so the cart belongs to the user.
 */
class CartService {

	const META = 'shapelms_cart';

	/**
	 * Orders.
	 *
	 * @var OrderService
	 */
	private $orders;

	/**
	 * Enrollment.
	 *
	 * @var EnrollmentService
	 */
	private $enrollment;

	/**
	 * Constructor.
	 *
	 * @param OrderService      $orders     Orders.
	 * @param EnrollmentService $enrollment Enrollment.
	 */
	public function __construct( OrderService $orders, EnrollmentService $enrollment ) {
		$this->orders     = $orders;
		$this->enrollment = $enrollment;
	}

	/**
	 * Whether students add courses to a cart instead of buying them directly.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return ! shapelms_get_setting( 'commerce', 'buy_now', true );
	}

	/**
	 * Whether a user may put a course in the cart. Follows the rules of the order itself,
	 * so a cart never leads to a checkout that refuses the order.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return true|\WP_Error
	 */
	public function can_add( $user_id, $course_id ) {
		$can = $this->orders->can_purchase( $user_id, $course_id );

		if ( is_wp_error( $can ) ) {
			return $can;
		}

		if ( CourseService::pricing( $course_id )['is_free'] ) {
			return new \WP_Error( 'shapelms_cart_free', __( 'This course is free. Enroll from its page.', 'shapelms' ) );
		}

		if ( $this->enrollment->is_enrolled( $user_id, $course_id ) ) {
			return new \WP_Error( 'shapelms_cart_enrolled', __( 'You are already enrolled in this course.', 'shapelms' ) );
		}

		if ( $this->orders->open_order( $user_id, $course_id ) ) {
			/* translators: %s: course title */
			return new \WP_Error( 'shapelms_order_on_hold', sprintf( __( 'You already have an order for %s that is waiting for payment.', 'shapelms' ), get_the_title( $course_id ) ) );
		}

		return true;
	}

	/**
	 * Courses in the cart that can still be bought.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public function course_ids( $user_id ) {
		$ids = array();

		foreach ( $this->stored( $user_id ) as $course_id ) {
			if ( true === $this->can_add( $user_id, $course_id ) ) {
				$ids[] = $course_id;
			}
		}

		return $ids;
	}

	/**
	 * Whether a course is in the cart.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return bool
	 */
	public function has( $user_id, $course_id ) {
		return in_array( (int) $course_id, $this->stored( $user_id ), true );
	}

	/**
	 * Adds a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return true|\WP_Error
	 */
	public function add( $user_id, $course_id ) {
		$can = $this->can_add( $user_id, $course_id );

		if ( is_wp_error( $can ) ) {
			return $can;
		}

		if ( ! $this->has( $user_id, $course_id ) ) {
			$this->save( $user_id, array_merge( $this->stored( $user_id ), array( (int) $course_id ) ) );
		}

		return true;
	}

	/**
	 * Removes a course.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return void
	 */
	public function remove( $user_id, $course_id ) {
		$this->save( $user_id, array_diff( $this->stored( $user_id ), array( (int) $course_id ) ) );
	}

	/**
	 * Stored course ids.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	private function stored( $user_id ) {
		$ids = get_user_meta( $user_id, self::META, true );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Stores course ids.
	 *
	 * @param int   $user_id User id.
	 * @param int[] $ids     Course ids.
	 * @return void
	 */
	private function save( $user_id, array $ids ) {
		if ( $ids ) {
			update_user_meta( $user_id, self::META, array_values( $ids ) );
		} else {
			delete_user_meta( $user_id, self::META );
		}
	}
}
