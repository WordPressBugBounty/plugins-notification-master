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
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Tier;
use WP_Error;

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

		// The CPT stores connection credentials and recipients: only administrators may touch it over REST.
		add_filter( 'rest_request_before_callbacks', array( $this, 'require_admin_for_notifications' ), 9, 3 );

		// Core wraps field validation errors as 400; unwrap our Pro refusal so clients see 403 ntfm_pro_required.
		add_filter( 'rest_request_before_callbacks', array( $this, 'surface_pro_refusal' ), 10, 3 );
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
						'validate_callback' => array( $this, 'validate_trigger' ),
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
						'schedule'    => array(
							'type'        => 'object',
							'description' => __( 'Per-connection scheduling settings.', 'notification-master' ),
							'properties'  => array(
								'enabled'            => array(
									'type'    => 'boolean',
									'default' => false,
								),
								'mode'               => array(
									'type'    => 'string',
									'enum'    => array( 'delay', 'merge_tag_relative' ),
									'default' => 'delay',
								),
								'delay'              => array(
									'type'       => 'object',
									'properties' => array(
										'value' => array(
											'type'    => 'integer',
											'minimum' => 0,
											'maximum' => 525600,
											'default' => 0,
										),
										'unit'  => array(
											'type'    => 'string',
											'enum'    => array( 'minutes', 'hours', 'days' ),
											'default' => 'minutes',
										),
									),
								),
								'merge_tag_relative' => array(
									'type'       => 'object',
									'properties' => array(
										'tag'        => array(
											'type'    => 'string',
											'default' => '',
										),
										'offset'     => array(
											'type'    => 'integer',
											'minimum' => 0,
											'maximum' => 525600,
											'default' => 0,
										),
										'unit'       => array(
											'type'    => 'string',
											'enum'    => array( 'minutes', 'hours', 'days' ),
											'default' => 'minutes',
										),
										'direction'  => array(
											'type'    => 'string',
											'enum'    => array( 'before', 'after' ),
											'default' => 'after',
										),
										'on_past'    => array(
											'type'    => 'string',
											'enum'    => array( 'send_now', 'skip' ),
											'default' => 'send_now',
										),
										'on_invalid' => array(
											'type'    => 'string',
											'enum'    => array( 'send_now', 'skip' ),
											'default' => 'send_now',
										),
									),
								),
							),
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
	 * @return bool|WP_Error
	 */
	public function validate_connections( $value ) {
		// Core's type check is bypassed by the arg_options callbacks, so refuse non-objects here.
		if ( null === $value || array() === $value ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'connections must be an object keyed by connection id.', 'notification-master' ),
				array( 'status' => 400 )
			);
		}

		$connections = $value;

		// One source of truth for what Free may not store (Pro channel, conditions).
		$pro_key = Tier::pro_only_connection_key( $connections );
		if ( null !== $pro_key ) {
			$pro_slug = isset( $connections[ $pro_key ]['integration'] ) ? (string) $connections[ $pro_key ]['integration'] : '';
			$what     = Tier::is_pro_integration( $pro_slug )
				/* translators: %s: integration slug */
				? sprintf( __( 'The "%s" channel', 'notification-master' ), $pro_slug )
				: __( 'Connection conditions', 'notification-master' );

			return Tier::pro_required_error( $what, array( 'connection_id' => $pro_key ) );
		}

		// Never get_integration(): it logs an invalid_integration row for unknown slugs.
		$integrations = Loader::get_instance()->get_integrations();

		foreach ( $connections as $connection_id => $connection ) {
			$slug = isset( $connection['integration'] ) ? (string) $connection['integration'] : '';

			if ( ! isset( $integrations[ $slug ] ) ) {
				return new WP_Error(
					'rest_invalid_param',
					sprintf(
						/* translators: 1: integration slug, 2: registered slugs */
						__( 'Unknown integration "%1$s". Registered integrations: %2$s.', 'notification-master' ),
						$slug,
						implode( ', ', array_keys( $integrations ) )
					),
					array( 'status' => 400 )
				);
			}

			$integration = $integrations[ $slug ];
			$attributes  = $integration->get_attributes_schema();
			$settings    = isset( $connection['settings'] ) ? $connection['settings'] : null;
			/* translators: %s: integration name */
			$settings = rest_validate_object_value_from_schema( $settings, $attributes, sprintf( '(%s) integration', $integration->name ) );

			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
		}

		return true;
	}

	/**
	 * Validate the trigger slug of a notification.
	 *
	 * Accepts '', the value already stored on the post (so a notification whose
	 * trigger family was disabled still saves) and any registered slug. Refuses
	 * Pro triggers on Free with ntfm_pro_required and unknown slugs with
	 * rest_invalid_param.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_trigger( $value, $request, $param ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'trigger must be a string.', 'notification-master' ),
				array( 'status' => 400 )
			);
		}

		$value  = null === $value ? '' : (string) $value;
		$stored = isset( $request['id'] ) ? (string) get_post_meta( (int) $request['id'], 'trigger', true ) : '';

		if ( '' === $value ) {
			// A draft may still have no trigger; a saved trigger is never blanked by an empty or null value.
			if ( '' !== $stored ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'trigger cannot be removed from a saved notification. Pick another trigger instead.', 'notification-master' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}

		if ( '' !== $stored && $stored === $value ) {
			return true;
		}

		$triggers = Triggers_Loader::get_instance()->get_triggers();
		if ( isset( $triggers[ $value ] ) ) {
			return true;
		}

		if ( Tier::is_pro_trigger( $value ) && ! Tier::is_pro() ) {
			/* translators: %s: trigger slug */
			return Tier::pro_required_error( sprintf( __( 'The "%s" trigger', 'notification-master' ), $value ) );
		}

		return new WP_Error(
			'rest_invalid_param',
			/* translators: %s: trigger slug */
			sprintf( __( 'Unknown trigger "%s".', 'notification-master' ), $value ),
			array(
				'status'  => 400,
				'allowed' => array_keys( $triggers ),
			)
		);
	}

	/**
	 * Refuse every /wp/v2/ntfm_notification* request from a user without manage_options.
	 *
	 * Runs before the route's own permission callback, so a published
	 * notification is never readable anonymously.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed           $response Result to send, or a WP_Error.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function require_admin_for_notifications( $response, $handler, $request ) {
		if ( ! ( $request instanceof \WP_REST_Request ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/wp/v2/ntfm_notification' ) || current_user_can( 'manage_options' ) ) {
			return $response;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'notification-master' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	/**
	 * Unwrap a Pro refusal that core folded into rest_invalid_param, on this plugin's routes only.
	 *
	 * On WordPress below 5.7 the nested details are absent and the response is returned unchanged.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed           $response Result to send, or a WP_Error.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function surface_pro_refusal( $response, $handler, $request ) {
		if ( ! is_wp_error( $response ) || 'rest_invalid_param' !== $response->get_error_code() || ! ( $request instanceof \WP_REST_Request ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/wp/v2/ntfm_notification' ) && 0 !== strpos( $route, '/ntfm/v1/' ) ) {
			return $response;
		}

		$data = $response->get_error_data();
		if ( ! isset( $data['details'] ) || ! is_array( $data['details'] ) ) {
			return $response;
		}

		foreach ( $data['details'] as $param => $detail ) {
			if ( ! isset( $detail['code'] ) || 'ntfm_pro_required' !== $detail['code'] ) {
				continue;
			}

			$inner           = isset( $detail['data'] ) && is_array( $detail['data'] ) ? $detail['data'] : array();
			$inner['status'] = 403;
			$inner['param']  = (string) $param;

			return new WP_Error( 'ntfm_pro_required', isset( $detail['message'] ) ? (string) $detail['message'] : '', $inner );
		}

		return $response;
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
		// null means "not sent" and must stay null so the stored meta is left alone.
		if ( ! is_array( $value ) ) {
			return null === $value ? null : array();
		}

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
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$trigger = get_post_meta( $object['id'], 'trigger', true );

		return $trigger ? (string) $trigger : '';
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
		$value = is_scalar( $value ) ? (string) $value : '';

		// Defence in depth behind the validator: a saved trigger is never blanked.
		if ( '' === $value && '' !== (string) get_post_meta( $object->ID, 'trigger', true ) ) {
			return true;
		}

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
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$connections = get_post_meta( $object['id'], 'connections', true );

		// A non-array left behind by an older build reads as an empty list, so the next save normalises it.
		return is_array( $connections ) ? $connections : array();
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
		if ( ! is_array( $value ) ) {
			return false;
		}

		return update_post_meta( $object->ID, 'connections', $value );
	}
}
