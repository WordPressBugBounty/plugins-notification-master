<?php
/**
 * Class Post_Scheduled
 *
 * This class is responsible for triggering notifications when a post is scheduled.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Scheduled class.
 */
class Post_Scheduled extends Post_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Scheduled';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'scheduled';

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'transition_post_status';

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
		return sprintf( __( 'When a %s is scheduled.', 'notification-master' ), $this->get_name() );
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
			array( "{$this->post_type}_scheduling_user" )
		);
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param object $post Post object.
	 *
	 * @return void
	 */
	public function process( $new_status, $old_status, $post ) {
		if ( ! $this->is_post_type( $post ) ) {
			return;
		}

		if ( 'future' !== $new_status || 'future' === $old_status ) {
			return;
		}

		// Check if this post has already been processed by this trigger
		if ( $this->is_post_processed( $post->ID ) ) {
			return;
		}

		// Mark this post as processed by this trigger
		$this->mark_post_as_processed( $post->ID );

		$this->post             = $post;
		$this->post_author      = get_userdata( $post->post_author );
		$last_editor_id         = get_post_meta( $post->ID, '_edit_last', true );
		$editor_id              = $last_editor_id ? intval( $last_editor_id ) : $post->post_author;
		$this->post_last_editor = get_userdata( $editor_id );
		$this->current_user     = wp_get_current_user();

		$this->do_connections();
	}
}
