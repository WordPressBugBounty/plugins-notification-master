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
	 * Notification ID.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public $notification_id = 0;

	/**
	 * Connection ID (key in the notification's connections).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $connection_id = '';

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
		add_action( 'notification_master_before_process_connection', array( $this, 'set_args' ), 10, 4 );
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
	 * @param string $connection_id   Connection ID (since 2.0.0).
	 *
	 * @return void
	 */
	public function set_args( $connection_settings, $trigger, $notification_id, $connection_id = '' ) {
		$this->attributes        = $connection_settings;
		$this->trigger           = $trigger;
		$this->notification_name = get_the_title( $notification_id );
		$this->notification_id   = absint( $notification_id );
		$this->connection_id     = is_scalar( $connection_id ) ? (string) $connection_id : '';
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
		$this->notification_id   = 0;
		$this->connection_id     = '';
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
		if ( $this->trigger ) {
			$response['trigger']      = $this->trigger->get_slug();
			$response['trigger_name'] = $this->trigger->get_name();
		}
		$response['notification_name'] = $this->notification_name;
		if ( $this->notification_id ) {
			$response['notification_id'] = (int) $this->notification_id;
		}
		if ( '' !== (string) $this->connection_id ) {
			$response['connection_id'] = (string) $this->connection_id;
		}

		return $response;
	}

	/**
	 * Whether this integration can be sent as a test.
	 *
	 * @since 2.0.0
	 *
	 * @return true|\WP_Error True, or an error that says why not.
	 */
	public function can_test() {
		return true;
	}

	/**
	 * Adjust connection settings before a test send (e.g. the recipient).
	 *
	 * @since 2.0.0
	 *
	 * @param array   $settings  Connection settings as posted.
	 * @param string  $recipient Optional override recipient.
	 * @param Trigger $trigger   Trigger with sample data.
	 *
	 * @return array|\WP_Error Settings to send with, or an error.
	 */
	public function prepare_test_attributes( $settings, $recipient, $trigger ) {
		return $settings;
	}
}
