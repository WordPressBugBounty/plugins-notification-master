<?php
/**
 * Class Taxonomy_Updated
 *
 * This class is responsible for triggering notifications when a taxonomy is updated.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Triggers\Taxonomy;

use Notification_Master\Abstracts\Taxonomy_Trigger;

/**
 * Taxonomy Updated class.
 */
class Taxonomy_Updated extends Taxonomy_Trigger {

	/**
	 * Trigger name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'Updated';

	/**
	 * Trigger slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $slug = 'updated';

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = 'edit_term';

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
		return sprintf( __( 'When a %s is updated.', 'notification-master' ), $this->taxonomy_object->labels->singular_name );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id    Term ID.
	 * @param int    $tt_id      Term taxonomy ID.
	 * @param string $taxonomy   Taxonomy.
	 * @param mixed  $previous   Previous term.
	 */
	public function process( $term_id, $tt_id, $taxonomy, $previous ) {
		if ( ! $this->is_taxonomy( $taxonomy ) ) {
			return;
		}

		$term       = get_term( $term_id, $taxonomy );
		$this->args = array(
			'date'             => gmdate( 'Y-m-d H:i:s', time() ),
			'term_id'          => $term_id,
			'term_name'        => $term->name,
			'term_description' => $term->description,
			'term_slug'        => $term->slug,
			'term_url'         => get_term_link( $term_id, $taxonomy ),
		);

		$this->do_connections();
	}
}
