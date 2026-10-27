<?php
/**
 * Delivery abilities for Notification Master.
 *
 * Everything that happens AFTER a notification is saved lives here: the sends
 * waiting in Action Scheduler, the per-send log, the debug log, aggregate send
 * stats and push subscriber counts, plus the two scheduled-send operations
 * (cancel, send now).
 *
 * Log content is free-form and written by the integrations, so no row is ever
 * returned raw: every text field passes through Redactor and recipient lists,
 * webhook URLs and credentials are never emitted at all.
 *
 * @package notification-master
 *
 * @since 1.8.0
 */

namespace Notification_Master\Abilities;

use Notification_Master\Scheduling\Scheduler;
use Notification_Master\DB\Models\Notification_Logs_Model;
use Notification_Master\DB\Models\Logs_Model;
use Notification_Master\DB\Models\Subscription_Model;
use Notification_Master\WebPush\Loader as WebPush_Loader;
use Notification_Master\Abilities\Redactor;

/**
 * Declares the scheduled-send, log, stats and subscriber abilities.
 *
 * @since 1.8.0
 */
class Delivery_Abilities {

	/**
	 * Build the ability definitions this provider owns.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module supplying the shared helpers.
	 * @return array Map of ability name => wp_register_ability() args.
	 */
	public static function definitions( $module ) {
		$read = $module->meta(
			array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			)
		);

		$write = $module->meta(
			array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			)
		);

		// Metered: it contacts real recipients, so it stays off MCP.
		$metered = $module->meta(
			array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
			true
		);

		return array(
			'notification-master/list-scheduled-sends'  => self::list_scheduled_sends( $module, $read ),
			'notification-master/list-send-log'         => self::list_send_log( $module, $read ),
			'notification-master/get-send-stats'        => self::get_send_stats( $module, $read ),
			'notification-master/list-debug-log'        => self::list_debug_log( $module, $read ),
			'notification-master/get-subscriber-stats'  => self::get_subscriber_stats( $module, $read ),
			'notification-master/cancel-scheduled-send' => self::cancel_scheduled_send( $module, $write ),
			'notification-master/send-scheduled-now'    => self::send_scheduled_now( $module, $metered ),
		);
	}

	/**
	 * Sends waiting in Action Scheduler, with their fire time.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_scheduled_sends( $module, array $meta ) {
		return array(
			'label'               => __( 'List scheduled sends', 'notification-master' ),
			'description'         => __( 'Lists the per-connection sends Notification Master has queued in Action Scheduler, one row per pending (or, with status, completed or failed) action: the notification and connection it belongs to, the channel, the trigger that queued it, and the fire time both as a UNIX timestamp (scheduled_for, 0 when unknown) and as a GMT datetime (scheduled_for_gmt). This is the same list the REST route GET /ntfm/v1/scheduled serves. Pass notification_id to see only one notification\'s sends; the filter runs in the database, so paging is exact. It reads only and never changes a schedule; use notification-master/cancel-scheduled-send to remove one. Cancelled actions are removed by Action Scheduler\'s cleaner on a later queue run, so they drop out of this list; the send log (notification-master/list-send-log) is the durable record of what was delivered. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[status]=pending&input[per_page]=50.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'status'          => array(
						'type'        => 'string',
						'enum'        => array( 'pending', 'complete', 'failed' ),
						'description' => __( 'Action status to list. Defaults to pending, the sends that have not fired yet.', 'notification-master' ),
					),
					'notification_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Return only sends belonging to this notification.', 'notification-master' ),
					),
					'page'            => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Page of results to return. Defaults to 1.', 'notification-master' ),
					),
					'per_page'        => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'Rows per page, 1 to 100. Defaults to 20.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'items'    => array(
						'type'  => 'array',
						'items' => self::scheduled_row_schema(),
					),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'status'   => array(
						'type' => 'string',
						'enum' => array( 'pending', 'complete', 'failed' ),
					),
				),
				'required'             => array( 'items', 'page', 'per_page', 'status' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults(
					$input,
					array(
						'status'          => 'pending',
						'notification_id' => 0,
						'page'            => 1,
						'per_page'        => 20,
					)
				);

				$status          = in_array( $params['status'], array( 'pending', 'complete', 'failed' ), true ) ? $params['status'] : 'pending';
				$page            = max( 1, (int) $params['page'] );
				$per_page        = max( 1, min( 100, (int) $params['per_page'] ) );
				$notification_id = (int) $params['notification_id'];
				$scheduler       = Scheduler::get_instance();

				$rows = $scheduler->get_pending(
					array(
						'status'          => $status,
						'page'            => $page,
						'per_page'        => $per_page,
						'notification_id' => $notification_id,
					)
				);

				$items = array();
				foreach ( (array) $rows as $row ) {
					$items[] = self::format_scheduled_row( $row );
				}

				return array(
					'items'    => $items,
					'page'     => $page,
					'per_page' => $per_page,
					'status'   => $status,
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-scheduled-sends' ),
			'meta'                => $meta,
		);
	}

	/**
	 * The per-send log: what was delivered, to which channel, and whether it worked.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_send_log( $module, array $meta ) {
		return array(
			'label'               => __( 'List send log', 'notification-master' ),
			'description'         => __( 'Lists Notification Master\'s send log, newest first: one row per delivery attempt with the channel (integration), whether it succeeded ("success") or failed ("error"), the notification and trigger it came from, the integration\'s own result message and, for HTTP channels, the response code or error text in detail. Filter by status, integration slug or a substring of the notification name; total counts every row matching the filters so a caller can page. This is the right place to answer "did it go out" and "why did it fail". It never returns the raw log content: recipient email addresses, webhook URLs, headers and credentials are stripped, and any email address left inside a message is masked as [email]. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[status]=error&input[per_page]=50.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'status'            => array(
						'type'        => 'string',
						'enum'        => array( 'success', 'error' ),
						'description' => __( 'Return only successful or only failed sends. Omit for both.', 'notification-master' ),
					),
					'integration'       => array(
						'type'        => 'string',
						'description' => __( 'Channel slug such as email, webpush, webhook, discord or instagram. See notification-master/list-channels.', 'notification-master' ),
					),
					'notification_name' => array(
						'type'        => 'string',
						'description' => __( 'Exact match on the notification name recorded with the send.', 'notification-master' ),
					),
					'page'              => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Page of results to return. Defaults to 1.', 'notification-master' ),
					),
					'per_page'          => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'Rows per page, 1 to 100. Defaults to 25.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'items'    => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'                => array( 'type' => 'integer' ),
								'integration'       => array( 'type' => 'string' ),
								'status'            => array(
									'type'        => 'string',
									'description' => __( '"success" or "error".', 'notification-master' ),
								),
								'notification_name' => array( 'type' => array( 'string', 'null' ) ),
								'trigger'           => array( 'type' => array( 'string', 'null' ) ),
								'trigger_name'      => array( 'type' => array( 'string', 'null' ) ),
								'message'           => array(
									'type'        => array( 'string', 'null' ),
									'description' => __( 'The integration\'s result message, with email addresses masked.', 'notification-master' ),
								),
								'detail'            => array(
									'type'        => array( 'string', 'null' ),
									'description' => __( 'HTTP response code and message, or the error text, when the integration recorded one.', 'notification-master' ),
								),
								'date'              => array( 'type' => array( 'string', 'null' ) ),
							),
							'required'             => array( 'id', 'integration', 'status', 'notification_name', 'trigger', 'trigger_name', 'message', 'detail', 'date' ),
							'additionalProperties' => false,
						),
					),
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
				),
				'required'             => array( 'items', 'total', 'page', 'per_page' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults(
					$input,
					array(
						'status'            => '',
						'integration'       => '',
						'notification_name' => '',
						'page'              => 1,
						'per_page'          => 25,
					)
				);

				$args = array(
					'status'            => (string) $params['status'],
					'integration'       => (string) $params['integration'],
					'notification_name' => (string) $params['notification_name'],
					'page'              => max( 1, (int) $params['page'] ),
					'per_page'          => max( 1, min( 100, (int) $params['per_page'] ) ),
				);

				$items = array();
				foreach ( Notification_Logs_Model::query( $args ) as $row ) {
					$items[] = self::send_log_row( $row );
				}

				return array(
					'items'    => $items,
					'total'    => (int) Notification_Logs_Model::count( $args ),
					'page'     => $args['page'],
					'per_page' => $args['per_page'],
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-send-log' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Aggregate send counts per day and per channel. Two queries, no rows.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function get_send_stats( $module, array $meta ) {
		return array(
			'label'               => __( 'Get send stats', 'notification-master' ),
			'description'         => __( 'Counts Notification Master\'s sends over the last N days (1 to 30, default 7) without returning a single log row: overall total, success and failed counts, a daily series with exactly one entry per requested day (days with no sends are present with zeros), and the same split per channel in by_integration. Use it to spot a channel that started failing or a day with no deliveries. Dates use the site timezone because the log rows were written with current_time(); the from/to values show the exact window counted. Runs two grouped queries regardless of log size. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[days]=14.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'days' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 30,
						'description' => __( 'How many days back to count, today included. Defaults to 7.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'from'           => array( 'type' => 'string' ),
					'to'             => array( 'type' => 'string' ),
					'total'          => array( 'type' => 'integer' ),
					'success'        => array( 'type' => 'integer' ),
					'failed'         => array( 'type' => 'integer' ),
					'daily'          => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'date'    => array( 'type' => 'string' ),
								'total'   => array( 'type' => 'integer' ),
								'success' => array( 'type' => 'integer' ),
								'failed'  => array( 'type' => 'integer' ),
							),
							'required'             => array( 'date', 'total', 'success', 'failed' ),
							'additionalProperties' => false,
						),
					),
					'by_integration' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'integration' => array( 'type' => 'string' ),
								'total'       => array( 'type' => 'integer' ),
								'success'     => array( 'type' => 'integer' ),
								'failed'      => array( 'type' => 'integer' ),
							),
							'required'             => array( 'integration', 'total', 'success', 'failed' ),
							'additionalProperties' => false,
						),
					),
					'timezone_note'  => array( 'type' => 'string' ),
				),
				'required'             => array( 'from', 'to', 'total', 'success', 'failed', 'daily', 'by_integration', 'timezone_note' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults( $input, array( 'days' => 7 ) );
				$days   = max( 1, min( 30, (int) $params['days'] ) );

				// Rows were written with current_time('mysql'), so count on the same
				// site-local clock. PHP runs in UTC, so strtotime() of that string is
				// the local wall time as a timestamp and gmdate() formats it unshifted.
				$to       = current_time( 'mysql' );
				$start_ts = (int) strtotime( $to ) - ( $days - 1 ) * DAY_IN_SECONDS;
				$from     = gmdate( 'Y-m-d 00:00:00', $start_ts );

				// One zeroed bucket per requested day, so the series never has gaps.
				$daily = array();
				for ( $i = 0; $i < $days; $i++ ) {
					$date           = gmdate( 'Y-m-d', $start_ts + $i * DAY_IN_SECONDS );
					$daily[ $date ] = array(
						'date'    => $date,
						'total'   => 0,
						'success' => 0,
						'failed'  => 0,
					);
				}

				foreach ( Notification_Logs_Model::get_daily_counts( $from, $to ) as $row ) {
					$day = isset( $row->day ) ? (string) $row->day : '';
					if ( ! isset( $daily[ $day ] ) ) {
						continue;
					}
					self::add_count( $daily[ $day ], $row );
				}

				$by_integration = array();
				foreach ( Notification_Logs_Model::get_counts_by_integration( $from, $to ) as $row ) {
					$slug = isset( $row->integration ) ? (string) $row->integration : '';
					if ( ! isset( $by_integration[ $slug ] ) ) {
						$by_integration[ $slug ] = array(
							'integration' => $slug,
							'total'       => 0,
							'success'     => 0,
							'failed'      => 0,
						);
					}
					self::add_count( $by_integration[ $slug ], $row );
				}

				$totals = array(
					'total'   => 0,
					'success' => 0,
					'failed'  => 0,
				);
				foreach ( $daily as $bucket ) {
					$totals['total']   += $bucket['total'];
					$totals['success'] += $bucket['success'];
					$totals['failed']  += $bucket['failed'];
				}

				return array(
					'from'           => $from,
					'to'             => $to,
					'total'          => $totals['total'],
					'success'        => $totals['success'],
					'failed'         => $totals['failed'],
					'daily'          => array_values( $daily ),
					'by_integration' => array_values( $by_integration ),
					'timezone_note'  => 'Dates use the site timezone (current_time), matching how rows were written.',
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/get-send-stats' ),
			'meta'                => $meta,
		);
	}

	/**
	 * The plugin's own debug log: dropped sends, invalid integrations, errors.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_debug_log( $module, array $meta ) {
		return array(
			'label'               => __( 'List debug log', 'notification-master' ),
			'description'         => __( 'Lists Notification Master\'s internal debug log, newest first. Unlike the send log, these rows are written by the plugin itself: a scheduled send that was dropped (action "scheduling", with the reason, e.g. notification_missing or connection_disabled, surfaced as summary), an unknown integration slug ("invalid_integration"), and other errors, info and debug notes. Filter by type (error, info, debug) or by the exact action string. content is the recorded context with credentials, endpoints, IP addresses and user agents replaced by [redacted] and email addresses masked; date may be null on old rows. It changes nothing. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[type]=error&input[per_page]=50.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'type'     => array(
						'type'        => 'string',
						'enum'        => array( 'error', 'info', 'debug' ),
						'description' => __( 'Return only rows of this level. Omit for all.', 'notification-master' ),
					),
					'action'   => array(
						'type'        => 'string',
						'description' => __( 'Exact action string, e.g. scheduling or invalid_integration.', 'notification-master' ),
					),
					'page'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Page of results to return. Defaults to 1.', 'notification-master' ),
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'Rows per page, 1 to 100. Defaults to 25.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'items'    => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'      => array( 'type' => 'integer' ),
								'type'    => array(
									'type'        => 'string',
									'description' => __( 'error, info or debug.', 'notification-master' ),
								),
								'action'  => array( 'type' => 'string' ),
								'summary' => array(
									'type'        => 'string',
									'description' => __( 'The drop reason for scheduling rows, otherwise the action.', 'notification-master' ),
								),
								'content' => array( 'type' => array( 'object', 'array', 'string', 'null' ) ),
								'date'    => array( 'type' => array( 'string', 'null' ) ),
							),
							'required'             => array( 'id', 'type', 'action', 'summary', 'content', 'date' ),
							'additionalProperties' => false,
						),
					),
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
				),
				'required'             => array( 'items', 'total', 'page', 'per_page' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults(
					$input,
					array(
						'type'     => '',
						'action'   => '',
						'page'     => 1,
						'per_page' => 25,
					)
				);

				$args = array(
					'type'     => (string) $params['type'],
					'action'   => (string) $params['action'],
					'page'     => max( 1, (int) $params['page'] ),
					'per_page' => max( 1, min( 100, (int) $params['per_page'] ) ),
				);

				$items = array();
				foreach ( Logs_Model::query( $args ) as $row ) {
					$items[] = self::debug_log_row( $row );
				}

				return array(
					'items'    => $items,
					'total'    => (int) Logs_Model::count( $args ),
					'page'     => $args['page'],
					'per_page' => $args['per_page'],
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-debug-log' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Push subscriber counts. Counts only, never a subscription row.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function get_subscriber_stats( $module, array $meta ) {
		$count_schema = array( 'type' => 'integer' );

		return array(
			'label'               => __( 'Get subscriber stats', 'notification-master' ),
			'description'         => __( 'Counts the site\'s web push subscribers: total, subscribed and unsubscribed, a split by device (desktop, mobile, tablet, unknown), a split by browser (chrome, firefox, safari, opera, other) and how many subscribed in the last 7 days, plus push_configured, which is false until VAPID keys are saved in Notification Master settings. It returns counts only: no subscription rows, endpoints, keys, IP addresses or user agents ever leave this ability, and there is no ability to list, delete or unsubscribe subscribers. Takes no input. Read-only, so the run endpoint is a GET.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'push_configured' => array( 'type' => 'boolean' ),
					'total'           => $count_schema,
					'subscribed'      => $count_schema,
					'unsubscribed'    => $count_schema,
					'by_device'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'desktop' => $count_schema,
							'mobile'  => $count_schema,
							'tablet'  => $count_schema,
							'unknown' => $count_schema,
						),
						'required'             => array( 'desktop', 'mobile', 'tablet', 'unknown' ),
						'additionalProperties' => false,
					),
					'by_browser'      => array(
						'type'                 => 'object',
						'properties'           => array(
							'chrome'  => $count_schema,
							'firefox' => $count_schema,
							'safari'  => $count_schema,
							'opera'   => $count_schema,
							'other'   => $count_schema,
						),
						'required'             => array( 'chrome', 'firefox', 'safari', 'opera', 'other' ),
						'additionalProperties' => false,
					),
					'new_last_7_days' => $count_schema,
				),
				'required'             => array( 'push_configured', 'total', 'subscribed', 'unsubscribed', 'by_device', 'by_browser', 'new_last_7_days' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the input schema is an empty object, so there is nothing to read.
				$status  = Subscription_Model::get_counts_grouped( 'status' );
				$device  = Subscription_Model::get_counts_grouped( 'device' );
				$browser = Subscription_Model::get_counts_grouped( 'browser' );

				// Subscriptions are stamped with current_time('mysql'), so the 7-day
				// window runs on the same site-local clock as the rows.
				$to   = current_time( 'mysql' );
				$from = gmdate( 'Y-m-d H:i:s', (int) strtotime( $to ) - 7 * DAY_IN_SECONDS );

				return array(
					'push_configured' => (bool) WebPush_Loader::get_instance()->is_configured(),
					'total'           => (int) array_sum( $status ),
					'subscribed'      => isset( $status['subscribed'] ) ? (int) $status['subscribed'] : 0,
					'unsubscribed'    => isset( $status['unsubscribed'] ) ? (int) $status['unsubscribed'] : 0,
					'by_device'       => self::bucket_counts( $device, array( 'desktop', 'mobile', 'tablet' ), 'unknown' ),
					'by_browser'      => self::bucket_counts( $browser, array( 'chrome', 'firefox', 'safari', 'opera' ), 'other' ),
					'new_last_7_days' => (int) Subscription_Model::get_count_by_date( $from, $to ),
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/get-subscriber-stats' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Remove one pending send from the queue. Reversible only by the trigger firing again.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared write meta block.
	 * @return array Ability args.
	 */
	private static function cancel_scheduled_send( $module, array $meta ) {
		return array(
			'label'               => __( 'Cancel scheduled send', 'notification-master' ),
			'description'         => __( 'Cancels one pending scheduled send so it never fires. Takes the action_id from notification-master/list-scheduled-sends and removes that Action Scheduler action; the notification itself, its connections and its schedule settings are untouched, so the next real trigger event schedules a fresh send as usual. Only a pending Notification Master send can be cancelled: an action_id that is missing, already complete, failed, cancelled, or that belongs to another plugin answers 404 ntfm_scheduled_send_not_found. Nothing is sent and no log row is written. The cancelled row keeps status canceled only until Action Scheduler\'s cleaner removes it on a later queue run, so do not rely on list-scheduled-sends as an audit trail; the send log is the durable record. The response repeats what was cancelled, including was_scheduled_for, so the caller can confirm it hit the intended send.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_ACTIONS,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'action_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Action Scheduler action ID of the pending send, from notification-master/list-scheduled-sends.', 'notification-master' ),
					),
				),
				'required'             => array( 'action_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'action_id'         => array( 'type' => 'integer' ),
					'cancelled'         => array(
						'type'        => 'boolean',
						'description' => __( 'Always true: the send is no longer queued.', 'notification-master' ),
					),
					'notification_id'   => array( 'type' => 'integer' ),
					'connection_id'     => array( 'type' => 'string' ),
					'integration'       => array( 'type' => 'string' ),
					'was_scheduled_for' => array(
						'type'        => 'integer',
						'description' => __( 'UNIX timestamp the send would have fired at, 0 when unknown.', 'notification-master' ),
					),
				),
				'required'             => array( 'action_id', 'cancelled', 'notification_id', 'connection_id', 'integration', 'was_scheduled_for' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$action_id = is_array( $input ) && isset( $input['action_id'] ) ? (int) $input['action_id'] : 0;

				$row = self::fetch_scheduled( $module, $action_id );
				if ( is_wp_error( $row ) ) {
					return $row;
				}

				// Same code path as POST /ntfm/v1/scheduled/{id}/cancel.
				if ( ! Scheduler::get_instance()->cancel( $action_id ) ) {
					return self::not_found( $module );
				}

				return array(
					'action_id'         => $action_id,
					'cancelled'         => true,
					'notification_id'   => $row['notification_id'],
					'connection_id'     => $row['connection_id'],
					'integration'       => $row['integration'],
					'was_scheduled_for' => $row['scheduled_for'],
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/cancel-scheduled-send' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Fire one pending send right now. Real delivery, so metered and off MCP.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Metered write meta block (mcp.public false).
	 * @return array Ability args.
	 */
	private static function send_scheduled_now( $module, array $meta ) {
		return array(
			'label'               => __( 'Send scheduled now', 'notification-master' ),
			'description'         => __( 'Delivers one pending scheduled send immediately instead of at its scheduled time. THIS SENDS FOR REAL: depending on the connection it emails the configured recipients, pushes to subscribers, posts to Discord or a webhook, or publishes to Instagram, exactly as the scheduled run would have. It is synchronous and blocks on the outbound HTTP call (Instagram can take up to 60 seconds), and the Action Scheduler row is cancelled before dispatch and marked complete afterwards with the log line "Sent immediately via send-now", so a timed-out request may still have delivered and list-scheduled-sends with status complete then shows it. Requires confirm set to true and confirm_action_id equal to action_id; a mismatch answers 400 ntfm_confirm_mismatch before anything is looked up. An action_id that is not a pending Notification Master send answers 404 ntfm_scheduled_send_not_found. The delivery result is written to the send log, not returned here: read notification-master/list-send-log afterwards. This ability is not available over MCP (meta.mcp.public is false); it runs only through the REST /run endpoint or PHP.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_ACTIONS,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'action_id'         => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Action Scheduler action ID of the pending send, from notification-master/list-scheduled-sends.', 'notification-master' ),
					),
					'confirm'           => array(
						'type'        => 'boolean',
						'enum'        => array( true ),
						'description' => __( 'Must be true. Confirms a human accepted that this delivers to real recipients now.', 'notification-master' ),
					),
					'confirm_action_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Must repeat action_id exactly. Guards against sending the wrong queued action.', 'notification-master' ),
					),
				),
				'required'             => array( 'action_id', 'confirm', 'confirm_action_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'action_id'       => array( 'type' => 'integer' ),
					'dispatched'      => array(
						'type'        => 'boolean',
						'description' => __( 'Always true: the send was handed to the integration.', 'notification-master' ),
					),
					'notification_id' => array( 'type' => 'integer' ),
					'connection_id'   => array( 'type' => 'string' ),
					'integration'     => array( 'type' => 'string' ),
					'send_log_hint'   => array( 'type' => 'string' ),
				),
				'required'             => array( 'action_id', 'dispatched', 'notification_id', 'connection_id', 'integration', 'send_log_hint' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$input             = is_array( $input ) ? $input : array();
				$action_id         = isset( $input['action_id'] ) ? (int) $input['action_id'] : 0;
				$confirm_action_id = isset( $input['confirm_action_id'] ) ? (int) $input['confirm_action_id'] : 0;
				$confirm           = isset( $input['confirm'] ) ? rest_sanitize_boolean( $input['confirm'] ) : false;

				// The gate runs before any lookup, so a typo never reaches Action Scheduler.
				if ( ! $confirm || $confirm_action_id !== $action_id ) {
					return $module->agent_error(
						'ntfm_confirm_mismatch',
						__( 'confirm must be true and confirm_action_id must equal action_id. Nothing was sent.', 'notification-master' ),
						array(
							'status'     => 400,
							'agent_hint' => __( 'confirm_action_id must repeat action_id exactly; this delivers to real recipients.', 'notification-master' ),
						)
					);
				}

				$row = self::fetch_scheduled( $module, $action_id );
				if ( is_wp_error( $row ) ) {
					return $row;
				}

				// Same code path as POST /ntfm/v1/scheduled/{id}/send-now: synchronous delivery.
				$sent = Scheduler::get_instance()->send_now( $action_id );
				if ( is_wp_error( $sent ) ) {
					return 'ntfm_scheduled_send_not_pending' === $sent->get_error_code() ? self::not_pending( $module ) : self::not_found( $module );
				}
				if ( true !== $sent ) {
					return self::not_found( $module );
				}

				return array(
					'action_id'       => $action_id,
					'dispatched'      => true,
					'notification_id' => $row['notification_id'],
					'connection_id'   => $row['connection_id'],
					'integration'     => $row['integration'],
					'send_log_hint'   => 'Check notification-master/list-send-log for the delivery result.',
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/send-scheduled-now' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Load one pending Notification Master send straight from the Action Scheduler store.
	 *
	 * Scheduler::get_action() is protected, so the lookup is repeated here with
	 * the same checks plus a pending-status check. Anything that is not a
	 * pending action on Scheduler::HOOK with args is answered with the 404.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module    Bootstrap module.
	 * @param int              $action_id Action Scheduler action ID.
	 * @return array|\WP_Error Summary row, or the not-found error.
	 */
	private static function fetch_scheduled( $module, $action_id ) {
		$action_id = (int) $action_id;

		if ( $action_id <= 0 || ! class_exists( '\\ActionScheduler' ) || ! class_exists( '\\ActionScheduler_Store' ) ) {
			return self::not_found( $module );
		}

		try {
			$action = \ActionScheduler::store()->fetch_action( $action_id );
		} catch ( \Exception $e ) {
			$action = null;
		}

		// A missing ID comes back as a NullAction with an empty hook.
		if ( ! $action || ! is_object( $action ) || Scheduler::HOOK !== $action->get_hook() ) {
			return self::not_found( $module );
		}

		$args = $action->get_args();
		if ( ! is_array( $args ) || empty( $args ) ) {
			return self::not_found( $module );
		}

		try {
			$status = \ActionScheduler::store()->get_status( $action_id );
		} catch ( \Exception $e ) {
			$status = '';
		}

		if ( \ActionScheduler_Store::STATUS_PENDING !== $status ) {
			return self::not_found( $module );
		}

		$snapshot = isset( $args[2] ) && is_array( $args[2] ) ? $args[2] : array();

		$scheduled_for = 0;
		$schedule      = $action->get_schedule();
		if ( $schedule && method_exists( $schedule, 'get_date' ) ) {
			$date = $schedule->get_date();
			if ( $date instanceof \DateTimeInterface ) {
				$scheduled_for = (int) $date->getTimestamp();
			}
		}

		return array(
			'notification_id' => isset( $args[0] ) ? (int) $args[0] : 0,
			'connection_id'   => isset( $args[1] ) ? (string) $args[1] : '',
			'integration'     => isset( $snapshot['connection']['integration'] ) ? (string) $snapshot['connection']['integration'] : '',
			'trigger_slug'    => isset( $snapshot['trigger']['slug'] ) ? (string) $snapshot['trigger']['slug'] : '',
			'scheduled_for'   => $scheduled_for,
		);
	}

	/**
	 * The one 404 every scheduled-send lookup shares.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return \WP_Error
	 */
	private static function not_found( $module ) {
		return $module->agent_error(
			'ntfm_scheduled_send_not_found',
			__( 'No pending scheduled send with that action_id.', 'notification-master' ),
			array(
				'status'     => 404,
				'agent_hint' => __( 'It may have already run or been cancelled.', 'notification-master' ),
				'recovery'   => array(
					'ability' => 'notification-master/list-scheduled-sends',
					'input'   => array( 'status' => 'pending' ),
				),
			)
		);
	}

	/**
	 * The row was pending a moment ago but a queue runner holds it right now.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Module for the error shape.
	 * @return \WP_Error
	 */
	private static function not_pending( $module ) {
		return $module->agent_error(
			'ntfm_scheduled_send_not_pending',
			__( 'The queue is delivering this scheduled send right now. Nothing was sent twice.', 'notification-master' ),
			array(
				'status'     => 409,
				'agent_hint' => __( 'The queue runner delivers it once. Check notification-master/list-send-log in a moment.', 'notification-master' ),
				'recovery'   => array(
					'ability' => 'notification-master/list-send-log',
					'input'   => array( 'per_page' => 5 ),
				),
			)
		);
	}

	/**
	 * Schema of one list-scheduled-sends row.
	 *
	 * @since 1.8.0
	 *
	 * @return array JSON schema.
	 */
	private static function scheduled_row_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'action_id'         => array( 'type' => 'integer' ),
				'notification_id'   => array( 'type' => 'integer' ),
				'notification_name' => array( 'type' => 'string' ),
				'connection_id'     => array( 'type' => 'string' ),
				'connection_name'   => array( 'type' => 'string' ),
				'integration'       => array( 'type' => 'string' ),
				'trigger_slug'      => array( 'type' => 'string' ),
				'trigger_name'      => array( 'type' => 'string' ),
				'scheduled_for'     => array(
					'type'        => 'integer',
					'description' => __( 'UNIX timestamp of the fire time, 0 when unknown.', 'notification-master' ),
				),
				'scheduled_for_gmt' => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'Fire time as a GMT datetime, null when scheduled_for is 0.', 'notification-master' ),
				),
				'status'            => array( 'type' => 'string' ),
			),
			'required'             => array( 'action_id', 'notification_id', 'notification_name', 'connection_id', 'connection_name', 'integration', 'trigger_slug', 'trigger_name', 'scheduled_for', 'scheduled_for_gmt', 'status' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Cast a Scheduler::get_pending() row to the output schema and add scheduled_for_gmt.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $row Row from Scheduler::get_pending().
	 * @return array
	 */
	private static function format_scheduled_row( $row ) {
		$row = is_array( $row ) ? $row : array();
		$ts  = isset( $row['scheduled_for'] ) ? (int) $row['scheduled_for'] : 0;

		return array(
			'action_id'         => isset( $row['action_id'] ) ? (int) $row['action_id'] : 0,
			'notification_id'   => isset( $row['notification_id'] ) ? (int) $row['notification_id'] : 0,
			'notification_name' => isset( $row['notification_name'] ) ? (string) $row['notification_name'] : '',
			'connection_id'     => isset( $row['connection_id'] ) ? (string) $row['connection_id'] : '',
			'connection_name'   => isset( $row['connection_name'] ) ? (string) $row['connection_name'] : '',
			'integration'       => isset( $row['integration'] ) ? (string) $row['integration'] : '',
			'trigger_slug'      => isset( $row['trigger_slug'] ) ? (string) $row['trigger_slug'] : '',
			'trigger_name'      => isset( $row['trigger_name'] ) ? (string) $row['trigger_name'] : '',
			'scheduled_for'     => $ts,
			'scheduled_for_gmt' => $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : null,
			'status'            => isset( $row['status'] ) ? (string) $row['status'] : '',
		);
	}

	/**
	 * Shape one ntfm_notification_logs row. The raw content never leaves.
	 *
	 * @since 1.8.0
	 *
	 * @param object $row Row object with id, integration, status, content, created_at.
	 * @return array
	 */
	private static function send_log_row( $row ) {
		$content = maybe_unserialize( isset( $row->content ) ? $row->content : '' );

		// A plain-string log is its own message.
		if ( is_string( $content ) ) {
			$content = array( 'message' => $content );
		}
		if ( ! is_array( $content ) ) {
			$content = array();
		}

		$message = self::nullable_text( $content, 'message' );

		return array(
			'id'                => isset( $row->id ) ? (int) $row->id : 0,
			'integration'       => isset( $row->integration ) ? (string) $row->integration : '',
			'status'            => isset( $row->status ) ? (string) $row->status : '',
			'notification_name' => self::nullable_text( $content, 'notification_name' ),
			'trigger'           => self::nullable_text( $content, 'trigger' ),
			'trigger_name'      => self::nullable_text( $content, 'trigger_name' ),
			'message'           => '' === $message ? null : $message,
			'detail'            => self::response_detail( $content ),
			'date'              => self::nullable_date( $row ),
		);
	}

	/**
	 * Shape one ntfm_logs row with its content redacted.
	 *
	 * @since 1.8.0
	 *
	 * @param object $row Row object with id, type, action, content, created_at.
	 * @return array
	 */
	private static function debug_log_row( $row ) {
		$content = Redactor::redact_content( maybe_unserialize( isset( $row->content ) ? $row->content : '' ) );

		// The schema allows object, array, string or null; fold any other scalar to text.
		if ( ! is_array( $content ) && ! is_string( $content ) && null !== $content ) {
			$content = is_scalar( $content ) ? (string) $content : null;
		}

		$action  = isset( $row->action ) ? (string) $row->action : '';
		$summary = $action;
		if ( 'scheduling' === $action && is_array( $content ) && isset( $content['reason'] ) && is_scalar( $content['reason'] ) ) {
			$summary = (string) $content['reason'];
		}

		return array(
			'id'      => isset( $row->id ) ? (int) $row->id : 0,
			'type'    => isset( $row->type ) ? (string) $row->type : '',
			'action'  => $action,
			'summary' => $summary,
			'content' => $content,
			'date'    => self::nullable_date( $row ),
		);
	}

	/**
	 * The row's created_at as text, or null for an empty or zero date.
	 *
	 * @since 1.8.0
	 *
	 * @param object $row Log row.
	 * @return string|null
	 */
	private static function nullable_date( $row ) {
		$date = isset( $row->created_at ) ? (string) $row->created_at : '';

		return ( '' === $date || 0 === strpos( $date, '0000-00-00' ) ) ? null : $date;
	}

	/**
	 * Read one scalar content key as redacted text, or null when absent.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $content Unserialized log content.
	 * @param string $key     Key to read.
	 * @return string|null
	 */
	private static function nullable_text( array $content, $key ) {
		if ( ! isset( $content[ $key ] ) || ! is_scalar( $content[ $key ] ) ) {
			return null;
		}

		return Redactor::redact_text( (string) $content[ $key ] );
	}

	/**
	 * Summarise the HTTP response or error an integration recorded, redacted.
	 *
	 * @since 1.8.0
	 *
	 * @param array $content Unserialized log content.
	 * @return string|null
	 */
	private static function response_detail( array $content ) {
		$response = isset( $content['response'] ) ? $content['response'] : null;
		$detail   = '';

		if ( is_wp_error( $response ) ) {
			$detail = (string) $response->get_error_message();
		} elseif ( is_array( $response ) ) {
			// wp_remote_* shape: response.code and response.message.
			$code    = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
			$message = isset( $response['response']['message'] ) && is_scalar( $response['response']['message'] ) ? (string) $response['response']['message'] : '';
			if ( $code > 0 || '' !== $message ) {
				$detail = trim( ( $code > 0 ? 'HTTP ' . $code : '' ) . ' ' . $message );
			}
		} elseif ( is_string( $response ) ) {
			$detail = $response;
		}

		// Some integrations record the failure under "error" instead.
		if ( '' === $detail && isset( $content['error'] ) && is_string( $content['error'] ) ) {
			$detail = $content['error'];
		}

		return '' === $detail ? null : Redactor::redact_text( $detail );
	}

	/**
	 * Add one {status, total} row to a stats bucket.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $bucket Bucket with total/success/failed, by reference.
	 * @param object $row    Row object with status and total.
	 * @return void
	 */
	private static function add_count( array &$bucket, $row ) {
		$total  = isset( $row->total ) ? (int) $row->total : 0;
		$status = isset( $row->status ) ? (string) $row->status : '';

		$bucket['total'] += $total;
		if ( 'success' === $status ) {
			$bucket['success'] += $total;
		} elseif ( 'error' === $status ) {
			$bucket['failed'] += $total;
		}
	}

	/**
	 * Fold grouped counts into fixed, lower-cased buckets plus a catch-all.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $counts   Value => count from Subscription_Model::get_counts_grouped().
	 * @param array  $known    Bucket names kept as-is.
	 * @param string $fallback Bucket that absorbs every other value.
	 * @return array Bucket => int, every bucket present.
	 */
	private static function bucket_counts( array $counts, array $known, $fallback ) {
		$buckets = array_fill_keys( array_merge( $known, array( $fallback ) ), 0 );

		foreach ( $counts as $value => $count ) {
			$key = strtolower( trim( (string) $value ) );
			if ( ! in_array( $key, $known, true ) ) {
				$key = $fallback;
			}
			$buckets[ $key ] += (int) $count;
		}

		return $buckets;
	}
}
