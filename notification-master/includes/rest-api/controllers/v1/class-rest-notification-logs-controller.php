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
use Notification_Master\DB\Models\Notification_Logs_Model;
use Notification_Master\Logs\Log_Formatter;
use Notification_Master\Stats\Overview;
use Notification_Master\Stats\Period;
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
					) + $this->get_filter_args(),
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => array( $this, 'get_logs_permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Log ID.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Filter args shared by the list route.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	private function get_filter_args() {
		return array(
			'integration'       => array(
				'type'              => 'string',
				'description'       => __( 'Integration slug.', 'notification-master' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'search'            => array(
				'type'              => 'string',
				'description'       => __( 'Search text.', 'notification-master' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'from'              => array(
				'type'              => 'string',
				'description'       => __( 'First day (Y-m-d, site time).', 'notification-master' ),
				'validate_callback' => array( $this, 'validate_date' ),
			),
			'to'                => array(
				'type'              => 'string',
				'description'       => __( 'Last day (Y-m-d, site time).', 'notification-master' ),
				'validate_callback' => array( $this, 'validate_date' ),
			),
			'notification_id'   => array(
				'type'        => 'integer',
				'description' => __( 'Notification ID.', 'notification-master' ),
				'minimum'     => 1,
			),
			'notification_name' => array(
				'type'              => 'string',
				'description'       => __( 'Notification name.', 'notification-master' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'exclude_tests'     => array(
				'type'        => 'boolean',
				'description' => __( 'Leave out "Send a test" rows.', 'notification-master' ),
				'default'     => false,
			),
		);
	}

	/**
	 * Validate a Y-m-d date param.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return true|WP_Error
	 */
	public function validate_date( $value ) {
		if ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );
			if ( checkdate( $month, $day, $year ) ) {
				return true;
			}
		}

		return new WP_Error( 'rest_invalid_param', __( 'Use a date in Y-m-d format.', 'notification-master' ), array( 'status' => 400 ) );
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
	 * Get logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_logs( $request ) {
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10 ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1 ) );
		$args     = $this->get_filters( $request );

		$counts = Notification_Logs_Model::count_by_status( $args );
		$total  = '' === $args['status'] ? $counts['all'] : $counts[ $args['status'] ];
		$rows   = Notification_Logs_Model::query(
			array_merge(
				$args,
				array(
					'per_page' => $per_page,
					'page'     => $page,
				)
			)
		);

		$result = array(
			'logs'     => array_map( array( Log_Formatter::class, 'format_row' ), $rows ),
			'count'    => $total,
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
			'counts'   => $counts,
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Read the list filters from a request.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return array Model args.
	 */
	private function get_filters( $request ) {
		$aliases = array(
			'success' => 'success',
			'sent'    => 'success',
			'error'   => 'error',
			'failed'  => 'error',
		);
		$status  = (string) $request->get_param( 'status' );
		$from    = (string) $request->get_param( 'from' );
		$to      = (string) $request->get_param( 'to' );

		return array(
			'status'            => isset( $aliases[ $status ] ) ? $aliases[ $status ] : '',
			'integration'       => (string) $request->get_param( 'integration' ),
			'search'            => (string) $request->get_param( 'search' ),
			'notification_id'   => (int) $request->get_param( 'notification_id' ),
			'notification_name' => (string) $request->get_param( 'notification_name' ),
			'from'              => '' !== $from ? $from . ' 00:00:00' : '',
			'to'                => '' !== $to ? $to . ' 23:59:59' : '',
			'exclude_tests'     => (bool) $request->get_param( 'exclude_tests' ),
		);
	}

	/**
	 * Get one log row.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_log( $request ) {
		$row = Notification_Logs_Model::get( (int) $request->get_param( 'id' ) );

		if ( ! $row ) {
			return new WP_Error( 'ntfm_log_not_found', __( 'Log entry not found.', 'notification-master' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( Log_Formatter::format_row( $row ), 200 );
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
		$days   = $request->get_param( 'days' );
		$period = new Period( Period::is_allowed( $days ) ? (int) $days : 7 );

		$current  = Overview::totals( $period->from, $period->to );
		$previous = Overview::totals( $period->previous_from, $period->previous_to );

		$result = array(
			'daily'    => Overview::daily( $period ),
			'total'    => $current['all'],
			'success'  => $current['success'],
			'failed'   => $current['error'],
			'previous' => array(
				'total'   => $previous['all'],
				'success' => $previous['success'],
				'failed'  => $previous['error'],
			),
			'days'     => $period->days,
			'from'     => $period->from,
			'to'       => $period->to,
			'timezone' => Period::timezone(),
		);

		return new WP_REST_Response( $result, 200 );
	}
}
