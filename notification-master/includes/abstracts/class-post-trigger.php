<?php
/**
 * Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;
use Notification_Master\Delayed_Notifications;

/**
 * Trigger Abstract class.
 */
abstract class Post_Trigger extends Trigger {

	/**
	 * Post type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * Post Object.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Post_Type
	 */
	public $post_type_object;

	/**
	 * Post.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Post|null
	 */
	public $post;

	/**
	 * Post author.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $post_author;

	/**
	 * Post last editor.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $post_last_editor;

	/**
	 * Current user.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $current_user;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		$this->post_type        = $post_type;
		$this->post_type_object = get_post_type_object( $this->post_type );
		parent::__construct();
	}

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->post_type_object->labels->singular_name . ' ' . $this->name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->post_type_object->name . '-' . $this->slug;
	}

	/**
	 * Get group.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_group() {
		return $this->post_type;
	}

	/**
	 * Is post type.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool
	 */
	public function is_post_type( $post ) {
		return $this->post_type === $post->post_type && ! wp_is_post_autosave( $post ) && ! wp_is_post_revision( $post );
	}

	/**
	 * Check if a post has already been processed by this trigger.
	 * Uses transient to track across hook calls in the same request.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return bool True if post has been processed, false otherwise.
	 */
	protected function is_post_processed( $post_id ) {
		$transient_key = 'ntfm_processed_post_' . $post_id . '_' . $this->get_slug();
		return (bool) get_transient( $transient_key );
	}

	/**
	 * Mark a post as processed by this trigger.
	 * Uses transient with a very short expiration to persist only for the current request.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function mark_post_as_processed( $post_id ) {
		$transient_key = 'ntfm_processed_post_' . $post_id . '_' . $this->get_slug();
		set_transient( $transient_key, true, 10 ); // 10 seconds should be enough
	}

	/**
	 * Schedule a delayed notification to ensure we have the most up-to-date data.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function schedule_delayed_notification( $post_id ) {
		// Check if there are any notifications configured for this trigger
		if ( ! $this->has_notifications() ) {
			return;
		}

		// Mark that we're handling this notification
		$this->mark_post_as_processed( $post_id );

		// Schedule the delayed notification via our dedicated handler
		$delayed_notifications = Delayed_Notifications::get_instance();
		$delayed_notifications->schedule( $post_id, $this->post_type, $this->slug );
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return array(
			$this->post_type,
			"{$this->post_type}_author",
			"{$this->post_type}_last_editor",
			"{$this->post_type}_acf_fields",
		);
	}
}
