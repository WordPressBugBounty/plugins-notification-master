<?php
/**
 * Class Plugin_Updated
 *
 * This class is responsible for triggering notifications when a plugin is updated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Plugin;

use Notification_Master\Abstracts\Plugin_Trigger;

/**
 * Plugin Updated class.
 */
class Plugin_Updated extends Plugin_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Updated';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'updated';

	/**
	 * Old version.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $old_version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'upgrader_process_complete';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
		add_filter( 'ntfm_plugin_merge_tags', array( $this, 'add_merge_tags' ) );
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
		if ( 'plugin' === $options['type'] && 'update' === $options['action'] ) {
			$plugin_info          = $upgrader->plugin_info();
			$skin                 = $upgrader->skin;
			$plugin               = $this->get_plugin_data( $plugin_info );
			$plugin['OldVersion'] = $skin->plugin_info['Version'];
			$this->plugin         = $plugin;
			$this->do_connections();
		}
	}

	/**
	 * Add merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $merge_tags Merge tags.
	 * @param string $trigger Trigger.
	 *
	 * @return array
	 */
	public function add_merge_tags( $merge_tags, $trigger = '' ) {
		$merge_tags['old_version'] = array(
			'label'       => __( 'Old Version', 'notification-master' ),
			'description' => __( 'The old version of the plugin.', 'notification-master' ),
			'callback'    => function ( $merge_tag_group ) {
				return $merge_tag_group->trigger->plugin['OldVersion'] ?? '';
			},
			'trigger'     => $this->get_slug(),
		);

		return $merge_tags;
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger fires when a plugin is updated.', 'notification-master' );
	}
}
