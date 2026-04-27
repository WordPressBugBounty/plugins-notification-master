<?php
/**
 * Class Taxonomy_Merge_Tags_Group
 *
 * This class is responsible for adding merge tags for taxonomy triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Abstracts\Merge_Tags_Group;

/**
 * Taxonomy Merge Tags Group class.
 */
class Taxonomy_Merge_Tags_Group extends Merge_Tags_Group {

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
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function __construct( $taxonomy ) {
		$this->taxonomy        = $taxonomy;
		$this->taxonomy_object = get_taxonomy( $this->taxonomy );
		parent::__construct();
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {}

	/**
	 * Set trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param Trigger $trigger Trigger.
	 */
	public function set_trigger( $trigger ) {
		parent::set_trigger( $trigger );
		$this->args = $trigger->args ?? null;
	}
}
