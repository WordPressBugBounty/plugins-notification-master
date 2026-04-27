<?php
/**
 * Class Taxonomy_Trigger
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Trigger;

/**
 * Taxonomy Trigger Abstract class.
 */
abstract class Taxonomy_Trigger extends Trigger {

	/**
	 * Taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * Taxonomy Object.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Taxonomy
	 */
	public $taxonomy_object;

	/**
	 * Args.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $args = array();

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function __construct( $taxonomy ) {
		$this->taxonomy        = $taxonomy;
		$this->taxonomy_object = get_taxonomy( $this->taxonomy );
		parent::__construct();
	}

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->taxonomy_object->labels->singular_name . ' ' . $this->name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->taxonomy_object->name . '-' . $this->slug;
	}

	/**
	 * Get Group.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_group() {
		return $this->taxonomy;
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
			$this->taxonomy,
		);
	}

	/**
	 * Is taxonomy type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return bool
	 */
	public function is_taxonomy( $taxonomy ) {
		return $this->taxonomy === $taxonomy;
	}
}
