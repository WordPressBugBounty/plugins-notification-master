<?php
/**
 * Class Loader
 *
 * This class is responsible for loading all the triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers;

use Notification_Master\Abstracts\Trigger;
use Notification_Master\Settings;
use Notification_Master\Utils;
use Notification_Master\Triggers\Post\Post_Added;
use Notification_Master\Triggers\Post\Post_Approved;
use Notification_Master\Triggers\Post\Post_Drafted;
use Notification_Master\Triggers\Post\Post_Published;
use Notification_Master\Triggers\Post\Post_Scheduled;
use Notification_Master\Triggers\Post\Post_Sent_To_Review;
use Notification_Master\Triggers\Post\Post_Updated;
use Notification_Master\Triggers\Post\Post_Published_Privately;
use Notification_Master\Triggers\Post\Post_Trashed;
use Notification_Master\Triggers\Taxonomy\Taxonomy_Created;
use Notification_Master\Triggers\Taxonomy\Taxonomy_Updated;
use Notification_Master\Triggers\Taxonomy\Taxonomy_Deleted;
use Notification_Master\Triggers\Comment\Comment_Added;
use Notification_Master\Triggers\Comment\Comment_Approved;
use Notification_Master\Triggers\Comment\Comment_Published;
use Notification_Master\Triggers\Comment\Comment_Trashed;
use Notification_Master\Triggers\Comment\Comment_Unapproved;
use Notification_Master\Triggers\Comment\Comment_Spammed;
use Notification_Master\Triggers\Comment\Comment_Replied;
use Notification_Master\Triggers\Theme\Theme_Installed;
use Notification_Master\Triggers\Theme\Theme_Switched;
use Notification_Master\Triggers\Theme\Theme_Updated;
use Notification_Master\Triggers\Plugin\Plugin_Installed;
use Notification_Master\Triggers\Plugin\Plugin_Activated;
use Notification_Master\Triggers\Plugin\Plugin_Updated;
use Notification_Master\Triggers\Plugin\Plugin_Deactivated;
use Notification_Master\Triggers\User\User_Registration;
use Notification_Master\Triggers\User\User_Profile_Updated;
use Notification_Master\Triggers\User\User_Deleted;
use Notification_Master\Triggers\User\User_Login;
use Notification_Master\Triggers\User\User_Logout;
use Notification_Master\Triggers\User\User_Lost_Password;
use Notification_Master\Triggers\User\User_Password_Changed;
use Notification_Master\Triggers\Media\Media_Published;
use Notification_Master\Triggers\Media\Media_Updated;
use Notification_Master\Triggers\Media\Media_Trashed;
use Notification_Master\Triggers\Privacy\Exported_Personal_Data;
use Notification_Master\Triggers\Privacy\Export_Personal_Data_Request;
use Notification_Master\Triggers\Privacy\Erase_Personal_Data_Request;
use Notification_Master\Triggers\Privacy\Erased_Personal_Data;

/**
 * Loader class.
 */
class Loader {

	/**
	 * Triggers.
	 *
	 * @since 1.0.0
	 *
	 * @var Trigger[]
	 */
	private $triggers = array();

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Loader
	 */
	private static $instance;

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
		// Load triggers.
		add_action( 'init', array( $this, 'load_triggers' ), 99 );
	}

	/**
	 * Register trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger object.
	 * @param string  $group   Trigger group.
	 *
	 * @return void
	 */
	public function register_trigger( Trigger $trigger ) {
		if ( ! $trigger instanceof Trigger ) {
			return;
		}

		// Check if trigger is already registered.
		if ( isset( $this->triggers[ $trigger->get_slug() ] ) ) {
			return;
		}

		$this->triggers[ $trigger->get_slug() ] = $trigger;
	}

	/**
	 * Load triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_triggers() {
		$this->load_post_triggers();
		$this->load_taxonomy_triggers();
		$this->load_comment_triggers();
		$this->load_theme_triggers();
		$this->load_plugin_triggers();
		$this->load_user_triggers();
		$this->load_media_triggers();
		$this->load_privacy_triggers();
	}

	/**
	 * Load post triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_post_triggers() {
		$post_types = Settings::get_setting( 'post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$this->register_trigger( new Post_Published( $post_type ) );
			$this->register_trigger( new Post_Approved( $post_type ) );
			$this->register_trigger( new Post_Drafted( $post_type ) );
			$this->register_trigger( new Post_Added( $post_type ) );
			$this->register_trigger( new Post_Scheduled( $post_type ) );
			$this->register_trigger( new Post_Sent_To_Review( $post_type ) );
			$this->register_trigger( new Post_Updated( $post_type ) );
			$this->register_trigger( new Post_Published_Privately( $post_type ) );
			$this->register_trigger( new Post_Trashed( $post_type ) );
		}
	}

	/**
	 * Load taxonomy triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_taxonomy_triggers() {
		$taxonomies = Settings::get_setting( 'taxonomies', array( 'category', 'post_tag' ) );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$this->register_trigger( new Taxonomy_Created( $taxonomy ) );
			$this->register_trigger( new Taxonomy_Updated( $taxonomy ) );
			$this->register_trigger( new Taxonomy_Deleted( $taxonomy ) );
		}
	}

	/**
	 * Load comment triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_comment_triggers() {
		$comment_types        = Settings::get_setting( 'comment_types', array( 'comment' ) );
		$comment_types_labels = Utils::get_comment_types_labels();

		foreach ( $comment_types as $comment_type ) {
			$label = isset( $comment_types_labels[ $comment_type ] ) ? $comment_types_labels[ $comment_type ] : '';

			$this->register_trigger( new Comment_Added( $comment_type, $label ) );
			$this->register_trigger( new Comment_Approved( $comment_type, $label ) );
			$this->register_trigger( new Comment_Published( $comment_type, $label ) );
			$this->register_trigger( new Comment_Trashed( $comment_type, $label ) );
			$this->register_trigger( new Comment_Unapproved( $comment_type, $label ) );
			$this->register_trigger( new Comment_Spammed( $comment_type, $label ) );
			$this->register_trigger( new Comment_Replied( $comment_type, $label ) );
		}
	}

	/**
	 * Load theme triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_theme_triggers() {
		$theme_change_trigger = Settings::get_setting( 'theme_change_trigger', true );
		if ( ! $theme_change_trigger ) {
			return;
		}

		$this->register_trigger( new Theme_Installed() );
		$this->register_trigger( new Theme_Switched() );
		$this->register_trigger( new Theme_Updated() );
	}

	/**
	 * Load plugin triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_plugin_triggers() {
		$plugin_change_trigger = Settings::get_setting( 'plugin_change_trigger', true );
		if ( ! $plugin_change_trigger ) {
			return;
		}

		$this->register_trigger( new Plugin_Installed() );
		$this->register_trigger( new Plugin_Activated() );
		$this->register_trigger( new Plugin_Updated() );
		$this->register_trigger( new Plugin_Deactivated() );
	}

	/**
	 * Load user triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_user_triggers() {
		$user_change_trigger = Settings::get_setting( 'user_change_trigger', true );
		if ( ! $user_change_trigger ) {
			return;
		}

		$this->register_trigger( new User_Registration() );
		$this->register_trigger( new User_Profile_Updated() );
		$this->register_trigger( new User_Deleted() );
		$this->register_trigger( new User_Login() );
		$this->register_trigger( new User_Logout() );
		$this->register_trigger( new User_Lost_Password() );
		$this->register_trigger( new User_Password_Changed() );
	}

	/**
	 * Load media triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_media_triggers() {
		$media_change_trigger = Settings::get_setting( 'media_change_trigger', true );
		if ( ! $media_change_trigger ) {
			return;
		}

		$this->register_trigger( new Media_Published() );
		$this->register_trigger( new Media_Updated() );
		$this->register_trigger( new Media_Trashed() );
	}

	/**
	 * Load privacy triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_privacy_triggers() {
		$privacy_trigger = Settings::get_setting( 'privacy_trigger', true );
		if ( ! $privacy_trigger ) {
			return;
		}

		$this->register_trigger( new Exported_Personal_Data() );
		$this->register_trigger( new Export_Personal_Data_Request() );
		$this->register_trigger( new Erase_Personal_Data_Request() );
		$this->register_trigger( new Erased_Personal_Data() );
	}

	/**
	 * Get triggers.
	 *
	 * @since 1.0.0
	 *
	 * @return Trigger[]
	 */
	public function get_triggers() {
		return $this->triggers;
	}

	/**
	 * Get trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Trigger slug.
	 * @return Trigger
	 */
	public function get_trigger( $slug ) {
		if ( isset( $this->triggers[ $slug ] ) ) {
			return $this->triggers[ $slug ];
		}

		return null;
	}
}
