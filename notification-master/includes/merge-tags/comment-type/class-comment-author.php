<?php
/**
 * Comment Author Merge Tags Group
 *
 * This class is responsible for loading the Comment Author Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Comment_Type;

use Notification_Master\Abstracts\Comment_Merge_Tags_Group;

/**
 * Comment Author Merge Tags Group class.
 */
class Comment_Author extends Comment_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		// For comment type name
		/* translators: %s: Comment type name */
		return sprintf( __( '%s Author', 'notification-master' ), $this->comment_type_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->comment_type . '_author';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_comment_author_merge_tags_{$this->comment_type}",
			array(
				'ip'     => array(
					'name'        => __( 'Author IP', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The IP of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'agent'  => array(
					'name'        => __( 'Author Agent', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The agent of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'id'     => array(
					'name'        => __( 'Author ID', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The ID of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'name'   => array(
					'name'        => __( 'Author Name', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The name of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'email'  => array(
					'name'        => __( 'Author Email', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The email of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'url'    => array(
					'name'        => __( 'Author URL', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The URL of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'avatar' => array(
					'name'        => __( 'Author Avatar URL', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The avatar of the author of the %s.', 'notification-master' ), $this->comment_type_name ),
				),
			)
		);
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge Tag slug.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( ! $this->comment ) {
			return '';
		}

		switch ( $tag ) {
			case 'ip':
				return $this->comment->comment_author_IP;
			case 'agent':
				return $this->comment->comment_agent;
			case 'id':
				return $this->comment->user_id;
			case 'name':
				return $this->comment->comment_author;
			case 'email':
				return $this->comment->comment_author_email;
			case 'url':
				return $this->comment->comment_author_url;
			case 'avatar':
				return get_avatar_url( $this->comment->comment_author_email, 32 );
		}
	}
}
