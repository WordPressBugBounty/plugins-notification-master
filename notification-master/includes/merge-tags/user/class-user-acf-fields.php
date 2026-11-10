<?php
/**
 * User ACF Fields Merge Tags
 *
 * This class is responsible for adding ACF fields as merge tags for user triggers.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Merge_Tags\User;

use Notification_Master\Abstracts\User_Trigger;
use Notification_Master\Abstracts\Merge_Tags_Group;
use Notification_Master\Utils;

/**
 * User ACF Fields Merge Tags class.
 */
class User_ACF_Fields extends Merge_Tags_Group {

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
	 */
	public function __construct() {
		parent::__construct();
		$this->plugin_dependency_active = Utils::is_acf_active();
	}

	/**
	 * User object.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_User
	 */
	public $user;

	/**
	 * Get name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'User ACF Fields', 'notification-master' );
	}

	/**
	 * Get slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'user_acf_fields';
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
		$this->user = $trigger->user ?? null;
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

		// Get all ACF field groups for users.
		$field_groups = acf_get_field_groups( array( 'user_form' => 'all' ) );

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
		if ( ! $this->user ) {
			return '';
		}

		return apply_filters( 'ntfm_user_acf_field_value', $tag, $this );
	}
}
