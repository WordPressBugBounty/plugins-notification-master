<?php
/**
 * Class Rest_Settings_Controller
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\REST_API\Controllers\V1\Rest_Controller;
use Notification_Master\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Settings controller class.
 */
class Rest_Settings_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'update_items_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_items' ),
					'permission_callback' => array( $this, 'delete_items_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
				),
			)
		);
	}

	/**
	 * Get schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'notification-master',
			'type'       => 'object',
			'properties' => array(
				'enable_background_processing'          => array(
					'description' => __( 'Enable background processing.', 'notification-master' ),
					'type'        => 'boolean',
				),
				'post_status_change_trigger'            => array(
					'type'        => 'boolean',
					'description' => __( 'Post status change trigger.', 'notification-master' ),
				),
				'post_types'                            => array(
					'type'        => 'array',
					'description' => __( 'Post types.', 'notification-master' ),
					'items'       => array(
						'type' => 'string',
					),
				),
				'taxonomy_term_change_trigger'          => array(
					'type'        => 'boolean',
					'description' => __( 'Taxonomy term change trigger.', 'notification-master' ),
				),
				'taxonomies'                            => array(
					'type'        => 'array',
					'description' => __( 'Taxonomies.', 'notification-master' ),
					'items'       => array(
						'type' => 'string',
					),
				),
				'comment_change_trigger'                => array(
					'type'        => 'boolean',
					'description' => __( 'Comment change trigger.', 'notification-master' ),
				),
				'comment_types'                         => array(
					'type'        => 'array',
					'description' => __( 'Comment types.', 'notification-master' ),
					'items'       => array(
						'type' => 'string',
					),
				),
				'user_change_trigger'                   => array(
					'type'        => 'boolean',
					'description' => __( 'User change trigger.', 'notification-master' ),
				),
				'theme_change_trigger'                  => array(
					'type'        => 'boolean',
					'description' => __( 'Theme change trigger.', 'notification-master' ),
				),
				'plugin_change_trigger'                 => array(
					'type'        => 'boolean',
					'description' => __( 'Plugin change trigger.', 'notification-master' ),
				),
				'media_change_trigger'                  => array(
					'type'        => 'boolean',
					'description' => __( 'Media change trigger.', 'notification-master' ),
				),
				'privacy_trigger'                       => array(
					'type'        => 'boolean',
					'description' => __( 'Privacy trigger.', 'notification-master' ),
				),
				'woocommerce_change_trigger'            => array(
					'type'        => 'boolean',
					'description' => __( 'WooCommerce change trigger.', 'notification-master' ),
				),
				'delete_logs_every'                     => array(
					'type'        => 'integer',
					'description' => __( 'Delete logs every.', 'notification-master' ),
				),
				'notifications_delete_logs_every'       => array(
					'type'        => 'integer',
					'description' => __( 'Notifications delete logs every.', 'notification-master' ),
				),
				'webpush_public_key'                    => array(
					'type'        => 'string',
					'description' => __( 'Web Push Public Key.', 'notification-master' ),
				),
				'webpush_private_key'                   => array(
					'type'        => 'string',
					'description' => __( 'Web Push Private Key.', 'notification-master' ),
				),
				'webpush_action_type'                   => array(
					'type'        => 'string',
					'description' => __( 'Web Push Action Type.', 'notification-master' ),
				),
				'webpush_enable_auto_delete_failed_subscriptions' => array(
					'type'        => 'boolean',
					'description' => __( 'Enable automatic deletion of failed subscriptions.', 'notification-master' ),
				),
				'webpush_auto_delete_failure_threshold' => array(
					'type'        => 'integer',
					'description' => __( 'Number of consecutive failures before auto-deleting subscription.', 'notification-master' ),
					'minimum'     => 1,
					'maximum'     => 50,
				),
				'auto_delete_after_failed_attempts'     => array(
					'type'        => 'integer',
					'description' => __( 'Auto delete subscription after failed attempts.', 'notification-master' ),
				),
			),
			'default'    => array(
				'enable_background_processing'             => false,
				'post_status_change_trigger'               => true,
				'post_types'                               => array( 'post', 'page' ),
				'taxonomy_term_change_trigger'             => true,
				'taxonomies'                               => array( 'category', 'post_tag' ),
				'comment_change_trigger'                   => true,
				'comment_types'                            => array( 'comment' ),
				'user_change_trigger'                      => true,
				'theme_change_trigger'                     => true,
				'plugin_change_trigger'                    => true,
				'media_change_trigger'                     => true,
				'privacy_trigger'                          => true,
				'delete_logs_every'                        => 30,
				'notifications_delete_logs_every'          => 30,
				'webpush_public_key'                       => '',
				'webpush_private_key'                      => '',
				'webpush_auto_prompt'                      => false,
				'webpush_enable_auto_delete_failed_subscriptions' => false,
				'webpush_auto_delete_failure_threshold'    => 5,
				'woocommerce_change_trigger'               => true,
				'normal_button_text'                       => __( 'Subscribe to Notifications!', 'notification-master' ),
				'normal_button_color'                      => '#ffffff',
				'normal_button_background_color'           => '#ff7900',
				'normal_button_hover_color'                => '#ffffff',
				'normal_button_hover_background_color'     => '#c75e02',
				'normal_button_padding'                    => '10 20 10 20',
				'normal_button_margin'                     => '10',
				'normal_button_border_radius'              => '5',
				'normal_button_unsubscribe_text'           => __( 'Unsubscribe!', 'notification-master' ),
				'normal_button_extra_class'                => '',
				'normal_button_id'                         => '',
				'enable_floating_button'                   => true,
				'enable_floating_button_animation'         => true,
				'enable_floating_button_tooltip'           => true,
				'floating_button_tooltip_subscribe_text'   => __( 'Subscribe!', 'notification-master' ),
				'floating_button_tooltip_unsubscribe_text' => __( 'Unsubscribe!', 'notification-master' ),
				'floating_button_color'                    => '#ffffff',
				'floating_button_background_color'         => '#ff7900',
				'floating_button_hover_color'              => '#ffffff',
				'floating_button_hover_background_color'   => '#c75e02',
				'floating_button_animation_color'          => 'rgba(181, 82, 20, 0.3)',
				'floating_button_width'                    => '50',
				'floating_button_height'                   => '50',
				'floating_button_border_radius'            => '50',
				'floating_button_position'                 => 'bottom-right',
				'floating_button_right'                    => array(
					'desktop' => '20',
					'tablet'  => '20',
					'mobile'  => '20',
				),
				'floating_button_bottom'                   => array(
					'desktop' => '20',
					'tablet'  => '20',
					'mobile'  => '20',
				),
				'floating_button_top'                      => array(
					'desktop' => '20',
					'tablet'  => '20',
					'mobile'  => '20',
				),
				'floating_button_left'                     => array(
					'desktop' => '20',
					'tablet'  => '20',
					'mobile'  => '20',
				),
				'floating_button_extra_class'              => '',
				'floating_button_id'                       => '',
				'floating_button_z_index'                  => '99999',
				'webpush_welcome_notification'             => true,
				'webpush_welcome_notification_title'       => __( 'Welcome!', 'notification-master' ),
				'webpush_welcome_notification_message'     => __( 'Thanks for subscribing to our notifications!', 'notification-master' ),
				'floating_button_hide_on_mobile'           => false,
				'floating_button_hide_on_desktop'          => false,
				'floating_button_hide_on_tablet'           => false,
				'floating_button_icon_type'                => 'default',
				'floating_button_custom_icon_id'           => 0,
				'floating_button_custom_icon_url'          => '',
			),
		);
	}

	/**
	 * Get items.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$schema   = $this->get_schema();
		$default  = $schema['default'];
		$settings = Settings::get_settings();
		$settings = wp_parse_args( $settings, $default );
		$response = rest_ensure_response( $settings );

		return $response;
	}

	/**
	 * Get items permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Update items.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function update_items( $request ) {
		$schema   = $this->get_schema();
		$default  = $schema['default'];
		$settings = Settings::get_settings( $default );
		$settings = wp_parse_args( $settings, $default );
		$params   = $request->get_param( 'settings' );
		foreach ( $params as $key => $value ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
			}
		}

		$updated  = Settings::update_settings( $settings );
		$response = rest_ensure_response( $updated );

		return $response;
	}

	/**
	 * Update items permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_Error
	 */
	public function update_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Delete items.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_items( $request ) {
		$schema   = $this->get_schema();
		$default  = $schema['default'];
		$settings = Settings::get_settings( $default );
		$params   = $request->get_param( 'settings' );
		foreach ( $params as $key => $value ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
			}
		}
		$updated  = Settings::update_settings( $settings );
		$response = rest_ensure_response( $updated );

		return $response;
	}

	/**
	 * Delete items permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_Error
	 */
	public function delete_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
