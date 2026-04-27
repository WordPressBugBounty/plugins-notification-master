<?php
/**
 * Class Plugin_Installed
 *
 * This class is responsible for triggering notifications when a plugin is installed.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Plugin;

use Notification_Master\Abstracts\Plugin_Trigger;

/**
 * Plugin Installed class.
 */
class Plugin_Installed extends Plugin_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Installed';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'installed';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'upgrader_process_complete';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param \Plugin_Upgrader $upgrader Object of Plugin_Upgrader.
	 * @param array            $options Options.
	 */
	public function process( $upgrader, $options ) {
		if ( 'plugin' === $options['type'] && 'install' === $options['action'] ) {
			$plugin_info  = $upgrader->plugin_info();
			$this->plugin = $this->get_plugin_data( $plugin_info );
			$this->do_connections();
		}
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger fires when a plugin is installed.', 'notification-master' );
	}
}
