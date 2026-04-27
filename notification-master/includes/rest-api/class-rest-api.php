<?php
/**
 * Class REST_API
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\REST_API;

use Notification_Master\REST_API\Controllers\V1\Rest_Settings_Controller;
use Notification_Master\REST_API\Controllers\V1\Rest_Notification_Controller;
use Notification_Master\REST_API\Controllers\V1\Rest_Logs_Controller;
use Notification_Master\REST_API\Controllers\V1\Rest_Notification_Logs_Controller;
use Notification_Master\REST_API\Controllers\V1\Rest_Subscriptions_Controller;

/**
 * REST_API class.
 */
class REST_API {

	/**
	 * Instance.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Get instance.
	 *
	 * @since 1.0.0
	 *
	 * @return REST_API
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new REST_API();
		}
		return self::$instance;
	}

	/**
	 * REST_API constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register rest routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		$controllers = array(
			Rest_Settings_Controller::class,
			Rest_Notification_Controller::class,
			Rest_Logs_Controller::class,
			Rest_Notification_Logs_Controller::class,
			Rest_Subscriptions_Controller::class,
		);

		foreach ( $controllers as $controller ) {
			$controller = new $controller();
			/** @var Rest_Controller $controller */
			$controller->register_routes();
		}
	}
}
