<?php
/**
 * Webhook Integration
 *
 * This class is responsible for sending webhook notifications.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;

use function Notification_Master\Notification_Master;

/**
 * Webhook Integration class.
 */
class Webhook_Integration extends Integration {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Webhook';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'webhook';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send webhook notifications.';

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		$valid = $this->validate_attributes();
		if ( ! $valid ) {
			Notification_Master()->logger->error(
				'webhook_integration_invalid_attributes',
				$this->prepare_response(
					array(
						'message'    => __( 'Invalid attributes.', 'notification-master' ),
						'attributes' => $this->attributes,
					)
				)
			);
			return;
		}

		// This function is used to get the values of merge tags and sanitize them.
		$this->process_attributes();
		$url         = $this->attributes['url'];
		$method      = $this->attributes['method'];
		$headers     = $this->attributes['headers'] ?? array();
		$body_format = $this->attributes['body_format'];
		$body        = $this->attributes['body'] ?? array();

		// Show empty fields.
		if ( $this->attributes['show_empty_fields'] ?? false ) {
			$body = array_filter(
				$body,
				function ( $value ) {
					return ! empty( $value );
				}
			);
		}

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			if ( 'json' === $body_format ) {
				$headers['Content-Type'] = 'application/json';
				$body                    = wp_json_encode( $body );
			} else {
				$body = $body;
			}
		} else {
			$body = http_build_query( $body );
			$url  = add_query_arg( $body, $url );
		}

		$this->send_notification( $url, $method, $headers, $body );
	}

	/**
	 * Send notification.
	 *
	 * @param string $url     URL.
	 * @param string $method  Method.
	 * @param array  $headers Headers.
	 * @param array  $body    Body.
	 *
	 * @since 1.0.0
	 */
	public function send_notification( $url, $method, $headers, $body ) {
		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ? $body : null,
			)
		);

		if ( is_wp_error( $response ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'  => __( 'Error sending request.', 'notification-master' ),
						'url'      => $url,
						'method'   => $method,
						'headers'  => $headers,
						'body'     => $body,
						'response' => $response,
					)
				)
			);
		} else {
			Notification_Master()->notification_logger->success(
				$this->slug,
				$this->prepare_response(
					array(
						'message'  => __( 'Notification sent successfully.', 'notification-master' ),
						'url'      => $url,
						'method'   => $method,
						'headers'  => $headers,
						'body'     => $body,
						'response' => $response,
					)
				)
			);
		}
	}

	/**
	 * Get attributes schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_attributes_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'url'               => array(
					'type'                          => 'string',
					'required'                      => true,
					'sanitize_and_prepare_callback' => 'esc_url_raw',
				),
				'method'            => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
				),
				'headers'           => array(
					'type'                          => 'array',
					'required'                      => false,
					'items'                         => array(
						'type'       => 'object',
						'properties' => array(
							'key'   => array(
								'type' => 'string',
							),
							'value' => array(
								'type' => 'string',
							),
						),
					),
					'sanitize_and_prepare_callback' => array( $this, 'sanitize_and_prepare_array' ),
					'default'                       => array(),
				),
				'body_format'       => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array( 'json', 'form-data' ),
				),
				'body'              => array(
					'type'                          => 'array',
					'required'                      => false,
					'items'                         => array(
						'type'       => 'object',
						'properties' => array(
							'key'   => array(
								'type' => 'string',
							),
							'value' => array(
								'type' => 'string',
							),
						),
					),
					'sanitize_and_prepare_callback' => array( $this, 'sanitize_and_prepare_array' ),
					'default'                       => array(),
				),
				'show_empty_fields' => array(
					'type'                          => 'boolean',
					'sanitize_and_prepare_callback' => 'rest_sanitize_boolean',
					'default'                       => false,
				),
			),
		);
	}

	/**
	 * Sanitize array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $array Headers.
	 *
	 * @return array
	 */
	public function sanitize_and_prepare_array( $array ) {
		$sanitized_array = array();

		foreach ( $array as $array_item ) {
			$key                     = sanitize_text_field( $array_item['key'] );
			$value                   = sanitize_text_field( $array_item['value'] );
			$sanitized_array[ $key ] = $value;
		}

		return $sanitized_array;
	}
}
