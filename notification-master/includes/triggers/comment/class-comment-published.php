<?php
/**
 * Class Comment_Published
 *
 * This class is responsible for triggering notifications when a comment is published.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Comment;

use Notification_Master\Abstracts\Comment_Trigger;

/**
 * Comment Published class.
 */
class Comment_Published extends Comment_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Published';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'published';

	/**
	 * Constructor.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		parent::__construct( $comment_type, $comment_type_name );
		$this->hook = "comment_approved_{$comment_type}"; // "comment_approved_comment"
		add_action( $this->hook, array( $this, 'process' ), 10, 2 );
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/* translators: %s Comment type name */
		return sprintf( 'When a %s is published.', $this->comment_type_name );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment Comment object.
	 */
	public function process( $comment_id, $comment ) {
		$post              = get_post( $comment->comment_post_ID );
		$this->comment     = $comment;
		$this->post        = $post;
		$this->post_author = get_userdata( $comment->user_id );

		$this->do_connections();
	}
}
