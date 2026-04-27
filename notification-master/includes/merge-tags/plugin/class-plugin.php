<?php
/**
 * Plugin Merge Tags Group
 *
 * This class is responsible for loading the Plugin Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Plugin;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Plugin Merge Tags Group class.
 */
class Plugin extends Merge_Tags_Group {

	/**
	 * Plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Plugin|null
	 */
	public $plugin;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Plugin';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'plugin';

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_plugin_merge_tags',
			array(
				'name'        => array(
					'label'       => __( 'Name', 'notification-master' ),
					'description' => __( 'The name of the plugin.', 'notification-master' ),
				),
				'version'     => array(
					'label'       => __( 'Version', 'notification-master' ),
					'description' => __( 'The version of the plugin.', 'notification-master' ),
				),
				'author'      => array(
					'label'       => __( 'Author', 'notification-master' ),
					'description' => __( 'The author of the plugin.', 'notification-master' ),
				),
				'author_uri'  => array(
					'label'       => __( 'Author URI', 'notification-master' ),
					'description' => __( 'The author URI of the plugin.', 'notification-master' ),
				),
				'description' => array(
					'label'       => __( 'Description', 'notification-master' ),
					'description' => __( 'The description of the plugin.', 'notification-master' ),
				),
				'plugin_uri'  => array(
					'label'       => __( 'Plugin URI', 'notification-master' ),
					'description' => __( 'The plugin URI of the plugin.', 'notification-master' ),
				),
				'text_domain' => array(
					'label'       => __( 'Text Domain', 'notification-master' ),
					'description' => __( 'The text domain of the plugin.', 'notification-master' ),
				),
				'domain_path' => array(
					'label'       => __( 'Domain Path', 'notification-master' ),
					'description' => __( 'The domain path of the plugin.', 'notification-master' ),
				),
				'file'        => array(
					'label'       => __( 'File', 'notification-master' ),
					'description' => __( 'The file of the plugin.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->plugin = $trigger->plugin ?? null;
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( empty( $this->plugin ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'name':
				return $this->plugin['Name'];
			case 'version':
				return $this->plugin['Version'];
			case 'author':
				return $this->plugin['Author'];
			case 'author_uri':
				return $this->plugin['AuthorURI'];
			case 'description':
				return $this->plugin['Description'];
			case 'plugin_uri':
				return $this->plugin['PluginURI'];
			case 'text_domain':
				return $this->plugin['TextDomain'];
			case 'domain_path':
				return $this->plugin['DomainPath'];
			default:
				return '';
		}
	}
}
