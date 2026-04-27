<?php
/**
 * Class Theme_Installed
 *
 * This class is responsible for triggering notifications when a theme is installed.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Theme;

use Notification_Master\Abstracts\Theme_Trigger;

/**
 * Theme Installed class.
 */
class Theme_Installed extends Theme_Trigger {

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
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger will be fired when a theme is installed.', 'notification-master' );
	}

	/**
	 * Process.
	 *
	 * @param \Theme_Upgrader $upgrader_object Upgrader object.
	 * @param array           $options Options.
	 */
	public function process( $upgrader_object, $options ) {
		if ( 'theme' === $options['type'] && 'install' === $options['action'] ) {
			$theme       = $upgrader_object->theme_info();
			$this->theme = $theme;

			$this->do_connections();
		}
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
			'theme',
		);
	}
}
