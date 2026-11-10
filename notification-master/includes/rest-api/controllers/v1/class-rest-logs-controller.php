<?php
/**
 * Class Rest_Logs_Controller
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
class Rest_Logs_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'logs';

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
						'type'     => array(
							'type'        => 'string',
							'description' => __( 'Log type.', 'notification-master' ),
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

		// Export logs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_logs' ),
				'permission_callback' => array( $this, 'get_logs_permissions_check' ),
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
		$type     = $request->get_param( 'type' );
		$count    = Notification_Master()->logger->get_count();

		$where = array();

		if ( $type ) {
			$where['type'] = $type;
		}

		$logs   = Notification_Master()->logger->get_logs( $per_page, $page, $where );
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
		Notification_Master()->logger->delete( $ids );

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
	 * Export logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_logs( $request ) {
		$logs     = Notification_Master()->logger->get_all_logs();
		$filename = 'logs-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode( $logs );
		exit;
	}
}
