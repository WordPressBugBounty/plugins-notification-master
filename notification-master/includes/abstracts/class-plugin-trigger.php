<?php
/**
 * Plugin Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * Plugin Trigger Abstract class.
 */
abstract class Plugin_Trigger extends Trigger {

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'plugin';

	/**
	 * Plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $plugin;

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Plugin name */
		return sprintf( __( 'Plugin %s', 'notification-master' ), $this->name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'plugin_' . $this->slug;
	}

	/**
	 * Get plugin data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_info Plugin info.
	 *
	 * @return array
	 */
	public function get_plugin_data( $plugin_info ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_info );

		return $plugin_data;
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return array(
			'plugin',
		);
	}
}
