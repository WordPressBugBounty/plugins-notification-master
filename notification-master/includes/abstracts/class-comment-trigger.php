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

/**
 * Trigger Abstract class.
 */
abstract class Comment_Trigger extends Trigger {

	/**
	 * Comment type.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $comment_type;

	/**
	 * Comment Type name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $comment_type_name;

	/**
	 * Comment.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Comment|null
	 */
	public $comment;

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
	 * Constructor.
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
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->comment_type_name . ' ' . $this->name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->comment_type . '_' . $this->slug;
	}

	/**
	 * Get group.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_group() {
		return $this->comment_type;
	}

	/**
	 * Is comment type.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Comment $comment Comment object.
	 *
	 * @return bool
	 */
	public function is_comment_type( $comment ) {
		return $this->comment_type === $comment->comment_type;
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
			$this->comment_type,
			"{$this->comment_type}_author",
			'post',
			'post_author',
		);
	}
}
