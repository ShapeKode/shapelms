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
						'status' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					)
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
						'enum'     => array( 'pending', 'completed', 'refunded', 'cancelled', 'failed' ),
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
				'status'   => $request->get_param( 'status' ),
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		return $this->paginated( array_map( array( $this->service, 'to_array' ), $result['items'] ), $result['total'], (int) $request->get_param( 'per_page' ) );
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

		$result = $this->service->set_status( (int) $order->id, $request->get_param( 'status' ) );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

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
