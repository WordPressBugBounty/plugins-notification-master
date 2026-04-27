<?php
/**
 * Privacy Export Personal Data Request Trigger
 *
 * This class is responsible for triggering notifications when a personal data export request is made.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Privacy;

use Notification_Master\Abstracts\Privacy_Trigger;

/**
 * Privacy Export Personal Data Request class.
 */
class Export_Personal_Data_Request extends Privacy_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Export Personal Data Request';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'export_personal_data_request';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when a personal data export request is made.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'user_request_action_confirmed';
		add_action( $this->hook, array( $this, 'process' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int $request_id The request ID.
	 */
	public function process( $request_id ) {
		$request    = wp_get_user_request( $request_id );
		$this->user = get_userdata( $request->user_id );

		$this->do_connections();
	}
}
