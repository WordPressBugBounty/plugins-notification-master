<?php
/**
 * Class Loader
 *
 * This class is responsible for loading all the integrations.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;
use Notification_Master\Integrations\Email_Integration;
use Notification_Master\Integrations\Webhook_Integration;
use Notification_Master\Integrations\Discord_Integration;
use Notification_Master\Integrations\WebPush_Integration;
use Notification_Master\Integrations\Instagram_Integration;

use function Notification_Master\Notification_Master;

/**
 * Loader class.
 */
class Loader {

	/**
	 * Integrations.
	 *
	 * @since 1.0.0
	 *
	 * @var Integration[]
	 */
	private $integrations = array();

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
		$this->load_integrations();
		add_filter( 'notification_master_integrations', array( $this, 'load_pro_integrations' ) );
	}

	/**
	 * Register integration.
	 *
	 * @param Integration $integration Integration object.
	 *
	 * @since 1.0.0
	 */
	public function register_integration( $integration ) {
		// Check if instance of Integration.
		if ( ! ( $integration instanceof Integration ) ) {
			return;
		}

		// Check if integration already exists.
		if ( isset( $this->integrations[ $integration->slug ] ) ) {
			return;
		}

		$this->integrations[ $integration->slug ] = $integration;
	}

	/**
	 * Load integrations.
	 *
	 * @since 1.0.0
	 */
	private function load_integrations() {
		$integrations = apply_filters(
			'notification_master_integrations_load',
			array(
				WebPush_Integration::class,
				Email_Integration::class,
				Webhook_Integration::class,
				Discord_Integration::class,
				Instagram_Integration::class,
			)
		);

		foreach ( $integrations as $class ) {
			$integration = new $class();
			$this->register_integration( $integration );
		}
	}

	/**
	 * Get integrations.
	 *
	 * @since 1.0.0
	 *
	 * @return Integration[]
	 */
	public function get_integrations() {
		return $this->integrations;
	}

	/**
	 * Get integration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Integration slug.
	 *
	 * @return Integration
	 */
	public function get_integration( $slug ) {
		if ( isset( $this->integrations[ $slug ] ) ) {
			return $this->integrations[ $slug ];
		} else {
			Notification_Master()->logger->error(
				'invalid_integration',
				array(
					'slug' => $slug,
				)
			);
		}

		return null;
	}

	/**
	 * Load pro integrations.
	 *
	 * @param array $integrations Integrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function load_pro_integrations( $integrations ) {
		$pro_integrations = array(
			'facebook' => array(
				'name'        => __( 'Facebook', 'notification-master' ),
				'description' => __( 'Send notifications to Facebook.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/facebook.svg',
			),
			'twitterx' => array(
				'name'        => __( 'X', 'notification-master' ),
				'description' => __( 'Send notifications to TwitterX.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/twitterx.svg',
			),
			'twilio'   => array(
				'name'        => __( 'Twilio', 'notification-master' ),
				'description' => __( 'Send notifications to twilio.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/twilio.svg',
			),
			'slack'    => array(
				'name'        => __( 'Slack', 'notification-master' ),
				'description' => __( 'Send notifications to Slack.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/slack.png',
			),
			'zapier'   => array(
				'name'        => __( 'Zapier', 'notification-master' ),
				'description' => __( 'Send notifications to Zapier.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/zapier.svg',
			),
			'make'     => array(
				'name'        => __( 'Make', 'notification-master' ),
				'description' => __( 'Send notifications to Make.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/make.png',
			),
			'whatsapp' => array(
				'name'        => __( 'WhatsApp', 'notification-master' ),
				'description' => __( 'Send your notifications to WhatsApp.', 'notification-master' ),
				'icon'        => NOTIFICATION_MASTER_URL . 'assets/integrations/whatsapp.svg',
			),
		);

		return array_merge( $integrations, $pro_integrations );
	}
}
