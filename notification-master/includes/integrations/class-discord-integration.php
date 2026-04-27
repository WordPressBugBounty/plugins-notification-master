<?php
/**
 * Class Discord Integration
 *
 * This class is responsible for sending discord notifications.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;

use function Notification_Master\Notification_Master;

/**
 * Discord Integration class.
 */
class Discord_Integration extends Integration {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Discord';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'discord';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send your notifications to Discord.';

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		$valid = $this->validate_attributes();
		if ( ! $valid ) {
			Notification_Master()->logger->error(
				'discord_integration_invalid_attributes',
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
		$webhook = $this->attributes['url'];
		$message = $this->attributes['message'];

		$this->send_notification( $webhook, $message );
	}

	/**
	 * Send message.
	 *
	 * @param string $webhook Webhook.
	 * @param array  $message Message.
	 *
	 * @since 1.0.0
	 */
	public function send_notification( $webhook, $message ) {
		$response = wp_remote_post(
			$webhook,
			array(
				'body'    => wp_json_encode(
					array(
						'content' => $message['content'],
						'embeds'  => array(
							array(
								'title'       => $message['title'],
								'title_link'  => $message['title_link'],
								'description' => $message['description'],
								'color'       => $message['color'],
								'author'      => array(
									'name'     => $message['author']['name'],
									'url'      => $message['author']['url'],
									'icon_url' => $message['author']['icon_url'],
								),
								'fields'      => $message['fields'],
							),
						),
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'webhook'  => $webhook,
						'message'  => __( 'Error sending request.', 'notification-master' ),
						'response' => $response,
					)
				)
			);
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 204 !== $response_code ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'webhook'  => $webhook,
						'message'  => __( 'Error sending request.', 'notification-master' ),
						'response' => $response,
					)
				)
			);
		}

		Notification_Master()->notification_logger->success(
			$this->slug,
			$this->prepare_response(
				array(
					'webhook'  => $webhook,
					'message'  => __( 'Request sent successfully.', 'notification-master' ),
					'response' => $response,
				)
			)
		);
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
				'url'     => array(
					'type'                          => 'string',
					'required'                      => true,
					'sanitize_and_prepare_callback' => 'esc_url_raw',
				),
				'message' => array(
					'type'                          => 'object',
					'required'                      => true,
					'properties'                    => array(
						'title'       => array(
							'type' => 'string',
						),
						'title_link'  => array(
							'type'   => 'string',
							'format' => 'text-field',
						),
						'description' => array(
							'type'   => 'string',
							'format' => 'text-field',
						),
						'content'     => array(
							'type'   => 'string',
							'format' => 'text-field',
						),
						'author'      => array(
							'type'       => 'object',
							'properties' => array(
								'name'     => array(
									'type'   => 'string',
									'format' => 'text-field',
								),
								'url'      => array(
									'type'   => 'string',
									'format' => 'text-field',
								),
								'icon_url' => array(
									'type'   => 'string',
									'format' => 'text-field',
								),
							),
						),
						'fields'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'   => array(
										'type'   => 'string',
										'format' => 'text-field',
									),
									'value'  => array(
										'type'   => 'string',
										'format' => 'text-field',
									),
									'inline' => array(
										'type' => 'boolean',
									),
								),
							),
						),
					),
					'sanitize_and_prepare_callback' => array( $this, 'sanitize_and_prepare_message' ),
				),
			),
		);
	}

	/**
	 * Sanitize and prepare message.
	 *
	 * @param array $message Message.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function sanitize_and_prepare_message( $message ) {
		$fields = array();

		foreach ( $message['fields'] ?? array() as $field ) {
			if ( ! isset( $field['name'] ) ) {
				continue;
			}

			$fields[] = array(
				'name'   => sanitize_text_field( $field['name'] ?? '' ),
				'value'  => sanitize_text_field( $field['value'] ?? '' ),
				'inline' => (bool) $field['inline'] ?? false,
			);
		}

		return array(
			'title'       => sanitize_text_field( $message['title'] ?? '' ),
			'title_link'  => esc_url( $message['title_link'] ?? '' ),
			'description' => sanitize_text_field( $message['description'] ?? '' ),
			'content'     => sanitize_text_field( $message['content'] ?? '' ),
			'color'       => hexdec( 'E6F9FF' ),
			'author'      => array(
				'name'     => sanitize_text_field( $message['author']['name'] ?? '' ),
				'url'      => esc_url( $message['author']['url'] ?? '' ),
				'icon_url' => esc_url( $message['author']['icon_url'] ?? '' ),
			),
			'fields'      => $fields,
		);
	}
}
