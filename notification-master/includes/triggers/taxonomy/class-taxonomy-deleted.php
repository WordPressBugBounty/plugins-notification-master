<?php
/**
 * Class Taxonomy_Deleted
 *
 * This class is responsible for triggering notifications when a taxonomy is deleted.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Taxonomy;

use Notification_Master\Abstracts\Taxonomy_Trigger;

/**
 * Taxonomy Deleted class.
 */
class Taxonomy_Deleted extends Taxonomy_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Deleted';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'deleted';

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'delete_term';

	/**
	 * Constructor.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function __construct( $taxonomy ) {
		parent::__construct( $taxonomy );
		add_action( $this->hook, array( $this, 'process' ), 10, 4 );
	}

	/**
	 * Get description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/* translators: %s: Taxonomy singular name */
		return sprintf( __( 'When a %s is deleted.', 'notification-master' ), $this->taxonomy_object->labels->singular_name );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @param object $deleted_term Deleted term.
	 */
	public function process( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		if ( ! $this->is_taxonomy( $taxonomy ) ) {
			return;
		}

		$this->args = array(
			'date'             => gmdate( 'Y-m-d H:i:s', time() ),
			'term_id'          => $term_id,
			'term_name'        => $deleted_term->name,
			'term_description' => $deleted_term->description,
			'term_slug'        => $deleted_term->slug,
			'term_url'         => __( 'N/A', 'notification-master' ),
		);

		$this->do_connections();
	}
}
