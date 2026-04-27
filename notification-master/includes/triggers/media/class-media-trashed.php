<?php
/**
 * Media Trashed.
 *
 * This class is responsible for sending notifications when media is trashed.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Media;

use Notification_Master\Abstracts\Media_Trigger;

/**
 * Media Trashed class.
 */
class Media_Trashed extends Media_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Trashed';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'trashed';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when media is trashed.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'delete_attachment';
		add_action( $this->hook, array( $this, 'process' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function process( $post_id ) {
		$this->attachment = get_post( $post_id );
		$this->author     = get_user_by( 'id', $this->attachment->post_author );

		$this->do_connections();
	}
}
