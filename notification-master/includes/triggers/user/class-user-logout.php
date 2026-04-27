<?php
/**
 * User Logout.
 *
 * This class is responsible for triggering notifications when a user logs out.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Logout class.
 */
class User_Logout extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Logout';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'logout';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user logs out.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'wp_logout';
		add_action( $this->hook, array( $this, 'process' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function process( $user_id ) {
		$this->user      = get_userdata( $user_id );
		$this->user_meta = get_user_meta( $user_id );
		$this->do_connections();
	}
}
