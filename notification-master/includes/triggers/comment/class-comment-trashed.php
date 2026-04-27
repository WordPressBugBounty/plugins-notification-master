<?php
/**
 * Class Comment_Trashed
 *
 * This class is responsible for triggering notifications when a comment is trashed.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Comment;

use Notification_Master\Abstracts\Comment_Trigger;

/**
 * Comment Trashed class.
 */
class Comment_Trashed extends Comment_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Trashed';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'trashed';

	/**
	 * Constructor.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		parent::__construct( $comment_type, $comment_type_name );
		$this->hook = 'trash_comment';
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
		return sprintf( __( 'When a %s - comment is trashed.', 'notification-master' ), $this->get_name() );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id Comment ID.
	 * @param object $comment Comment object.
	 */
	public function process( $comment_id, $comment ) {
		if ( ! $this->is_comment_type( $comment ) ) {
			return;
		}

		$post              = get_post( $comment->comment_post_ID );
		$this->comment     = $comment;
		$this->post        = $post;
		$this->post_author = get_userdata( $comment->user_id );

		$this->do_connections();
	}
}
