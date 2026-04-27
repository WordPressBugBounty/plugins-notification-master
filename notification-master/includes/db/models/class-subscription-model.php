<?php
/**
 * Class Subscription_Model
 *
 * @package notification-master
 *
 * @since 1.4.0
 */

namespace Notification_Master\DB\Models;

use Jenssegers\Agent\Agent;

/**
 * Class Subscription_Model
 */
class Subscription_Model {

	/**
	 * Table name.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public static $table_name = 'ntfm_subscriptions';

	/**
	 * Primary key.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public static $primary_key = 'id';

	/**
	 * Prepare data.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data Data.
	 *
	 * @return array
	 */
	public static function prepare_data( $data ) {
		$agent   = new Agent();
		$browser = $agent->browser();
		$os      = $agent->platform();
		$user_id = get_current_user_id() ? get_current_user_id() : null;
		$device  = 'desktop';
		if ( $agent->isMobile() ) {
			$device = 'mobile';
		} elseif ( $agent->isTablet() ) {
			$device = 'tablet';
		}

		$prepared_data = array(
			'user_id'          => $user_id,
			'browser'          => $browser,
			'operating_system' => $os,
			'device'           => $device,
			'ip_address'       => self::get_ip_address(),
			'user_agent'       => self::get_user_agent(),
			'endpoint'         => $data['endpoint'],
			'auth'             => $data['auth'],
			'p256dh'           => $data['p256dh'],
			'content_encoding' => $data['content_encoding'],
			'expiration_time'  => $data['expiration_time'],
			'failure_count'    => 0,
			'last_failure_at'  => null,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		return $prepared_data;
	}

	/**
	 * Get
	 *
	 * @since 1.4.0
	 *
	 * @param int $id ID.
	 *
	 * @return object
	 */
	public static function get( $id ) {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions WHERE %s = %d", self::$primary_key, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $result;
	}

	/**
	 * Get by endpoint.
	 *
	 * @since 1.4.0
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return object
	 */
	public static function get_by_endpoint( $endpoint ) {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions WHERE endpoint = %s", $endpoint ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $result;
	}

	/**
	 * Get all.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;

		$result = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
		return $result;
	}

	/**
	 * Insert.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data Data.
	 *
	 * @return int
	 */
	public static function insert( $data ) {
		global $wpdb;

		$data = self::prepare_data( $data );
		$wpdb->insert( "{$wpdb->prefix}ntfm_subscriptions", $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $wpdb->insert_id;
	}

	/**
	 * Delete.
	 *
	 * @since 1.4.0
	 *
	 * @return int
	 */
	public static function delete() {
		global $wpdb;

		return $wpdb->query( "DELETE FROM {$wpdb->prefix}ntfm_subscriptions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Delete by IDs.
	 *
	 * @since 1.4.0
	 *
	 * @param array $ids IDs.
	 */
	public static function delete_by_ids( $ids ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_subscriptions WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Delete by date.
	 *
	 * @since 1.4.0
	 *
	 * @param string $date Date.
	 */
	public static function delete_by_date( $date ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ntfm_subscriptions WHERE created_at < %s", $date ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Get rows.
	 *
	 * @since 1.4.0
	 *
	 * @param int    $per_page Per page.
	 * @param int    $page Page.
	 * @param string $status Status.
	 *
	 * @return array
	 */
	public static function get_rows( $per_page = 10, $page = 1, $status = 'all' ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$result = array();

		if ( 'all' === $status ) {
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions ORDER BY id DESC LIMIT %d, %d", $offset, $per_page ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
		} else {
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions WHERE `status` = %s ORDER BY id DESC LIMIT %d, %d", $status, $offset, $per_page ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
		}

		return $result;
	}

	/**
	 * Get subscriptions by user IDs.
	 *
	 * This function retrieves subscription rows based on specific user IDs.
	 *
	 * @since 1.4.5
	 *
	 * @param array  $user_ids Array of user IDs.
	 * @param int    $per_page Number of results per page. Default 10.
	 * @param int    $page     Current page. Default 1.
	 * @param string $status   Subscription status. Default 'all'.
	 *
	 * @return array Retrieved subscription rows.
	 */
	public static function get_subscriptions_by_user_ids( $user_ids = array(), $per_page = 10, $page = 1, $status = 'all' ) {
		global $wpdb;

		if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
			return array();
		}

		$user_ids             = array_map( 'intval', array_filter( $user_ids ) );
		$user_ids_placeholder = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		$offset = ( $page - 1 ) * $per_page;

		// Prepare base SQL query
		$sql = "SELECT * FROM {$wpdb->prefix}ntfm_subscriptions WHERE `user_id` IN ($user_ids_placeholder)";

		// Filter by status if needed
		if ( 'all' !== $status ) {
			$sql       .= ' AND `status` = %s';
			$query_args = array_merge( $user_ids, array( $status, $offset, $per_page ) );
		} else {
			$query_args = array_merge( $user_ids, array( $offset, $per_page ) );
		}

		// Add sorting and pagination
		$sql .= ' ORDER BY id DESC LIMIT %d, %d';

		// Execute query
		$result = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result;
	}

	/**
	 * Get count.
	 *
	 * @since 1.4.0
	 *
	 * @return int
	 */
	public static function get_count() {
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Get count by status.
	 *
	 * @since 1.4.3
	 *
	 * @param string $status Status.
	 *
	 * @return int
	 */
	public static function get_count_by_status( $status = 'subscribed' ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions WHERE `status` = %s", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}

	/**
	 * Get count by browser.
	 *
	 * @since 1.4.3
	 *
	 * @param string $browser Browser.
	 *
	 * @return int
	 */
	public static function get_count_by_browser( $browser ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions WHERE browser = %s", $browser ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}

	/**
	 * Get count for browsers that not equal to chrome, firefox, safari, opera.
	 *
	 * @since 1.4.3
	 *
	 * @return int
	 */
	public static function get_count_for_other_browsers() {
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions WHERE browser NOT IN ('chrome', 'firefox', 'safari', 'opera')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.
	}

	/**
	 * Get count by device.
	 *
	 * @since 1.4.3
	 *
	 * @param string $device Device.
	 *
	 * @return int
	 */
	public static function get_count_by_device( $device ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions WHERE device = %s", $device ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}

	/**
	 * Get count by date.
	 *
	 * @since 1.4.0
	 *
	 * @param string $from_date From date.
	 * @param string $to_date To date.
	 * @param string $status Status.
	 *
	 * @return int
	 */
	public static function get_count_by_date( $from_date, $to_date, $status = '' ) {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ntfm_subscriptions WHERE created_at BETWEEN %s AND %s";

		$args = array( $from_date, $to_date );

		if ( ! empty( $status ) ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		return $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared in the line above and no caching needed.
	}

	/**
	 * Update status.
	 *
	 * @since 1.4.3
	 *
	 * @param array  $ids IDs.
	 * @param string $status Status.
	 */
	public static function update_status( $ids, $status ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}ntfm_subscriptions 
			SET `status` = %s 
			WHERE id IN ($placeholders)",
				array_merge( array( $status ), $ids )
			)
		);
	}

	/**
	 * Update
	 *
	 * @since 1.4.0
	 *
	 * @param int   $id ID.
	 * @param array $data Data.
	 *
	 * @return int
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$wpdb->update( "{$wpdb->prefix}ntfm_subscriptions", $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $id;
	}

	/**
	 * Get IP address.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public static function get_ip_address() {
		$ip_address = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}

		return $ip_address;
	}

	/**
	 * Get user agent.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public static function get_user_agent() {
		return $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Record notification failure for a subscription.
	 *
	 * @since 1.5.0
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return bool
	 */
	public static function record_failure( $endpoint ) {
		global $wpdb;

		$subscription = self::get_by_endpoint( $endpoint );
		if ( ! $subscription ) {
			return false;
		}

		$failure_count = ( $subscription->failure_count ?? 0 ) + 1;

		$updated = $wpdb->update(
			"{$wpdb->prefix}ntfm_subscriptions",
			array(
				'failure_count'   => $failure_count,
				'last_failure_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'endpoint' => $endpoint ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		// Check if subscription should be auto-deleted.
		$max_failures        = \Notification_Master\Settings::get_setting( 'webpush_auto_delete_failure_threshold', 5 );
		$auto_delete_enabled = \Notification_Master\Settings::get_setting( 'webpush_enable_auto_delete_failed_subscriptions', false );

		if ( $auto_delete_enabled && $failure_count >= $max_failures ) {
			self::delete_by_endpoint( $endpoint );

			// Log the auto-deletion.
			if ( function_exists( 'Notification_Master' ) ) {
				\Notification_Master\Notification_Master()->logger->info(
					'webpush',
					sprintf(
						/* translators: %1$s: endpoint, %2$d: failure count */
						__( 'Auto-deleted subscription %1$s after %2$d failed attempts.', 'notification-master' ),
						$endpoint,
						$failure_count
					)
				);
			}
		}

		return $updated !== false;
	}

	/**
	 * Record notification success for a subscription (resets failure count).
	 *
	 * @since 1.5.0
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return bool
	 */
	public static function record_success( $endpoint ) {
		global $wpdb;

		$updated = $wpdb->update(
			"{$wpdb->prefix}ntfm_subscriptions",
			array(
				'failure_count'   => 0,
				'last_failure_at' => null,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'endpoint' => $endpoint ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $updated !== false;
	}

	/**
	 * Delete subscription by endpoint.
	 *
	 * @since 1.5.0
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return bool
	 */
	public static function delete_by_endpoint( $endpoint ) {
		global $wpdb;

		$deleted = $wpdb->delete(
			"{$wpdb->prefix}ntfm_subscriptions",
			array( 'endpoint' => $endpoint ),
			array( '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $deleted !== false;
	}

	/**
	 * Get subscriptions with high failure counts for monitoring.
	 *
	 * @since 1.5.0
	 *
	 * @param int $threshold Failure count threshold.
	 *
	 * @return array
	 */
	public static function get_high_failure_subscriptions( $threshold = 3 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ntfm_subscriptions WHERE failure_count >= %d AND status = 'subscribed' ORDER BY failure_count DESC, last_failure_at DESC",
				$threshold
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $results;
	}

	/**
	 * Clean up old failed subscriptions.
	 *
	 * @since 1.5.0
	 *
	 * @param int $days Number of days to keep failed subscriptions.
	 *
	 * @return int Number of deleted subscriptions.
	 */
	public static function cleanup_old_failed_subscriptions( $days = 30 ) {
		global $wpdb;

		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$max_failures   = \Notification_Master\Settings::get_setting( 'webpush_auto_delete_failure_threshold', 5 );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ntfm_subscriptions WHERE failure_count >= %d AND last_failure_at <= %s",
				$max_failures,
				$date_threshold
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed.

		return $deleted;
	}
}
