<?php
/**
 * Class Post Scheduling User Merge Tags
 *
 * This class is responsible for adding merge tags for post user triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Post_Type;

use Notification_Master\Abstracts\Post_Merge_Tags_Group;
use Notification_Master\Merge_Tags\Post_Type\Traits\Post_User;

/**
 * Post Scheduling_User Merge Tags class.
 */
class Post_Scheduling_User extends Post_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s Post type singular name */
		return sprintf( __( '%s Scheduling User', 'notification-master' ), $this->post_type_object->labels->singular_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->post_type . '_scheduling_user';
	}

	use Post_User;
}
