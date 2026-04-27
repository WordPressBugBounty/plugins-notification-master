<?php
/**
 * Class Post_Added
 *
 * This class is responsible for triggering notifications when a post is added to the database.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Added class.
 */
class Post_Added extends Post_Trigger {

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
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'wp_insert_post';

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
		return sprintf( 'This trigger fires when a %s is inserted into the database.', $this->post_type );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post   Post object.
	 * @param bool     $update Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function process( $post_id, $post, $update ) {
		// Only process new posts and ensure correct post type
		if ( $update || ! $this->is_post_type( $post ) ) {
			return;
		}

		// Don't fire for auto-draft posts or when a post is directly published
		if ( 'auto-draft' === $post->post_status || 'publish' === $post->post_status ) {
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
