<?php
/**
 * User Login.
 *
 * This class is responsible for triggering notifications when a user logs in.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\User;

use Notification_Master\Abstracts\User_Trigger;

/**
 * User Login class.
 */
class User_Login extends User_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Login';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'login';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description = 'This trigger fires when a user logs in.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'wp_login';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
		add_filter( 'ntfm_user_merge_tags', array( $this, 'add_merge_tags' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $user_login User login.
	 * @param \WP_User $user WP_User object.
	 *
	 * @return void
	 */
	public function process( $user_login, $user ) {
		$this->user      = $user;
		$this->user_meta = get_user_meta( $user->ID );

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
		$merge_tags['ip'] = array(
			'name'        => __( 'IP Address', 'notification-master' ),
			'description' => __( 'The IP address of the user.', 'notification-master' ),
			'callback'    => function () {
				return $this->get_user_ip();
			},
			'trigger'     => $this->get_slug(),
		);

		return $merge_tags;
	}

	/**
	 * Get user IP.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_user_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} else {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
