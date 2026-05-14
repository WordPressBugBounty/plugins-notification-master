<?php
/**
 * Class Plugin
 *
 * @package notification-master
 * @since 1.0.0
 */

namespace Notification_Master;

use Notification_Master\Admin\Admin;
use Notification_Master\REST_API\REST_API;
use Notification_Master\Notifications;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;
use Notification_Master\Integrations\Loader as Integrations_Loader;
use Notification_Master\WebPush\Loader as WebPush_Loader;
use Notification_Master\Connections\Process as Connections_Process;
use Notification_Master\WebPush\Background_Process as WebPush_Background_Process;
use Notification_Master\Email\Background_Process as Email_Background_Process;
use Notification_Master\Logger;
use Notification_Master\Notification_Logger;
use Notification_Master\DB\Tables\Logs_Table;
use Notification_Master\DB\Tables\Notification_Logs_Table;
use Notification_Master\DB\Tables\Subscriptions_Table;
use Notification_Master\Delayed_Notifications;
use Notification_Master\Scheduling\Scheduler;

/**
 * Activate, deactivate and load classes.
 */
class Plugin {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Logger
	 */
	public $logger;

	/**
	 * Notification Logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Notification_Logger
	 */
	public $notification_logger;

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Plugin
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		// Register activation hook.
		register_activation_hook( NOTIFICATION_MASTER_FILE, array( $this, 'activate' ) );
		// Register deactivation hook.
		register_deactivation_hook( NOTIFICATION_MASTER_FILE, array( $this, 'deactivate' ) );
		// init.
		$this->init();
		// Load classes.
		add_action( 'init', array( $this, 'load_classes' ) );
		// Register tables.
		add_action( 'admin_init', array( $this, 'register_tables' ) );

		// Schedule cleanup of failed subscriptions.
		add_action( 'ntfm_cleanup_failed_subscriptions', array( $this, 'cleanup_failed_subscriptions' ) );
		add_action( 'init', array( $this, 'schedule_failed_subscriptions_cleanup' ) );
	}

	/**
	 * Load text domain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'notification-master', false, NOTIFICATION_MASTER_DIR . '/languages' );
	}

	/**
	 * Activate plugin.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'ntfm_notifications_delete_logs' );
		wp_clear_scheduled_hook( 'ntfm_delete_logs' );
		wp_clear_scheduled_hook( 'ntfm_cleanup_failed_subscriptions' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Init.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Load rest api class.
		REST_API::get_instance();
		// Load notifications class.
		Notifications::get_instance();
		// Load connections class.
		Connections_Process::get_instance();
		// Load triggers class.
		Triggers_Loader::get_instance();
		// Load webpush class.
		WebPush_Loader::get_instance();
		// Load webpush background process class.
		WebPush_Background_Process::get_instance();
		// Load email background process class.
		Email_Background_Process::get_instance();
		// Load delay class.
		Delayed_Notifications::init();
		// Load scheduler.
		Scheduler::init();

		// Load logger class.
		$this->logger = Logger::get_instance();

		// Load notification logger class.
		$this->notification_logger = Notification_Logger::get_instance();

		// Schedule cleanup of failed subscriptions.
		$this->schedule_failed_subscriptions_cleanup();
	}

	/**
	 * Load classes.
	 *
	 * @since 1.0.0
	 */
	public function load_classes() {
		// Load admin class.
		if ( is_admin() ) {
			Admin::get_instance();
		}

		// Load merge tags class.
		Merge_Tags_Loader::get_instance();
		// Load integrations class.
		Integrations_Loader::get_instance();
	}

	/**
	 * Register tables.
	 *
	 * @since 1.0.0
	 */
	public function register_tables() {
		$logs_table = new Logs_Table();
		$logs_table->create_table();

		$notification_logs_table = new Notification_Logs_Table();
		$notification_logs_table->create_table();

		$subscriptions_table = new Subscriptions_Table();
		$subscriptions_table->create_table();

		if ( version_compare( NOTIFICATION_MASTER_VERSION, '1.4.3', '<=' ) ) {
			$subscriptions_table->add_status_column();
		}

		// Add failure tracking columns for auto-delete functionality.
		if ( version_compare( get_option( 'notification_master_version', '1.0.0' ), '1.6.5', '<' ) ) {
			$subscriptions_table->add_failure_tracking_columns();
			update_option( 'notification_master_version', NOTIFICATION_MASTER_VERSION );
		}
	}

	/**
	 * Schedule cleanup of failed subscriptions.
	 *
	 * @since 1.5.0
	 */
	public function schedule_failed_subscriptions_cleanup() {
		if ( ! wp_next_scheduled( 'ntfm_cleanup_failed_subscriptions' ) ) {
			wp_schedule_event( time(), 'daily', 'ntfm_cleanup_failed_subscriptions' );
		}
	}

	/**
	 * Cleanup old failed subscriptions.
	 *
	 * @since 1.5.0
	 */
	public function cleanup_failed_subscriptions() {
		// Log that cleanup is running for testing.
		$this->logger->info(
			'webpush',
			__( 'Running cleanup task for failed subscriptions...', 'notification-master' )
		);

		// Only cleanup if auto-delete is enabled.
		$auto_delete_enabled = Settings::get_setting( 'webpush_enable_auto_delete_failed_subscriptions', false );
		if ( ! $auto_delete_enabled ) {
			$this->logger->info(
				'webpush',
				__( 'Auto-delete is disabled. Skipping cleanup.', 'notification-master' )
			);
			return;
		}

		// Use shorter time for testing (1 day instead of 30 days).
		$deleted_count = DB\Models\Subscription_Model::cleanup_old_failed_subscriptions( 1 );

		if ( $deleted_count > 0 ) {
			$this->logger->info(
				'webpush',
				sprintf(
					/* translators: %d: number of deleted subscriptions */
					__( 'Cleaned up %d old failed subscriptions.', 'notification-master' ),
					$deleted_count
				)
			);
		} else {
			$this->logger->info(
				'webpush',
				__( 'No old failed subscriptions found to clean up.', 'notification-master' )
			);
		}
	}
}

if ( ! function_exists( 'Notification_Master' ) ) {
	/**
	 * Get instance of Plugin class.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	function Notification_Master() {
		return Plugin::get_instance();
	}
}
