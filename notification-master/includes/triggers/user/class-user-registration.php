<?php
/**
 * User Registration
 *
 * This class is responsible for triggering notifications when a user is registered.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Registration class.
 */
class User_Registration extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Registration';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'registration';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user is registered.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'user_register';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
		add_filter( 'ntfm_user_merge_tags', array( $this, 'add_merge_tags' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $user_data User data.
	 *
	 * @return void
	 */
	public function process( $user_id, $user_data ) {
		$this->user      = get_userdata( $user_id );
		$this->user_meta = get_user_meta( $user_id );

		$this->do_connections();
	}

	/**
	 * Add merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param array $merge_tags Merge tags.
	 *
	 * @return array
	 */
	public function add_merge_tags( $merge_tags ) {
		$merge_tags['set_password_link'] = array(
			'label'       => __( 'Password Set Link', 'notification-master' ),
			'description' => __( 'The link to set the user\'s password.', 'notification-master' ),
			'callback'    => array( $this, 'get_set_password_link' ),
			'trigger'     => $this->get_slug(),
		);

		return $merge_tags;
	}

	/**
	 * Get password set link.
	 *
	 * @since 1.0.0
	 *
	 * @param Merge_Tag_Group $merge_tag_group Group.
	 *
	 * @return string
	 */
	public function get_set_password_link( $merge_tag_group ) {
		if ( ! $merge_tag_group->user ) {
			return '';
		}

		add_filter( 'allow_password_reset', '__return_true', 99 );
		$user = $merge_tag_group->user;
		$url  = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => get_password_reset_key( $user ),
				'login'  => rawurlencode( $user->user_login ),
			),
			wp_login_url()
		);
		remove_filter( 'allow_password_reset', '__return_true', 99 );

		return $url;
	}
}
