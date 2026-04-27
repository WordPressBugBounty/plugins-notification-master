<?php
/**
 * Plugin Deactivated
 *
 * This class is responsible for triggering notifications when a plugin is deactivated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Plugin;

use Notification_Master\Abstracts\Plugin_Trigger;

/**
 * Plugin Deactivated class.
 */
class Plugin_Deactivated extends Plugin_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Deactivated';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'deactivated';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'deactivated_plugin';
		add_action( $this->hook, array( $this, 'process' ), 99, 2 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin Plugin path.
	 * @param bool   $network_wide Network wide.
	 * @return void
	 */
	public function process( $plugin, $network_wide ) {
		$this->plugin = $this->get_plugin_data( $plugin );
		$this->do_connections();
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger fires when a plugin is deactivated.', 'notification-master' );
	}
}
