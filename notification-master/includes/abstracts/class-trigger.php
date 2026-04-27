<?php
/**
 * Trigger Abstract
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Connections\Process as Connections_Process;

/**
 * Trigger Abstract class.
 */
abstract class Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Group.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $group;

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook;

	/**
	 * Merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $merge_tags = array();

	/**
	 * Construct.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'notification_master_triggers', array( $this, 'register_trigger' ) );
	}

	/**
	 * Register trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param array $triggers Triggers.
	 * @return array
	 */
	public function register_trigger( $triggers ) {
		if ( ! isset( $triggers[ $this->get_group() ] ) ) {
			return $triggers;
		}

		$triggers[ $this->get_group() ]['triggers'][ $this->get_slug() ] = array(
			'name'        => $this->get_name(),
			'description' => $this->get_description(),
		);

		return $triggers;
	}

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get group.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return $this->merge_tags;
	}

	/**
	 * Check if there are any notifications configured for this trigger
	 *
	 * @since 1.6.1
	 * @return bool True if notifications exist, false otherwise
	 */
	public function has_notifications() {
		$posts = get_posts(
			array(
				'post_type'   => 'ntfm_notification',
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'   => 'trigger',
						'value' => $this->get_slug(),
					),
				),
				'fields'      => 'ids',
			)
		);

		return ! empty( $posts );
	}

	/**
	 * Get all tigger connections
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function do_connections() {
		$posts = get_posts(
			array(
				'post_type'   => 'ntfm_notification',
				'post_status' => 'publish',
				'per_page'    => -1,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reason: Need to get all posts with this trigger meta.
					array(
						'key'   => 'trigger',
						'value' => $this->get_slug(),
					),
				),
				'fields'      => 'ids',
			)
		);

		foreach ( $posts as $id ) {
			$connections = get_post_meta( $id, 'connections', true ) ?? array();
			if ( empty( $connections ) ) {
				continue;
			}

			Connections_Process::get_instance()->process_connections( $connections, $this, $id );
		}
	}
}
