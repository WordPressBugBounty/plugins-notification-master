<?php
/**
 * Privacy Archive Merge Tags Group
 *
 * This class is responsible for loading the Privacy Archive Merge Tags Group.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Privacy;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Privacy Archive Merge Tags Group class.
 */
class Archive extends Merge_Tags_Group {

	/**
	 * Archive.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $archive;

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Archive';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'archive';

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			'ntfm_archive_merge_tags',
			array(
				'archive_pathname'     => array(
					'label'       => __( 'Archive Pathname', 'notification-master' ),
					'description' => __( 'The pathname of the archive.', 'notification-master' ),
				),
				'archive_url'          => array(
					'label'       => __( 'Archive URL', 'notification-master' ),
					'description' => __( 'The URL of the archive.', 'notification-master' ),
				),
				'html_report_pathname' => array(
					'label'       => __( 'HTML Report Pathname', 'notification-master' ),
					'description' => __( 'The pathname of the HTML report.', 'notification-master' ),
				),
				'json_report_pathname' => array(
					'label'       => __( 'JSON Report Pathname', 'notification-master' ),
					'description' => __( 'The pathname of the JSON report.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->archive = $trigger->archive ?? null;
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return string
	 */
	public function get_value( $tag ) {
		if ( empty( $this->archive ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'archive_pathname':
				return $this->archive['archive_pathname'];
			case 'archive_url':
				return $this->archive['archive_url'];
			case 'html_report_pathname':
				return $this->archive['html_report_pathname'];
			case 'json_report_pathname':
				return $this->archive['json_report_pathname'];
		}
	}
}
