<?php
/**
 * Class Subscriptions_Table
 *
 * @package notification-master
 *
 * @since 1.4.0
 */

namespace Notification_Master\DB\Tables;

use Notification_Master\Abstracts\DB_Table;

/**
 * Class Subscriptions_Table
 *
 * @package notification-master
 *
 * @since 1.4.0
 */
class Subscriptions_Table extends DB_Table {

	/**
	 * Table name
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	public $table_name = 'ntfm_subscriptions';

	/**
	 * Get table columns
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	/**
	 * Get Columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'id',
			'user_id',
			'ip_address',
			'device',
			'operating_system',
			'browser',
			'user_agent',
			'endpoint',
			'auth',
			'p256dh',
			'status',
			'failure_count',
			'last_failure_at',
			'created_at',
			'updated_at',
		);
	}

	/**
	 * Get create table query.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public function get_create_table_query() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			user_agent VARCHAR(255) NULL DEFAULT NULL,
			browser VARCHAR(255) NULL DEFAULT NULL,
			operating_system VARCHAR(255) NULL DEFAULT NULL,
			ip_address VARCHAR(255) NULL DEFAULT NULL,
			device VARCHAR(255) NULL DEFAULT NULL,
            `endpoint` VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
			`status` VARCHAR(255) NULL DEFAULT 'subscribed',
			failure_count INT(11) NULL DEFAULT 0,
			last_failure_at TIMESTAMP NULL DEFAULT NULL,
			expiration_time TIMESTAMP NULL DEFAULT NULL,
			content_encoding VARCHAR(255) NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY `endpoint` (`endpoint`),
            KEY `status` (`status`),
            KEY `failure_count` (`failure_count`)
        ) $charset_collate;";

		return $query;
	}

	/**
	 * Add status column.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function add_status_column() {
		global $wpdb;

		// Check if column exists.
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}{$this->table_name} LIKE 'status'" );
		if ( ! empty( $column_exists ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$this->table_name} ADD status VARCHAR(255) NULL DEFAULT 'subscribed' AFTER p256dh" );
	}

	/**
	 * Add failure tracking columns.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function add_failure_tracking_columns() {
		global $wpdb;

		// Check if failure_count column exists.
		$failure_count_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}{$this->table_name} LIKE 'failure_count'" );
		if ( empty( $failure_count_exists ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$this->table_name} ADD failure_count INT(11) NULL DEFAULT 0 AFTER status" );
		}

		// Check if last_failure_at column exists.
		$last_failure_at_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}{$this->table_name} LIKE 'last_failure_at'" );
		if ( empty( $last_failure_at_exists ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$this->table_name} ADD last_failure_at TIMESTAMP NULL DEFAULT NULL AFTER failure_count" );
		}

		// Add indexes if they don't exist.
		$indexes     = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}{$this->table_name}" );
		$index_names = wp_list_pluck( $indexes, 'Key_name' );

		if ( ! in_array( 'status', $index_names, true ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$this->table_name} ADD KEY `status` (`status`)" );
		}

		if ( ! in_array( 'failure_count', $index_names, true ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}{$this->table_name} ADD KEY `failure_count` (`failure_count`)" );
		}
	}
}
