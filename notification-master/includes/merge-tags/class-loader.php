<?php
/**
 * Class Loader
 *
 * This class is responsible for loading all the merge_tags.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags;

use Notification_Master\Settings;
use Notification_Master\Utils;
use Notification_Master\Abstracts\Trigger;
use Notification_Master\Abstracts\Merge_Tags_Group;
use Notification_Master\Merge_Tags\Post_Type\Post;
use Notification_Master\Merge_Tags\Post_Type\Post_Author;
use Notification_Master\Merge_Tags\Post_Type\Post_Last_Editor;
use Notification_Master\Merge_Tags\Post_Type\Post_Publishing_User;
use Notification_Master\Merge_Tags\Post_Type\Post_Trashing_User;
use Notification_Master\Merge_Tags\Post_Type\Post_Scheduling_User;
use Notification_Master\Merge_Tags\Taxonomy_Type\Taxonomy;
use Notification_Master\Merge_Tags\Comment_Type\Comment;
use Notification_Master\Merge_Tags\Comment_Type\Parent_Comment;
use Notification_Master\Merge_Tags\Comment_Type\Comment_Author;
use Notification_Master\Merge_Tags\Comment_Type\Parent_Comment_Author;
use Notification_Master\Merge_Tags\Theme\Theme;
use Notification_Master\Merge_Tags\Theme\Old_Theme;
use Notification_Master\Merge_Tags\Plugin\Plugin;
use Notification_Master\Merge_Tags\User\User;
use Notification_Master\Merge_Tags\Media\Attachment;
use Notification_Master\Merge_Tags\Media\Attachment_Author;
use Notification_Master\Merge_Tags\Privacy\Archive;
use Notification_Master\Merge_Tags\General\General;
use Notification_Master\Merge_Tags\Post_Type\Post_ACF_Fields;
use Notification_Master\Merge_Tags\User\User_ACF_Fields;

/**
 * Loader class.
 */
class Loader {

	/**
	 * Groups.
	 *
	 * @since 1.0.0
	 *
	 * @var Merge_Tags_Group[]
	 */
	protected $groups = array();

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Loader
	 */
	protected static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Loader
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Loader ) ) {
			self::$instance = new Loader();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Load merge_tags.
		add_action( 'init', array( $this, 'load_merge_tags' ), 99 );
	}

	/**
	 * Register merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param Merge_Tags_Group $merge_tags_group Merge_Tags_Group.
	 *
	 * @return void
	 */
	public function register_group( $merge_tags_group ) {
		if ( ! $merge_tags_group instanceof Merge_Tags_Group ) {
			return;
		}

		if ( isset( $this->groups[ $merge_tags_group->get_slug() ] ) ) {
			return;
		}

		$this->groups[ $merge_tags_group->get_slug() ] = $merge_tags_group;
	}

	/**
	 * Load merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_merge_tags() {
		$this->register_group( new General() );
		$this->load_post_merge_tags();
		$this->load_taxonomy_merge_tags();
		$this->load_comment_merge_tags();
		$this->load_theme_merge_tags();
		$this->load_plugin_merge_tags();
		$this->load_user_merge_tags();
		$this->load_media_merge_tags();
		$this->load_privacy_merge_tags();
	}

	/**
	 * Load post merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_post_merge_tags() {
		$post_types = Settings::get_setting( 'post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$this->register_group( new Post( $post_type ) );
			$this->register_group( new Post_Author( $post_type ) );
			$this->register_group( new Post_Last_Editor( $post_type ) );
			$this->register_group( new Post_Publishing_User( $post_type ) );
			$this->register_group( new Post_Trashing_User( $post_type ) );
			$this->register_group( new Post_Scheduling_User( $post_type ) );
			$this->register_group( new Post_ACF_Fields( $post_type ) );
		}
	}

	/**
	 * Load taxonomy merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_taxonomy_merge_tags() {
		$taxonomies = Settings::get_setting( 'taxonomies', array( 'category', 'post_tag' ) );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$this->register_group( new Taxonomy( $taxonomy ) );
		}
	}

	/**
	 * Load comment merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_comment_merge_tags() {
		$comment_types        = Settings::get_setting( 'comment_types', array( 'comment' ) );
		$comment_types_labels = Utils::get_comment_types_labels();

		foreach ( $comment_types as $comment_type ) {
			$label = isset( $comment_types_labels[ $comment_type ] ) ? $comment_types_labels[ $comment_type ] : '';

			$this->register_group( new Comment( $comment_type, $label ) );
			$this->register_group( new Parent_Comment( $comment_type, $label ) );
			$this->register_group( new Comment_Author( $comment_type, $label ) );
			$this->register_group( new Parent_Comment_Author( $comment_type, $label ) );
			$this->register_group( new Post( 'post' ) );
			$this->register_group( new Post_Author( 'post' ) );
		}
	}

	/**
	 * Load theme merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_theme_merge_tags() {
		$theme_change_trigger = Settings::get_setting( 'theme_change_trigger', true );
		if ( ! $theme_change_trigger ) {
			return;
		}

		$this->register_group( new Theme() );
		$this->register_group( new Old_Theme() );
	}

	/**
	 * Load plugin merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_plugin_merge_tags() {
		$plugin_change_trigger = Settings::get_setting( 'plugin_change_trigger', true );
		if ( ! $plugin_change_trigger ) {
			return;
		}

		$this->register_group( new Plugin() );
	}

	/**
	 * Load user merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_user_merge_tags() {
		$user_change_trigger = Settings::get_setting( 'user_change_trigger', true );
		if ( ! $user_change_trigger ) {
			return;
		}

		$this->register_group( new User() );
		$this->register_group( new User_ACF_Fields() );
	}

	/**
	 * Load media merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_media_merge_tags() {
		$media_change_trigger = Settings::get_setting( 'media_change_trigger', true );
		if ( ! $media_change_trigger ) {
			return;
		}

		$this->register_group( new Attachment() );
		$this->register_group( new Attachment_Author() );
	}

	/**
	 * Load privacy merge_tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_privacy_merge_tags() {
		$privacy_trigger = Settings::get_setting( 'privacy_trigger', true );
		if ( ! $privacy_trigger ) {
			return;
		}

		$this->register_group( new Archive() );
		$this->register_group( new User() );
	}

	/**
	 * Get Groups.
	 *
	 * @since 1.0.0
	 *
	 * @return Merge_Tags_Group[]
	 */
	public function get_merge_tags_groups() {
		return $this->groups;
	}

	/**
	 * Get group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Group slug.
	 *
	 * @return Merge_Tags_Group | null
	 */
	public function get_group( $slug ) {
		if ( ! isset( $this->groups[ $slug ] ) ) {
			return null;
		}

		return $this->groups[ $slug ];
	}

	/**
	 * Processes merge tags in the given content.
	 *
	 * @param Trigger $trigger The trigger object.
	 * @param mixed   $content The content to process merge tags in.
	 *
	 * @return mixed The processed content with merge tags replaced, or the original content if no merge tags were found.
	 */
	public function process_merge_tags( $trigger, $content ) {
		$groups = $trigger->get_merge_tags();
		array_push( $groups, 'general' );

		foreach ( $groups as $group_slug ) {
			$group = $this->get_group( $group_slug );
			if ( ! $group ) {
				continue;
			}

			$group->set_trigger( $trigger );
			$content = $group->process( $content );
		}

		return $content;
	}
}
