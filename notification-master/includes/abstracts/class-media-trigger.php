<?php
/**
 * Media Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * Media Trigger Abstract class.
 */
abstract class Media_Trigger extends Trigger {

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group = 'media';

	/**
	 * Attachment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Post|null
	 */
	public $attachment;

	/**
	 * Author.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $author;

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Media name */
		return sprintf( __( 'Media %s', 'notification-master' ), $this->name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'media_' . $this->slug;
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/* translators: %s: Media name */
		return sprintf( __( 'Triggered when %s media.', 'notification-master' ), $this->name );
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
			'attachment',
			'attachment_author',
		);
	}
}
