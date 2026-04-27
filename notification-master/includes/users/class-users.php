<?php
/**
 * Users.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Users;

/**
 * Users class.
 */
class Users {

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Users
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Users
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Users ) ) {
			self::$instance = new Users();
		}

		return self::$instance;
	}

	/**
	 * Get roles options.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_roles_options() {
		$options = array();
		$roles   = $this->get_roles();

		// Add roles.
		foreach ( $roles as $role => $role_data ) {
			// Get count of users with this role.
			$users = get_users(
				array(
					'role'   => $role,
					'fields' => 'ID',
				)
			);
			$count = count( $users );

			$options[ $role ] = array(
				'label'       => "{$role_data['name']} ({$count} users)",
				// translators: %1$s: role name, %2$d: users count.
				'description' => sprintf( __( 'All users with the role %1$s. (%2$d)', 'notification-master' ), $role_data['name'], $count ),
			);
		}

		return $options;
	}

	/**
	 * Get roles.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_roles() {
		// Get all users roles.
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		$roles = $wp_roles->roles;

		return $roles;
	}

	/**
	 * Get users emails by role.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Role.
	 *
	 * @return array
	 */
	public function get_users_emails_by_role( $role ) {
		$users = get_users(
			array(
				'role'   => $role,
				'fields' => array( 'user_email' ),
			)
		);

		$emails = array();
		foreach ( $users ?? array() as $user ) {
			$emails[] = $user->user_email;
		}

		return $emails;
	}
}
