<?php
/**
 * Class Utils
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master;

use Notification_Master\Settings;

/**
 * Utility class.
 */
class Utils {

	/**
	 * Get post types.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		// Remove attachment post type.
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		// Remove product post type.
		if ( isset( $post_types['product'] ) ) {
			unset( $post_types['product'] );
		}

		$post_types = wp_list_pluck( $post_types, 'label' );
		$post_types = array_map(
			function ( $label, $key ) {
				return array(
					'label' => $label,
					'value' => $key,
				);
			},
			$post_types,
			array_keys( $post_types )
		);

		return $post_types;
	}

	/**
	 * Get taxonomies.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_taxonomies() {
		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'objects'
		);

		$taxonomies = wp_list_pluck( $taxonomies, 'label' );
		$taxonomies = array_map(
			function ( $label, $key ) {
				return array(
					'label' => $label,
					'value' => $key,
				);
			},
			$taxonomies,
			array_keys( $taxonomies )
		);

		return $taxonomies;
	}

	/**
	 * Get comment types.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_comment_types() {
		$comment_types = array(
			'comment'   => __( 'Comment', 'notification-master' ),
			'pingback'  => __( 'Pingback', 'notification-master' ),
			'trackback' => __( 'Trackback', 'notification-master' ),
		);

		$comment_types = array_map(
			function ( $label, $key ) {
				return array(
					'label' => $label,
					'value' => $key,
				);
			},
			$comment_types,
			array_keys( $comment_types )
		);

		return $comment_types;
	}

	/**
	 * Get comment types labels.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_comment_types_labels() {
		$comment_types = array(
			'comment'   => __( 'Comment', 'notification-master' ),
			'pingback'  => __( 'Pingback', 'notification-master' ),
			'trackback' => __( 'Trackback', 'notification-master' ),
		);

		return $comment_types;
	}

	/**
	 * Get total ntfm_notification posts count.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function get_total_notifications_count() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'ntfm_notification',
				'posts_per_page' => -1,
			)
		);

		return $query->post_count;
	}

	/**
	 * Get Triggers Groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_triggers_groups() {
		$triggers                     = array();
		$post_status_change_trigger   = Settings::get_setting( 'post_status_change_trigger', true );
		$post_type                    = Settings::get_setting( 'post_types', array( 'post', 'page' ) );
		$taxonomy_term_change_trigger = Settings::get_setting( 'taxonomy_term_change_trigger', true );
		$taxonomies                   = Settings::get_setting( 'taxonomies', array( 'category', 'post_tag' ) );
		$comment_change_trigger       = Settings::get_setting( 'comment_change_trigger', true );
		$comment_types                = Settings::get_setting( 'comment_types', array( 'comment' ) );
		$user_change_trigger          = Settings::get_setting( 'user_change_trigger', true );
		$theme_change_trigger         = Settings::get_setting( 'theme_change_trigger', true );
		$plugin_change_trigger        = Settings::get_setting( 'plugin_change_trigger', true );
		$media_change_trigger         = Settings::get_setting( 'media_change_trigger', true );
		$privacy_trigger              = Settings::get_setting( 'privacy_trigger', true );
		$comments_types_labels        = self::get_comment_types_labels();

		if ( $post_status_change_trigger ) {
			foreach ( $post_type as $type ) {
				$post_type_object = get_post_type_object( $type );
				if ( ! $post_type_object ) {
					continue;
				}
				$triggers[ $type ] = array(
					'label'    => $post_type_object->label,
					'triggers' => array(),
				);
			}
		}

		if ( $taxonomy_term_change_trigger ) {
			foreach ( $taxonomies as $taxonomy ) {
				$triggers[ $taxonomy ] = array(
					'label'    => get_taxonomy( $taxonomy )->label,
					'triggers' => array(),
				);
			}
		}

		if ( $comment_change_trigger ) {
			foreach ( $comment_types as $type ) {
				$triggers[ $type ] = array(
					'label'    => $comments_types_labels[ $type ],
					'triggers' => array(),
				);
			}
		}

		if ( $media_change_trigger ) {
			$triggers['media'] = array(
				'label'    => __( 'Media', 'notification-master' ),
				'triggers' => array(),
			);
		}

		if ( $user_change_trigger ) {
			$triggers['user'] = array(
				'label'    => __( 'User', 'notification-master' ),
				'triggers' => array(),
			);
		}

		if ( $theme_change_trigger ) {
			$triggers['theme'] = array(
				'label'    => __( 'Theme', 'notification-master' ),
				'triggers' => array(),
			);
		}

		if ( $plugin_change_trigger ) {
			$triggers['plugin'] = array(
				'label'    => __( 'Plugin', 'notification-master' ),
				'triggers' => array(),
			);
		}

		if ( $privacy_trigger ) {
			$triggers['privacy'] = array(
				'label'    => __( 'Privacy', 'notification-master' ),
				'triggers' => array(),
			);
		}

		return $triggers;
	}

	/**
	 * Convert array to object.
	 *
	 * @param array $array Array.
	 *
	 * @return object
	 */
	public static function array_to_object( $array ) {
		return json_decode( wp_json_encode( $array ) );
	}

	/**
	 * Enqueue async action.
	 *
	 * @since 1.4.6
	 *
	 * @param string $action Action.
	 * @param array  $args Args.
	 *
	 * @return void
	 */
	public static function enqueue_async_action( $action, $args ) {
		$option_name = "ntfm_background_process_{$action}_" . bin2hex( random_bytes( 10 ) );
		$updated     = update_option(
			$option_name,
			$args,
			false
		);

		if ( $updated ) {
			as_enqueue_async_action(
				"notification_master_{$action}_process",
				array(
					'option_name' => $option_name,
				),
				"ntfm_background_process_{$action}",
				false
			);
		}
	}

	/**
	 * Get action hook name.
	 *
	 * @since 1.4.6
	 *
	 * @param string $action Action.
	 *
	 * @return string
	 */
	public static function get_action_hook( $action ) {
		return "notification_master_{$action}_process";
	}

	/**
	 * Check if ACF plugin is active
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_acf_active() {
		return class_exists( 'ACF' );
	}
}
