<?php
/**
 * Class Theme Switched
 *
 * This class is responsible for triggering notifications when a theme is switched.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Theme;

use Notification_Master\Abstracts\Trigger;

/**
 * Theme Switched class.
 */
class Theme_Switched extends Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Theme Switched';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'theme_switched';

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
		$this->hook = 'switch_theme';
		add_action( $this->hook, array( $this, 'process' ), 10, 3 );
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'This trigger fires when a theme is switched.', 'notification-master' );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $new_name New theme name.
	 * @param \WP_Theme $new_theme New theme object.
	 * @param \WP_Theme $old_theme Old theme object.
	 */
	public function process( $new_name, $new_theme, $old_theme ) {
		$this->theme     = $new_theme;
		$this->old_theme = $old_theme;

		$this->do_connections();
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
			'old_theme',
		);
	}
}
