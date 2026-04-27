<?php
/**
 * Author Merge Tags Group
 *
 * This class is responsible for loading the Author Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Media;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Author Merge Tags Group class.
 */
class Attachment_Author extends Merge_Tags_Group {

	/**
	 * Author.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User|null
	 */
	public $author;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Author';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'attachment_author';

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_attachment_author_merge_tags',
			array(
				'id'           => array(
					'name'        => __( 'Author ID', 'notification-master' ),
					'description' => __( 'The ID of the author.', 'notification-master' ),
				),
				'display_name' => array(
					'name'        => __( 'Author Display Name', 'notification-master' ),
					'description' => __( 'The display name of the author.', 'notification-master' ),
				),
				'user_login'   => array(
					'name'        => __( 'Author User Login', 'notification-master' ),
					'description' => __( 'The user login of the author.', 'notification-master' ),
				),
				'email'        => array(
					'name'        => __( 'Author Email', 'notification-master' ),
					'description' => __( 'The email of the author.', 'notification-master' ),
				),
				'firstname'    => array(
					'name'        => __( 'Author First Name', 'notification-master' ),
					'description' => __( 'The first name of the author.', 'notification-master' ),
				),
				'lastname'     => array(
					'name'        => __( 'Author Last Name', 'notification-master' ),
					'description' => __( 'The last name of the author.', 'notification-master' ),
				),
				'nickname'     => array(
					'name'        => __( 'Author Nickname', 'notification-master' ),
					'description' => __( 'The nickname of the author.', 'notification-master' ),
				),
				'avatar'       => array(
					'name'        => __( 'Author Avatar', 'notification-master' ),
					'description' => __( 'The avatar of the author.', 'notification-master' ),
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
		$this->author = $trigger->author ?? null;
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge Tag slug.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( ! $this->author ) {
			return '';
		}

		switch ( $tag ) {
			case 'id':
				return $this->author->ID;
			case 'display_name':
				return $this->author->display_name;
			case 'user_login':
				return $this->author->user_login;
			case 'email':
				return $this->author->user_email;
			case 'firstname':
				return $this->author->first_name;
			case 'lastname':
				return $this->author->last_name;
			case 'nickname':
				return $this->author->nickname;
			case 'avatar':
				return get_avatar_url( $this->author->ID, array( 'size' => 32 ) );
		}
	}
}
