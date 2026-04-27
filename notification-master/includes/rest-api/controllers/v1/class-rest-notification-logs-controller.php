<?php
/**
 * Class Rest_Notification_Logs_Controller
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\REST_API\Controllers\V1\Rest_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function Notification_Master\Notification_Master;

/**
 * Logger controller class.
 */
class Rest_Notification_Logs_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'notification-logs';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'get_logs_permissions_check' ),
					'args'                => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => __( 'Current page.', 'notification-master' ),
							'required'    => false,
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => __( 'Number of items per page.', 'notification-master' ),
							'required'    => false,
						),
						'status'   => array(
							'type'        => 'string',
							'description' => __( 'Log status.', 'notification-master' ),
							'required'    => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_logs' ),
					'permission_callback' => array( $this, 'delete_logs_permissions_check' ),
					'args'                => array(
						'ids' => array(
							'type'        => 'array',
							'description' => __( 'Log IDs.', 'notification-master' ),
							'required'    => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs_count' ),
					'permission_callback' => array( $this, 'get_logs_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_logs( $request ) {
		$per_page = $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10;
		$page     = $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1;
		$status   = $request->get_param( 'status' );
		$count    = Notification_Master()->notification_logger->get_count();

		$where = array();

		if ( $status ) {
			$where['status'] = $status;
		}

		$logs   = Notification_Master()->notification_logger->get_logs( $per_page, $page, $where );
		$result = array(
			'logs'  => $logs,
			'count' => $count,
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get logs permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_logs_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Delete logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_logs( $request ) {
		$ids = $request->get_param( 'ids' ) ? $request->get_param( 'ids' ) : array();
		Notification_Master()->notification_logger->delete( $ids );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Delete logs permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_logs_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get logs count.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_logs_count( $request ) {
		// Get logs count for last week.
		$from_date = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
		$to_date   = gmdate( 'Y-m-d 23:59:59' );

		$count       = Notification_Master()->notification_logger->get_count_by_date( $from_date, $to_date );
		$success     = Notification_Master()->notification_logger->get_count_by_date( $from_date, $to_date, 'success' );
		$error       = Notification_Master()->notification_logger->get_count_by_date( $from_date, $to_date, 'error' );
		$daily_count = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$from                = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . $i . ' days' ) );
			$to                  = gmdate( 'Y-m-d 23:59:59', strtotime( '-' . $i . ' days' ) );
			$day                 = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$daily_count[ $day ] = array(
				'count'   => Notification_Master()->notification_logger->get_count_by_date( $from, $to ),
				'success' => Notification_Master()->notification_logger->get_count_by_date( $from, $to, 'success' ),
				'failed'  => Notification_Master()->notification_logger->get_count_by_date( $from, $to, 'error' ),
			);
		}

		// Reverse the array.
		$daily_count = array_reverse( $daily_count );

		$result = array(
			'daily'   => $daily_count,
			'total'   => $count,
			'success' => $success,
			'failed'  => $error,
		);

		return new WP_REST_Response( $result, 200 );
	}
}
