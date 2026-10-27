<?php
/**
 * Class Logs
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\DB\Models;

/**
 * Class Logs
 */
class Logs_Model {

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $table_name = 'ntfm_logs';

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
			'type'       => $data['type'],
			'action'     => $data['action'],
			'content'    => $data['content'],
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		return $prepared_data;
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
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_logs WHERE {$key} = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Static column name; no caching needed.

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

		$result = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ntfm_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
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
		$wpdb->insert( "{$wpdb->prefix}ntfm_logs", $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

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

		return $wpdb->query( "DELETE FROM {$wpdb->prefix}ntfm_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Delete by IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids IDs.
	 */
	public static function delete_by_ids( $ids ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_logs WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
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
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ntfm_logs'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
			return;
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_logs WHERE created_at < %s", $date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
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
				"SELECT * FROM {$wpdb->prefix}ntfm_logs ORDER BY id DESC LIMIT %d OFFSET %d",
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

		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
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

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_logs WHERE created_at BETWEEN %s AND %s";

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
			'type'     => '',
			'action'   => '',
			'per_page' => 25,
			'page'     => 1,
		);
	}

	/**
	 * Build the WHERE clause shared by query() and count().
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Parsed args: type, action.
	 *
	 * @return array SQL fragment (empty or leading with ' WHERE ') and its prepare() params.
	 */
	private static function build_where( $args ) {
		$where  = array();
		$params = array();

		$type = isset( $args['type'] ) ? (string) $args['type'] : '';
		if ( in_array( $type, array( 'error', 'info', 'debug' ), true ) ) {
			$where[]  = '`type` = %s';
			$params[] = $type;
		}

		$action = isset( $args['action'] ) ? (string) $args['action'] : '';
		if ( '' !== $action ) {
			$where[]  = '`action` = %s';
			$params[] = $action;
		}

		$sql = empty( $where ) ? '' : ' WHERE ' . implode( ' AND ', $where );

		return array( $sql, $params );
	}

	/**
	 * Query rows, newest first, with optional filters.
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Args: type (''|error|info|debug), action (exact), per_page (1..100), page (>= 1).
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

		$sql = "SELECT * FROM {$wpdb->prefix}ntfm_logs{$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count rows matching the same filters as query().
	 *
	 * @since 1.8.0
	 *
	 * @param array $args Args: type, action.
	 *
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( is_array( $args ) ? $args : array(), self::query_defaults() );

		list( $where, $params ) = self::build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_logs{$where}";

		// No filters means no placeholders, and prepare() refuses a query without one.
		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query and no caching needed.
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}
}
