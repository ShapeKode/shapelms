<?php
/**
 * Order service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

use ShapeLMS\Core\Events;
use ShapeLMS\Database\OrderRepository;

/**
 * Creates orders, completes them (enrolling the buyer) and refunds them.
 */
class OrderService {

	/**
	 * Orders.
	 *
	 * @var OrderRepository
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
	 * @param OrderRepository   $orders     Orders.
	 * @param EnrollmentService $enrollment Enrollment.
	 */
	public function __construct( OrderRepository $orders, EnrollmentService $enrollment ) {
		$this->orders     = $orders;
		$this->enrollment = $enrollment;
	}

	/**
	 * Creates a pending order for one or more courses.
	 *
	 * @param int    $user_id    Buyer.
	 * @param int[]  $course_ids Courses.
	 * @param string $gateway    Gateway id.
	 * @return object|\WP_Error Order row.
	 */
	public function create( $user_id, array $course_ids, $gateway ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'shapelms_invalid_user', __( 'User not found.', 'shapelms' ) );
		}

		$items    = array();
		$subtotal = 0.0;

		foreach ( array_unique( array_map( 'intval', $course_ids ) ) as $course_id ) {
			if ( ! CourseService::is_course( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
				continue;
			}
			if ( $this->enrollment->is_enrolled( $user_id, $course_id ) ) {
				continue;
			}
			$pricing = CourseService::pricing( $course_id );
			if ( $pricing['is_free'] ) {
				continue;
			}
			$items[]   = array(
				'course_id' => $course_id,
				'title'     => get_the_title( $course_id ),
				'price'     => $pricing['current'],
			);
			$subtotal += $pricing['current'];
		}

		if ( empty( $items ) ) {
			return new \WP_Error( 'shapelms_empty_order', __( 'There is nothing to purchase.', 'shapelms' ) );
		}

		/**
		 * Filters order totals before creation (coupons hook here).
		 *
		 * @param array $totals  { subtotal, discount, total }.
		 * @param array $items   Items.
		 * @param int   $user_id User id.
		 */
		$totals = apply_filters(
			'shapelms_order_totals',
			array(
				'subtotal' => $subtotal,
				'discount' => 0.0,
				'total'    => $subtotal,
			),
			$items,
			$user_id
		);

		$order_id = $this->orders->insert(
			array(
				'order_key'     => 'slms_' . wp_generate_password( 24, false ),
				'user_id'       => (int) $user_id,
				'status'        => 'pending',
				'currency'      => CurrencyService::code(),
				'subtotal'      => (float) $totals['subtotal'],
				'discount'      => (float) $totals['discount'],
				'total'         => max( 0, (float) $totals['total'] ),
				'gateway'       => sanitize_key( $gateway ),
				'billing_email' => $user->user_email,
				'billing_name'  => $user->display_name,
				'ip_address'    => $this->client_ip(),
				'created_at'    => shapelms_now(),
			)
		);

		if ( ! $order_id ) {
			return new \WP_Error( 'shapelms_order_failed', __( 'Could not create the order.', 'shapelms' ) );
		}

		foreach ( $items as $item ) {
			$this->orders->add_item( $order_id, $item['course_id'], $item['title'], $item['price'] );
		}

		return $this->orders->find( $order_id );
	}

	/**
	 * Marks an order paid and enrolls the buyer.
	 *
	 * @param int    $order_id       Order id.
	 * @param string $transaction_id Gateway transaction id.
	 * @return bool
	 */
	public function complete( $order_id, $transaction_id = '' ) {
		$order = $this->orders->find( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( 'completed' === $order->status ) {
			return true;
		}

		$this->orders->update(
			(int) $order->id,
			array(
				'status'         => 'completed',
				'transaction_id' => $transaction_id ? sanitize_text_field( $transaction_id ) : $order->transaction_id,
				'paid_at'        => shapelms_now(),
			)
		);

		foreach ( $this->orders->items( (int) $order->id ) as $item ) {
			$this->enrollment->enroll( (int) $order->user_id, (int) $item->course_id, 'order', (int) $order->id );
		}

		Events::fire(
			Events::ORDER_COMPLETED,
			(int) $order->user_id,
			array(
				'order_id' => (int) $order->id,
				'value'    => (float) $order->total,
				'total'    => (float) $order->total,
			)
		);

		return true;
	}

	/**
	 * Refunds (or cancels) an order and revokes access.
	 *
	 * @param int    $order_id Order id.
	 * @param string $status   refunded|cancelled|failed.
	 * @return bool
	 */
	public function revoke( $order_id, $status = 'refunded' ) {
		$order = $this->orders->find( $order_id );

		if ( ! $order ) {
			return false;
		}

		$status = in_array( $status, array( 'refunded', 'cancelled', 'failed' ), true ) ? $status : 'refunded';

		$this->orders->update(
			(int) $order->id,
			array(
				'status'      => $status,
				'refunded_at' => 'refunded' === $status ? shapelms_now() : $order->refunded_at,
			)
		);

		$this->enrollment->revoke_order( (int) $order->id );

		if ( 'refunded' === $status ) {
			Events::fire( Events::ORDER_REFUNDED, (int) $order->user_id, array( 'order_id' => (int) $order->id ) );
		}

		return true;
	}

	/**
	 * Admin status change.
	 *
	 * @param int    $order_id Order id.
	 * @param string $status   New status.
	 * @return true|\WP_Error
	 */
	public function set_status( $order_id, $status ) {
		switch ( $status ) {
			case 'completed':
				$this->complete( $order_id );
				return true;
			case 'refunded':
			case 'cancelled':
			case 'failed':
				$this->revoke( $order_id, $status );
				return true;
			case 'pending':
				$this->orders->update( $order_id, array( 'status' => 'pending' ) );
				return true;
			default:
				return new \WP_Error( 'shapelms_invalid_status', __( 'Invalid status.', 'shapelms' ) );
		}
	}

	/**
	 * API shape with items.
	 *
	 * @param object $order Order row.
	 * @return array<string, mixed>
	 */
	public function to_array( $order ) {
		$items = array();

		foreach ( $this->orders->items( (int) $order->id ) as $item ) {
			$items[] = array(
				'id'         => (int) $item->id,
				'course_id'  => (int) $item->course_id,
				'title'      => $item->title,
				'price'      => (float) $item->price,
				'price_html' => shapelms_format_price( (float) $item->price ),
				'url'        => get_permalink( (int) $item->course_id ),
			);
		}

		return array(
			'id'             => (int) $order->id,
			'order_key'      => $order->order_key,
			'user_id'        => (int) $order->user_id,
			'status'         => $order->status,
			'currency'       => $order->currency,
			'subtotal'       => (float) $order->subtotal,
			'discount'       => (float) $order->discount,
			'total'          => (float) $order->total,
			'total_html'     => shapelms_format_price( (float) $order->total ),
			'gateway'        => $order->gateway,
			'transaction_id' => $order->transaction_id,
			'billing_email'  => $order->billing_email,
			'billing_name'   => $order->billing_name,
			'created_at'     => $order->created_at,
			'paid_at'        => $order->paid_at,
			'refunded_at'    => $order->refunded_at,
			'items'          => $items,
		);
	}

	/**
	 * Client IP (first hop only, sanitized).
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
