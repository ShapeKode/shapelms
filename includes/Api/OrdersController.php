<?php
/**
 * Orders endpoints (admin).
 *
 * @package ShapeLMS
 */

namespace ShapeLMS\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ShapeLMS\Database\OrderRepository;
use ShapeLMS\Services\OrderService;
use WP_REST_Request;
use WP_REST_Server;

/**
 * List, view, refund, delete orders.
 */
class OrdersController extends RestController {

	/**
	 * Base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Orders.
	 *
	 * @var OrderRepository
	 */
	private $orders;

	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param OrderRepository $orders  Orders.
	 * @param OrderService    $service Service.
	 */
	public function __construct( OrderRepository $orders, OrderService $service ) {
		$this->orders  = $orders;
		$this->service = $service;
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->route(
			'',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_orders' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array_merge(
					$this->pagination_args(),
					array(
						'status'  => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'view'    => array(
							'type'    => 'string',
							'default' => '',
							'enum'    => array_merge( array( '', 'all', 'trash' ), array_keys( OrderRepository::VIEWS ) ),
						),
						'payment' => array(
							'type'    => 'string',
							'default' => '',
							'enum'    => array_merge( array( '' ), array_keys( OrderRepository::PAYMENT_SQL ) ),
						),
						'orderby' => array(
							'type'    => 'string',
							'default' => 'id',
							'enum'    => OrderRepository::SORTABLE,
						),
						'order'   => array(
							'type'    => 'string',
							'default' => 'DESC',
							'enum'    => array( 'ASC', 'DESC', 'asc', 'desc' ),
						),
					)
				),
			)
		);

		$this->route(
			'/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'trash', 'restore', 'delete', 'complete', 'cancel' ),
					),
					'ids'    => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
						'minItems' => 1,
						'maxItems' => 100,
					),
				),
			)
		);

		$this->route(
			'/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'my_orders' ),
				'permission_callback' => array( $this, 'allow_logged_in' ),
			)
		);

		$this->route(
			'/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order' ),
					'permission_callback' => array( $this, 'allow_manager' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_order' ),
					'permission_callback' => array( $this, 'allow_manager' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_status' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'id'     => $this->id_arg(),
					'status' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'pending', 'on-hold', 'completed', 'cancelled', 'failed' ),
					),
					'reason' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)/refund',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refund' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'id'     => $this->id_arg(),
					'amount' => array(
						'type'     => 'number',
						'required' => true,
						'minimum'  => 0.01,
					),
					'reason' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		$this->route(
			'/(?P<id>\d+)/notes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_note' ),
				'permission_callback' => array( $this, 'allow_manager' ),
				'args'                => array(
					'id'   => $this->id_arg(),
					'note' => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 2000,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Lists orders.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_orders( WP_REST_Request $request ) {
		$result = $this->orders->paginate(
			array(
				'search'   => $request->get_param( 'search' ),
				'view'     => $request->get_param( 'view' ),
				'status'   => $request->get_param( 'status' ),
				'payment'  => $request->get_param( 'payment' ),
				'orderby'  => $request->get_param( 'orderby' ),
				'order'    => $request->get_param( 'order' ),
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		$response = $this->paginated( array_map( array( $this->service, 'to_array' ), $result['items'] ), $result['total'], (int) $request->get_param( 'per_page' ) );
		$data     = $response->get_data();

		// Tab counts travel with the list so the tabs update after every change.
		$data['counts'] = $this->orders->counts();
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Bulk trash, restore or permanent delete. Permanent delete only removes trashed orders,
	 * and none of these change enrollments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{done: int}
	 */
	public function bulk( WP_REST_Request $request ) {
		$ids = array_map( 'absint', (array) $request->get_param( 'ids' ) );

		switch ( $request->get_param( 'action' ) ) {
			case 'complete':
			case 'cancel':
				// Orders that cannot make this move (for example already cancelled) are skipped.
				$status = 'complete' === $request->get_param( 'action' ) ? 'completed' : 'cancelled';
				$done   = 0;
				foreach ( $ids as $id ) {
					if ( true === $this->service->set_status( $id, $status ) ) {
						++$done;
					}
				}
				return array(
					'done'    => $done,
					'skipped' => count( $ids ) - $done,
				);

			case 'trash':
				return array( 'done' => $this->orders->trash( $ids ) );

			case 'restore':
				return array( 'done' => $this->orders->restore( $ids ) );

			default:
				$done = 0;
				foreach ( $ids as $id ) {
					$order = $this->orders->find( $id );
					if ( $order && ! empty( $order->trashed_at ) && $this->orders->delete( $id ) ) {
						++$done;
					}
				}
				return array( 'done' => $done );
		}
	}

	/**
	 * Current user's orders.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function my_orders() {
		$result = $this->orders->paginate(
			array(
				'user_id'  => get_current_user_id(),
				'per_page' => 100,
			)
		);

		return array_map( array( $this->service, 'to_array' ), $result['items'] );
	}

	/**
	 * One order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_order( WP_REST_Request $request ) {
		$order = $this->orders->find( (int) $request->get_param( 'id' ) );

		return $order ? $this->service->to_array( $order ) : $this->not_found();
	}

	/**
	 * Changes order status (completing enrolls, refunding/cancelling revokes).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function set_status( WP_REST_Request $request ) {
		$order = $this->orders->find( (int) $request->get_param( 'id' ) );

		if ( ! $order ) {
			return $this->not_found();
		}

		$result = $this->service->set_status( (int) $order->id, $request->get_param( 'status' ), (string) $request->get_param( 'reason' ) );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->service->to_array( $this->orders->find( (int) $order->id ) );
	}

	/**
	 * Records a full or partial refund.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function refund( WP_REST_Request $request ) {
		$order = $this->orders->find( (int) $request->get_param( 'id' ) );

		if ( ! $order ) {
			return $this->not_found();
		}

		$result = $this->service->admin_refund( (int) $order->id, (float) $request->get_param( 'amount' ), (string) $request->get_param( 'reason' ) );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->service->to_array( $this->orders->find( (int) $order->id ) );
	}

	/**
	 * Adds an admin note to the order history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function add_note( WP_REST_Request $request ) {
		$order = $this->orders->find( (int) $request->get_param( 'id' ) );
		$note  = trim( (string) $request->get_param( 'note' ) );

		if ( ! $order ) {
			return $this->not_found();
		}
		if ( '' === $note ) {
			return $this->error( new \WP_Error( 'shapelms_empty_note', __( 'Write a note first.', 'shapelms' ) ) );
		}

		$this->service->add_note( (int) $order->id, $note, 'note' );

		return $this->service->to_array( $this->orders->find( (int) $order->id ) );
	}

	/**
	 * Deletes an order (does not change enrollments).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{deleted: bool}
	 */
	public function delete_order( WP_REST_Request $request ) {
		return array( 'deleted' => $this->orders->delete( (int) $request->get_param( 'id' ) ) );
	}
}
