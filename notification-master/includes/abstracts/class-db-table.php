<?php
/**
 * Abstract class for database table.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

/**
 * Abstract class for database table.
 */
abstract class DB_Table {

	/**
	 * Table name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $table_name;

	/**
	 * Get Columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 * Get create table query.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_create_table_query() {
		return '';
	}

	/**
	 * Create table.
	 *
	 * @since 1.0.0
	 */
	public function create_table() {
		global $wpdb;

		$sql = $this->get_create_table_query();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
