<?php
/**
 * Class Post_User trait
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Post_Type\Traits;


/**
 * Post User trait.
 */
trait Post_User {

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_post_user_merge_tags_{$this->post_type}",
			array(
				'id'           => array(
					'name'        => __( 'ID', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The ID of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'display_name' => array(
					'name'        => __( 'Display Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The display name of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'user_login'   => array(
					'name'        => __( 'Username', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The username of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'email'        => array(
					'name'        => __( 'Email', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The email of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'firstname'    => array(
					'name'        => __( 'First Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The first name of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'lastname'     => array(
					'name'        => __( 'Last Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The last name of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'nickname'     => array(
					'name'        => __( 'Nickname', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The nickname of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'avatar'       => array(
					'name'        => __( 'Avatar', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The avatar of the %s user.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
			)
		);
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Tag.
	 *
	 * @return mixed
	 */
	public function get_value( $tag ) {
		if ( ! $this->user ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->user->ID;
			case 'display_name':
				return $this->user->display_name;
			case 'user_login':
				return $this->user->user_login;
			case 'email':
				return $this->user->user_email;
			case 'firstname':
				return $this->user->first_name;
			case 'lastname':
				return $this->user->last_name;
			case 'nickname':
				return $this->user->nickname;
			case 'avatar':
				$avatar_url = get_avatar_url( $this->user->user_email, array( 'size' => 32 ) );
				return $avatar_url;
		}
	}
}
