<?php
/**
 * Notification Logs table.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\DB\Tables;

use Notification_Master\Abstracts\DB_Table;

/**
 * Notification Logs table.
 */
class Notification_Logs_Table extends DB_Table {

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $table_name = 'ntfm_notification_logs';

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
			'integration',
			'status',
			'content',
			'created_at',
			'updated_at',
		);
	}

	/**
	 * Get create table query.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_create_table_query() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$this->table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                integration VARCHAR(255) NOT NULL,
                `status` VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY integration (integration),
                KEY `status` (`status`)
            ) $charset_collate;";

		return $query;
	}
}
