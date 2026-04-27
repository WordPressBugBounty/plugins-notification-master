<?php
/**
 * Class Notification_Logger
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master;

use Notification_Master\DB\Models\Notification_Logs_Model;
use Notification_Master\Settings;

/**
 * Notification Logger class.
 */
class Notification_Logger {

	const STATUS_ERROR   = 'error';
	const STATUS_SUCCESS = 'success';

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Notification_Logger
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Notification_Logger
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Notification_Logger ) ) {
			self::$instance = new Notification_Logger();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_action_scheduler' ) );
		add_action( 'ntfm_notifications_delete_logs', array( $this, 'delete_logs' ) );
	}

	/**
	 * success.
	 *
	 * @since 1.0.0
	 *
	 * @param string $integration Integration.
	 * @param array  $content Content.
	 */
	public function success( $integration, $content = array() ) {
		$this->log( $integration, self::STATUS_SUCCESS, $content );
	}

	/**
	 * error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $integration Integration.
	 * @param array  $content Content.
	 */
	public function error( $integration, $content = array() ) {
		$this->log( $integration, self::STATUS_ERROR, $content );
	}

	/**
	 * Log.
	 *
	 * @since 1.0.0
	 *
	 * @param string $integration Integration.
	 * @param string $status Status.
	 * @param array  $content Content.
	 */
	public function log( $integration, $status, $content = array() ) {
		Notification_Logs_Model::insert(
			array(
				'integration' => $integration,
				'status'      => $status,
				'content'     => maybe_serialize( $content ),
			)
		);
	}

	/**
	 * Add action scheduler.
	 *
	 * @since 1.0.0
	 */
	public function add_action_scheduler() {
		if ( ! wp_next_scheduled( 'ntfm_notifications_delete_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'ntfm_notifications_delete_logs' );
		}
	}

	/**
	 * Delete logs.
	 *
	 * @since 1.0.0
	 */
	public function delete_logs() {
		$every_days   = Settings::get_setting( 'notifications_delete_logs_every', 30 );
		$last_deleted = Settings::get_setting( 'notifications_last_deleted_logs', null );

		if ( $last_deleted && strtotime( $last_deleted ) > strtotime( '-' . $every_days . ' days' ) ) {
			return;
		}

		$date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $every_days . ' days' ) );
		Notification_Logs_Model::delete_by_date( $date );
		Settings::update_setting( 'notifications_last_deleted_logs', gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $per_page Per page.
	 * @param int $page Page.
	 *
	 * @return array
	 */
	public function get_logs( $per_page = 10, $page = 1 ) {
		$logs   = Notification_Logs_Model::get_rows( $per_page, $page );
		$result = array();

		foreach ( $logs as $log ) {
			$result[] = array(
				'id'          => $log->id,
				'integration' => $log->integration,
				'status'      => $log->status,
				'content'     => maybe_unserialize( $log->content ),
				'date'        => $log->created_at,
			);
		}

		return $result;
	}

	/**
	 * Delete logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids IDs.
	 */
	public function delete( $ids = array() ) {
		if ( empty( $ids ) ) {
			Notification_Logs_Model::delete();
		} else {
			Notification_Logs_Model::delete_by_ids( $ids );
		}
	}

	/**
	 * Get logs count.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_count() {
		return Notification_Logs_Model::get_count();
	}

	/**
	 * Get count by date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_date From date.
	 * @param string $to_date To date.
	 * @param string $status Status.
	 *
	 * @return int
	 */
	public function get_count_by_date( $from_date, $to_date, $status = '' ) {
		return Notification_Logs_Model::get_count_by_date( $from_date, $to_date, $status );
	}
}
