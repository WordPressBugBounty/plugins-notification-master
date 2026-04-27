<?php
/**
 * Class Comment_Approved
 *
 * This class is responsible for triggering notifications when a comment is approved.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Comment;

use Notification_Master\Abstracts\Comment_Trigger;

/**
 * Comment Approved class.
 */
class Comment_Approved extends Comment_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Approved';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'approved';

	/**
	 * Constructor.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		parent::__construct( $comment_type, $comment_type_name );
		$this->hook = 'transition_comment_status';
		add_action( $this->hook, array( $this, 'process' ), 10, 3 );
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/* translators: %s: Comment type name */
		return sprintf( 'When a %s comment is approved.', $this->comment_type_name );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment Comment object.
	 */
	public function process( $new_status, $old_status, $comment ) {
		if ( 'approved' === $new_status && 'approved' !== $old_status ) {
			$post              = get_post( $comment->comment_post_ID );
			$this->comment     = $comment;
			$this->post        = $post;
			$this->post_author = get_userdata( $comment->user_id );

			$this->do_connections();
		}
	}
}
