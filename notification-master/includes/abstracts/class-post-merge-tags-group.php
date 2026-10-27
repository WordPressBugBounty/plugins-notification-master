<?php
/**
 * Class Post Merge Tags
 *
 * This class is responsible for adding merge tags for post triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Post Merge Tags Group class.
 */
class Post_Merge_Tags_Group extends Merge_Tags_Group {

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
	public $user;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		$this->post_type        = $post_type;
		$this->post_type_object = get_post_type_object( $this->post_type );
		parent::__construct();
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->post             = $trigger->post ?? null;
		$this->post_author      = $trigger->post_author ?? null;
		$this->post_last_editor = $trigger->post_last_editor ?? null;
		$this->user             = $trigger->current_user ?? null;
	}
}
