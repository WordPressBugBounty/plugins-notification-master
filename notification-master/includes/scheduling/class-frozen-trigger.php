<?php
/**
 * Frozen Trigger
 *
 * A minimal Trigger stand-in used at scheduled fire time. The settings were
 * already merge-tag-resolved at schedule time, so any second pass through the
 * merge-tag loader finds no `{{...}}` patterns and short-circuits — making the
 * lack of live trigger state (post, user, etc.) safe.
 *
 * @package notification-master
 *
 * @since 1.7.0
 */

namespace Notification_Master\Scheduling;

use Notification_Master\Abstracts\Trigger;

/**
 * Frozen_Trigger class.
 */
class Frozen_Trigger extends Trigger {

	/**
	 * Constructor.
	 *
	 * @param array $data Snapshot trigger payload (slug, name, merge_tags).
	 */
	public function __construct( $data ) {
		// Intentionally do NOT call parent::__construct() — we don't want this
		// stand-in to register itself with the `notification_master_triggers`
		// filter.
		$this->slug       = isset( $data['slug'] ) ? (string) $data['slug'] : '';
		$this->name       = isset( $data['name'] ) ? (string) $data['name'] : '';
		$this->merge_tags = isset( $data['merge_tags'] ) && is_array( $data['merge_tags'] ) ? $data['merge_tags'] : array();
	}
}
