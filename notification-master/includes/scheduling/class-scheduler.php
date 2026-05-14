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
		if ( ! function_exists( 'as_unschedule_action' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		$action = $this->get_action( $action_id );
		if ( ! $action ) {
			return false;
		}

		$args = $action->get_args();
		as_unschedule_action( self::HOOK, $args, self::GROUP );

		return true;
	}

	/**
	 * Force-dispatch a pending scheduled connection immediately.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return bool
	 */
	public function send_now( $action_id ) {
		$action = $this->get_action( $action_id );
		if ( ! $action ) {
			return false;
		}

		$args = $action->get_args();

		// Cancel the pending action first so it doesn't fire again.
		as_unschedule_action( self::HOOK, $args, self::GROUP );

		// Dispatch synchronously using the snapshot we just removed.
		$this->handle_scheduled( $args[0] ?? 0, $args[1] ?? '', $args[2] ?? array() );

		return true;
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
	 * Get pending scheduled actions.
	 *
	 * @param array $args Query args (per_page, page, status, notification_id).
	 * @return array List of pending action descriptors.
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

		$status_map = array(
			'pending'  => \ActionScheduler_Store::STATUS_PENDING,
			'complete' => \ActionScheduler_Store::STATUS_COMPLETE,
			'failed'   => \ActionScheduler_Store::STATUS_FAILED,
		);

		$query = array(
			'hook'     => self::HOOK,
			'group'    => self::GROUP,
			'status'   => $status_map[ $args['status'] ] ?? \ActionScheduler_Store::STATUS_PENDING,
			'per_page' => max( 1, min( 100, (int) $args['per_page'] ) ),
			'offset'   => max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] ),
			'orderby'  => 'date',
			'order'    => 'ASC',
		);

		// Default return-format gives us ActionScheduler_Action objects keyed
		// by ID. We deliberately avoid 'ARRAY_A' because that returns raw DB
		// rows where `args` is still a JSON string, not an unpacked array.
		$actions = as_get_scheduled_actions( $query );

		if ( empty( $actions ) ) {
			return array();
		}

		$results = array();
		foreach ( $actions as $action_id => $action ) {
			$results[] = $this->format_action_summary( $action_id, $action );
		}

		return $results;
	}

	/**
	 * Format an action row into a UI-friendly summary.
	 *
	 * @param int                                              $action_id Action ID.
	 * @param array|\ActionScheduler_Action|\ActionScheduler_Action $action    Action.
	 * @return array
	 */
	protected function format_action_summary( $action_id, $action ) {
		// as_get_scheduled_actions in ARRAY_A mode returns ActionScheduler_Action objects keyed by ID.
		if ( $action instanceof \ActionScheduler_Action ) {
			$args     = $action->get_args();
			$schedule = $action->get_schedule();
			$next_ts  = $schedule && method_exists( $schedule, 'get_date' ) && $schedule->get_date()
				? $schedule->get_date()->getTimestamp()
				: 0;
			$status   = '';
			try {
				$status = \ActionScheduler::store()->get_status( $action_id );
			} catch ( \Exception $e ) {
				$status = 'unknown';
			}
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
