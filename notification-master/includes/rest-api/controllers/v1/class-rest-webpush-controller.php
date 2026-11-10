<?php
/**
 * Class Rest_Webpush_Controller
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\WebPush\Health;
use Notification_Master\WebPush\Preview;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Web Push health and preview endpoints.
 */
class Rest_Webpush_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'webpush';

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_preview' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'settings' => array(
							'type'        => 'object',
							'default'     => array(),
							'description' => __( 'Unsaved button and bell settings.', 'notification-master' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Markup + CSS the front end would print for unsaved button/bell settings. Nothing is saved.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_preview( $request ) {
		$patch = $request->get_param( 'settings' );

		return new WP_REST_Response( Preview::render( is_array( $patch ) ? $patch : array() ), 200 );
	}

	/**
	 * Run the health checks.
	 *
	 * @since 2.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_health() {
		return new WP_REST_Response( Health::run(), 200 );
	}

	/**
	 * Admins only.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}
}
