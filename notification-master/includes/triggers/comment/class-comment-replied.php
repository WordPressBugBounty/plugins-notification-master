<?php
/**
 * Comment Replied Trigger
 *
 * This class is responsible for loading the Comment Replied trigger.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Comment;

use Notification_Master\Abstracts\Comment_Trigger;

/**
 * Comment Replied class.
 */
class Comment_Replied extends Comment_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Replied';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'replied';

	/**
	 * Parent comment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Comment|null
	 */
	public $comment_parent;

	/**
	 * Constructor.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		parent::__construct( $comment_type, $comment_type_name );
		$this->hook = 'wp_insert_comment';
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Get description.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/* translators: %s: comment type label, e.g. comment. */
		return sprintf( __( 'This trigger fires when someone replies to any %s.', 'notification-master' ), $this->label_in_sentence( $this->comment_type_name ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment Comment.
	 */
	public function process( $comment_id, $comment ) {
		if ( $comment->comment_parent > 0 ) {
			$post                 = get_post( $comment->comment_post_ID );
			$this->comment        = $comment;
			$this->comment_parent = get_comment( $comment->comment_parent );
			$this->post           = $post;
			$this->post_author    = get_userdata( $comment->user_id );

			$this->do_connections();
		}
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		$parent = parent::get_merge_tags();

		return array_merge(
			$parent,
			array( "{$this->comment_type}_parent", "{$this->comment_type}_parent_author" )
		);
	}
}
