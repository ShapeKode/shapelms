<?php
/**
 * Order service.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Core\Events;
use ShapeLMS\Database\OrderRepository;

/**
 * Creates orders, completes them (enrolling the buyer) and refunds them.
 */
class OrderService {

	/**
	 * Status changes an admin may make, from => to. Refunds go through admin_refund() so the
	 * amount is recorded, and a refunded order is final.
	 *
	 * @var array<string, string[]>
	 */
	const TRANSITIONS = array(
		'pending'   => array( 'completed', 'on-hold', 'cancelled', 'failed' ),
		'failed'    => array( 'completed', 'pending', 'cancelled' ),
		'on-hold'   => array( 'completed', 'pending', 'cancelled' ),
		'completed' => array( 'cancelled' ),
		'cancelled' => array( 'completed', 'pending', 'on-hold' ),
		'refunded'  => array(),
	);

	/**
	 * Most entries kept in an order's history.
	 */
	const MAX_NOTES = 100;

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
	 * Whether a user may buy a course right now. The checkout page and the order share this rule.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return true|\WP_Error
	 */
	public function can_purchase( $user_id, $course_id ) {
		$can = true;

		if ( ! CourseService::is_course( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			$can = new \WP_Error( 'shapelms_not_available', __( 'This course is not available.', 'shapelms' ) );
		} elseif ( post_password_required( $course_id ) ) {
			$can = new \WP_Error( 'shapelms_password_required', __( 'Enter the password on the course page first.', 'shapelms' ) );
		} elseif ( shapelms()->get( CourseService::class )->is_full( $course_id ) ) {
			$can = new \WP_Error(
				'shapelms_course_full',
				/* translators: %s: course title */
				sprintf( __( '%s is full.', 'shapelms' ), get_the_title( $course_id ) )
			);
		} elseif ( $user_id ) {
			$missing = $this->enrollment->missing_prerequisites( $user_id, $course_id );
			if ( $missing ) {
				$can = new \WP_Error(
					'shapelms_prerequisites',
					sprintf(
						/* translators: 1: course title, 2: list of course titles */
						__( '%1$s requires you to complete these courses first: %2$s', 'shapelms' ),
						get_the_title( $course_id ),
						implode( ', ', array_map( 'get_the_title', $missing ) )
					),
					array( 'courses' => $missing )
				);
			}
		}

		/**
		 * Filters whether a user may buy a course.
		 *
		 * @param true|\WP_Error $can       Decision.
		 * @param int            $user_id   User id.
		 * @param int            $course_id Course id.
		 */
		return apply_filters( 'shapelms_can_purchase_course', $can, (int) $user_id, (int) $course_id );
	}

	/**
	 * The user's unpaid order with exactly these items, so trying again does not pile up orders.
	 *
	 * @param int               $user_id User id.
	 * @param array<int, array> $items   Items.
	 * @param float             $total   Order total.
	 * @return object|null
	 */
	private function unpaid_order( $user_id, array $items, $total ) {
		$wanted = array_map( 'intval', wp_list_pluck( $items, 'course_id' ) );
		sort( $wanted );

		$rows = $this->orders->where(
			array(
				'user_id'    => (int) $user_id,
				'status'     => array( 'pending', 'failed' ),
				'trashed_at' => array(
					'op'    => '=',
					'value' => null,
				),
			),
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
				'limit'   => 20,
			)
		);

		foreach ( $rows as $row ) {
			$have = array_map( 'intval', wp_list_pluck( $this->orders->items( (int) $row->id ), 'course_id' ) );
			sort( $have );

			if ( $have === $wanted && abs( (float) $row->total - (float) $total ) < 0.001 ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Creates a pending order for one or more courses.
	 *
	 * @param int    $user_id    Buyer.
	 * @param int[]  $course_ids Courses.
	 * @param string $gateway    Gateway id.
	 * @param array  $billing    Billing details from checkout.
	 * @return object|\WP_Error Order row.
	 */
	public function create( $user_id, array $course_ids, $gateway, array $billing = array() ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'shapelms_invalid_user', __( 'User not found.', 'shapelms' ) );
		}

		$items    = array();
		$subtotal = 0.0;

		foreach ( array_unique( array_map( 'intval', $course_ids ) ) as $course_id ) {
			/**
			 * Lets an add-on sell something other than a single course (for example a bundle).
			 *
			 * Return null to use the default course handling, false to skip the id, a WP_Error to stop
			 * the order, or an item { course_id, title, price }. The item's course_id is stored on the
			 * order line; use the shapelms_order_item_course_ids filter to say which courses it grants.
			 *
			 * @param array|false|\WP_Error|null $item    Item.
			 * @param int                        $id      Requested id.
			 * @param int                        $user_id Buyer.
			 */
			$custom = apply_filters( 'shapelms_order_item', null, $course_id, (int) $user_id );

			if ( is_wp_error( $custom ) ) {
				return $custom;
			}
			if ( false === $custom ) {
				continue;
			}
			if ( is_array( $custom ) ) {
				$price     = max( 0.0, (float) ( $custom['price'] ?? 0 ) );
				$items[]   = array(
					'course_id' => isset( $custom['course_id'] ) ? (int) $custom['course_id'] : $course_id,
					'title'     => sanitize_text_field( (string) ( $custom['title'] ?? get_the_title( $course_id ) ) ),
					'price'     => $price,
				);
				$subtotal += $price;
				continue;
			}

			if ( ! CourseService::is_course( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
				continue;
			}
			if ( $this->enrollment->is_enrolled( $user_id, $course_id ) ) {
				continue;
			}
			if ( $this->open_order( $user_id, $course_id ) ) {
				return new \WP_Error(
					'shapelms_order_on_hold',
					/* translators: %s: course title */
					sprintf( __( 'You already have an order for %s that is waiting for payment.', 'shapelms' ), get_the_title( $course_id ) )
				);
			}

			$pricing = CourseService::pricing( $course_id );
			if ( $pricing['is_free'] ) {
				continue;
			}
			$can = $this->can_purchase( $user_id, $course_id );
			if ( is_wp_error( $can ) ) {
				return $can;
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

		$payer = array(
			'gateway'       => sanitize_key( $gateway ),
			'billing_email' => ! empty( $billing['email'] ) ? $billing['email'] : $user->user_email,
			'billing_name'  => ! empty( $billing['first_name'] ) ? trim( $billing['first_name'] . ' ' . ( $billing['last_name'] ?? '' ) ) : $user->display_name,
			'billing'       => $billing ? (string) wp_json_encode( $billing ) : null,
		);

		$unpaid = $this->unpaid_order( $user_id, $items, max( 0, (float) $totals['total'] ) );

		if ( $unpaid ) {
			$this->orders->update( (int) $unpaid->id, array_merge( $payer, array( 'status' => 'pending' ) ) );

			return $this->orders->find( (int) $unpaid->id );
		}

		$order_id = $this->orders->insert(
			array_merge(
				$payer,
				array(
					'order_key'  => 'slms_' . wp_generate_password( 24, false ),
					'user_id'    => (int) $user_id,
					'status'     => 'pending',
					'currency'   => CurrencyService::code(),
					'subtotal'   => (float) $totals['subtotal'],
					'discount'   => (float) $totals['discount'],
					'total'      => max( 0, (float) $totals['total'] ),
					'ip_address' => $this->client_ip(),
					'created_at' => shapelms_now(),
				)
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
			/**
			 * Courses an order line grants. By default the line's own course.
			 *
			 * @param int[]  $course_ids Course ids.
			 * @param object $item       Order item row.
			 * @param object $order      Order row.
			 */
			$grant = (array) apply_filters( 'shapelms_order_item_course_ids', array( (int) $item->course_id ), $item, $order );

			foreach ( array_unique( array_map( 'intval', $grant ) ) as $grant_id ) {
				if ( $grant_id > 0 ) {
					$this->enrollment->enroll( (int) $order->user_id, $grant_id, 'order', (int) $order->id );
				}
			}
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

		$this->add_note( (int) $order->id, __( 'Payment completed. Course access granted.', 'shapelms' ), 'status' );

		return true;
	}

	/**
	 * Marks an order as waiting for an offline payment.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function hold( $order_id ) {
		$this->orders->update( (int) $order_id, array( 'status' => 'on-hold' ) );
		$this->add_note( (int) $order_id, __( 'Order on hold, waiting for payment.', 'shapelms' ), 'status' );

		/**
		 * Fires when an order starts waiting for an offline payment.
		 *
		 * @param object $order Order.
		 */
		do_action( 'shapelms_order_on_hold', $this->orders->find( (int) $order_id ) );
	}

	/**
	 * The user's order for a course that is waiting for an offline payment.
	 *
	 * @param int $user_id   User id.
	 * @param int $course_id Course id.
	 * @return object|null
	 */
	public function open_order( $user_id, $course_id ) {
		return $user_id ? $this->orders->find_on_hold( (int) $user_id, (int) $course_id ) : null;
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
				'status'          => $status,
				'refunded_at'     => 'refunded' === $status ? shapelms_now() : $order->refunded_at,
				'refunded_amount' => 'refunded' === $status ? (float) $order->total : (float) $order->refunded_amount,
			)
		);

		$this->enrollment->revoke_order( (int) $order->id );

		$messages = array(
			'refunded'  => __( 'Order refunded in full. Course access removed.', 'shapelms' ),
			'cancelled' => __( 'Order cancelled. Course access removed.', 'shapelms' ),
			'failed'    => __( 'Payment failed.', 'shapelms' ),
		);
		$this->add_note( (int) $order->id, $messages[ $status ], 'status' );

		/**
		 * Fires after an order is refunded, cancelled or failed and its course access was removed.
		 * Add-ons that granted something else for the order remove it here.
		 *
		 * @param object $order  Order row (before the status change).
		 * @param string $status refunded|cancelled|failed.
		 */
		do_action( 'shapelms_order_revoked', $order, $status );

		if ( 'refunded' === $status ) {
			Events::fire( Events::ORDER_REFUNDED, (int) $order->user_id, array( 'order_id' => (int) $order->id ) );
		}

		return true;
	}

	/**
	 * Admin status change.
	 *
	 * @param int    $order_id Order id.
	 * Only the moves in TRANSITIONS are allowed; refunds use admin_refund().
	 *
	 * @param int    $order_id Order id.
	 * @param string $status   New status.
	 * @param string $reason   Optional reason, kept in the order history.
	 * @return true|\WP_Error
	 */
	public function set_status( $order_id, $status, $reason = '' ) {
		$order = $this->orders->find( (int) $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Order not found.', 'shapelms' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $order->trashed_at ) ) {
			return new \WP_Error( 'shapelms_order_trashed', __( 'Restore this order from the trash before changing its status.', 'shapelms' ), array( 'status' => 409 ) );
		}
		if ( ! in_array( $status, self::allowed_statuses( $order ), true ) ) {
			return new \WP_Error(
				'shapelms_invalid_transition',
				sprintf(
					/* translators: 1: current status, 2: requested status */
					__( 'An order that is %1$s cannot be changed to %2$s.', 'shapelms' ),
					self::status_label( $order->status ),
					self::status_label( $status )
				),
				array( 'status' => 409 )
			);
		}

		$reason = sanitize_textarea_field( (string) $reason );

		switch ( $status ) {
			case 'completed':
				// Completing a cancelled order again starts its payment over.
				$this->orders->update( (int) $order->id, array( 'refunded_amount' => 0 ) );
				$this->complete( (int) $order->id );
				break;

			case 'cancelled':
			case 'failed':
				$this->revoke( (int) $order->id, $status );
				break;

			default:
				// pending or on-hold: waiting for payment again, so no access until it is paid.
				$this->enrollment->revoke_order( (int) $order->id );
				$this->orders->update( (int) $order->id, array( 'status' => $status ) );
				$this->add_note(
					(int) $order->id,
					'on-hold' === $status ? __( 'Order on hold, waiting for payment.', 'shapelms' ) : __( 'Order set to pending payment.', 'shapelms' ),
					'status'
				);
		}

		if ( '' !== $reason ) {
			/* translators: %s: reason given by the admin */
			$this->append_to_last_note( (int) $order->id, sprintf( __( 'Reason: %s', 'shapelms' ), $reason ) );
		}

		return true;
	}

	/**
	 * Records a refund made by an admin.
	 *
	 * ShapeLMS does not move the money: the admin returns it from the gateway dashboard or in
	 * person. When the gateway later reports the same refund, refund() sees it is already counted.
	 *
	 * @param int    $order_id Order id.
	 * @param float  $amount   Amount refunded now.
	 * @param string $reason   Optional reason.
	 * @return string|\WP_Error refunded|partially-refunded.
	 */
	public function admin_refund( $order_id, $amount, $reason = '' ) {
		$order = $this->orders->find( (int) $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'shapelms_not_found', __( 'Order not found.', 'shapelms' ), array( 'status' => 404 ) );
		}
		if ( 'completed' !== $order->status || ! empty( $order->trashed_at ) ) {
			return new \WP_Error( 'shapelms_not_refundable', __( 'Only a paid order can be refunded.', 'shapelms' ), array( 'status' => 409 ) );
		}

		$left   = round( (float) $order->total - (float) $order->refunded_amount, 2 );
		$amount = round( (float) $amount, 2 );

		if ( $amount <= 0 || $amount > $left + 0.001 ) {
			return new \WP_Error(
				'shapelms_refund_amount',
				/* translators: %s: amount that can still be refunded */
				sprintf( __( 'Enter an amount between 0 and %s.', 'shapelms' ), shapelms_format_price( $left ) ),
				array( 'status' => 400 )
			);
		}

		$result = $this->refund( (int) $order->id, (float) $order->refunded_amount + $amount );
		$reason = sanitize_textarea_field( (string) $reason );

		if ( '' !== $reason ) {
			/* translators: %s: reason given by the admin */
			$this->append_to_last_note( (int) $order->id, sprintf( __( 'Reason: %s', 'shapelms' ), $reason ) );
		}

		return $result;
	}

	/**
	 * Statuses an admin can move an order to.
	 *
	 * @param object $order Order row.
	 * @return string[]
	 */
	public static function allowed_statuses( $order ) {
		return self::TRANSITIONS[ $order->status ] ?? array();
	}

	/**
	 * Readable order status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'pending'   => __( 'pending payment', 'shapelms' ),
			'on-hold'   => __( 'on hold', 'shapelms' ),
			'completed' => __( 'completed', 'shapelms' ),
			'refunded'  => __( 'refunded', 'shapelms' ),
			'cancelled' => __( 'cancelled', 'shapelms' ),
			'failed'    => __( 'failed', 'shapelms' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Adds an entry to the order history.
	 *
	 * @param int    $order_id Order id.
	 * @param string $text     Text.
	 * @param string $type     status|refund|note.
	 * @return array<string, mixed>|null The entry, null when the order is gone.
	 */
	public function add_note( $order_id, $text, $type = 'note' ) {
		$order = $this->orders->find( (int) $order_id );

		if ( ! $order ) {
			return null;
		}

		$notes   = self::notes( $order );
		$entry   = array(
			'time' => shapelms_now(),
			'type' => in_array( $type, array( 'status', 'refund', 'note' ), true ) ? $type : 'note',
			'text' => sanitize_textarea_field( (string) $text ),
			// 0 = the system (a gateway, a webhook or checkout).
			'user' => (int) get_current_user_id(),
		);
		$notes[] = $entry;

		$this->orders->update( (int) $order->id, array( 'notes' => (string) wp_json_encode( array_slice( $notes, -self::MAX_NOTES ) ) ) );

		return $entry;
	}

	/**
	 * Adds a line (such as the admin's reason) to the newest history entry, the one the
	 * action just wrote, so the reason sits with its event.
	 *
	 * @param int    $order_id Order id.
	 * @param string $line     Line to add.
	 * @return void
	 */
	private function append_to_last_note( $order_id, $line ) {
		$order = $this->orders->find( (int) $order_id );
		$notes = $order ? self::notes( $order ) : array();

		if ( ! $notes ) {
			$this->add_note( (int) $order_id, $line, 'note' );
			return;
		}

		$last                   = count( $notes ) - 1;
		$notes[ $last ]['text'] = trim( (string) ( $notes[ $last ]['text'] ?? '' ) . "\n" . sanitize_textarea_field( $line ) );
		$this->orders->update( (int) $order_id, array( 'notes' => (string) wp_json_encode( $notes ) ) );
	}

	/**
	 * The order history for the admin screen, newest first, with author names.
	 *
	 * @param object $order Order row.
	 * @return array<int, array<string, mixed>>
	 */
	private static function history( $order ) {
		$out = array();

		foreach ( array_reverse( self::notes( $order ) ) as $note ) {
			$user  = ! empty( $note['user'] ) ? get_userdata( (int) $note['user'] ) : false;
			$out[] = array(
				'time'   => (string) ( $note['time'] ?? '' ),
				'type'   => (string) ( $note['type'] ?? 'note' ),
				'text'   => (string) ( $note['text'] ?? '' ),
				'author' => $user ? $user->display_name : __( 'System', 'shapelms' ),
			);
		}

		return $out;
	}

	/**
	 * The order history, oldest first.
	 *
	 * @param object $order Order row.
	 * @return array<int, array<string, mixed>>
	 */
	public static function notes( $order ) {
		$raw = isset( $order->notes ) ? (string) $order->notes : '';

		if ( '' === $raw ) {
			return array();
		}

		$notes = json_decode( $raw, true );

		return is_array( $notes ) ? array_values( array_filter( $notes, 'is_array' ) ) : array();
	}

	/**
	 * Records a refund reported by the gateway.
	 *
	 * A full refund revokes the order. A partial one keeps the order completed and the course
	 * access, and only records how much went back (shown as "Partially refunded").
	 *
	 * @param int        $order_id Order id.
	 * @param float|null $refunded Total refunded so far, in the order currency; null = full refund.
	 * @return string refunded|partially-refunded|ignored.
	 */
	public function refund( $order_id, $refunded = null ) {
		$order = $this->orders->find( (int) $order_id );

		// Already refunded (for example recorded by an admin before the gateway's event arrived).
		if ( ! $order || 'refunded' === $order->status ) {
			return 'ignored';
		}

		if ( null === $refunded || (float) $refunded + 0.001 >= (float) $order->total ) {
			return $this->revoke( (int) $order->id, 'refunded' ) ? 'refunded' : 'ignored';
		}

		// Only a paid order can be partly refunded; a late or repeated event must not lower the amount.
		if ( 'completed' !== $order->status || (float) $refunded <= (float) $order->refunded_amount ) {
			return 'ignored';
		}

		$this->orders->update( (int) $order->id, array( 'refunded_amount' => round( (float) $refunded, 2 ) ) );
		$this->add_note(
			(int) $order->id,
			sprintf(
				/* translators: 1: amount refunded now, 2: total refunded so far */
				__( 'Partially refunded %1$s (%2$s in total). Course access kept.', 'shapelms' ),
				shapelms_format_price( (float) $refunded - (float) $order->refunded_amount ),
				shapelms_format_price( (float) $refunded )
			),
			'refund'
		);

		/**
		 * Fires after part of an order was refunded. Course access is kept.
		 *
		 * @param object $order    Order row (before the change).
		 * @param float  $refunded Total refunded so far.
		 */
		do_action( 'shapelms_order_partially_refunded', $order, (float) $refunded );

		return 'partially-refunded';
	}

	/**
	 * Admin list tab an order belongs to.
	 *
	 * @param object $order Order row.
	 * @return string incomplete|pending|completed|cancelled|trash.
	 */
	public static function order_view( $order ) {
		if ( ! empty( $order->trashed_at ) ) {
			return 'trash';
		}

		foreach ( OrderRepository::VIEWS as $view => $statuses ) {
			if ( in_array( $order->status, $statuses, true ) ) {
				return $view;
			}
		}

		return 'incomplete';
	}

	/**
	 * Payment status of an order. Mirrors OrderRepository::PAYMENT_SQL.
	 *
	 * @param object $order Order row.
	 * @return string paid|partially-refunded|unpaid|pending|failed|refunded.
	 */
	public static function payment_status( $order ) {
		switch ( $order->status ) {
			case 'completed':
				return (float) ( $order->refunded_amount ?? 0 ) > 0 ? 'partially-refunded' : 'paid';
			case 'on-hold':
				return 'pending';
			case 'failed':
				return 'failed';
			case 'refunded':
				return 'refunded';
			case 'cancelled':
				return empty( $order->paid_at ) ? 'unpaid' : 'paid';
			default:
				return 'unpaid';
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
			'refunded'       => (float) ( $order->refunded_amount ?? 0 ),
			'refunded_html'  => shapelms_format_price( (float) ( $order->refunded_amount ?? 0 ) ),
			'view'           => self::order_view( $order ),
			'payment_status' => self::payment_status( $order ),
			'trashed_at'     => $order->trashed_at ?? null,
			'allowed'        => empty( $order->trashed_at ) ? self::allowed_statuses( $order ) : array(),
			'refundable'     => 'completed' === $order->status && empty( $order->trashed_at ) ? round( (float) $order->total - (float) ( $order->refunded_amount ?? 0 ), 2 ) : 0,
			'history'        => self::history( $order ),
			'gateway'        => $order->gateway,
			'transaction_id' => $order->transaction_id,
			'billing_email'  => $order->billing_email,
			'billing_name'   => $order->billing_name,
			'billing'        => ! empty( $order->billing ) ? (array) json_decode( (string) $order->billing, true ) : array(),
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
