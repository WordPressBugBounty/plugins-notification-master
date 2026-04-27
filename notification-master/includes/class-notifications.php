<?php
/**
 * Class Notifications
 *
 * This class is responsible for adding notifications to the site.
 *
 * @package     notification-master
 *
 * @since       1.0.0
 */

namespace Notification_Master;

use Notification_Master\Integrations\Loader;

/**
 * Notifications class.
 */
class Notifications {

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Notifications
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Notifications
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Notifications ) ) {
			self::$instance = new Notifications();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Register notification post type.
		add_action( 'init', array( $this, 'register_notification_post_type' ) );

		// Register rest fields for notification post type.
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	/**
	 * Register notification post type.
	 *
	 * @since 1.0.0
	 */
	public function register_notification_post_type() {
		$labels = array(
			'name'               => _x( 'Notifications', 'post type general name', 'notification-master' ),
			'singular_name'      => _x( 'Notification', 'post type singular name', 'notification-master' ),
			'menu_name'          => _x( 'Notifications', 'admin menu', 'notification-master' ),
			'name_admin_bar'     => _x( 'Notification', 'add new on admin bar', 'notification-master' ),
			'add_new'            => _x( 'Add New', 'notification', 'notification-master' ),
			'add_new_item'       => __( 'Add New Notification', 'notification-master' ),
			'new_item'           => __( 'New Notification', 'notification-master' ),
			'edit_item'          => __( 'Edit Notification', 'notification-master' ),
			'view_item'          => __( 'View Notification', 'notification-master' ),
			'all_items'          => __( 'All Notifications', 'notification-master' ),
			'search_items'       => __( 'Search Notifications', 'notification-master' ),
			'parent_item_colon'  => __( 'Parent Notifications:', 'notification-master' ),
			'not_found'          => __( 'No notifications found.', 'notification-master' ),
			'not_found_in_trash' => __( 'No notifications found in Trash.', 'notification-master' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Description.', 'notification-master' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'ntfm_notification' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'trash' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'ntfm_notification', $args );
	}

	/**
	 * Register rest fields for notification post type.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_fields() {
		// Trigger field.
		register_rest_field(
			'ntfm_notification',
			'trigger',
			array(
				'get_callback'    => array( $this, 'get_trigger' ),
				'update_callback' => array( $this, 'update_trigger' ),
				'schema'          => array(
					'description' => __( 'Notification trigger.', 'notification-master' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Integration field.
		register_rest_field(
			'ntfm_notification',
			'connections',
			array(
				'get_callback'    => array( $this, 'get_connections' ),
				'update_callback' => array( $this, 'update_connections' ),
				'schema'          => array(
					'description' => __( 'Notification connection.', 'notification-master' ),
					'type'        => 'object',
					'properties'  => array(
						'enabled'     => array(
							'type'        => 'boolean',
							'description' => __( 'Connection enabled.', 'notification-master' ),
						),
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'Connection name.', 'notification-master' ),
						),
						'integration' => array(
							'type'        => 'string',
							'description' => __( 'Connection integration.', 'notification-master' ),
						),
						'settings'    => array(
							'type'                 => 'object',
							'description'          => __( 'Connection settings.', 'notification-master' ),
							'additionalProperties' => true,
						),
					),
					'arg_options' => array(
						'validate_callback' => array( $this, 'validate_connections' ),
						'sanitize_callback' => array( $this, 'sanitize_connections' ),
					),
				),
				'default'         => array(),
			)
		);
	}

	/**
	 * Validate connection.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Value.
	 *
	 * @return bool
	 */
	public function validate_connections( $value ) {
		$connections = $value;

		if ( empty( $connections ) ) {
			return true;
		}

		foreach ( $connections as $connection_id => $connection ) {
			$integration = Loader::get_instance()->get_integration( $connection['integration'] );

			if ( $integration ) {
				$attributes = $integration->get_attributes_schema();
				/* translators: %s: integration name */
				$settings = rest_validate_object_value_from_schema( $connection['settings'], $attributes, sprintf( '(%s) integration', $integration->name ) );

				if ( is_wp_error( $settings ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Sanitize connection.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value Value.
	 *
	 * @return array
	 */
	public function sanitize_connections( $value ) {
		$connections = $value;

		foreach ( $connections as $connection_id => $connection ) {
			$integration = Loader::get_instance()->get_integration( $connection['integration'] );

			if ( ! $integration ) {
				unset( $connections[ $connection_id ] );
				continue;
			}

			$attributes = $integration->get_attributes_schema();
			/* translators: %s: integration name */
			$settings                                  = rest_sanitize_value_from_schema( $connection['settings'], $attributes, sprintf( '(%s) integration', $integration->name ) );
			$connections[ $connection_id ]['settings'] = $settings;
		}

		return $connections;
	}

	/**
	 * Get trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param object $object Object.
	 *
	 * @return array
	 */
	public function get_trigger( $object ) {
		$trigger = get_post_meta( $object['id'], 'trigger', true );

		return $trigger ?? '';
	}

	/**
	 * Update trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $value  Value.
	 * @param object $object Object.
	 *
	 * @return bool
	 */
	public function update_trigger( $value, $object ) {
		return update_post_meta( $object->ID, 'trigger', $value );
	}

	/**
	 * Get connections.
	 *
	 * @since 1.0.0
	 *
	 * @param object $object Object.
	 *
	 * @return array
	 */
	public function get_connections( $object ) {
		$connections = get_post_meta( $object['id'], 'connections', true );

		return ! empty( $connections ) ? $connections : array();
	}

	/**
	 * Update connections.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $value  Value.
	 * @param object $object Object.
	 *
	 * @return bool
	 */
	public function update_connections( $value, $object ) {
		return update_post_meta( $object->ID, 'connections', $value );
	}
}
