<?php
/**
 * Class Theme Updated
 *
 * This class is responsible for triggering notifications when a theme is updated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Theme;

use Notification_Master\Abstracts\Trigger;

/**
 * Theme Updated class.
 */
class Theme_Updated extends Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Theme Updated';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'theme_updated';

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'theme';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'upgrader_process_complete';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Get trigger description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger will be fired when a theme is updated.', 'notification-master' );
	}

	/**
	 * Process trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param \Theme_Upgrader $upgrader_object Upgrader object.
	 * @param array           $options         Options.
	 *
	 * @return void
	 */
	public function process( $upgrader_object, $options ) {
		if ( 'update' === $options['action'] && 'theme' === $options['type'] ) {
			$this->theme = $upgrader_object->theme_info();

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
