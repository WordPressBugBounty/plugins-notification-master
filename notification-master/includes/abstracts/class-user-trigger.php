<?php
/**
 * User Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * User Trigger Abstract class.
 */
abstract class User_Trigger extends Trigger {

	/**
	 * User.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User
	 */
	public $user;

	/**
	 * User meta.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $user_meta;

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'user';

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: User name */
		return sprintf( __( 'User %s', 'notification-master' ), $this->name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'user_' . $this->slug;
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
			'user',
			'user_acf_fields',
		);
	}
}
