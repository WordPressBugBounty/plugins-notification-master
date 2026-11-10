<?php
/**
 * Delayed Notifications Handler
 *
 * This class is responsible for handling delayed notifications to ensure
 * fresh data is used when processing post-related triggers.
 *
 * @package notification-master
 *
 * @since 1.6.0
 */

namespace Notification_Master;

use Notification_Master\Triggers\Post\Post_Added;
use Notification_Master\Triggers\Post\Post_Approved;
use Notification_Master\Triggers\Post\Post_Drafted;
use Notification_Master\Triggers\Post\Post_Published;
use Notification_Master\Triggers\Post\Post_Published_Privately;
use Notification_Master\Triggers\Post\Post_Scheduled;
use Notification_Master\Triggers\Post\Post_Sent_To_Review;
use Notification_Master\Triggers\Post\Post_Trashed;
use Notification_Master\Triggers\Post\Post_Updated;

/**
 * Delayed Notifications Handler Class
 */
class Delayed_Notifications {

	/**
	 * Class instance
	 *
	 * @var Delayed_Notifications|null
	 */
	private static $instance = null;

	/**
	 * Delay in seconds
	 *
	 * @var integer
	 */
	public $delay = 3;

	/**
	 * Action group name for the scheduler
	 *
	 * @var string
	 */
	private $group = 'ntfm_delayed_triggers';

	/**
	 * Constructor
	 */
	private function __construct() {
		// Register the handler for delayed notifications
		add_action( 'ntfm_process_delayed_post_notification', array( $this, 'process_delayed_post_notification' ), 10, 2 );
	}

	/**
	 * Get instance
	 *
	 * @return Delayed_Notifications
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the delayed notifications system
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Schedule a delayed notification for a post
	 *
	 * @param int    $post_id   Post ID to process.
	 * @param string $post_type Post type.
	 * @param string $slug      Trigger slug.
	 * @return void
	 */
	public function schedule( $post_id, $post_type, $slug ) {
		// Check if we already have a scheduled action for this post and trigger
		$existing_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'ntfm_process_delayed_post_notification',
				'status' => \ActionScheduler_Store::STATUS_PENDING,
				'args'   => array(
					$post_id,
					array(
						'post_type' => $post_type,
						'slug'      => $slug,
					),
				),
				'group'  => $this->group,
			),
			'ids'
		);

		// If there's already a scheduled action, don't schedule another one
		if ( ! empty( $existing_actions ) ) {
			return;
		}

		// Schedule action to run after 3 seconds
		as_schedule_single_action(
			time() + $this->delay,
			'ntfm_process_delayed_post_notification',
			array(
				$post_id,
				array(
					'post_type' => $post_type,
					'slug'      => $slug,
				),
			),
			$this->group
		);
	}

	/**
	 * Process a delayed post notification
	 *
	 * @param int   $post_id Post ID to process.
	 * @param array $data    Associated data (post_type and slug).
	 * @return void
	 */
	public function process_delayed_post_notification( $post_id, $data ) {
		$post_type = $data['post_type'] ?? '';
		$slug      = $data['slug'] ?? '';

		if ( empty( $post_type ) || empty( $slug ) ) {
			return;
		}

		// Get a fresh copy of the post
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Get the actual trigger class from the slug and post type
		$trigger_class = $this->get_trigger_class_from_slug( $post_type, $slug );
		if ( ! $trigger_class ) {
			return;
		}

		// Create an instance of the trigger class
		if ( class_exists( $trigger_class ) ) {
			$trigger = new $trigger_class( $post_type );

			// Set up the trigger with fresh data
			$trigger->post             = $post;
			$trigger->post_author      = get_userdata( $post->post_author );
			$last_editor_id            = get_post_meta( $post->ID, '_edit_last', true );
			$editor_id                 = $last_editor_id ? intval( $last_editor_id ) : $post->post_author;
			$trigger->post_last_editor = get_userdata( $editor_id );
			$trigger->current_user     = wp_get_current_user();

			// Process the notification with fresh data
			$trigger->do_connections();
		}
	}

	/**
	 * Get the trigger class from slug and post type
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Trigger slug.
	 * @return string|null The class name or null if not found
	 */
	private function get_trigger_class_from_slug( $post_type, $slug ) {
		$trigger_slug = $post_type . '-' . $slug;

		// Map of known post trigger slugs to their class names
		$trigger_classes = array(
			$post_type . '-updated'             => Post_Updated::class,
			$post_type . '-added'               => Post_Added::class,
			$post_type . '-trashed'             => Post_Trashed::class,
			$post_type . '-sent-to-review'      => Post_Sent_To_Review::class,
			$post_type . '-scheduled'           => Post_Scheduled::class,
			$post_type . '-published'           => Post_Published::class,
			$post_type . '-published_privately' => Post_Published_Privately::class,
			$post_type . '-drafted'             => Post_Drafted::class,
			$post_type . '-approved'            => Post_Approved::class,
		);

		return $trigger_classes[ $trigger_slug ] ?? null;
	}
}
