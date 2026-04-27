<?php
/**
 * User Deleted.
 *
 * This class is responsible for triggering notifications when a user is deleted.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Deleted class.
 */
class User_Deleted extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Deleted';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'deleted';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user is deleted.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'deleted_user';
		add_action( $this->hook, array( $this, 'process' ), 10, 3 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $user_id User ID.
	 * @param int      $reassign Reassign user ID.
	 * @param \WP_User $user WP_User object.
	 *
	 * @return void
	 */
	public function process( $user_id, $reassign, $user ) {
		$this->user      = $user;
		$this->user_meta = get_user_meta( $user_id );
		$this->do_connections();
	}
}
