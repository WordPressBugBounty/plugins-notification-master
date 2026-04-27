<?php
/**
 * Class Logs table.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\DB\Tables;

use Notification_Master\Abstracts\DB_Table;

/**
 * Class Logs table.
 */
class Logs_Table extends DB_Table {

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $table_name = 'ntfm_logs';

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
			'type',
			'action',
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
                `type` VARCHAR(255) NOT NULL,
                `action` VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";

		return $query;
	}
}
