<?php
/**
 * Class Rest_Scheduled_Controller
 *
 * REST endpoints for inspecting and managing scheduled notification connections.
 *
 * @package notification-master
 *
 * @since 1.7.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\Scheduling\Scheduler;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Scheduled controller.
 */
class Rest_Scheduled_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'scheduled';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_scheduled' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'pending', 'complete', 'failed' ),
							'default' => 'pending',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<action_id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_action' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<action_id>\d+)/send-now',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_now' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'action_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permissions check.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List scheduled connections.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_scheduled( $request ) {
		$results = Scheduler::get_instance()->get_pending(
			array(
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
				'status'   => (string) $request->get_param( 'status' ),
			)
		);

		return new WP_REST_Response(
			array(
				'items' => $results,
			),
			200
		);
	}

	/**
	 * Cancel a scheduled connection.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_action( $request ) {
		$action_id = (int) $request->get_param( 'action_id' );

		$ok = Scheduler::get_instance()->cancel( $action_id );
		if ( ! $ok ) {
			return new WP_Error( 'ntfm_cancel_failed', __( 'Could not cancel the scheduled action.', 'notification-master' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'cancelled' => true ), 200 );
	}

	/**
	 * Force-dispatch a scheduled connection immediately.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_now( $request ) {
		$action_id = (int) $request->get_param( 'action_id' );

		$ok = Scheduler::get_instance()->send_now( $action_id );
		if ( ! $ok ) {
			return new WP_Error( 'ntfm_send_now_failed', __( 'Could not dispatch the scheduled action.', 'notification-master' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'dispatched' => true ), 200 );
	}
}
