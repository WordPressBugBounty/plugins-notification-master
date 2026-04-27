<?php
/**
 * Class Logger
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master;

use Notification_Master\DB\Models\Logs_Model;
use Notification_Master\Settings;

/**
 * Logger class.
 */
class Logger {

	const TYPE_ERROR = 'error';
	const TYPE_INFO  = 'info';
	const TYPE_DEBUG = 'debug';

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Logger
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Logger
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Logger ) ) {
			self::$instance = new Logger();
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
		add_action( 'ntfm_delete_logs', array( $this, 'delete_logs' ) );
	}

	/**
	 * Log error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action  Action.
	 * @param string $content Content.
	 */
	public function error( $action, $content ) {
		$this->log( self::TYPE_ERROR, $action, $content );
	}

	/**
	 * Log info.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action  Action.
	 * @param string $content Content.
	 */
	public function info( $action, $content ) {
		$this->log( self::TYPE_INFO, $action, $content );
	}

	/**
	 * Log debug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action  Action.
	 * @param string $content Content.
	 */
	public function debug( $action, $content ) {
		$this->log( self::TYPE_DEBUG, $action, $content );
	}

	/**
	 * Log.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Type.
	 * @param string $action  Action.
	 * @param string $content Content.
	 */
	private function log( $type, $action, $content ) {
		Logs_Model::insert(
			array(
				'type'    => $type,
				'action'  => $action,
				'content' => maybe_serialize( $content ),
			)
		);
	}

	/**
	 * Get logs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $per_page Per page.
	 * @param int $page     Page.
	 *
	 * @return array
	 */
	public function get_logs( $per_page = 10, $page = 1 ) {
		$logs   = Logs_Model::get_rows( $per_page, $page );
		$result = array();

		foreach ( $logs as $log ) {
			$result[] = array(
				'id'      => $log->id,
				'type'    => $log->type,
				'action'  => $log->action,
				'content' => maybe_unserialize( $log->content ),
				'date'    => $log->created_at,
			);
		}

		return $result;
	}

	/**
	 * Get all logs.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_all_logs() {
		$logs   = Logs_Model::get_all();
		$result = array();

		foreach ( $logs as $log ) {
			$result[] = array(
				'id'      => $log->id,
				'type'    => $log->type,
				'action'  => $log->action,
				'content' => maybe_unserialize( $log->content ),
				'date'    => $log->created_at,
			);
		}

		return $result;
	}

	/**
	 * Delete logs.
	 *
	 * @since 1.0.0
	 */
	public function delete_logs() {
		$every_days   = Settings::get_setting( 'delete_logs_every', 30 );
		$last_deleted = Settings::get_setting( 'last_deleted_logs', null );

		if ( $last_deleted && strtotime( $last_deleted ) > strtotime( '-' . $every_days . ' days' ) ) {
			return;
		}

		$date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $every_days . ' days' ) );
		Logs_Model::delete_by_date( $date );
		Settings::update_setting( 'last_deleted_logs', gmdate( 'Y-m-d H:i:s' ) );
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
			Logs_Model::delete();
		} else {
			Logs_Model::delete_by_ids( $ids );
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
		return Logs_Model::get_count();
	}

	/**
	 * Add action scheduler to delete logs.
	 *
	 * @since 1.0.0
	 */
	public function add_action_scheduler() {
		if ( ! wp_next_scheduled( 'ntfm_delete_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'ntfm_delete_logs' );
		}
	}
}
