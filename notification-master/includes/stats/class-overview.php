<?php
/**
 * Class Overview
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Stats;

use Notification_Master\DB\Models\Notification_Logs_Model;
use Notification_Master\Logs\Log_Formatter;

/**
 * Builds the numbers for the Overview screen and the count endpoint (test sends excluded).
 */
class Overview {

	/**
	 * Full Overview payload.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array
	 */
	public static function get( Period $period ) {
		$current  = self::totals( $period->from, $period->to );
		$previous = self::totals( $period->previous_from, $period->previous_to );
		$daily    = array();

		foreach ( self::daily( $period ) as $date => $bucket ) {
			$daily[] = array(
				'date'    => $date,
				'total'   => $bucket['count'],
				'success' => $bucket['success'],
				'failed'  => $bucket['failed'],
			);
		}

		return array(
			'days'                  => $period->days,
			'from'                  => $period->from,
			'to'                    => $period->to,
			'timezone'              => Period::timezone(),
			'total'                 => array(
				'value'    => $current['all'],
				'previous' => $previous['all'],
			),
			'sent'                  => array(
				'value'    => $current['success'],
				'previous' => $previous['success'],
			),
			'failed'                => array(
				'value'    => $current['error'],
				'previous' => $previous['error'],
			),
			'failed_by_integration' => self::failed_by_integration( $period ),
			'active_notifications'  => self::active_notifications( $period ),
			'subscribers'           => self::subscribers( $period ),
			'daily'                 => $daily,
			'recent_failures'       => self::recent_failures( 5 ),
			'has_logs'              => (int) Notification_Logs_Model::get_count() > 0,
		);
	}

	/**
	 * Per-status totals in a range.
	 *
	 * @since 2.0.0
	 *
	 * @param string $from From (site-local datetime).
	 * @param string $to   To (site-local datetime).
	 *
	 * @return array { all, success, error }
	 */
	public static function totals( $from, $to ) {
		return Notification_Logs_Model::count_by_status(
			array(
				'from'          => $from,
				'to'            => $to,
				'exclude_tests' => true,
			)
		);
	}

	/**
	 * Daily buckets keyed by Y-m-d, oldest first, zeros included (one grouped query).
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array Date => { count, success, failed }.
	 */
	public static function daily( Period $period ) {
		$daily = array();
		foreach ( $period->dates as $date ) {
			$daily[ $date ] = array(
				'count'   => 0,
				'success' => 0,
				'failed'  => 0,
			);
		}

		foreach ( Notification_Logs_Model::get_daily_counts( $period->from, $period->to, true ) as $row ) {
			$day = (string) $row->day;
			if ( ! isset( $daily[ $day ] ) ) {
				continue;
			}

			$total                     = (int) $row->total;
			$daily[ $day ]['count']   += $total;
			$daily[ $day ]['success'] += 'success' === $row->status ? $total : 0;
			$daily[ $day ]['failed']  += 'error' === $row->status ? $total : 0;
		}

		return $daily;
	}

	/**
	 * Failed sends per integration in the window, most first.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array List of { integration, name, count }.
	 */
	public static function failed_by_integration( Period $period ) {
		$result = array();

		foreach ( Notification_Logs_Model::get_counts_by_integration( $period->from, $period->to, true ) as $row ) {
			if ( 'error' !== $row->status ) {
				continue;
			}

			$result[] = array(
				'integration' => (string) $row->integration,
				'name'        => Log_Formatter::integration_name( $row->integration ),
				'count'       => (int) $row->total,
			);
		}

		usort(
			$result,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return $result;
	}

	/**
	 * Published notifications; "new" = created inside the window.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array { value, new, previous }
	 */
	public static function active_notifications( Period $period ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM( post_date BETWEEN %s AND %s ) AS fresh FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $period->from, $period->to, 'ntfm_notification', 'publish' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One cheap count per page view.

		$value = $row ? (int) $row->total : 0;
		$fresh = $row ? (int) $row->fresh : 0;

		return array(
			'value'    => $value,
			'new'      => $fresh,
			'previous' => $value - $fresh,
		);
	}

	/**
	 * Active push subscribers and new ones per window.
	 *
	 * @since 2.0.0
	 *
	 * @param Period $period Window.
	 *
	 * @return array { value, new, previous_new }
	 */
	public static function subscribers( Period $period ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT SUM( `status` = %s ) AS active, SUM( `status` = %s AND created_at BETWEEN %s AND %s ) AS fresh, SUM( `status` = %s AND created_at BETWEEN %s AND %s ) AS previous_fresh FROM {$wpdb->prefix}ntfm_subscriptions", 'subscribed', 'subscribed', $period->from, $period->to, 'subscribed', $period->previous_from, $period->previous_to ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One grouped count per page view.

		return array(
			'value'        => $row ? (int) $row->active : 0,
			'new'          => $row ? (int) $row->fresh : 0,
			'previous_new' => $row ? (int) $row->previous_fresh : 0,
		);
	}

	/**
	 * Newest failed sends.
	 *
	 * @since 2.0.0
	 *
	 * @param int $limit How many.
	 *
	 * @return array List of { id, date, notification_id, notification_name, integration, integration_name, message, reason, http_code, recipient, test }.
	 */
	public static function recent_failures( $limit = 5 ) {
		$rows = Notification_Logs_Model::query(
			array(
				'status'        => 'error',
				'exclude_tests' => true,
				'per_page'      => $limit,
				'page'          => 1,
			)
		);

		$result = array();
		foreach ( $rows as $row ) {
			$log      = Log_Formatter::format_row( $row );
			$result[] = array(
				'id'                => (int) $log['id'],
				'date'              => $log['date'],
				'notification_id'   => $log['notification_id'],
				'notification_name' => $log['notification_name'],
				'integration'       => $log['integration'],
				'integration_name'  => $log['integration_name'],
				'message'           => $log['message'],
				'reason'            => $log['reason'],
				'http_code'         => $log['http_code'],
				'recipient'         => $log['recipient'],
				'test'              => $log['test'],
			);
		}

		return $result;
	}
}
