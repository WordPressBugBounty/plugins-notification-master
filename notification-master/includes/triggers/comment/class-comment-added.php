<?php
/**
 * Class Comment_Added
 *
 * This class is responsible for triggering notifications when a comment is added.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Comment;

use Notification_Master\Abstracts\Comment_Trigger;

/**
 * Comment Added class.
 */
class Comment_Added extends Comment_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Added';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'added';

	/**
	 * Constructor.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		parent::__construct( $comment_type, $comment_type_name );
		$this->hook = 'wp_insert_comment'; // "publish_comment
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
		/* translators: %s: Comment type name */
		return sprintf( __( 'When a %s - comment is added.', 'notification-master' ), $this->get_name() );
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
		$parent = absint( $comment->comment_parent );
		if ( ! $this->is_comment_type( $comment ) || 0 !== $parent ) {
			return;
		}

		$post              = get_post( $comment->comment_post_ID );
		$this->comment     = $comment;
		$this->post        = $post;
		$this->post_author = get_userdata( $comment->user_id );

		$this->do_connections();
	}
}
