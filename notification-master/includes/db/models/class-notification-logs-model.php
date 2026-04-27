<?php
/**
 * Notification Logs model.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\DB\Models;

/**
 * Notification Logs model.
 */
class Notification_Logs_Model {

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $table_name = 'ntfm_notification_logs';

	/**
	 * Primary key.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $primary_key = 'id';

	/**
	 * Prepare data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data.
	 *
	 * @return array
	 */
	public static function prepare_data( $data ) {
		$prepared_data = array(
			'integration' => $data['integration'],
			'status'      => $data['status'],
			'content'     => $data['content'],
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

		return $prepared_data;
	}

	/**
	 * Get count by integration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $integration Integration.
	 * @param string $from_date From date.
	 * @param string $to_date To date.
	 *
	 * @return int
	 */
	public static function get_count_by_integration( $integration, $from_date, $to_date ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_notification_logs WHERE integration = %s AND created_at BETWEEN %s AND %s", $integration, $from_date, $to_date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
		return $result;
	}

	/**
	 * Get
	 *
	 * @since 1.0.0
	 *
	 * @param int $id ID.
	 *
	 * @return object
	 */
	public static function get( $id ) {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_notification_logs WHERE %s = %d", self::$primary_key, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $result;
	}

	/**
	 * Get all.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;

		$result = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ntfm_notification_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
		return $result;
	}

	/**
	 * Insert.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data.
	 *
	 * @return int
	 */
	public static function insert( $data ) {
		global $wpdb;

		$data = self::prepare_data( $data );
		$wpdb->insert( "{$wpdb->prefix}ntfm_notification_logs", $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $wpdb->insert_id;
	}

	/**
	 * Delete.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function delete() {
		global $wpdb;

		return $wpdb->query( "DELETE FROM {$wpdb->prefix}ntfm_notification_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Delete by IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids IDs.
	 *
	 * @return int
	 */
	public static function delete_by_ids( $ids ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_notification_logs WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Delete by date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date Date.
	 */
	public static function delete_by_date( $date ) {
		global $wpdb;

		// Check if table exists.
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ntfm_notification_logs'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
			return;
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_notification_logs WHERE created_at < %s", $date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Get rows.
	 *
	 * @since 1.0.0
	 *
	 * @param int $per_page Per page.
	 * @param int $page Page.
	 *
	 * @return array
	 */
	public static function get_rows( $per_page = 10, $page = 1 ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ntfm_notification_logs ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Get count.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function get_count() {
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_notification_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
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
	public static function get_count_by_date( $from_date, $to_date, $status = '' ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_notification_logs WHERE created_at BETWEEN %s AND %s";

		$args = array( $from_date, $to_date );

		if ( ! empty( $status ) ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		return $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}
}
