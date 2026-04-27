<?php
/**
 * Attachment Merge Tags Group
 *
 * This class is responsible for loading the Attachment Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Media;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Attachment Merge Tags Group class.
 */
class Attachment extends Merge_Tags_Group {

	/**
	 * Attachment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Post|null
	 */
	public $attachment;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Attachment';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'attachment';

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_attachment_merge_tags',
			array(
				'id'   => array(
					'name'        => __( 'Attachment ID', 'notification-master' ),
					'description' => __( 'The ID of the attachment.', 'notification-master' ),
				),
				'url'  => array(
					'name'        => __( 'Attachment URL', 'notification-master' ),
					'description' => __( 'The URL of the attachment.', 'notification-master' ),
				),
				'name' => array(
					'name'        => __( 'Attachment Name', 'notification-master' ),
					'description' => __( 'The name of the attachment.', 'notification-master' ),
				),
				'type' => array(
					'name'        => __( 'Attachment Type', 'notification-master' ),
					'description' => __( 'The type of the attachment.', 'notification-master' ),
				),
				'size' => array(
					'name'        => __( 'Attachment Size', 'notification-master' ),
					'description' => __( 'The size of the attachment.', 'notification-master' ),
				),
				'date' => array(
					'name'        => __( 'Attachment Date', 'notification-master' ),
					'description' => __( 'The date of the attachment.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->attachment = $trigger->attachment ?? null;
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( ! $this->attachment ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->attachment->ID;
			case 'url':
				return wp_get_attachment_url( $this->attachment->ID );
			case 'name':
				return $this->attachment->post_title;
			case 'type':
				return $this->attachment->post_mime_type;
			case 'size':
				return $this->get_attachment_size();
			case 'date':
				return $this->attachment->post_date;
			default:
				return '';
		}
	}

	/**
	 * Get attachment size.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_attachment_size() {
		$size = filesize( get_attached_file( $this->attachment->ID ) );
		$size = size_format( $size );

		return $size;
	}
}
