<?php
/**
 * Media Updated.
 *
 * This class is responsible for sending notifications when media is updated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Media;

use Notification_Master\Abstracts\Media_Trigger;

/**
 * Media Updated class.
 */
class Media_Updated extends Media_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Updated';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'updated';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when media is updated.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'edit_attachment';
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
		$this->author     = get_user_by( 'id', $this->attachment->post_author );

		$this->do_connections();
	}
}
