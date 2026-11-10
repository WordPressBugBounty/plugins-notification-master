<?php
/**
 * User Password Changed.
 *
 * This class is responsible for triggering notifications when a user changes or resets their password.
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
	protected $description = 'This trigger fires when a user changes their password on the profile screen or resets it.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'password_reset';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
		// Profile, admin and wp-cli changes go through wp_update_user(); a reset uses wp_set_password(), so no double send.
		add_action( 'profile_update', array( $this, 'process_profile_update' ), 10, 2 );
	}

	/**
	 * Fires when a profile save changed the password hash.
	 *
	 * @since 2.0.0
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data User before the update.
	 *
	 * @return void
	 */
	public function process_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $old_user_data instanceof \WP_User || $user->user_pass === $old_user_data->user_pass ) {
			return;
		}

		$this->process( $user, '' );
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
