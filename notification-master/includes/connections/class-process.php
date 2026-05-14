<?php
/**
 * Class Process Connections
 *
 * This class is responsible for processing connections.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */
namespace Notification_Master\Connections;

use Notification_Master\Integrations\Loader as Integrations_Loader;
use Notification_Master\Abstracts\Trigger;
use Notification_Master\Scheduling\Scheduler;
use Notification_Master\Settings;
use Notification_Master\Utils;

use function Notification_Master\Notification_Master;

/**
 * Process Connections class.
 *
 * @since 1.0.0
 */
class Process {

	/**
	 * Action name.
	 *
	 * @since 1.4.6
	 *
	 * @var string
	 */
	public $action_name = 'connections';

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Process
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Process
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Process ) ) {
			self::$instance = new Process();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$hook = Utils::get_action_hook( $this->action_name );
		// Process connection.
		add_action( $hook, array( $this, 'process_async_connections' ), 10, 1 );
	}

	/**
	 * Process connections.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $connections Connections.
	 * @param Trigger $trigger Trigger.
	 * @param int     $notification_id Notification ID.
	 *
	 * @return void
	 */
	public function process_connections( $connections, $trigger, $notification_id ) {
		// Peel off connections that have a future scheduled delay. Each becomes
		// its own Action Scheduler entry; the remainder continues through the
		// existing immediate/async dispatch path.
		$scheduler = Scheduler::get_instance();

		foreach ( $connections as $connection_id => $connection ) {
			$enabled = isset( $connection['enabled'] ) ? $connection['enabled'] : true;
			if ( ! $enabled ) {
				continue;
			}

			$delay = $scheduler->resolve_delay( $connection, $trigger );

			// `false` means the scheduler decided to drop this connection
			// entirely (e.g. merge-tag target is in the past with on_past=skip).
			if ( false === $delay ) {
				unset( $connections[ $connection_id ] );
				continue;
			}

			if ( $delay <= 0 ) {
				continue;
			}

			// Conditions still need to gate scheduling: a notification whose
			// live conditions fail should never be queued for later delivery.
			if ( ! empty( $connection['enable_conditions'] ) ) {
				$conditions = $connection['conditions'] ?? array();
				if ( ! apply_filters( 'notification_master_can_send_notification', true, $conditions, $trigger ) ) {
					unset( $connections[ $connection_id ] );
					continue;
				}
			}

			$scheduler->schedule( $connection, $trigger, $notification_id, $connection_id, $delay );
			unset( $connections[ $connection_id ] );
		}

		if ( empty( $connections ) ) {
			return;
		}

		$background_process = Settings::get_setting( 'enable_background_processing', false );

		if ( $background_process ) {
			$args = array(
				'connections'     => $connections,
				'trigger'         => $trigger,
				'notification_id' => $notification_id,
			);

			Utils::enqueue_async_action( $this->action_name, $args );
			return;
		}

		$this->process( $connections, $trigger, $notification_id );
	}

	/**
	 * Process connections.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $connections Connections.
	 * @param Trigger $trigger Trigger.
	 * @param int     $notification_id Notification ID.
	 *
	 * @return void
	 */
	public function process( $connections, $trigger, $notification_id ) {
		foreach ( $connections as $connection_id => $connection ) {
			$enabled     = isset( $connection['enabled'] ) ? $connection['enabled'] : true;
			$integration = Integrations_Loader::get_instance()->get_integration( $connection['integration'] );

			if ( ! $enabled || ! $integration ) {
				continue;
			}

			$conditions_enabled = $connection['enable_conditions'] ?? false;
			$conditions         = $connection['conditions'] ?? array();

			if ( $conditions_enabled ) {
				if ( ! apply_filters( 'notification_master_can_send_notification', true, $conditions, $trigger ) ) {
					continue;
				}
			}

			$connection_settings = $connection['settings'] ?? array();

			// Add action before process connection.
			do_action( 'notification_master_before_process_connection', $connection_settings, $trigger, $notification_id );

			$integration->process();

			// Add action after process connection.
			do_action( 'notification_master_after_process_connection' );
		}
	}

	/**
	 * Process async connections.
	 *
	 * @since 1.4.6
	 *
	 * @param string $option_name Option name.
	 *
	 * @return void
	 */
	public function process_async_connections( $option_name ) {
		$option = get_option( $option_name );
		if ( ! $option ) {
			Notification_Master()->logger->error(
				'async_process_connections_option_not_found',
				array(
					'option_name' => $option_name,
				)
			);
			return;
		}

		$connections     = $option['connections'];
		$trigger         = $option['trigger'];
		$notification_id = $option['notification_id'];

		$this->process( $connections, $trigger, $notification_id );

		delete_option( $option_name );
	}
}
