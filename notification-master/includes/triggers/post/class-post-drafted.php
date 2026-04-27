<?php
/**
 * Class Post_Drafted
 *
 * This class is responsible for triggering notifications when a post is drafted.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Drafted class.
 */
class Post_Drafted extends Post_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Drafted';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'drafted';

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
		$this->hook = "draft_{$post_type}";
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
		/* translators: %s: Trigger name */
		return sprintf( 'This trigger fires when a %s is drafted.', $this->get_name() );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 *
	 * @return void
	 */
	public function process( $post_id, $post ) {
		if ( ! $this->is_post_type( $post ) ) {
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
