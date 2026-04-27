<?php
/**
 * Erased Personal Data Trigger
 *
 * This class is responsible for triggering notifications when personal data is erased.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Privacy;

use Notification_Master\Abstracts\Privacy_Trigger;

/**
 * Erased Personal Data class.
 */
class Erased_Personal_Data extends Privacy_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Erased Personal Data';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'erased_personal_data';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when personal data is erased.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'wp_privacy_personal_data_erased';
		add_action( $this->hook, array( $this, 'process' ) );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int $request_id Request ID.
	 */
	public function process( $request_id ) {
		$request    = wp_get_user_request( $request_id );
		$this->user = get_userdata( $request->user_id );

		$this->do_connections();
	}
}
