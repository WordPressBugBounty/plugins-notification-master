<?php
/**
 * Class WebPush Integration
 *
 * This class is responsible for sending webpush notifications.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Notification_Master\DB\Models\Subscription_Model;
use Notification_Master\Settings;
use Notification_Master\WebPush\Loader as WebPush_Loader;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Utils;

use function Notification_Master\Notification_Master;

/**
 * WebPush Integration class.
 */
class WebPush_Integration extends Integration {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'WebPush';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'webpush';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send your notifications to WebPush.';

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
	 * Process.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		if ( ! WebPush_Loader::get_instance()->is_configured() ) {
			Notification_Master()->logger->error(
				'webpush_integration_not_configured',
				$this->prepare_response(
					array(
						'message' => __( 'WebPush is not configured.', 'notification-master' ),
					)
				)
			);
			return;
		}

		$valid = $this->validate_attributes();
		if ( ! $valid ) {
			Notification_Master()->logger->error(
				'webpush_integration_invalid_attributes',
				$this->prepare_response(
					array(
						'message'    => __( 'Invalid attributes', 'notification-master' ),
						'attributes' => $this->attributes,
						'args'       => $this->args,
					)
				)
			);
			return;
		}
		// This function is used to get the values of merge tags and sanitize them.
		$this->process_attributes();

		$title   = $this->attributes['title'];
		$message = $this->attributes['message'];
		$icon    = $this->attributes['icon'] ?? '';
		$image   = $this->attributes['image'] ?? '';
		$url     = $this->attributes['url'] ?? '';
		$urgency = $this->attributes['urgency'] ?? 'normal';
		if ( empty( $message ) ) {
			return;
		}

		$this->send_notification( $title, $message, $icon, $image, $url, $urgency );
	}

	/**
	 * Send notification.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @param string $icon    Icon.
	 * @param string $image   Image.
	 * @param string $url     URL.
	 * @param string $urgency Urgency.
	 */
	public function send_notification( $title, $message, $icon, $image, $url, $urgency ) {
		$args = array(
			'notification' => array(
				'title'   => $title,
				'message' => $message,
				'icon'    => $icon,
				'image'   => $image,
				'url'     => $url,
				'urgency' => $urgency,
			),
			'page'         => 1,
			'args'         => array(
				'trigger'           => $this->trigger->get_slug(),
				'notification_name' => $this->notification_name,
				'send_to'           => $this->attributes['send_to'] ?? 'all',
				'specific_users'    => $this->get_valid_user_ids( $this->attributes['specific_users'] ?? array() ),
			),
		);
		$this->process_batch( $args );
	}

	/**
	 * Extract valid user IDs from an array of values.
	 *
	 * This function processes an array of values, checking each one to see if it is
	 * a merge tag or a user ID. It returns an array of IDs only, removing empty values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $values Array of user values (merge tags or user IDs).
	 *
	 * @return array Filtered array containing only valid IDs.
	 */
	function get_valid_user_ids( $values ) {
		if ( is_string( $values ) ) {
			$values = explode( ',', $values );
			$values = array_map( 'trim', $values );
			if ( empty( $values ) ) {
				return array();
			}
		}

		$ids = array();

		foreach ( $values as $value ) {
			$value = trim( $value );

			if ( empty( $value ) ) {
				continue;
			}

			// Check if the value is a user ID or a merge tag
			if ( strpos( $value, ':' ) !== false ) {
				list( $name, $id ) = explode( ':', $value, 2 );
				$id                = intval( trim( $id ) );
			} else {
				$id = $this->merge_tags_loader->process_merge_tags( $this->trigger, $value );
				if ( ! empty( $id ) ) {
					$id = intval( $id );
				}
			}

			if ( 0 < $id ) {
				$ids[] = $id;
			}
		}

		return array_filter( $ids );
	}

	/**
	 * Process batch.
	 *
	 * @since 1.2.1
	 *
	 * @param array $data|string $data Args.
	 *
	 * @return void
	 */
	public function process_batch( $data ) {
		$notification = $data['notification'] ?? array();
		$page         = $data['page'] ?? 1;
		$args         = $data['args'] ?? array();
		if ( empty( $notification ) || empty( $args ) ) {
			return;
		}

		if ( ! $this->trigger ) {
			$this->trigger = Triggers_Loader::get_instance()->get_trigger( $args['trigger'] );
		}

		if ( ! $this->notification_name ) {
			$this->notification_name = $args['notification_name'];
		}

		$title   = $notification['title'];
		$message = $notification['message'];
		$icon    = $notification['icon'];
		$image   = $notification['image'];
		$url     = ! empty( $notification['url'] ) ? $notification['url'] : home_url();
		$urgency = $notification['urgency'];
		$limit   = 20;

		$subscriptions = array();
		if ( 'specific_users' === $args['send_to'] ) {
			$subscriptions = Subscription_Model::get_subscriptions_by_user_ids( $args['specific_users'], $limit, $page, 'subscribed' );
		} else {
			$subscriptions = Subscription_Model::get_rows( $limit, $page, 'subscribed' );
		}

		if ( empty( $subscriptions ) ) {
			return;
		}

		$auth = array(
			'VAPID' => array(
				'subject'    => home_url(),
				'publicKey'  => Settings::get_setting( 'webpush_public_key' ),
				'privateKey' => Settings::get_setting( 'webpush_private_key' ),
			),
		);

		$webPush = new WebPush( $auth );

		foreach ( $subscriptions as $subscription ) {
			$endpoint         = $subscription->endpoint;
			$user_auth        = $subscription->auth;
			$p256dh           = $subscription->p256dh;
			$expiration_time  = $subscription->expiration_time;
			$content_encoding = $subscription->content_encoding;

			$subscription = Subscription::create(
				array(
					'endpoint'        => $endpoint,
					'keys'            => array(
						'auth'   => $user_auth,
						'p256dh' => $p256dh,
					),
					'expirationTime'  => $expiration_time,
					'contentEncoding' => $content_encoding,
				)
			);

			$webPush->queueNotification(
				$subscription,
				wp_json_encode(
					array(
						'title'   => $title,
						'message' => $message,
						'icon'    => $icon,
						'image'   => $image,
						'url'     => $url,
					)
				),
				array(
					'urgency' => $urgency,
				)
			);
		}

		/**
		 * Check sent results
		 *
		 * @var MessageSentReport $report
		 */
		foreach ( $webPush->flush() as $report ) {
			$endpoint = $report->getRequest()->getUri()->__toString();

			if ( $report->isSuccess() ) {
				// Record success and reset failure count.
				Subscription_Model::record_success( $endpoint );

				Notification_Master()->notification_logger->success(
					$this->slug,
					$this->prepare_response(
						array(
							'subscription' => $endpoint,
							'message'      => __( 'Notification sent successfully.', 'notification-master' ),
						)
					)
				);
			} else {
				// Record failure and potentially auto-delete subscription.
				Subscription_Model::record_failure( $endpoint );

				Notification_Master()->notification_logger->error(
					$this->slug,
					$this->prepare_response(
						array(
							'subscription' => $endpoint,
							'message'      => $report->getReason(),
						)
					)
				);
			}
		}

		// Check if there are more subscriptions to process.
		$count = Subscription_Model::get_count();
		if ( $count > ( $page * $limit ) ) {
			$data['page'] = $page + 1;
			Utils::enqueue_async_action( $this->slug, $data );
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
				'send_to'        => array(
					'type'     => 'string',
					'enum'     => array( 'all', 'specific_users' ),
					'required' => false,
				),
				'specific_users' => array(
					'type'     => 'array',
					'items'    => array(
						'type' => array( 'string', 'integer' ),
					),
					'required' => false,
				),
				'title'          => array(
					'type'     => 'string',
					'format'   => 'text-field',
					'required' => true,
				),
				'message'        => array(
					'type'     => 'string',
					'format'   => 'text-field',
					'required' => true,
				),
				'icon'           => array(
					'type'                          => 'string',
					'sanitize_and_prepare_callback' => 'esc_url',
				),
				'image'          => array(
					'type'                          => 'string',
					'sanitize_and_prepare_callback' => 'esc_url',
				),
				'url'            => array(
					'type'                          => 'string',
					'sanitize_and_prepare_callback' => 'esc_url',
				),
				'urgency'        => array(
					'type'     => 'string',
					'enum'     => array( 'very-low', 'low', 'normal', 'high' ),
					'required' => true,
				),
			),
		);
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
}
