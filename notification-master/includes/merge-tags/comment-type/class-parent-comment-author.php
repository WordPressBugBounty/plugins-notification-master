<?php
/**
 * Class Parent_Comment_Author
 *
 * This class is responsible for triggering notifications when a comment is made on a post by the author of the post.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Comment_Type;

use Notification_Master\Abstracts\Comment_Merge_Tags_Group;

/**
 * Parent Comment Author class.
 */
class Parent_Comment_Author extends Comment_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Comment type name */
		return sprintf( __( 'Parent %s Author', 'notification-master' ), $this->comment_type_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->comment_type . '_parent_author';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_comment_parent_author_merge_tags_{$this->comment_type}",
			array(
				'ip'     => array(
					'name'        => __( 'Author IP', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The IP of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'agent'  => array(
					'name'        => __( 'Author Agent', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The agent of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'name'   => array(
					'name'        => __( 'Author Name', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The name of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'email'  => array(
					'name'        => __( 'Author Email', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The email of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'url'    => array(
					'name'        => __( 'Author URL', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The URL of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'avatar' => array(
					'name'        => __( 'Author Avatar', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The avatar of the author of the parent %s.', 'notification-master' ), $this->comment_type_name ),
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
		if ( ! $this->parent_comment ) {
			return '';
		}

		switch ( $tag ) {
			case 'ip':
				return $this->parent_comment->comment_author_IP;
			case 'agent':
				return $this->parent_comment->comment_agent;
			case 'id':
				return $this->parent_comment->user_id;
			case 'name':
				return $this->parent_comment->comment_author;
			case 'email':
				return $this->parent_comment->comment_author_email;
			case 'url':
				return $this->parent_comment->comment_author_url;
			case 'avatar':
				return get_avatar_url( $this->parent_comment->comment_author_email, 32 );
		}
	}
}
