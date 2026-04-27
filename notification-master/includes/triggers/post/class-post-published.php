<?php
/**
 * Class Post_Published
 *
 * This class is responsible for triggering notifications when a post is published.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Published class.
 */
class Post_Published extends Post_Trigger {

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
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->hook = "publish_{$post_type}"; // "publish_post
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
		/* translators: %s: Trigger name */
		return sprintf( __( 'This trigger will send a notification when a %s is published.', 'notification-master' ), $this->get_name() );
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
			array( "{$this->post_type}_publishing_user" )
		);
	}

	/**
	 * Process trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param string $old_status Old status.
	 *
	 * @return void
	 */
	public function process( $post_id, $post, $old_status ) {
		if ( ! $this->is_post_type( $post ) ) {
			return;
		}

		// Only fire when a post is newly published, not for updates to already published posts
		if ( 'publish' === $old_status ) {
			return;
		}

		// Don't fire when a post is published from a 'pending' status - that's handled by Post_Approved
		if ( 'pending' === $old_status ) {
			return;
		}

		// Check if this post has already been processed by this trigger
		if ( $this->is_post_processed( $post_id ) ) {
			return;
		}

		// Schedule a delayed notification to ensure we get fresh data
		$this->schedule_delayed_notification( $post_id );
	}
}
