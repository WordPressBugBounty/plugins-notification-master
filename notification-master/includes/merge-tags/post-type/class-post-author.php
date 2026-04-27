<?php
/**
 * Class Post Author Merge Tags
 *
 * This class is responsible for adding merge tags for post author triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Post_Type;

use Notification_Master\Abstracts\Post_Merge_Tags_Group;

/**
 * Post Author Merge Tags class.
 */
class Post_Author extends Post_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Post type singular name */
		return sprintf( __( '%1s Author', 'notification-master' ), $this->post_type_object->labels->singular_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->post_type . '_author';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_post_author_merge_tags_{$this->post_type}",
			array(
				'id'           => array(
					'name'        => __( 'ID', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The ID of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'display_name' => array(
					'name'        => __( 'Display Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The display name of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'user_login'   => array(
					'name'        => __( 'Username', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The username of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'email'        => array(
					'name'        => __( 'Email', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The email of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'firstname'    => array(
					'name'        => __( 'First Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The first name of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'lastname'     => array(
					'name'        => __( 'Last Name', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The last name of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'nickname'     => array(
					'name'        => __( 'Nickname', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The nickname of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
				),
				'avatar'       => array(
					'name'        => __( 'Avatar', 'notification-master' ),
					/* translators: %s: Post type singular name */
					'description' => sprintf( __( 'The avatar of the %s author.', 'notification-master' ), $this->post_type_object->labels->singular_name ),
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
		if ( ! $this->post_author ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->post_author->ID;
			case 'display_name':
				return $this->post_author->display_name;
			case 'user_login':
				return $this->post_author->user_login;
			case 'email':
				return $this->post_author->user_email;
			case 'firstname':
				return $this->post_author->first_name;
			case 'lastname':
				return $this->post_author->last_name;
			case 'nickname':
				return $this->post_author->nickname;
			case 'avatar':
				$avatar_url = get_avatar_url( $this->post_author->user_email, array( 'size' => 32 ) );
				return $avatar_url;
		}
	}
}
