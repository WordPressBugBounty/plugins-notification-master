<?php
/**
 * Class Post_Trashed
 *
 * This class is responsible for triggering notifications when a post is trashed.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Post;

use Notification_Master\Abstracts\Post_Trigger;

/**
 * Post Trashed class.
 */
class Post_Trashed extends Post_Trigger {

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
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'trash_';

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->hook .= $post_type;
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
		return sprintf( __( 'When a %s is trashed.', 'notification-master' ), $this->get_name() );
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
			array( "{$this->post_type}_trashing_user" )
		);
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function process( $post_id, $post ) {
		if ( ! $this->is_post_type( $post ) ) {
			return;
		}

		// Check if this post has already been processed by this trigger
		if ( $this->is_post_processed( $post_id ) ) {
			return;
		}

		// Mark this post as processed by this trigger
		$this->mark_post_as_processed( $post_id );

		$this->post             = $post;
		$this->post_author      = get_userdata( $post->post_author );
		$last_editor_id         = get_post_meta( $post->ID, '_edit_last', true );
		$editor_id              = $last_editor_id ? intval( $last_editor_id ) : $post->post_author;
		$this->post_last_editor = get_userdata( $editor_id );
		$this->current_user     = wp_get_current_user();

		$this->do_connections();
	}
}
