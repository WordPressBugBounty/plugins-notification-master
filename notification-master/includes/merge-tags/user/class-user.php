<?php
/**
 * User Merge Tags Group
 *
 * This class is responsible for loading the User Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\User;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * User Merge Tags Group class.
 */
class User extends Merge_Tags_Group {

	/**
	 * User.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User
	 */
	public $user;

	/**
	 * User meta.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $user_meta;

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'User', 'notification-master' );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'user';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_user_merge_tags',
			array(
				'id'           => array(
					'label'       => __( 'User ID', 'notification-master' ),
					'description' => __( 'The ID of the user.', 'notification-master' ),
				),
				'username'     => array(
					'label'       => __( 'Username', 'notification-master' ),
					'description' => __( 'The username of the user.', 'notification-master' ),
				),
				'email'        => array(
					'label'       => __( 'Email', 'notification-master' ),
					'description' => __( 'The email address of the user.', 'notification-master' ),
				),
				'display_name' => array(
					'label'       => __( 'Display Name', 'notification-master' ),
					'description' => __( 'The display name of the user.', 'notification-master' ),
				),
				'first_name'   => array(
					'label'       => __( 'First Name', 'notification-master' ),
					'description' => __( 'The first name of the user.', 'notification-master' ),
				),
				'last_name'    => array(
					'label'       => __( 'Last Name', 'notification-master' ),
					'description' => __( 'The last name of the user.', 'notification-master' ),
				),
				'bio'          => array(
					'label'       => __( 'Bio', 'notification-master' ),
					'description' => __( 'The bio of the user.', 'notification-master' ),
				),
				'avatar'       => array(
					'label'       => __( 'Avatar', 'notification-master' ),
					'description' => __( 'The avatar of the user.', 'notification-master' ),
				),
				'role'         => array(
					'label'       => __( 'Role', 'notification-master' ),
					'description' => __( 'The role of the user.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->user      = $trigger->user ?? null;
		$this->user_meta = $trigger->user_meta ?? null;
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( empty( $this->user ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->user->ID;
			case 'username':
				return $this->user->user_login;
			case 'email':
				return $this->user->user_email;
			case 'display_name':
				return $this->user->display_name;
			case 'avatar':
				$avatar_url = get_avatar_url( $this->user->ID, array( 'size' => 32 ) );
				return $avatar_url;
			case 'role':
				return $this->user->roles[0];
			case 'first_name':
				return $this->user->first_name;
			case 'last_name':
				return $this->user->last_name;
			case 'bio':
				return $this->user->description;
			default:
				return '';
		}
	}
}
