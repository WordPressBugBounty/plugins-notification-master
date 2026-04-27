<?php
/**
 * Class Post_Updated
 *
 * This class is responsible for triggering notifications when a post is updated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Updated class.
 */
class Post_Updated extends Post_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Updated';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'updated';

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'post_updated';

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
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
		return sprintf( 'When a %s is updated.', $this->get_name() );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post_after    Post object after update.
	 * @param \WP_Post $post_before   Post object before update.
	 *
	 * @return void
	 */
	public function process( $post_id, $post_after, $post_before ) {
		// Ensure it's an update of the correct post type
		if ( ! $this->is_post_type( $post_after ) ) {
			return;
		}

		// Only fire for updates of already published posts
		if ( 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) {
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
