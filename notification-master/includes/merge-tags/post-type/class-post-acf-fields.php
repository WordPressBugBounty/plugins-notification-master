<?php
/**
 * Post ACF Fields Merge Tags
 *
 * This class is responsible for adding ACF fields as merge tags for post triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\Post_Type;

use Notification_Master\Abstracts\Post_Merge_Tags_Group;
use Notification_Master\Utils;

/**
 * Post ACF Fields Merge Tags class.
 */
class Post_ACF_Fields extends Post_Merge_Tags_Group {

	/**
	 * Is advanced.
	 *
	 * @since 1.0.0
	 */
	protected $is_advanced = true;

	/**
	 * Depends on plugin.
	 *
	 * @since 1.5.0
	 *
	 * @var string
	 */
	protected $depends_on_plugin = 'Advanced Custom Fields';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Post type.
	 */
	public function __construct( $post_type ) {
		parent::__construct( $post_type );
		$this->plugin_dependency_active = Utils::is_acf_active();
	}

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		/* translators: %s: Post type singular name */
		return sprintf( __( '%s ACF Fields', 'notification-master' ), $this->post_type_object->labels->singular_name );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->post_type . '_acf_fields';
	}

	/**
	 * Set merge tags.
	 *
	 * @since 1.0.0
	 */
	public function set_merge_tags() {
		// Check if ACF is active.
		if ( ! Utils::is_acf_active() ) {
			return;
		}

		// Get all ACF field groups for this post type.
		$field_groups = acf_get_field_groups( array( 'post_type' => $this->post_type ) );
		if ( empty( $field_groups ) ) {
			return;
		}

		$this->merge_tags = array();

		// Loop through field groups.
		foreach ( $field_groups as $field_group ) {
			// Get fields for this field group.
			$fields = acf_get_fields( $field_group );

			// Loop through fields.
			foreach ( $fields as $field ) {
				$this->add_field_merge_tag( $field );
			}
		}
	}

	/**
	 * Add field merge tag.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field ACF field.
	 */
	protected function add_field_merge_tag( $field ) {
		// Skip fields with unsupported types.
		$unsupported_types = array(
			'repeater',
			'flexible_content',
			'clone',
			'group',
		);

		if ( in_array( $field['type'], $unsupported_types, true ) ) {
			// Handle nested fields if this is a group field.
			if ( 'group' === $field['type'] && isset( $field['sub_fields'] ) && ! empty( $field['sub_fields'] ) ) {
				foreach ( $field['sub_fields'] as $sub_field ) {
					// Prefix the name with the parent field name.
					$sub_field['name']  = $field['name'] . '_' . $sub_field['name'];
					$sub_field['label'] = $field['label'] . ' - ' . $sub_field['label'];
					$this->add_field_merge_tag( $sub_field );
				}
			}
			return;
		}

		$this->merge_tags[ $field['name'] ] = array(
			'label'       => $field['label'],
			'description' => isset( $field['instructions'] ) && $field['instructions'] ? $field['instructions'] : sprintf(
				/* translators: %1$s: Field type, %2$s: Field name */
				__( 'ACF field of type %1$s with name %2$s.', 'notification-master' ),
				$field['type'],
				$field['label']
			),
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
		if ( ! $this->post ) {
			return '';
		}

		return apply_filters( 'ntfm_post_acf_field_value', $tag, $this );
	}
}
