<?php
/**
 * Privacy (GDPR) module.
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Modules\Privacy;

use ShapeLMS\Core\Modules\AbstractModule;
use ShapeLMS\Database\AttemptRepository;
use ShapeLMS\Database\EnrollmentRepository;
use ShapeLMS\Database\OrderRepository;
use ShapeLMS\Database\ProgressRepository;

/**
 * Registers personal data exporters and erasers.
 */
class Module extends AbstractModule {

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id() {
		return 'privacy';
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Privacy tools', 'shapelms' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Adds LMS data to the WordPress personal data export and erasure tools.', 'shapelms' );
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function exporters( $exporters ) {
		$exporters['shapelms'] = array(
			'exporter_friendly_name' => __( 'ShapeLMS learning data', 'shapelms' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function erasers( $erasers ) {
		$erasers['shapelms'] = array(
			'eraser_friendly_name' => __( 'ShapeLMS learning data', 'shapelms' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Exports enrollments, attempts and orders.
	 *
	 * @param string $email Email.
	 * @return array{data: array, done: bool}
	 */
	public function export( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();

		if ( ! $user ) {
			return array(
				'data' => $data,
				'done' => true,
			);
		}

		$enrollments = shapelms()->get( EnrollmentRepository::class )->where( array( 'user_id' => $user->ID ) );
		foreach ( $enrollments as $row ) {
			$data[] = array(
				'group_id'    => 'shapelms_enrollments',
				'group_label' => __( 'Course enrollments', 'shapelms' ),
				'item_id'     => 'enrollment-' . $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Course', 'shapelms' ),
						'value' => get_the_title( (int) $row->course_id ),
					),
					array(
						'name'  => __( 'Status', 'shapelms' ),
						'value' => $row->status,
					),
					array(
						'name'  => __( 'Progress', 'shapelms' ),
						'value' => $row->progress . '%',
					),
					array(
						'name'  => __( 'Started', 'shapelms' ),
						'value' => $row->started_at,
					),
					array(
						'name'  => __( 'Completed', 'shapelms' ),
						'value' => (string) $row->completed_at,
					),
				),
			);
		}

		$attempts = shapelms()->get( AttemptRepository::class )->where( array( 'user_id' => $user->ID ) );
		foreach ( $attempts as $row ) {
			$data[] = array(
				'group_id'    => 'shapelms_attempts',
				'group_label' => __( 'Quiz attempts', 'shapelms' ),
				'item_id'     => 'attempt-' . $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Quiz', 'shapelms' ),
						'value' => get_the_title( (int) $row->quiz_id ),
					),
					array(
						'name'  => __( 'Score', 'shapelms' ),
						'value' => $row->percentage . '%',
					),
					array(
						'name'  => __( 'Passed', 'shapelms' ),
						'value' => $row->passed ? __( 'Yes', 'shapelms' ) : __( 'No', 'shapelms' ),
					),
					array(
						'name'  => __( 'Date', 'shapelms' ),
						'value' => $row->started_at,
					),
				),
			);
		}

		$orders = shapelms()->get( OrderRepository::class )->where( array( 'user_id' => $user->ID ) );
		foreach ( $orders as $row ) {
			$data[] = array(
				'group_id'    => 'shapelms_orders',
				'group_label' => __( 'Course orders', 'shapelms' ),
				'item_id'     => 'order-' . $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Order', 'shapelms' ),
						'value' => '#' . $row->id,
					),
					array(
						'name'  => __( 'Total', 'shapelms' ),
						'value' => $row->total . ' ' . $row->currency,
					),
					array(
						'name'  => __( 'Status', 'shapelms' ),
						'value' => $row->status,
					),
					array(
						'name'  => __( 'Billing email', 'shapelms' ),
						'value' => $row->billing_email,
					),
					array(
						'name'  => __( 'IP address', 'shapelms' ),
						'value' => $row->ip_address,
					),
					array(
						'name'  => __( 'Date', 'shapelms' ),
						'value' => $row->created_at,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erases learning data; orders are anonymized (kept for accounting).
	 *
	 * @param string $email Email.
	 * @return array{items_removed: int, items_retained: int, messages: string[], done: bool}
	 */
	public function erase( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$removed  = 0;
		$removed += shapelms()->get( ProgressRepository::class )->delete_where( array( 'user_id' => $user->ID ) );
		$removed += shapelms()->get( EnrollmentRepository::class )->delete_where( array( 'user_id' => $user->ID ) );

		$attempts = shapelms()->get( AttemptRepository::class );
		foreach ( $attempts->where( array( 'user_id' => $user->ID ) ) as $row ) {
			$attempts->delete( (int) $row->id );
			++$removed;
		}

		$orders   = shapelms()->get( OrderRepository::class );
		$retained = 0;
		foreach ( $orders->where( array( 'user_id' => $user->ID ) ) as $row ) {
			$orders->update(
				(int) $row->id,
				array(
					'billing_email' => 'deleted@site.invalid',
					'billing_name'  => __( 'Anonymized', 'shapelms' ),
					'ip_address'    => '',
				)
			);
			++$retained;
		}

		shapelms()->get( \ShapeLMS\Database\ActivityLogRepository::class )->delete_where( array( 'user_id' => $user->ID ) );
		delete_user_meta( $user->ID, 'shapelms_wishlist' );

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Orders were anonymized and kept for accounting.', 'shapelms' ) ) : array(),
			'done'           => true,
		);
	}
}
