<?php
/**
 * Privacy Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * Privacy Trigger Abstract class.
 */
abstract class Privacy_Trigger extends Trigger {

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'privacy';

	/**
	 * User.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $user;

	/**
	 * Archive.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $archive;

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
		);
	}
}
