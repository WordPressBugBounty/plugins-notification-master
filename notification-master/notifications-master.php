<?php
/**
 * Plugin Name: Notification Master
 *
 * Description: Enhance user engagement. Trigger notifications for events, support multiple channels like email and Discord, and personalize with dynamic merge tags. Easy setup and customization.
 *
 * Version: 1.6.9
 *
 * Author: Notification Master
 *
 * Author URI: https://notification-master.com
 *
 * Text Domain: notification-master
 *
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0 or later
 *
 * @package notification-master
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define notification-master constants.
define( 'NOTIFICATION_MASTER_VERSION', '1.6.9' );
define( 'NOTIFICATION_MASTER_FILE', __FILE__ );
define( 'NOTIFICATION_MASTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOTIFICATION_MASTER_URL', plugin_dir_url( __FILE__ ) );
define( 'NOTIFICATION_MASTER_BASENAME', plugin_basename( __FILE__ ) );
define( 'NOTIFICATION_MASTER_SITE', 'https://notification-master.com' );

// Action scheduler.
require_once NOTIFICATION_MASTER_DIR . 'includes/libraries/load.php';

// autoload.
require_once NOTIFICATION_MASTER_DIR . 'includes/autoload.php';


Notification_Master\Plugin::get_instance();

// Load plugin.
add_action(
	'plugins_loaded',
	function () {
		do_action( 'notification_master_loaded' );
	}
);
