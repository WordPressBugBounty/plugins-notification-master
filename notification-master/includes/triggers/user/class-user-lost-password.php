<?php
/**
 * User Lost Password.
 *
 * This class is responsible for triggering notifications when a user loses their password.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Lost Password class.
 */
class User_Lost_Password extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Lost Password';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'lost_password';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user loses their password.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'lostpassword_post';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error      $errors WP_Error object.
	 * @param \WP_User|false $user WP_User object or false.
	 *
	 * @return void
	 */
	public function process( $errors, $user ) {
		if ( $user ) {
			$this->user      = $user;
			$this->user_meta = get_user_meta( $user->ID );

			$this->do_connections();
		}
	}
}
