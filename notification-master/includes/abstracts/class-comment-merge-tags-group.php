<?php
/**
 * Comment Merge Tags Group
 *
 * This class is responsible for loading the Comment Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Merge_Tags_Group;
use Notification_Master\Utils;

/**
 * Comment Merge Tags Group class.
 */
class Comment_Merge_Tags_Group extends Merge_Tags_Group {

	/**
	 * Comment type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $comment_type;

	/**
	 * Comment type name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $comment_type_name;

	/**
	 * Comment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Comment|false
	 */
	public $comment;

	/**
	 * Parent Comment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Comment|false
	 */
	public $parent_comment;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $comment_type Comment type.
	 * @param string $comment_type_name Comment type name.
	 */
	public function __construct( $comment_type, $comment_type_name ) {
		$this->comment_type      = $comment_type;
		$this->comment_type_name = $comment_type_name;
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
	 *
	 * @return void
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->comment        = $trigger->comment ?? null;
		$this->parent_comment = $trigger->comment_parent ?? null;
	}
}
