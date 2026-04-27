<?php
/**
 * Export Personal Data.
 *
 * This class is responsible for exporting personal data.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Privacy;

use Notification_Master\Abstracts\Privacy_Trigger;

/**
 * Export Personal Data class.
 */
class Exported_Personal_Data extends Privacy_Trigger {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Exported Personal Data';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'exported_personal_data';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send notifications when personal data is exported.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->hook = 'wp_privacy_personal_data_export_file_created';
		add_action( $this->hook, array( $this, 'process' ), 10, 5 );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param string $archive_pathname The path to the export file.
	 * @param string $archive_url      The URL to the export file.
	 * @param string $html_report_pathname The path to the HTML report file.
	 * @param int    $request_id       The request ID.
	 * @param string $json_report_pathname The path to the JSON report file.
	 *
	 * @return void
	 */
	public function process( $archive_pathname, $archive_url, $html_report_pathname, $request_id, $json_report_pathname ) {
		$user          = wp_get_user_request( $request_id );
		$this->user    = get_userdata( $user->user_id );
		$this->archive = array(
			'archive_pathname'     => $archive_pathname,
			'archive_url'          => $archive_url,
			'html_report_pathname' => $html_report_pathname,
			'json_report_pathname' => $json_report_pathname,
		);

		$this->do_connections();
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return array(
			'user',
			'archive',
		);
	}
}
