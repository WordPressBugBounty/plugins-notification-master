<?php
/**
 * Scheduler
 *
 * Per-connection notification scheduling pipeline. Resolves a delay (or future fire
 * time) from a connection's `schedule` settings, snapshots the trigger context, and
 * enqueues a single Action Scheduler action that re-dispatches the connection at
 * fire time.
 *
 * @package notification-master
 *
 * @since 1.7.0
 */

namespace Notification_Master\Scheduling;

use Notification_Master\Abstracts\Trigger;
use Notification_Master\Connections\Process as Connections_Process;
use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;
use WP_Error;

use function Notification_Master\Notification_Master;

/**
 * Phase 2 modes:
 *   - delay                 Send N units after the trigger fires.
 *   - merge_tag_relative    Send N units before/after a date-typed merge tag's value.
 */

/**
 * Scheduler class.
 */
class Scheduler {

	/**
	 * Action Scheduler hook fired when a scheduled connection is due.
	 */
	const HOOK = 'ntfm_scheduled_connection';

	/**
	 * Action Scheduler group used for scheduled connections.
	 */
	const GROUP = 'ntfm_scheduled_connections';

	/**
	 * Snapshot schema version. Bump when the snapshot structure changes so old
	 * pending actions can be handled (or dropped) gracefully.
	 */
	const SNAPSHOT_VERSION = 1;

	/**
	 * Maximum supported delay in seconds (1 year). Anything beyond this is
	 * almost certainly a config mistake.
	 */
	const MAX_DELAY_SECONDS = YEAR_IN_SECONDS;

	/**
	 * Instance.
	 *
	 * @var Scheduler|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Scheduler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( self::HOOK, array( $this, 'handle_scheduled' ), 10, 3 );
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Resolve the delay (in seconds from now) for a connection's schedule
	 * settings. Returns 0 when the connection should be dispatched immediately,
	 * and false when the connection should be dropped entirely (e.g. the merge
	 * tag references a date that's in the past and the fallback is `skip`).
	 *
	 * @param array   $connection Connection definition.
	 * @param Trigger $trigger    Live trigger.
	 * @return int|false Seconds from now, 0 to dispatch immediately, or false to drop.
	 */
	public function resolve_delay( $connection, $trigger ) {
		$schedule = $connection['schedule'] ?? array();

		if ( empty( $schedule['enabled'] ) ) {
			return 0;
		}

		$mode = $schedule['mode'] ?? 'delay';

		switch ( $mode ) {
			case 'delay':
				return $this->seconds_from_delay( $schedule['delay'] ?? array() );
			case 'merge_tag_relative':
				return $this->seconds_from_merge_tag_relative(
					$schedule['merge_tag_relative'] ?? array(),
					$trigger
				);
			default:
				// Unknown mode: defer to immediate dispatch rather than block the notification.
				return 0;
		}
	}

	/**
	 * Compute the delay (in seconds from now) for a `merge_tag_relative`
	 * schedule. Resolves the referenced merge tag against the live trigger,
	 * applies the offset, and respects the `on_past` / `on_invalid` fallbacks.
	 *
	 * @param array   $config  Merge-tag-relative block.
	 * @param Trigger $trigger Live trigger.
	 * @return int|false Seconds from now, 0 to dispatch immediately, or false to drop.
	 */
	protected function seconds_from_merge_tag_relative( $config, $trigger ) {
		$tag        = isset( $config['tag'] ) ? (string) $config['tag'] : '';
		$direction  = isset( $config['direction'] ) ? (string) $config['direction'] : 'after';
		$offset     = isset( $config['offset'] ) ? (int) $config['offset'] : 0;
		$unit       = isset( $config['unit'] ) ? (string) $config['unit'] : 'minutes';
		$on_past    = isset( $config['on_past'] ) ? (string) $config['on_past'] : 'send_now';
		$on_invalid = isset( $config['on_invalid'] ) ? (string) $config['on_invalid'] : 'send_now';

		$source_ts = $this->resolve_merge_tag_timestamp( $tag, $trigger );

		if ( null === $source_ts ) {
			return 'skip' === $on_invalid ? false : 0;
		}

		$offset_seconds = $this->offset_to_seconds( $offset, $unit );
		$fire_at        = 'before' === $direction
			? $source_ts - $offset_seconds
			: $source_ts + $offset_seconds;

		$delta = $fire_at - time();

		if ( $delta <= 0 ) {
			return 'skip' === $on_past ? false : 0;
		}

		return min( $delta, self::MAX_DELAY_SECONDS );
	}

	/**
	 * Resolve a `{{group.key}}` merge tag reference to a UNIX timestamp using
	 * the trigger's currently-registered merge-tag groups.
	 *
	 * @param string  $tag     Merge tag literal, e.g. `{{post.published_date}}`.
	 * @param Trigger $trigger Live trigger.
	 * @return int|null
	 */
	protected function resolve_merge_tag_timestamp( $tag, $trigger ) {
		if ( ! preg_match( '/^\{\{([^.]+)\.([^}]+)\}\}$/', $tag, $matches ) ) {
			return null;
		}

		$group_slug = $matches[1];
		$tag_key    = $matches[2];

		$allowed_groups = $trigger->get_merge_tags();
		$allowed_groups[] = 'general';
		if ( ! in_array( $group_slug, $allowed_groups, true ) ) {
			return null;
		}

		$group = Merge_Tags_Loader::get_instance()->get_group( $group_slug );
		if ( ! $group ) {
			return null;
		}

		$group->set_trigger( $trigger );

		if ( ! method_exists( $group, 'get_value_timestamp' ) ) {
			return null;
		}

		$ts = $group->get_value_timestamp( $tag_key );
		return is_int( $ts ) ? $ts : null;
	}

	/**
	 * Convert an offset value + unit to seconds.
	 *
	 * @param int    $value Offset value.
	 * @param string $unit  Unit identifier.
	 * @return int
	 */
	protected function offset_to_seconds( $value, $unit ) {
		$value = max( 0, (int) $value );
		switch ( $unit ) {
			case 'days':
				return $value * DAY_IN_SECONDS;
			case 'hours':
				return $value * HOUR_IN_SECONDS;
			case 'minutes':
			default:
				return $value * MINUTE_IN_SECONDS;
		}
	}

	/**
	 * Convert a {value, unit} delay block to seconds.
	 *
	 * @param array $delay Delay block.
	 * @return int
	 */
	protected function seconds_from_delay( $delay ) {
		$value = isset( $delay['value'] ) ? (int) $delay['value'] : 0;
		$unit  = isset( $delay['unit'] ) ? (string) $delay['unit'] : 'minutes';

		if ( $value <= 0 ) {
			return 0;
		}

		switch ( $unit ) {
			case 'days':
				$seconds = $value * DAY_IN_SECONDS;
				break;
			case 'hours':
				$seconds = $value * HOUR_IN_SECONDS;
				break;
			case 'minutes':
			default:
				$seconds = $value * MINUTE_IN_SECONDS;
				break;
		}

		return min( $seconds, self::MAX_DELAY_SECONDS );
	}

	/**
	 * Schedule a single connection for future dispatch.
	 *
	 * Pre-resolves all merge tags inside the connection settings against the
	 * live trigger so the action carries already-substituted strings. At fire
	 * time we restore a Frozen_Trigger whose merge-tag passes are no-ops.
	 *
	 * @param array   $connection      Connection definition.
	 * @param Trigger $trigger         Live trigger.
	 * @param int     $notification_id Notification post ID.
	 * @param string  $connection_id   Connection ID (key within the notification's connections meta).
	 * @param int     $delay           Delay in seconds from now.
	 * @return int|false Action ID on success, false on failure.
	 */
	public function schedule( $connection, $trigger, $notification_id, $connection_id, $delay ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		$snapshot = $this->capture_snapshot( $connection, $trigger );

		$args = array(
			(int) $notification_id,
			(string) $connection_id,
			$snapshot,
		);

		$timestamp = time() + max( 1, (int) $delay );

		$action_id = as_schedule_single_action( $timestamp, self::HOOK, $args, self::GROUP );

		if ( $action_id ) {
			/**
			 * Fires after a connection has been scheduled for future dispatch.
			 *
			 * @since 1.7.0
			 *
			 * @param int    $action_id       Action Scheduler action ID.
			 * @param int    $notification_id Notification post ID.
			 * @param string $connection_id   Connection ID.
			 * @param array  $snapshot        Captured snapshot.
			 */
			do_action( 'notification_master_connection_scheduled', $action_id, $notification_id, $connection_id, $snapshot );
		}

		return $action_id ? (int) $action_id : false;
	}

	/**
	 * Build a self-contained snapshot of everything needed to dispatch the
	 * connection later without the live trigger object.
	 *
	 * @param array   $connection Connection definition.
	 * @param Trigger $trigger    Live trigger.
	 * @return array
	 */
	protected function capture_snapshot( $connection, $trigger ) {
		$resolved_settings = $this->preresolve_settings( $connection['settings'] ?? array(), $trigger );

		return array(
			'version'         => self::SNAPSHOT_VERSION,
			'scheduled_at'    => time(),
			'trigger'         => array(
				'slug'        => $trigger->get_slug(),
				'name'        => $trigger->get_name(),
				'merge_tags'  => array_values( $trigger->get_merge_tags() ),
			),
			'connection'      => array(
				'integration' => $connection['integration'] ?? '',
				'name'        => $connection['name'] ?? '',
				'enabled'     => isset( $connection['enabled'] ) ? (bool) $connection['enabled'] : true,
				'settings'    => $resolved_settings,
			),
		);
	}

	/**
	 * Recursively run the merge-tag loader against the connection settings so
	 * the snapshot stores resolved values.
	 *
	 * @param mixed   $value   Settings value (string or array of strings).
	 * @param Trigger $trigger Live trigger.
	 * @return mixed
	 */
	protected function preresolve_settings( $value, $trigger ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $sub ) {
				$value[ $key ] = $this->preresolve_settings( $sub, $trigger );
			}
			return $value;
		}

		if ( is_string( $value ) && false !== strpos( $value, '{{' ) ) {
			return Merge_Tags_Loader::get_instance()->process_merge_tags( $trigger, $value );
		}

		return $value;
	}

	/**
	 * Handle a scheduled connection firing.
	 *
	 * @param int    $notification_id Notification post ID.
	 * @param string $connection_id   Connection ID (informational; not used for re-lookup).
	 * @param array  $snapshot        Captured snapshot.
	 * @return void
	 */
	public function handle_scheduled( $notification_id, $connection_id, $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot['version'] ) ) {
			$this->log_drop( 'invalid_snapshot', $notification_id, $connection_id );
			return;
		}

		if ( (int) $snapshot['version'] !== self::SNAPSHOT_VERSION ) {
			$this->log_drop( 'snapshot_version_mismatch', $notification_id, $connection_id, array( 'version' => $snapshot['version'] ) );
			return;
		}

		// Drop if the notification post was deleted between schedule and fire.
		$post = get_post( $notification_id );
		if ( ! $post || 'ntfm_notification' !== $post->post_type ) {
			$this->log_drop( 'notification_missing', $notification_id, $connection_id );
			return;
		}

		// Drop if the notification was unpublished after scheduling.
		if ( 'publish' !== $post->post_status ) {
			$this->log_drop( 'notification_unpublished', $notification_id, $connection_id );
			return;
		}

		$connection_snapshot = $snapshot['connection'] ?? array();
		if ( empty( $connection_snapshot['integration'] ) ) {
			$this->log_drop( 'no_integration_in_snapshot', $notification_id, $connection_id );
			return;
		}

		// Respect the current `enabled` state on the live connection if it still
		// exists. If the connection was removed entirely, fall back to the
		// snapshot's enabled flag.
		$current_connections = get_post_meta( $notification_id, 'connections', true ) ?: array();
		$live_connection     = $current_connections[ $connection_id ] ?? null;

		if ( $live_connection && empty( $live_connection['enabled'] ) ) {
			$this->log_drop( 'connection_disabled', $notification_id, $connection_id );
			return;
		}

		// Build a frozen trigger so any merge-tag re-processing inside the
		// integration is a no-op (settings are pre-resolved).
		$frozen_trigger = new Frozen_Trigger( $snapshot['trigger'] ?? array() );

		// Reconstruct a single-element connections array that bypasses
		// scheduling and conditions (both already evaluated at schedule time).
		$dispatch_connection                       = $connection_snapshot;
		$dispatch_connection['enabled']            = true;
		$dispatch_connection['enable_conditions']  = false;
		$dispatch_connection['conditions']         = array();
		unset( $dispatch_connection['schedule'] );

		Connections_Process::get_instance()->process(
			array( $connection_id => $dispatch_connection ),
			$frozen_trigger,
			$notification_id
		);
	}

	/**
	 * Cancel a pending scheduled connection by action ID.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return bool
	 */
	public function cancel( $action_id ) {
		if ( ! class_exists( '\ActionScheduler' ) ) {
			return false;
		}

		$action = $this->get_action( $action_id );
		if ( ! $action ) {
			return false;
		}

		return $this->cancel_by_id( $action_id );
	}

	/**
	 * Force-dispatch a pending scheduled connection immediately.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return true|WP_Error True when dispatched; 404 for an unknown or finished id, 409 while the queue holds it.
	 */
	public function send_now( $action_id ) {
		$action = $this->get_action( $action_id );
		if ( ! $action ) {
			return new WP_Error(
				'ntfm_scheduled_send_not_found',
				__( 'No pending scheduled send with that action_id.', 'notification-master' ),
				array( 'status' => 404 )
			);
		}

		$args = $action->get_args();

		// Take the row before dispatching, the way the runner does (in progress + attempt stamp):
		// a queue runner picking it up at the same moment loses (or we do), never both.
		if ( ! $this->take_pending( $action_id, \ActionScheduler_Store::STATUS_RUNNING, true ) ) {
			// Still pending or in progress means the queue holds it right now; anything else already ran or was cancelled.
			if ( ! in_array( $this->status_of( $action_id ), array( \ActionScheduler_Store::STATUS_PENDING, \ActionScheduler_Store::STATUS_RUNNING ), true ) ) {
				return new WP_Error(
					'ntfm_scheduled_send_not_found',
					__( 'No pending scheduled send with that action_id.', 'notification-master' ),
					array( 'status' => 404 )
				);
			}

			return new WP_Error(
				'ntfm_scheduled_send_not_pending',
				__( 'The queue is delivering this scheduled send right now. Nothing was sent twice.', 'notification-master' ),
				array( 'status' => 409 )
			);
		}

		// Dispatch synchronously using the snapshot we just removed.
		$this->handle_scheduled( $args[0] ?? 0, $args[1] ?? '', $args[2] ?? array() );

		// The row is in progress like a runner-owned action; record the completion.
		try {
			\ActionScheduler::store()->mark_complete( (int) $action_id );
			\ActionScheduler_Logger::instance()->log( (int) $action_id, 'Sent immediately via send-now' );
		} catch ( \Exception $e ) {
			Notification_Master()->logger->error(
				'send_now_mark_complete',
				array(
					'action_id' => (int) $action_id,
					'message'   => $e->getMessage(),
				)
			);
		}

		return true;
	}

	/**
	 * Cancel exactly this pending action id.
	 *
	 * Unlike as_unschedule_action(), which removes the oldest action with the
	 * same args, this addresses the store by id. False when the id is not pending.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return bool
	 */
	protected function cancel_by_id( $action_id ) {
		if ( ! $this->take_pending( $action_id, \ActionScheduler_Store::STATUS_CANCELED, false ) ) {
			return false;
		}

		// Same hook the store fires, so the Action Scheduler log keeps its "action canceled" row.
		do_action( 'action_scheduler_canceled_action', (int) $action_id );

		return true;
	}

	/**
	 * Move a pending, unclaimed action to a new status in one statement.
	 *
	 * The queue runner claims rows with the same compare-and-set
	 * (claim_id = 0 AND status = pending), so exactly one side wins. With
	 * $stamp the attempt is recorded like the runner does, which keeps the
	 * cleaner from treating the row as never attempted.
	 *
	 * @since 1.8.0
	 *
	 * @param int    $action_id Action Scheduler action ID.
	 * @param string $status    Status to set.
	 * @param bool   $stamp     Whether to count an attempt and stamp its time.
	 * @return bool True when this call took the row.
	 */
	protected function take_pending( $action_id, $status, $stamp ) {
		global $wpdb;

		$table = ! empty( $wpdb->actionscheduler_actions ) ? $wpdb->actionscheduler_actions : $wpdb->prefix . 'actionscheduler_actions';

		if ( $stamp ) {
			$sql = $wpdb->prepare(
				"UPDATE {$table} SET status = %s, attempts = attempts + 1, last_attempt_gmt = %s, last_attempt_local = %s WHERE action_id = %d AND status = %s AND claim_id = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
				$status,
				current_time( 'mysql', true ),
				current_time( 'mysql' ),
				(int) $action_id,
				\ActionScheduler_Store::STATUS_PENDING
			);
		} else {
			$sql = $wpdb->prepare(
				"UPDATE {$table} SET status = %s WHERE action_id = %d AND status = %s AND claim_id = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
				$status,
				(int) $action_id,
				\ActionScheduler_Store::STATUS_PENDING
			);
		}

		return 1 === $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above; a compare-and-set has no store API.
	}

	/**
	 * Current Action Scheduler status of an action, 'unknown' when it cannot be read.
	 *
	 * @param int $action_id Action ID.
	 * @return string
	 */
	protected function status_of( $action_id ) {
		try {
			return (string) \ActionScheduler::store()->get_status( (int) $action_id );
		} catch ( \Exception $e ) {
			return 'unknown';
		}
	}

	/**
	 * Get a pending action by ID.
	 *
	 * @param int $action_id Action ID.
	 * @return \ActionScheduler_Action|null
	 */
	protected function get_action( $action_id ) {
		if ( ! class_exists( '\ActionScheduler' ) ) {
			return null;
		}

		try {
			$action = \ActionScheduler::store()->fetch_action( (int) $action_id );
		} catch ( \Exception $e ) {
			return null;
		}

		if ( ! $action || self::HOOK !== $action->get_hook() ) {
			return null;
		}

		return $action;
	}

	/**
	 * Get scheduled actions as summaries.
	 *
	 * @param array $args Query args: per_page, page, status (pending|complete|failed),
	 *                    notification_id (only that notification's actions; 0 = all).
	 * @return array List of action descriptors.
	 */
	public function get_pending( $args = array() ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return array();
		}

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'status'   => 'pending',
		);

		$args = wp_parse_args( $args, $defaults );

		$notification_id = isset( $args['notification_id'] ) ? (int) $args['notification_id'] : 0;
		$status          = $this->map_status( $args['status'] );

		$query = array(
			'hook'     => self::HOOK,
			'group'    => self::GROUP,
			'status'   => $status,
			'per_page' => max( 1, min( 100, (int) $args['per_page'] ) ),
			'offset'   => max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] ),
			'orderby'  => 'date',
			'order'    => 'ASC',
		);

		// Objects keyed by id; one fetch per id is Action Scheduler's own cost.
		if ( $notification_id > 0 ) {
			$actions = $this->fetch_notification_actions( $notification_id, $status, $query['per_page'], $query['offset'] );
		} else {
			$actions = as_get_scheduled_actions( $query );
		}

		if ( empty( $actions ) ) {
			return array();
		}

		// Prime the notification posts once so each title is a cache hit.
		$post_ids = array();
		foreach ( $actions as $action ) {
			if ( $action instanceof \ActionScheduler_Action ) {
				$action_args = $action->get_args();
				if ( isset( $action_args[0] ) && (int) $action_args[0] > 0 ) {
					$post_ids[] = (int) $action_args[0];
				}
			}
		}
		if ( ! empty( $post_ids ) ) {
			_prime_post_caches( array_values( array_unique( $post_ids ) ), false, false );
		}

		$results = array();
		foreach ( $actions as $action_id => $action ) {
			$results[] = $this->format_action_summary( $action_id, $action, $status );
		}

		return $results;
	}

	/**
	 * Count a notification's queued sends with one query.
	 *
	 * @param int    $notification_id Notification post id.
	 * @param string $status          pending|complete|failed.
	 * @return int
	 */
	public function count_pending( $notification_id, $status = 'pending' ) {
		global $wpdb;

		$where = $this->notification_where_sql( (int) $notification_id, $this->map_status( $status ) );
		if ( '' === $where ) {
			return 0;
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) ' . $where ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where is prepared; counts must be live.
	}

	/**
	 * Prepared FROM/WHERE fragment selecting one notification's actions, or '' when the AS tables are unknown.
	 *
	 * @param int    $notification_id Notification post id.
	 * @param string $status          Action Scheduler status.
	 * @return string
	 */
	protected function notification_where_sql( $notification_id, $status ) {
		global $wpdb;

		if ( $notification_id <= 0 || empty( $wpdb->actionscheduler_actions ) || empty( $wpdb->actionscheduler_groups ) ) {
			return '';
		}

		// Args are stored as a JSON list; long args live in extended_args with an md5 in args.
		$like = '[' . $notification_id . ',%';

		return $wpdb->prepare(
			"FROM {$wpdb->actionscheduler_actions} a INNER JOIN {$wpdb->actionscheduler_groups} g ON g.group_id = a.group_id WHERE a.hook = %s AND g.slug = %s AND a.status = %s AND ( ( a.extended_args IS NULL AND a.args LIKE %s ) OR a.extended_args LIKE %s )",
			self::HOOK,
			self::GROUP,
			$status,
			$like,
			$like
		);
	}

	/**
	 * Map the public status word to the Action Scheduler constant.
	 *
	 * @param string $status pending|complete|failed.
	 * @return string
	 */
	protected function map_status( $status ) {
		$map = array(
			'pending'  => \ActionScheduler_Store::STATUS_PENDING,
			'complete' => \ActionScheduler_Store::STATUS_COMPLETE,
			'failed'   => \ActionScheduler_Store::STATUS_FAILED,
		);

		return is_string( $status ) && isset( $map[ $status ] ) ? $map[ $status ] : \ActionScheduler_Store::STATUS_PENDING;
	}

	/**
	 * One notification's actions for a page, hydrated one by one (the only API the store offers).
	 *
	 * @param int    $notification_id Notification post id.
	 * @param string $status          Action Scheduler status.
	 * @param int    $per_page        Page size.
	 * @param int    $offset          Row offset.
	 * @return array<int, \ActionScheduler_Action>
	 */
	protected function fetch_notification_actions( $notification_id, $status, $per_page, $offset ) {
		global $wpdb;

		$where = $this->notification_where_sql( (int) $notification_id, $status );
		if ( '' === $where || ! class_exists( '\ActionScheduler' ) ) {
			return array();
		}

		// $where is already prepared; the two ints are cast, so no second prepare().
		$sql = 'SELECT a.action_id ' . $where . sprintf( ' ORDER BY a.scheduled_date_gmt ASC, a.action_id ASC LIMIT %d OFFSET %d', (int) $per_page, (int) $offset );
		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the queue must be live.

		$actions = array();
		foreach ( $ids as $id ) {
			$action = $this->get_action( (int) $id );
			if ( $action ) {
				$actions[ (int) $id ] = $action;
			}
		}

		return $actions;
	}

	/**
	 * Format an action row into a UI-friendly summary.
	 *
	 * @param int                           $action_id    Action ID.
	 * @param array|\ActionScheduler_Action $action       Action.
	 * @param string                        $known_status Status the query already filtered on; '' looks it up.
	 * @return array
	 */
	protected function format_action_summary( $action_id, $action, $known_status = '' ) {
		if ( $action instanceof \ActionScheduler_Action ) {
			$args     = $action->get_args();
			$schedule = $action->get_schedule();
			$next_ts  = $schedule && method_exists( $schedule, 'get_date' ) && $schedule->get_date()
				? $schedule->get_date()->getTimestamp()
				: 0;
			$status   = '' !== $known_status ? $known_status : $this->status_of( $action_id );
		} else {
			$args    = $action['args'] ?? array();
			$next_ts = isset( $action['scheduled_date_gmt'] ) ? strtotime( $action['scheduled_date_gmt'] ) : 0;
			$status  = $action['status'] ?? 'unknown';
		}

		$notification_id = isset( $args[0] ) ? (int) $args[0] : 0;
		$connection_id   = isset( $args[1] ) ? (string) $args[1] : '';
		$snapshot        = isset( $args[2] ) && is_array( $args[2] ) ? $args[2] : array();

		return array(
			'action_id'         => (int) $action_id,
			'notification_id'   => $notification_id,
			'notification_name' => $notification_id ? get_the_title( $notification_id ) : '',
			'connection_id'     => $connection_id,
			'connection_name'   => $snapshot['connection']['name'] ?? '',
			'integration'       => $snapshot['connection']['integration'] ?? '',
			'trigger_slug'      => $snapshot['trigger']['slug'] ?? '',
			'trigger_name'      => $snapshot['trigger']['name'] ?? '',
			'scheduled_for'     => $next_ts,
			'status'            => $status,
		);
	}

	/**
	 * Log a drop reason to the notification logger.
	 *
	 * @param string $reason          Drop reason code.
	 * @param int    $notification_id Notification ID.
	 * @param string $connection_id   Connection ID.
	 * @param array  $extra           Extra context.
	 * @return void
	 */
	protected function log_drop( $reason, $notification_id, $connection_id, $extra = array() ) {
		$plugin = Notification_Master();
		if ( ! $plugin || empty( $plugin->logger ) ) {
			return;
		}

		$plugin->logger->info(
			'scheduling',
			array_merge(
				array(
					'reason'          => $reason,
					'notification_id' => (int) $notification_id,
					'connection_id'   => $connection_id,
				),
				$extra
			)
		);
	}
}
