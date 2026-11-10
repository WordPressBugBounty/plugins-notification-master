<?php
/**
 * Class Test_Trigger
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Testing;

use Notification_Master\Abstracts\Trigger;

/**
 * Wraps a sample trigger for a test send.
 *
 * Merge tags are resolved before the send, so it exposes no merge-tag groups and
 * the integration cannot blank out tags that had no sample value.
 */
class Test_Trigger extends Trigger {

	/**
	 * Wrapped sample trigger.
	 *
	 * @since 2.0.0
	 *
	 * @var Trigger
	 */
	private $inner;

	/**
	 * Constructor; skips the parent so it is never registered as a trigger.
	 *
	 * @since 2.0.0
	 *
	 * @param Trigger $inner Sample trigger.
	 */
	public function __construct( Trigger $inner ) {
		$this->inner = $inner;
	}

	/**
	 * Wrapped trigger name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->inner->get_name();
	}

	/**
	 * Wrapped trigger slug.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->inner->get_slug();
	}

	/**
	 * Wrapped trigger description.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->inner->get_description();
	}

	/**
	 * Wrapped trigger group.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_group() {
		return $this->inner->get_group();
	}

	/**
	 * No groups: tags were resolved up front.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_merge_tags() {
		return array();
	}

	/**
	 * Read a public property of the wrapped trigger.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Property.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return isset( $this->inner->$name ) ? $this->inner->$name : null;
	}

	/**
	 * Whether the wrapped trigger has a public property set.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Property.
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->inner->$name );
	}
}
