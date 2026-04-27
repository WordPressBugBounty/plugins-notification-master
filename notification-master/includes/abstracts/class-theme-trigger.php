<?php
/**
 * Theme Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * Theme Trigger Abstract class.
 */
abstract class Theme_Trigger extends Trigger {

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'theme';

	/**
	 * Theme.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Theme|null
	 */
	public $theme;

	/**
	 * Old theme.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Theme|null
	 */
	public $old_theme;

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Theme name */
		return sprintf( __( 'Theme %s', 'notification-master' ), $this->name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'theme_' . $this->slug;
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
