<?php
/**
 * Class Rest_Overview_Controller
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\Admin\Pro_Notice;
use Notification_Master\Stats\Failures;
use Notification_Master\Stats\Last_Sent;
use Notification_Master\Stats\Overview;
use Notification_Master\Stats\Period;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Overview screen data in one call.
 */
class Rest_Overview_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'overview';

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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_overview' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'days' => array(
							'type'              => 'integer',
							'description'       => __( 'Number of days: 7, 30 or 90.', 'notification-master' ),
							'default'           => 7,
							'validate_callback' => array( $this, 'validate_days' ),
						),
					),
				),
			)
		);

		// Dismiss a notice passed in NotificationsMasterConfig.notices.
		register_rest_route(
			$this->namespace,
			'/notices/dismiss',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'dismiss_notice' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'     => 'string',
							'enum'     => array( Pro_Notice::ID ),
							'required' => true,
						),
					),
				),
			)
		);

		// Newest send per notification, for the list's "Last sent" column.
		register_rest_route(
			$this->namespace,
			'/notifications/last-sent',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_last_sent' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'ids' => array(
							'type'              => array( 'string', 'array' ),
							'description'       => __( 'Notification IDs: comma-separated or an array (max 100).', 'notification-master' ),
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_ids' ),
						),
					),
				),
			)
		);

		// Failed sends per notification, for the Notifications list and the editor.
		register_rest_route(
			$this->namespace,
			'/notifications/failures',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_failures' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'days' => array(
							'type'              => 'integer',
							'description'       => __( 'Number of days: 7, 30 or 90.', 'notification-master' ),
							'default'           => 7,
							'validate_callback' => array( $this, 'validate_days' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Dismiss an app notice for the current user.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function dismiss_notice( $request ) {
		Pro_Notice::dismiss();

		return new WP_REST_Response( array( 'dismissed' => (string) $request->get_param( 'id' ) ), 200 );
	}

	/**
	 * Turn "1,2,3" or [1,2,3] into a list of positive ints.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Raw ids.
	 *
	 * @return int[]
	 */
	public function sanitize_ids( $value ) {
		$list = is_array( $value ) ? $value : explode( ',', (string) $value );

		return array_values( array_filter( array_map( 'absint', $list ) ) );
	}

	/**
	 * Get the newest send date per notification.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_last_sent( $request ) {
		return new WP_REST_Response( (object) Last_Sent::get( (array) $request->get_param( 'ids' ) ), 200 );
	}

	/**
	 * Get failed sends per notification and integration.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_failures( $request ) {
		$days   = $request->get_param( 'days' );
		$period = new Period( Period::is_allowed( $days ) ? (int) $days : 7 );
		$items  = Failures::get( $period );

		foreach ( $items as $id => $item ) {
			$items[ $id ]['integrations'] = (object) $item['integrations'];
		}

		return new WP_REST_Response(
			array(
				'days'          => $period->days,
				'from'          => $period->from,
				'to'            => $period->to,
				'timezone'      => Period::timezone(),
				'notifications' => (object) $items,
			),
			200
		);
	}

	/**
	 * Get the Overview payload.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_overview( $request ) {
		$days = $request->get_param( 'days' );

		return new WP_REST_Response( Overview::get( new Period( Period::is_allowed( $days ) ? (int) $days : 7 ) ), 200 );
	}

	/**
	 * Validate the days param.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return true|WP_Error
	 */
	public function validate_days( $value ) {
		if ( Period::is_allowed( $value ) ) {
			return true;
		}

		return new WP_Error( 'rest_invalid_param', __( 'Days must be 7, 30 or 90.', 'notification-master' ), array( 'status' => 400 ) );
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
