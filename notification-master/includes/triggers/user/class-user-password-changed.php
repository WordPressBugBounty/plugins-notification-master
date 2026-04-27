<?php
/**
 * User Password Changed.
 *
 * This class is responsible for triggering notifications when a user changes their password.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Password Changed class.
 */
class User_Password_Changed extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Password Changed';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'password_changed';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user changes their password.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'password_reset';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user WP_User object.
	 * @param string   $new_pass New password.
	 *
	 * @return void
	 */
	public function process( $user, $new_pass ) {
		$this->user      = $user;
		$this->user_meta = get_user_meta( $user->ID );
		$this->do_connections();
	}
}
