<?php
/**
 * Class Last_Sent
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Stats;

use Notification_Master\DB\Models\Notification_Logs_Model;

/**
 * Newest send (any status, tests excluded) per notification, in one grouped query.
 */
class Last_Sent {

	/**
	 * Most IDs accepted in one call.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	const MAX_IDS = 100;

	/**
	 * Newest log date per requested notification ID.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $ids Notification IDs.
	 *
	 * @return array ID => site-local 'Y-m-d H:i:s' or null (never sent).
	 */
	public static function get( $ids ) {
		$ids    = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, self::MAX_IDS );
		$result = array_fill_keys( $ids, null );
		if ( empty( $ids ) ) {
			return $result;
		}

		list( $titles ) = Failures::notification_titles();

		foreach ( self::grouped_rows() as $group ) {
			$id = (int) $group->notification_id;

			// Pre-2.0 rows: the stored (texturized) title, only when one notification has it.
			if ( 0 === $id ) {
				$name = (string) $group->notification_name;
				if ( '' === $name || ! isset( $titles[ $name ] ) || 1 !== count( $titles[ $name ] ) ) {
					continue;
				}
				$id = (int) reset( $titles[ $name ] );
			}

			if ( array_key_exists( $id, $result ) && ( null === $result[ $id ] || $group->last_date > $result[ $id ] ) ) {
				$result[ $id ] = (string) $group->last_date;
			}
		}

		return $result;
	}

	/**
	 * One grouped query: newest date per stored notification ID / stored name.
	 *
	 * @since 2.0.0
	 *
	 * @return array Row objects { notification_id, notification_name, last_date }.
	 */
	private static function grouped_rows() {
		global $wpdb;

		$id_key   = 's:15:"notification_id";i:';
		$name_key = 's:17:"notification_name";s:';

		$sql = "SELECT
				IF( content LIKE %s, CAST( SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), ';', 1 ) AS UNSIGNED ), 0 ) AS notification_id,
				IF( content NOT LIKE %s AND content LIKE %s, SUBSTRING( SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), '\";', 1 ), LOCATE( ':\"', SUBSTRING_INDEX( SUBSTRING_INDEX( content, %s, -1 ), '\";', 1 ) ) + 2 ), '' ) AS notification_name,
				MAX(created_at) AS last_date
			FROM {$wpdb->prefix}ntfm_notification_logs
			WHERE content NOT LIKE %s
			GROUP BY notification_id, notification_name";

		$id_like   = '%' . $wpdb->esc_like( $id_key ) . '%';
		$name_like = '%' . $wpdb->esc_like( $name_key ) . '%';
		$test_like = '%' . $wpdb->esc_like( Notification_Logs_Model::TEST_MARKER ) . '%';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $id_like, $id_key, $id_like, $name_like, $name_key, $name_key, $test_like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared here; one grouped query per list page.

		return is_array( $rows ) ? $rows : array();
	}
}
