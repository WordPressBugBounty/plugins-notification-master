<?php
/**
 * Instagram Integration
 *
 * This class is responsible for sending Instagram notifications.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;

use function Notification_Master\Notification_Master;

/**
 * Instagram Integration class.
 */
class Instagram_Integration extends Integration {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Instagram';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'instagram';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Automatically post to Instagram.';

	/**
	 * Token refresh threshold in seconds (1 day before expiry).
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	protected $token_refresh_threshold = 86400;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Generate token.
		add_action( 'admin_init', array( $this, 'generate_token' ) );
		// Ajax action for generating access token.
		add_action( 'wp_ajax_notification_master_generate_instagram_access_token', array( $this, 'generate_access_token' ) );
		// Add token refresh action.
		add_action( 'notification_master_refresh_instagram_token', array( $this, 'maybe_refresh_token' ) );

		// Schedule token refresh check.
		if ( ! wp_next_scheduled( 'notification_master_refresh_instagram_token' ) ) {
			wp_schedule_event( time(), 'daily', 'notification_master_refresh_instagram_token' );
		}
	}

	/**
	 * Get Instagram accounts.
	 *
	 * @param string $access_token Access token.
	 *
	 * @return array|WP_Error Instagram account data or error.
	 */
	public function get_instagram_account( $access_token ) {
		check_ajax_referer( 'notification-master', 'nonce' );

		if ( empty( $access_token ) ) {
			return new \WP_Error( 'error', esc_html__( 'Error, Unable to get Instagram account.', 'notification-master' ) );
		}

		$url = 'https://graph.instagram.com/v22.0/me?access_token=' . $access_token . '&fields=user_id';

		// Make the request.
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'error', esc_html__( 'Error, Unable to get Instagram account.', 'notification-master' ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Generate token.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function generate_token() {
		$state = $_GET['state'] ?? '';
		if ( 'ntfm-instagram' !== $state ) {
			return;
		}

		// Ensure authorize code.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : null;
		if ( empty( $code ) ) {
			echo esc_html__( 'Error, There is no authorize code passed!', 'notification-master' );
			exit;
		}

		// Successfully added.
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Authorization Complete', 'notification-master' ); ?></title>
		</head>
		<body>
			<p><?php echo esc_html__( "The account is added successfully. If this window isn't closed automatically.", 'notification-master' ); ?></p>
			<script>
				if ( typeof window.opener !== 'undefined' && window.opener ) {
					window.opener.generateInstagramAccessToken( '<?php echo esc_js( $code ); ?>' );
					window.close();
				}
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Generate access token.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function generate_access_token() {
		check_ajax_referer( 'notification-master', 'nonce' );

		$code          = sanitize_text_field( $_POST['code'] ?? '' );
		$client_id     = sanitize_text_field( $_POST['app_id'] ?? '' );
		$client_secret = sanitize_text_field( $_POST['app_secret'] ?? '' );

		if ( empty( $code ) || empty( $client_id ) || empty( $client_secret ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error, There is no authorize code passed!', 'notification-master' ) ) );
		}

		$url = 'https://api.instagram.com/oauth/access_token';

		$params = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => admin_url( 'admin.php', 'https' ),
			'code'          => $code,
		);

		// Make the request.
		$response = wp_remote_post(
			$url,
			array(
				'body' => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error, Unable to generate access token.', 'notification-master' ) ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Error, Unable to generate access token.', 'notification-master' ),
					'details' => isset( $data['error_description'] ) ? $data['error_description'] : '',
				)
			);
		}

		$long_lived_token = $this->get_long_lived_token( $data['access_token'], $client_id, $client_secret );
		if ( isset( $long_lived_token['error'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error, Unable to generate long lived token.', 'notification-master' ) ) );
		}

		// Get the user id.
		$instagram_account = $this->get_instagram_account( $long_lived_token['access_token'] );
		if ( isset( $instagram_account['error'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error, Unable to get Instagram account.', 'notification-master' ) ) );
		}

		$user_id = $instagram_account['user_id'] ?? '';
		if ( empty( $user_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error, Unable to get Instagram account.', 'notification-master' ) ) );
		}

		// Store token expiry time.
		$expires_at = time() + $long_lived_token['expires_in'];

		// Save the access token.
		$access_tokens             = get_option( 'notification_master_instagram_tokens', array() );
		$access_tokens[ $user_id ] = array(
			'access_token' => $long_lived_token['access_token'],
			'expires_at'   => $expires_at,
		);
		update_option( 'notification_master_instagram_tokens', $access_tokens );
		Notification_Master()->logger->info(
			'instagram_token_generated',
			array(
				'user_id'    => $user_id,
				'expires_at' => date( 'Y-m-d H:i:s', $expires_at ),
			)
		);

		wp_send_json_success(
			array(
				'access_token' => $long_lived_token['access_token'],
				'user_id'      => $user_id,
				'expires_at'   => $expires_at,
			)
		);
	}

	/**
	 * Get long lived token.
	 *
	 * @since 1.0.0
	 *
	 * @param string $access_token Access token.
	 * @param string $client_id    Client ID.
	 * @param string $client_secret Client secret.
	 *
	 * @return array
	 */
	public function get_long_lived_token( $access_token, $client_id, $client_secret ) {
		$url    = 'https://graph.instagram.com/access_token';
		$params = array(
			'grant_type'    => 'ig_exchange_token',
			'client_secret' => $client_secret,
			'access_token'  => $access_token,
		);
		// Make the request.
		$response = wp_remote_get(
			$url,
			array(
				'body' => $params,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => esc_html__( 'Error, Unable to generate long lived token.', 'notification-master' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( isset( $data['error'] ) ) {
			return array(
				'error'   => esc_html__( 'Error, Unable to generate long lived token.', 'notification-master' ),
				'details' => isset( $data['error_description'] ) ? $data['error_description'] : '',
			);
		}

		return array(
			'access_token' => $data['access_token'],
			'expires_in'   => $data['expires_in'],
		);
	}

	/**
	 * Refresh Instagram access token.
	 *
	 * @since 1.1.0
	 *
	 * @param string $access_token Access token to refresh.
	 * @return array Refreshed token data or error.
	 */
	public function refresh_access_token( $access_token ) {
		$url    = 'https://graph.instagram.com/refresh_access_token';
		$params = array(
			'grant_type'   => 'ig_refresh_token',
			'access_token' => $access_token,
		);

		$response = wp_remote_get(
			$url,
			array(
				'body' => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => esc_html__( 'Error refreshing access token.', 'notification-master' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return array(
				'error'   => esc_html__( 'Error refreshing access token.', 'notification-master' ),
				'details' => $data['error']['message'] ?? '',
			);
		}

		return array(
			'access_token' => $data['access_token'],
			'expires_in'   => $data['expires_in'],
		);
	}

	/**
	 * Check if token needs refreshing and refresh if needed.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function maybe_refresh_token() {
		$saved_tokens = get_option( 'notification_master_instagram_tokens', array() );

		foreach ( $saved_tokens as $user_id => $token_data ) {
			// If token expires soon, refresh it
			if ( isset( $token_data['expires_at'] ) && $token_data['expires_at'] <= ( time() + $this->token_refresh_threshold ) ) {
				$refreshed_token = $this->refresh_access_token( $token_data['access_token'] );

				if ( ! isset( $refreshed_token['error'] ) ) {
					$saved_tokens[ $user_id ]['access_token'] = $refreshed_token['access_token'];
					$saved_tokens[ $user_id ]['expires_at']   = time() + $refreshed_token['expires_in'];

					Notification_Master()->logger->info(
						'instagram_token_refreshed',
						array(
							'user_id'    => $user_id,
							'expires_at' => date( 'Y-m-d H:i:s', $saved_tokens[ $user_id ]['expires_at'] ),
						)
					);
				} else {
					Notification_Master()->logger->error(
						'instagram_token_refresh_failed',
						array(
							'user_id' => $user_id,
							'error'   => $refreshed_token['error'],
							'details' => $refreshed_token['details'] ?? '',
						)
					);
				}
			}
		}

		update_option( 'notification_master_instagram_tokens', $saved_tokens );
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		$valid = $this->validate_attributes();
		if ( ! $valid ) {
			Notification_Master()->logger->error(
				'instagram_integration_invalid_attributes',
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
		$caption   = $this->attributes['text'];
		$image_url = $this->attributes['image_url'];
		$alt_text  = $this->attributes['alt_text'];
		$this->send_notification( $image_url, $caption, $alt_text );
	}

	/**
	 * Send notification.
	 *
	 * @since 1.0.0
	 *
	 * @param string $image_url Image URL.
	 * @param string $text      Text.
	 * @param string $alt_text  Alt text.
	 *
	 * @return bool Whether the notification was sent successfully.
	 */
	public function send_notification( $image_url, $text, $alt_text ) {
		$access_tokens = get_option( 'notification_master_instagram_tokens', array() );
		$access_token  = $access_tokens[ $this->attributes['user_id'] ]['access_token'] ?? $this->attributes['access_token'];
		$app_id        = $this->attributes['app_id'];
		$app_secret    = $this->attributes['app_secret'];
		$user_id       = $this->attributes['user_id'];

		if ( empty( $access_token ) || empty( $app_id ) || empty( $app_secret ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message' => __( 'Missing required credentials (access_token, app_id, or app_secret).', 'notification-master' ),
					)
				)
			);
			return false;
		}

		if ( empty( $image_url ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'    => __( 'Missing required image URL.', 'notification-master' ),
						'attributes' => $this->attributes,
					)
				)
			);
			return false;
		}

		$create_response = wp_remote_post(
			add_query_arg(
				array(
					'access_token' => $access_token,
				),
				"https://graph.instagram.com/v22.0/{$user_id}/media"
			),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'image_url' => $image_url,
						'caption'   => $text,
						'alt_text'  => $alt_text,
					)
				),
				'timeout' => 60,
			),
		);

		if ( is_wp_error( $create_response ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'    => __( 'Error creating media container', 'notification-master' ),
						'error'      => $create_response->get_error_message(),
						'attributes' => $this->attributes,
					)
				)
			);
			return false;
		}

		$create_body = json_decode( wp_remote_retrieve_body( $create_response ), true );
		if ( empty( $create_body['id'] ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'    => __( 'Failed to create media container', 'notification-master' ),
						'response'   => $create_body,
						'attributes' => $this->attributes,
					)
				)
			);
			return false;
		}

		$creation_id      = $create_body['id'];
		$publish_response = wp_remote_post(
			add_query_arg(
				array(
					'access_token' => $access_token,
				),
				"https://graph.instagram.com/v22.0/{$user_id}/media_publish"
			),
			array(
				'body' => array(
					'creation_id' => $creation_id,
				),
			)
		);

		if ( is_wp_error( $publish_response ) ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'     => __( 'Error publishing media', 'notification-master' ),
						'error'       => $publish_response->get_error_message(),
						'creation_id' => $creation_id,
						'attributes'  => $this->attributes,
					)
				)
			);
			return false;
		}

		$publish_body = json_decode( wp_remote_retrieve_body( $publish_response ), true );

		if ( isset( $publish_body['id'] ) ) {
			// Success.
			Notification_Master()->notification_logger->success(
				$this->slug,
				$this->prepare_response(
					array(
						'message' => __( 'Successfully published to Instagram', 'notification-master' ),
						'post_id' => $publish_body['id'],
					)
				)
			);
			return true;
		} else {
			// Error.
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message'     => __( 'Failed to publish to Instagram', 'notification-master' ),
						'response'    => $publish_body,
						'creation_id' => $creation_id,
					)
				)
			);
			return false;
		}
	}

	/**
	 * Get Instagram authorization URL.
	 *
	 * @since 1.1.0
	 *
	 * @param string $client_id  Client ID.
	 * @param string $redirect_uri Redirect URI.
	 * @return string Authorization URL.
	 */
	public function get_auth_url( $client_id, $redirect_uri = '' ) {
		if ( empty( $redirect_uri ) ) {
			$redirect_uri = admin_url( 'admin.php', 'https' );
		}

		$auth_url = add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect_uri,
				'scope'         => 'user_profile,user_media',
				'response_type' => 'code',
				'state'         => 'ntfm-instagram',
			),
			'https://api.instagram.com/oauth/authorize'
		);

		return $auth_url;
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
				'app_id'       => array(
					'type'        => 'string',
					'format'      => 'text-field',
					'required'    => true,
					'description' => __( 'Instagram App ID', 'notification-master' ),
				),
				'app_secret'   => array(
					'type'        => 'string',
					'format'      => 'text-field',
					'required'    => true,
					'description' => __( 'Instagram App Secret', 'notification-master' ),
				),
				'access_token' => array(
					'type'        => 'string',
					'format'      => 'text-field',
					'required'    => true,
					'description' => __( 'Instagram Access Token', 'notification-master' ),
				),
				'user_id'      => array(
					'type'                          => array(
						'string',
						'integer',
					),
					'required'                      => true,
					'sanitize_and_prepare_callback' => 'absint',
					'description'                   => __( 'Instagram User ID', 'notification-master' ),
				),
				'text'         => array(
					'type'        => 'string',
					'format'      => 'text-field',
					'required'    => false,
					'description' => __( 'Post caption', 'notification-master' ),
				),
				'alt_text'     => array(
					'type'        => 'string',
					'format'      => 'text-field',
					'required'    => false,
					'description' => __( 'Image alt text for accessibility', 'notification-master' ),
				),
				'image_url'    => array(
					'type'                          => 'string',
					'sanitize_and_prepare_callback' => 'esc_url_raw',
					'required'                      => true,
					'description'                   => __( 'URL of the image to post', 'notification-master' ),
				),
			),
		);
	}
}