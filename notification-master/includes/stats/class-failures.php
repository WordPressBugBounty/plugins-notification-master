<?php
/**
 * Class Failures
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Stats;

use Notification_Master\DB\Models\Notification_Logs_Model;
use Notification_Master\Logs\Log_Formatter;

/**
 * Failed sends per notification and per integration (Notifications list + editor).
 */
class Failures {

	/**
	 * Failures in a window, keyed by notification ID; test sends excluded.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array Notification ID => { notification_id, failed, integrations }.
	 */
	public static function get( Period $period ) {
		$groups = self::grouped_rows( $period );
		if ( empty( $groups ) ) {
			return array();
		}

		list( $titles, $existing ) = self::notification_titles();
		$result                    = array();
		$last_ids                  = array();

		foreach ( $groups as $group ) {
			$id = (int) $group->notification_id;

			// Pre-2.0 rows have no ID: match the stored title only when one notification has it.
			if ( 0 === $id ) {
				$name = (string) $group->notification_name;
				if ( '' === $name || ! isset( $titles[ $name ] ) || 1 !== count( $titles[ $name ] ) ) {
					continue;
				}
				$id = (int) reset( $titles[ $name ] );
			}

			if ( ! isset( $existing[ $id ] ) ) {
				continue;
			}

			$slug = (string) $group->integration;
			if ( ! isset( $result[ $id ] ) ) {
				$result[ $id ] = array(
					'notification_id' => $id,
					'failed'          => 0,
					'integrations'    => array(),
				);
			}
			if ( ! isset( $result[ $id ]['integrations'][ $slug ] ) ) {
				$result[ $id ]['integrations'][ $slug ] = array(
					'integration' => $slug,
					'name'        => Log_Formatter::integration_name( $slug ),
					'failed'      => 0,
					'last_log_id' => 0,
				);
			}

			$bucket                   = &$result[ $id ]['integrations'][ $slug ];
			$bucket['failed']        += (int) $group->failed;
			$bucket['last_log_id']    = max( $bucket['last_log_id'], (int) $group->last_id );
			$result[ $id ]['failed'] += (int) $group->failed;
			unset( $bucket );
		}

		foreach ( $result as $notification ) {
			foreach ( $notification['integrations'] as $bucket ) {
				$last_ids[] = $bucket['last_log_id'];
			}
		}

		return self::add_last_details( $result, $last_ids );
	}

	/**
	 * One grouped query: failed count and newest row ID per notification ID / stored name / integration.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array Row objects { notification_id, notification_name, integration, failed, last_id }.
	 */
	private static function grouped_rows( Period $period ) {
		global $wpdb;

		$id_key   = 's:15:"notification_id";i:';
		$name_key = 's:17:"notification_name";s:';

		// The ID is read from the serialized content; the name only when the ID is missing.
		$sql = "SELECT
				IF( content LIKE %s, CAST( SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), ';', 1 ) AS UNSIGNED ), 0 ) AS notification_id,
				IF( content NOT LIKE %s AND content LIKE %s, SUBSTRING( SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), '\";', 1 ), LOCATE( ':\"', SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), '\";', 1 ) ) + 2 ), '' ) AS notification_name,
				integration,
				COUNT(*) AS failed,
				MAX(id) AS last_id
			FROM {$wpdb->prefix}ntfm_notification_logs
			WHERE status = 'error' AND created_at BETWEEN %s AND %s AND content NOT LIKE %s
			GROUP BY notification_id, notification_name, integration";

		$id_like   = '%' . $wpdb->esc_like( $id_key ) . '%';
		$name_like = '%' . $wpdb->esc_like( $name_key ) . '%';
		$test_like = '%' . $wpdb->esc_like( Notification_Logs_Model::TEST_MARKER ) . '%';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $id_like, $id_key, $id_like, $name_like, $name_key, $name_key, $period->from, $period->to, $test_like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared here; one grouped count per page view.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Map of notification titles to IDs, and the set of existing IDs (trash excluded).
	 *
	 * @since 2.0.0
	 *
	 * @return array [ title => int[], id => true ]
	 */
	public static function notification_titles() {
		$posts = get_posts(
			array(
				'post_type'   => 'ntfm_notification',
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
			)
		);

		$titles   = array();
		$existing = array();
		foreach ( $posts as $post ) {
			$existing[ $post->ID ] = true;
			// Logs store get_the_title(); keep the raw title too.
			foreach ( array_unique( array( $post->post_title, get_the_title( $post ) ) ) as $title ) {
				$titles[ $title ][ $post->ID ] = $post->ID;
			}
		}

		return array( $titles, $existing );
	}

	/**
	 * Add reason, HTTP code, message, date and connection of each newest failed row (one query).
	 *
	 * @since 2.0.0
	 *
	 * @param array $result   Grouped result.
	 * @param int[] $last_ids Newest row ID per bucket.
	 *
	 * @return array
	 */
	private static function add_last_details( $result, $last_ids ) {
		global $wpdb;

		$rows = array();
		if ( $last_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $last_ids ), '%d' ) );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_notification_logs WHERE id IN ($placeholders)", $last_ids ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders built above.
				$rows[ (int) $row->id ] = Log_Formatter::format_row( $row );
			}
		}

		foreach ( $result as $id => $notification ) {
			foreach ( $notification['integrations'] as $slug => $bucket ) {
				$log = isset( $rows[ $bucket['last_log_id'] ] ) ? $rows[ $bucket['last_log_id'] ] : null;

				$result[ $id ]['integrations'][ $slug ] += array(
					'last_reason'        => $log ? $log['reason'] : null,
					'last_message'       => $log ? $log['message'] : null,
					'last_http_code'     => $log ? $log['http_code'] : null,
					'last_date'          => $log ? $log['date'] : null,
					'last_connection_id' => $log && is_array( $log['content'] ) && isset( $log['content']['connection_id'] ) ? (string) $log['content']['connection_id'] : null,
				);
			}
		}

		return $result;
	}
}
