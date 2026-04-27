<?php
/**
 * Class Taxonomy Merge Tags
 *
 * This class is responsible for adding merge tags for taxonomy triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Taxonomy_Type;

use Notification_Master\Abstracts\Taxonomy_Merge_Tags_Group;

/**
 * Taxonomy Merge Tags class.
 */
class Taxonomy extends Taxonomy_Merge_Tags_Group {

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->taxonomy_object->labels->singular_name;
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->taxonomy;
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		$this->merge_tags = apply_filters(
			"ntfm_taxonomy_merge_tags_{$this->taxonomy}",
			array(
				'term_id'          => array(
					'label'       => __( 'Term ID', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The ID of the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'term_name'        => array(
					'label'       => __( 'Term Name', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The name of the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'term_slug'        => array(
					'label'       => __( 'Term Slug', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The slug of the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'term_description' => array(
					'label'       => __( 'Description', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The description of the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'term_url'         => array(
					'label'       => __( 'URL', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The URL of the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'date'             => array(
					'label'       => __( 'Action Date', 'notification-master' ),
					/* translators: %s: Taxonomy singular name */
					'description' => sprintf( __( 'The date of the action for the %s term.', 'notification-master' ), $this->taxonomy_object->labels->singular_name ),
				),
				'name'             => array(
					'label'       => __( 'Name', 'notification-master' ),
					'description' => __( 'The Taxonomy name.', 'notification-master' ),
				),
				'slug'             => array(
					'label'       => __( 'Slug', 'notification-master' ),
					'description' => __( 'The Taxonomy slug.', 'notification-master' ),
				),
			)
		);
	}

	/**
	 * Get value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag Merge tag.
	 *
	 * @return mixed
	 */
	public function get_value( $tag ) {
		if ( empty( $this->args ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'term_id':
				return $this->args['term_id'];
			case 'term_name':
				return $this->args['term_name'];
			case 'term_slug':
				return $this->args['term_slug'];
			case 'term_description':
				return $this->args['term_description'];
			case 'term_url':
				return $this->args['term_url'];
			case 'date':
				return $this->args['date'];
			case 'name':
				return $this->get_name();
			case 'slug':
				return $this->get_slug();
		}
	}
}
