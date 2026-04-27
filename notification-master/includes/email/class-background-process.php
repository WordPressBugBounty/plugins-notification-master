<?php
/**
 * Background Process
 *
 * @package notification-master
 *
 * @since 1.4.6
 */

namespace Notification_Master\Email;

use Notification_Master\Utils;
use Notification_Master\Integrations\Loader as Integrations_Loader;

/**
 * Background Process class.
 */
class Background_Process {

	/**
	 * Action name.
	 *
	 * @since 1.4.6
	 *
	 * @var string
	 */
	public $action_name = 'email';

	/**
	 * Instance of this class.
	 *
	 * @since 1.4.6
	 *
	 * @var Background_Process
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.4.6
	 *
	 * @return Background_Process
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Background_Process ) ) {
			self::$instance = new Background_Process();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.4.6
	 */
	private function __construct() {
		$hook = Utils::get_action_hook( $this->action_name );
		add_action( $hook, array( $this, 'process_batch' ) );
	}

	/**
	 * Process batch.
	 *
	 * @since 1.4.6
	 *
	 * @param string $option_name Option name.
	 *
	 * @return void
	 */
	public function process_batch( $option_name ) {
		$data  = get_option( $option_name, array() );
		$email = Integrations_Loader::get_instance()->get_integration( 'email' );
		$email->process_batch( $data );
		delete_option( $option_name );
	}
}
