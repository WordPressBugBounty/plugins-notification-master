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

		$key    = self::$primary_key;
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_notification_logs WHERE {$key} = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Static column name; no caching needed.

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

	/**
	 * Default args shared by query() and count().
	 *
	 * @since 1.8.0
	 *
	 * @return array
	 */
	private static function query_defaults() {
		return array(
			'status'            => '',
			'integration'       => '',
			'notification_name' => '',
			'per_page'          => 25,
			'page'              => 1,
		);
	}

	/**
	 * Build the WHERE clause shared by query() and count().
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Parsed args: status, integration, notification_name.
	 *
	 * @return array SQL fragment (empty or leading with ' WHERE ') and its prepare() params.
	 */
	private static function build_where( $args ) {
		global $wpdb;

		$where  = array();
		$params = array();

		$status = isset( $args['status'] ) ? (string) $args['status'] : '';
		if ( in_array( $status, array( 'success', 'error' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$integration = isset( $args['integration'] ) ? (string) $args['integration'] : '';
		if ( '' !== $integration ) {
			$where[]  = 'integration = %s';
			$params[] = $integration;
		}

		// Content is a serialized array; match its exact notification_name entry, not any substring.
		$name = isset( $args['notification_name'] ) ? (string) $args['notification_name'] : '';
		if ( '' !== $name ) {
			$where[]  = 'content LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sprintf( 's:17:"notification_name";s:%d:"%s";', strlen( $name ), $name ) ) . '%';
		}

		$sql = empty( $where ) ? '' : ' WHERE ' . implode( ' AND ', $where );

		return array( $sql, $params );
	}

	/**
	 * Query rows, newest first, with optional filters.
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Args: status (''|success|error), integration, notification_name, per_page (1..100), page (>= 1).
	 *
	 * @return array Row objects.
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args     = wp_parse_args( is_array( $args ) ? $args : array(), self::query_defaults() );
		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		list( $where, $params ) = self::build_where( $args );
		$params[]               = $per_page;
		$params[]               = $offset;

		$sql = "SELECT * FROM {$wpdb->prefix}ntfm_notification_logs{$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count rows matching the same filters as query().
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Args: status, integration, notification_name.
	 *
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( is_array( $args ) ? $args : array(), self::query_defaults() );

		list( $where, $params ) = self::build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_notification_logs{$where}";

		// No filters means no placeholders, and prepare() refuses a query without one.
		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query and no caching needed.
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}

	/**
	 * Get per-day, per-status totals in a date range.
	 *
	 * @since 1.8.0
	 *
	 * @param string $from_date From date (MySQL datetime).
	 * @param string $to_date   To date (MySQL datetime).
	 *
	 * @return array Row objects {day, status, total}.
	 */
	public static function get_daily_counts( $from_date, $to_date ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS `day`, status, COUNT(*) AS total FROM {$wpdb->prefix}ntfm_notification_logs WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at), status ORDER BY `day` ASC", $from_date, $to_date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get per-integration, per-status totals in a date range.
	 *
	 * @since 1.8.0
	 *
	 * @param string $from_date From date (MySQL datetime).
	 * @param string $to_date   To date (MySQL datetime).
	 *
	 * @return array Row objects {integration, status, total}.
	 */
	public static function get_counts_by_integration( $from_date, $to_date ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT integration, status, COUNT(*) AS total FROM {$wpdb->prefix}ntfm_notification_logs WHERE created_at BETWEEN %s AND %s GROUP BY integration, status ORDER BY integration ASC, status ASC", $from_date, $to_date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return is_array( $rows ) ? $rows : array();
	}
}
