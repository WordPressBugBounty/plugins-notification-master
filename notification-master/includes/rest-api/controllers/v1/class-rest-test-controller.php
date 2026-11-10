<?php
/**
 * Class Rest_Test_Controller
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\Testing\Test_Sender;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * "Send a test" endpoint.
 */
class Rest_Test_Controller extends Rest_Controller {

	/**
	 * Tests allowed per user per window.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	const RATE_LIMIT = 5;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	const RATE_WINDOW = 60;

	/**
	 * Route base.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'notifications/test';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_test' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'integration'       => array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Integration slug of the connection.', 'notification-master' ),
						),
						'settings'          => array(
							'type'        => 'object',
							'required'    => true,
							'description' => __( 'The connection settings to send with.', 'notification-master' ),
						),
						'trigger'           => array(
							'type'        => 'string',
							'required'    => true,
							'description' => __( 'Trigger slug used for sample merge-tag values.', 'notification-master' ),
						),
						'notification_id'   => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Saved notification ID.', 'notification-master' ),
						),
						'notification_name' => array(
							'type'        => 'string',
							'description' => __( 'Notification name for the log.', 'notification-master' ),
						),
						'recipient'         => array(
							'type'        => 'string',
							'description' => __( 'Email address to send the test to (Email only).', 'notification-master' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Send the test.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_test( $request ) {
		$limited = $this->hit_rate_limit();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$result = Test_Sender::send(
			array(
				'integration'       => $request->get_param( 'integration' ),
				'settings'          => $request->get_param( 'settings' ),
				'trigger'           => $request->get_param( 'trigger' ),
				'notification_id'   => $request->get_param( 'notification_id' ),
				'notification_name' => $request->get_param( 'notification_name' ),
				'recipient'         => $request->get_param( 'recipient' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Count this request against the user's limit.
	 *
	 * @since 2.0.0
	 *
	 * @return true|WP_Error
	 */
	private function hit_rate_limit() {
		$key   = 'ntfm_test_rate_' . get_current_user_id();
		$state = get_transient( $key );
		$now   = time();

		if ( ! is_array( $state ) || $now - (int) $state['start'] >= self::RATE_WINDOW ) {
			$state = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( (int) $state['count'] >= self::RATE_LIMIT ) {
			return new WP_Error(
				'ntfm_test_rate_limited',
				__( 'Too many tests in a row. Wait a minute, then try again.', 'notification-master' ),
				array(
					'status'      => 429,
					'retry_after' => max( 1, self::RATE_WINDOW - ( $now - (int) $state['start'] ) ),
				)
			);
		}

		++$state['count'];
		set_transient( $key, $state, self::RATE_WINDOW );

		return true;
	}

	/**
	 * Admins only.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}
}
