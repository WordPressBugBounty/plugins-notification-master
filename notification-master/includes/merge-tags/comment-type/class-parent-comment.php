<?php
/**
 * Parent Comment Merge Tags Group
 *
 * This class is responsible for loading the Comment Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Comment_Type;

use Notification_Master\Abstracts\Comment_Merge_Tags_Group;

/**
 * Parent Comment Merge Tags Group class.
 */
class Parent_Comment extends Comment_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Comment type name */
		return sprintf( __( 'Parent %s', 'notification-master' ), $this->comment_type_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->comment_type . '_parent';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_comment_parent_merge_tags_{$this->comment_type}",
			array(
				'id'       => array(
					'name'        => __( 'ID', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The ID of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'content'  => array(
					'name'        => __( 'Content', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The content of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'status'   => array(
					'name'        => __( 'Status', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The status of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'type'     => array(
					'name'        => __( 'Type', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The type of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
				'datetime' => array(
					'name'        => __( 'Date Time', 'notification-master' ),
					/* translators: %s: Comment type name */
					'description' => sprintf( __( 'The date time of the parent %s.', 'notification-master' ), $this->comment_type_name ),
				),
			)
		);
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
		if ( ! $this->parent_comment ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->parent_comment->comment_ID;
			case 'content':
				return $this->parent_comment->comment_content;
			case 'status':
				$approved_status = $this->parent_comment->comment_approved;
				if ( '1' === $approved_status ) {
					return __( 'Approved', 'notification-master' );
				} elseif ( '0' === $approved_status ) {
					return __( 'Pending', 'notification-master' );
				} elseif ( 'spam' === $approved_status ) {
					return __( 'Spam', 'notification-master' );
				} elseif ( 'trash' === $approved_status ) {
					return __( 'Trash', 'notification-master' );
				} else {
					return __( 'Unknown', 'notification-master' );
				}
			case 'type':
				return $this->parent_comment->comment_type;
			case 'datetime':
				return $this->parent_comment->comment_date;
			default:
				return '';
		}
	}
}
