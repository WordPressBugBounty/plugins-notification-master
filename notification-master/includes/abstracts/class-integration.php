<?php
/**
 * Class Abstract Integration
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Abstracts;

use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;
use Notification_Master\Abstracts\Trigger;

/**
 * Abstract Integration class.
 */
abstract class Integration {

	/**
	 * Integration name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Integration slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Integration description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Attributes.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $attributes;

	/**
	 * Merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @var Merge_Tags_Loader
	 */
	public $merge_tags_loader;

	/**
	 * Notification.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $notification_name;

	/**
	 * Trigger.
	 *
	 * @since 1.0.0
	 *
	 * @var Trigger
	 */
	public $trigger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->merge_tags_loader = Merge_Tags_Loader::get_instance();
		add_action( 'notification_master_before_process_connection', array( $this, 'set_args' ), 10, 3 );
		add_action( 'notification_master_after_process_connection', array( $this, 'clear_args' ) );
		add_filter( 'notification_master_integrations', array( $this, 'register' ) );
	}

	/**
	 * Set trigger args.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $connection_settings Connection settings.
	 * @param string $trigger Trigger.
	 * @param int    $notification_id Notification ID.
	 *
	 * @return void
	 */
	public function set_args( $connection_settings, $trigger, $notification_id ) {
		$this->attributes        = $connection_settings;
		$this->trigger           = $trigger;
		$this->notification_name = get_the_title( $notification_id );
	}

	/**
	 * Clear args.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear_args() {
		$this->attributes        = array();
		$this->trigger           = '';
		$this->notification_name = null;
	}

	/**
	 * Register integration.
	 *
	 * @param array $integrations Integrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function register( $integrations ) {
		$integrations[ $this->slug ] = array(
			'name'        => $this->name,
			'description' => $this->description,
			'icon'        => $this->get_icon(),
			'properties'  => $this->get_attributes_schema()['properties'] ?? array(),
		);

		return $integrations;
	}

	/**
	 * Get attributes schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_attributes_schema() {
		return array();
	}

	/**
	 * Get attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Key.
	 *
	 * @return mixed
	 */
	public function get_attribute( $key ) {
		return $this->attributes[ $key ] ?? '';
	}

	/**
	 * Validate attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function validate_attributes() {
		$schema = $this->get_attributes_schema();
		$valid  = true;

		foreach ( $schema['properties'] as $key => $property ) {
			if ( ! isset( $this->attributes[ $key ] ) && ! ( $property['required'] ?? false ) ) {
				continue;
			}

			if ( ( $property['required'] ?? false ) && empty( $this->attributes[ $key ] ?? null ) ) {
				$valid = false;
				break;
			}
			$attr_valid = rest_validate_value_from_schema( $this->attributes[ $key ], $property, $key );
			if ( is_wp_error( $attr_valid ) ) {
				$valid = false;
				break;
			}
		}

		return $valid;
	}

	/**
	 * Process attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_attributes() {
		$schema = $this->get_attributes_schema();

		foreach ( $schema['properties'] as $key => $property ) {
			if ( ! isset( $this->attributes[ $key ] ) ) {
				$this->attributes[ $key ] = $property['default'] ?? '';
			}

			$this->attributes[ $key ] = $this->merge_tags_loader->process_merge_tags( $this->trigger, $this->attributes[ $key ] );
			if ( isset( $property['sanitize_and_prepare_callback'] ) ) {
				$this->attributes[ $key ] = call_user_func( $property['sanitize_and_prepare_callback'], $this->attributes[ $key ] );
			}
		}
	}

	/**
	 * Get icon.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_icon() {
		return NOTIFICATION_MASTER_URL . 'assets/integrations/' . $this->slug . '.svg';
	}

	/**
	 * Prepare Response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $response Response.
	 *
	 * @return array
	 */
	public function prepare_response( $response ) {
		$response['trigger']           = $this->trigger->get_slug();
		$response['trigger_name']      = $this->trigger->get_name();
		$response['notification_name'] = $this->notification_name;

		return $response;
	}
}
