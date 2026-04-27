<?php
/**
 * Media Published.
 *
 * This class is responsible for sending notifications when media is published.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Media;

use Notification_Master\Abstracts\Media_Trigger;

/**
 * Media Published class.
 */
class Media_Published extends Media_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Published';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'published';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when media is published.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'add_attachment';
		add_action( $this->hook, array( $this, 'process' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function process( $attachment_id ) {
		$this->attachment = get_post( $attachment_id );
		$this->author     = get_userdata( $this->attachment->post_author );

		$this->do_connections();
	}
}
