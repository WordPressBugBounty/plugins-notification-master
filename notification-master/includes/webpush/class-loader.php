<?php
/**
 * Class Webpush_Class_Loader
 *
 * @package notification-master
 *
 * @since 1.4.0
 */

namespace Notification_Master\WebPush;

use Notification_Master\Settings;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use function Notification_Master\Notification_Master;

/**
 * Class Loader
 *
 * @package notification-master
 *
 * @since 1.4.0
 */
class Loader {

	/**
	 * Instance of this class.
	 *
	 * @since 1.4.0
	 *
	 * @var Loader
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.4.0
	 *
	 * @return Loader
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Loader ) ) {
			self::$instance = new Loader();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_head', array( $this, 'add_manifest_link' ) );

		// Add shortcode button display notification.
		add_shortcode( 'notification-master-subscribe-btn', array( $this, 'get_button_markup' ) );

		// Add shortcode floating button display notification.
		add_shortcode( 'notification-master-floating-button', array( $this, 'get_floating_button_markup' ) );

		// Ajax actions.
		add_action( 'wp_ajax_ntfm_generate_keys', array( $this, 'generate_vapid_keys' ) );

		add_filter( 'notification_master_admin_config', array( $this, 'add_webpush_settings' ) );

		add_action( 'wp_footer', array( $this, 'maybe_add_floating_button' ) );

		add_action( 'notification_master_send_webpush', array( $this, 'send_webpush' ), 10, 3 );
	}

	/**
	 * Send webpush.
	 *
	 * @since 1.4.0
	 *
	 * @param array  $subscription Subscription.
	 * @param bool   $exsited    Existing.
	 * @param string $type      Type.
	 *
	 * @return void
	 */
	public function send_webpush( $subscription, $exsited = false, $type = '' ) {
		$enabled = Settings::get_setting( 'webpush_welcome_notification', true );
		if ( ! $enabled ) {
			return;
		}

		if ( $exsited && 'button' !== $type ) {
			return;
		}

		if ( is_array( $subscription ) ) {
			$subscription = (object) $subscription;
		}

		$title        = Settings::get_setting( 'webpush_welcome_notification_title', __( 'Welcome!', 'notification-master' ) );
		$message      = Settings::get_setting( 'webpush_welcome_notification_message', __( 'Thanks for subscribing to our notifications!', 'notification-master' ) );
		$notification = array(
			'title'   => $title,
			'message' => $message,
			'url'     => home_url(),
			'icon'    => get_site_icon_url(),
		);

		$auth = array(
			'VAPID' => array(
				'subject'    => home_url(),
				'publicKey'  => Settings::get_setting( 'webpush_public_key' ),
				'privateKey' => Settings::get_setting( 'webpush_private_key' ),
			),
		);

		$webPush = new WebPush( $auth );

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
				$notification
			),
			array(
				'urgency' => 'normal',
			)
		);

		/**
		 * Check sent results
		 *
		 * @var MessageSentReport $report
		 */
		foreach ( $webPush->flush() as $report ) {
			$endpoint = $report->getRequest()->getUri()->__toString();

			if ( $report->isSuccess() ) {
				Notification_Master()->logger->info(
					'welcome_webpush_notification',
					array(
						'subscription' => $endpoint,
						'message'      => __( 'Welcome notification sent successfully.', 'notification-master' ),
					)
				);
			} else {
				Notification_Master()->logger->error(
					'welcome_webpush_notification',
					array(
						'subscription' => $endpoint,
						'message'      => $report->getReason(),
					)
				);
			}
		}
	}

	/**
	 * Add webpush settings.
	 *
	 * @since 1.4.0
	 *
	 * @param array $config Notification Master config.
	 *
	 * @return array
	 */
	public function add_webpush_settings( $config ) {
		$config['subscribeButtonShortCode'] = '[notification-master-subscribe-btn]';
		$config['floatingButtonShortCode']  = '[notification-master-floating-button]';
		$config['webpushConfigured']        = $this->is_configured();

		return $config;
	}

	/**
	 * Is configured.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public function is_configured() {
		return Settings::get_setting( 'webpush_public_key', null ) && Settings::get_setting( 'webpush_private_key', null );
	}

	/**
	 * Get floating button markup.
	 *
	 * @since 1.4.5
	 *
	 * @return string
	 */
	public function get_floating_button_markup( $force = false ) {
		$settings = $this->get_floating_button_settings();

		if ( ! $this->is_configured() ) {
			return '';
		}

		if ( $settings['enabled'] && ! $force ) {
			return '';
		}

		// Get icon markup based on icon type.
		$icon_markup = $this->get_floating_button_icon_markup( $settings );

		ob_start();
		?>
		<button class="ntfm-subscribe-floating-btn notification-master-subscribe<?php echo esc_attr( $settings['extra_class'] ); ?>" <?php echo ! empty( $settings['id'] ) ? 'id="' . esc_attr( $settings['id'] ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $settings['text_subscribe'] ); ?>">
		<?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $settings['tooltip'] ) : ?>
			<span class="ntfm-subscribe-floating-btn-tooltip"><?php echo esc_html( $settings['text_subscribe'] ); ?></span>
		<?php endif; ?>
		</button>
		<button class="ntfm-subscribe-floating-btn subscribed notification-master-unsubscribe<?php echo esc_attr( $settings['extra_class'] ); ?>" <?php echo ! empty( $settings['id'] ) ? 'id="' . esc_attr( $settings['id'] ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $settings['text_unsubscribe'] ); ?>">
		<?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $settings['tooltip'] ) : ?>
			<span class="ntfm-subscribe-floating-btn-tooltip"><?php echo esc_html( $settings['text_unsubscribe'] ); ?></span>
		<?php endif; ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get floating button icon markup.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Floating button settings.
	 *
	 * @return string Icon markup.
	 */
	private function get_floating_button_icon_markup( $settings ) {
		$icon_type      = isset( $settings['icon_type'] ) ? $settings['icon_type'] : 'default';
		$custom_icon_id = isset( $settings['custom_icon_id'] ) ? absint( $settings['custom_icon_id'] ) : 0;
		$color          = isset( $settings['styles']['normal']['color'] ) ? $settings['styles']['normal']['color'] : '#ffffff';

		// Use custom icon if selected and valid.
		if ( 'custom' === $icon_type && $custom_icon_id > 0 ) {
			$svg_content = $this->get_custom_svg_content( $custom_icon_id );
			if ( ! empty( $svg_content ) ) {
				return $svg_content;
			}
		}

		// Default icon fallback.
		return '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 312.061 373.784">
			<g id="Group_698" data-name="Group 698" transform="translate(0.528)">
			<path id="Subtraction_6" data-name="Subtraction 6" d="M295.268,267.945H15.737c-8.218,0-13.042-6.144-14.819-11.895-2.115-6.864-.854-16.106,6.732-21.28l22.87-15.594C47,207.945,57.235,187.4,57.235,165.57V96.741c0-25.84,8.858-50.134,24.939-68.406S119.635,0,142.376,0h26.254c22.743,0,44.12,10.062,60.2,28.334S253.763,70.9,253.763,96.741V165.57c0,21.834,10.238,42.375,26.713,53.608l22.881,15.594c7.586,5.173,8.844,14.415,6.73,21.28C308.311,261.8,303.488,267.945,295.268,267.945ZM97.286,184.863c1.7,7.8,3.927,12.72,7.025,15.494,7.7,6.89,19.276,6.89,42.337,6.89h17.709c23.063,0,34.636,0,42.337-6.89,3.1-2.776,5.33-7.7,7.027-15.494Zm-6.77-63.1v.009c-.408,3.09.117,7.968.909,15.352l1.495,13.9q.389,3.622.748,7.124l.02.206c.525,5.055,1.021,9.83,1.6,14.3H215.721c.588-4.6,1.1-9.476,1.63-14.644q.358-3.431.735-6.987l1.492-13.88.018-.17c.787-7.314,1.308-12.146.893-15.21l.144.007a12.219,12.219,0,1,0-9.243-4.237c-2.667,1.645-6.11,5.079-11.324,10.278-4.04,4.024-6.056,6.032-8.306,6.348a6.819,6.819,0,0,1-3.664-.508c-2.083-.924-3.465-3.407-6.216-8.353L167.31,99.178l-.063-.114c-1.578-2.828-3.068-5.5-4.353-7.558a16.281,16.281,0,1,0-14.783,0c-1.29,2.066-2.778,4.734-4.353,7.558l-.063.114L129.124,125.3c-2.75,4.947-4.132,7.43-6.216,8.353a6.819,6.819,0,0,1-3.664.508c-2.241-.315-4.254-2.32-8.272-6.315-5.244-5.229-8.691-8.666-11.358-10.311a12.206,12.206,0,1,0-9.241,4.237l.133-.007Z" transform="translate(0 68.281)" stroke="rgba(0,0,0,0)" stroke-miterlimit="10" stroke-width="1"/>
			<circle id="Ellipse_2" data-name="Ellipse 2" cx="23.353" cy="23.353" r="23.353" transform="translate(132.149 0)"/>
			<circle id="Ellipse_3" data-name="Ellipse 3" cx="37.557" cy="37.557" r="37.557" transform="translate(117.944 298.67)"/>
			</g>
		</svg>';
	}

	/**
	 * Get custom SVG content from attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string Sanitized SVG content.
	 */
	private function get_custom_svg_content( $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return '';
		}

		// Verify it's an SVG file.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || 'svg' !== strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
			return '';
		}

		// Use WordPress file system to read SVG content.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		WP_Filesystem();

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return '';
		}

		// Read and sanitize SVG content.
		$svg_content = $wp_filesystem->get_contents( $file_path );
		if ( false === $svg_content ) {
			return '';
		}

		return $svg_content;
	}
	/**
	 * Get button markup.
	 *
	 * @since 1.4.5
	 *
	 * @return string
	 */
	public function get_button_markup() {
		$settings = $this->get_button_settings();

		if ( ! $this->is_configured() ) {
			return '';
		}

		ob_start();
		?>
		<button class="ntfm-subscribe-btn notification-master-subscribe<?php echo esc_attr( $settings['extra_class'] ); ?>" <?php echo ! empty( $settings['id'] ) ? 'id="' . esc_attr( $settings['id'] ) . '"' : ''; ?>>
			<?php echo esc_html( $settings['text'] ); ?>
		</button>
		<button class="ntfm-subscribe-btn subscribed notification-master-unsubscribe<?php echo esc_attr( $settings['extra_class'] ); ?>" <?php echo ! empty( $settings['id'] ) ? 'id="' . esc_attr( $settings['id'] ) . '"' : ''; ?>>
			<?php echo esc_html( $settings['unsubscribe_text'] ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get button settings.
	 *
	 * @since 1.4.5
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$text                   = Settings::get_setting( 'normal_button_text', __( 'Subscribe to Notifications!', 'notification-master' ) );
		$color                  = Settings::get_setting( 'normal_button_color', '#ffffff' );
		$background_color       = Settings::get_setting( 'normal_button_background_color', '#ff7900' );
		$hover_color            = Settings::get_setting( 'normal_button_hover_color', '#ffffff' );
		$hover_background_color = Settings::get_setting( 'normal_button_hover_background_color', '#c75e02' );
		$padding                = Settings::get_setting( 'normal_button_padding', '10 20 10 20' );
		$margin                 = Settings::get_setting( 'normal_button_margin', '10' );
		$border_radius          = Settings::get_setting( 'normal_button_border_radius', '5' );
		$unsubscribe_text       = Settings::get_setting( 'normal_button_unsubscribe_text', __( 'Unsubscribe!', 'notification-master' ) );
		$extra_class            = Settings::get_setting( 'normal_button_extra_class', '' );
		$id                     = Settings::get_setting( 'normal_button_id', '' );

		if ( ! empty( $extra_class ) ) {
			$extra_class = ' ' . $extra_class;
		}

		$styles = array(
			'normal' => array(
				'color'            => $color,
				'background-color' => $background_color,
				'padding'          => $this->get_spacing_values( $padding ),
				'margin'           => $this->get_spacing_values( $margin ),
				'border-radius'    => $border_radius,
			),
			'hover'  => array(
				'color'            => $hover_color,
				'background-color' => $hover_background_color,
			),
		);

		return compact( 'text', 'styles', 'unsubscribe_text', 'extra_class', 'id' );
	}

	/**
	 * Get floating button settings.
	 *
	 * @since 1.4.5
	 *
	 * @return array
	 */
	public function get_floating_button_settings() {
		$enabled                = Settings::get_setting( 'enable_floating_button', true );
		$animation              = Settings::get_setting( 'enable_floating_button_animation', true );
		$tooltip                = Settings::get_setting( 'enable_floating_button_tooltip', true );
		$text_subscribe         = Settings::get_setting( 'floating_button_tooltip_subscribe_text', __( 'Subscribe!', 'notification-master' ) );
		$text_unsubscribe       = Settings::get_setting( 'floating_button_tooltip_unsubscribe_text', __( 'Unsubscribe!', 'notification-master' ) );
		$color                  = Settings::get_setting( 'floating_button_color', '#ffffff' );
		$background_color       = Settings::get_setting( 'floating_button_background_color', '#ff7900' );
		$hover_color            = Settings::get_setting( 'floating_button_hover_color', '#ffffff' );
		$hover_background_color = Settings::get_setting( 'floating_button_hover_background_color', '#c75e02' );
		$width                  = Settings::get_setting( 'floating_button_width', '50' );
		$height                 = Settings::get_setting( 'floating_button_height', '50' );
		$border_radius          = Settings::get_setting( 'floating_button_border_radius', '50' );
		$position               = Settings::get_setting( 'floating_button_position', 'bottom-right' );
		$right                  = Settings::get_setting( 'floating_button_right', '20' );
		$bottom                 = Settings::get_setting( 'floating_button_bottom', '20' );
		$top                    = Settings::get_setting( 'floating_button_top', '20' );
		$left                   = Settings::get_setting( 'floating_button_left', '20' );
		$extra_class            = Settings::get_setting( 'floating_button_extra_class', '' );
		$id                     = Settings::get_setting( 'floating_button_id', '' );
		$z_index                = Settings::get_setting( 'floating_button_z_index', '99999' );
		$hide_desktop           = Settings::get_setting( 'floating_button_hide_on_desktop', false );
		$hide_tablet            = Settings::get_setting( 'floating_button_hide_on_tablet', false );
		$hide_mobile            = Settings::get_setting( 'floating_button_hide_on_mobile', false );
		$icon_type              = Settings::get_setting( 'floating_button_icon_type', 'default' );
		$custom_icon_id         = Settings::get_setting( 'floating_button_custom_icon_id', 0 );

		if ( ! empty( $extra_class ) ) {
			$extra_class = ' ' . $extra_class;
		}

		if ( $animation ) {
			$extra_class .= ' animated';
		}

		$styles = array(
			'normal' => array(
				'color'            => $color,
				'background-color' => $background_color,
				'border-radius'    => $border_radius,
				'width'            => $width,
				'height'           => $height,
			),
			'hover'  => array(
				'color'            => $hover_color,
				'background-color' => $hover_background_color,
			),
		);

		return compact( 'text_subscribe', 'text_unsubscribe', 'styles', 'position', 'right', 'bottom', 'top', 'left', 'extra_class', 'id', 'z_index', 'enabled', 'animation', 'tooltip', 'hide_desktop', 'hide_tablet', 'hide_mobile', 'icon_type', 'custom_icon_id' );
	}

	/**
	 * Get inline styles with responsiveness.
	 *
	 * @since 1.4.5
	 *
	 * @return string
	 */
	private function get_inline_styles() {
		$settings = $this->get_button_settings();
		$floating = $this->get_floating_button_settings();
		$styles   = '';

		$styles .= '.ntfm-subscribe-btn {' . PHP_EOL;
		$styles .= 'color: ' . $settings['styles']['normal']['color'] . ' !important;' . PHP_EOL;
		$styles .= 'background-color: ' . $settings['styles']['normal']['background-color'] . ' !important;' . PHP_EOL;
		$styles .= 'padding: ' . implode( ' ', $settings['styles']['normal']['padding'] ) . ' !important;' . PHP_EOL;
		$styles .= 'margin: ' . implode( ' ', $settings['styles']['normal']['margin'] ) . ' !important;' . PHP_EOL;
		$styles .= 'border-radius: ' . $settings['styles']['normal']['border-radius'] . 'px;' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		$styles .= '.ntfm-subscribe-btn:hover {' . PHP_EOL;
		$styles .= 'color: ' . $settings['styles']['hover']['color'] . ' !important;' . PHP_EOL;
		$styles .= 'background-color: ' . $settings['styles']['hover']['background-color'] . ' !important;' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		$styles .= '.ntfm-subscribe-floating-btn svg {' . PHP_EOL;
		$styles .= 'fill: ' . $floating['styles']['normal']['color'] . ' !important;' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		$styles .= '.ntfm-subscribe-floating-btn:hover svg {' . PHP_EOL;
		$styles .= 'fill: ' . $floating['styles']['hover']['color'] . ' !important;' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		$styles .= '.ntfm-subscribe-floating-btn {' . PHP_EOL;
		$styles .= 'background-color: ' . $floating['styles']['normal']['background-color'] . ' !important;' . PHP_EOL;
		$styles .= 'border-radius: ' . $floating['styles']['normal']['border-radius'] . '%;' . PHP_EOL;
		$styles .= 'width: ' . $floating['styles']['normal']['width'] . 'px;' . PHP_EOL;
		$styles .= 'height: ' . $floating['styles']['normal']['height'] . 'px;' . PHP_EOL;
		$styles .= 'z-index: ' . $floating['z_index'] . ';' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		// Positioning the floating button using the helper function
		$styles .= $this->get_floating_button_position( $floating );

		// Hide the floating button on desktop, tablet, or mobile
		$styles .= $this->get_floating_button_hide( $floating );

		// Hover styles for the floating button
		$styles .= '.ntfm-subscribe-floating-btn:hover {' . PHP_EOL;
		$styles .= 'background-color: ' . $floating['styles']['hover']['background-color'] . ' !important;' . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		return $styles;
	}

	/**
	 * Get the inline styles for hiding the floating button on desktop, tablet, or mobile.
	 *
	 * @param array $floating The floating button settings, including hide settings.
	 *
	 * @return string The CSS styles for hiding the floating button on desktop, tablet, or mobile.
	 */
	private function get_floating_button_hide( $floating ) {
		$styles = '';

		// Get hide settings for desktop, tablet, and mobile
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( isset( $floating[ 'hide_' . $device ] ) && $floating[ 'hide_' . $device ] ) {
				// Determine media query based on device
				if ( $device === 'desktop' ) {
					$styles .= '@media (min-width: 1025px) {' . PHP_EOL;
				} elseif ( $device === 'tablet' ) {
					$styles .= '@media (max-width: 1024px) and (min-width: 768px) {' . PHP_EOL;
				} elseif ( $device === 'mobile' ) {
					$styles .= '@media (max-width: 768px) {' . PHP_EOL;
				}

				// Hide the floating button on the device
				$styles .= '.ntfm-subscribe-floating-btn {' . PHP_EOL;
				$styles .= 'display: none !important;' . PHP_EOL;
				$styles .= '}' . PHP_EOL;

				$styles .= '}' . PHP_EOL;
			}
		}

		return $styles;
	}

	/**
	 * Get the inline position styles for the floating button.
	 *
	 * @param array $floating The floating button settings, including position and size.
	 *
	 * @return string The CSS styles for positioning the floating button.
	 */
	private function get_floating_button_position( $floating ) {
		$styles = '';

		// Get position styles for desktop, tablet, and mobile
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( isset( $floating['position'] ) ) {
				$position = is_array( $floating['position'] ) ? $floating['position'][ $device ] : $floating['position'];
				$top      = $this->get_position_values( $floating['top'] );
				$right    = $this->get_position_values( $floating['right'] );
				$bottom   = $this->get_position_values( $floating['bottom'] );
				$left     = $this->get_position_values( $floating['left'] );

				// Generate position styles for each device
				switch ( $position ) {
					case 'top-left':
						$styles .= $this->get_position_styles( $device, $top[ $device ], $left[ $device ], 'top', 'left' );
						break;
					case 'top-right':
						$styles .= $this->get_position_styles( $device, $top[ $device ], $right[ $device ], 'top', 'right' );
						break;
					case 'bottom-left':
						$styles .= $this->get_position_styles( $device, $bottom[ $device ], $left[ $device ], 'bottom', 'left' );
						break;
					case 'bottom-right':
						$styles .= $this->get_position_styles( $device, $bottom[ $device ], $right[ $device ], 'bottom', 'right' );
						break;
				}
			}
		}

		return $styles;
	}

	/**
	 * Get the position styles for a given device.
	 *
	 * @param string $device The device type (desktop, tablet, or mobile).
	 * @param mixed  $position    The position value for the device.
	 * @param mixed  $side   The left or right position value for the device (depending on the case).
	 * @param string $top_or_bottom The position type (top or bottom).
	 * @param string $left_or_right The position type (left or right).
	 *
	 * @return string The CSS position styles for the device.
	 */
	private function get_position_styles( $device, $position, $side, $top_or_bottom, $left_or_right ) {
		$styles = '';

		// Determine media query based on device
		if ( $device === 'desktop' ) {
			$styles .= '@media (min-width: 1025px) {' . PHP_EOL;
		} elseif ( $device === 'tablet' ) {
			$styles .= '@media (max-width: 1024px) and (min-width: 768px) {' . PHP_EOL;
		} elseif ( $device === 'mobile' ) {
			$styles .= '@media (max-width: 768px) {' . PHP_EOL;
		}

		// Apply position styles based on top/bottom and left/right
		$styles .= ".ntfm-subscribe-floating-btn.ntfm-{$device} {" . PHP_EOL;
		$styles .= "{$top_or_bottom}: {$position}px;" . PHP_EOL;
		$styles .= "{$left_or_right}: {$side}px;" . PHP_EOL;
		$styles .= '}' . PHP_EOL;

		$styles .= '}' . PHP_EOL;

		return $styles;
	}

	/**
	 * Get position values for desktop, tablet, and mobile.
	 *
	 * This function supports both old single-value string format and new array format.
	 * If a string is passed (old format), it will be used for all devices (desktop, tablet, mobile).
	 * If an array is passed (new format), it will use the array values for each device.
	 * If any device value is missing in the array, it will set the default value.
	 *
	 * @param mixed $value The position value as a string (e.g., '20') or an associative array (e.g., ['desktop' => '10', 'tablet' => '20', 'mobile' => '30']).
	 *
	 * @return array Associative array with 'desktop', 'tablet', and 'mobile' keys and their respective values.
	 */
	function get_position_values( $value ) {
		// Default values for desktop, tablet, and mobile
		$values = array(
			'desktop' => '0',
			'tablet'  => '0',
			'mobile'  => '0',
		);

		// If the input is a string (old format, single value for all devices)
		if ( is_string( $value ) ) {
			// Trim any whitespace and check if it's a valid single value
			$value = trim( $value );
			if ( ! empty( $value ) ) {
				// Apply the same value for all devices
				$values['desktop'] = $values['tablet'] = $values['mobile'] = $value;
			}
		} elseif ( is_array( $value ) ) {
			// If the input is an array (new format)
			// Use the array values for desktop, tablet, and mobile
			$values['desktop'] = isset( $value['desktop'] ) ? $value['desktop'] : $values['desktop'];
			$values['tablet']  = isset( $value['tablet'] ) ? $value['tablet'] : $values['tablet'];
			$values['mobile']  = isset( $value['mobile'] ) ? $value['mobile'] : $values['mobile'];
		}

		return $values;
	}

	/**
	 * Get floating button settings.
	 *
	 * @since 1.4.5
	 *
	 * @param string $value The value to parse.
	 *
	 * @return array
	 */
	private function get_spacing_values( $value ) {
		$values = array(
			'top'    => '0',
			'right'  => '0',
			'bottom' => '0',
			'left'   => '0',
		);

		$parts = explode( ' ', $value );

		if ( count( $parts ) === 1 ) {
			$values['top'] = $values['right'] = $values['bottom'] = $values['left'] = $parts[0];
		} elseif ( count( $parts ) === 4 ) {
			$values['top']    = $parts[0];
			$values['right']  = $parts[1];
			$values['bottom'] = $parts[2];
			$values['left']   = $parts[3];
		}

		// Add px to each value.
		foreach ( $values as $key => $val ) {
			$values[ $key ] = "{$val}px";
		}

		return $values;
	}

	/**
	 * Create manifest file if not exists in uploads directory.
	 *
	 * @since 1.4.0
	 */
	public function create_manifest_file() {
		if ( ! $this->is_configured() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php'; // We will probably need to load this file.
		global $wp_filesystem;
		WP_Filesystem(); // Initial WP file system.

		$upload_dir = wp_upload_dir();
		$manifest   = $upload_dir['basedir'] . '/notification-master/manifest.json';

		if ( ! file_exists( $manifest ) ) {
			$manifest_content = array(
				'name'    => get_bloginfo( 'name' ),
				'display' => 'standalone',
			);

			// Create directory if not exists.
			if ( ! file_exists( $upload_dir['basedir'] . '/notification-master' ) ) {
				wp_mkdir_p( $upload_dir['basedir'] . '/notification-master' );
			}

			$wp_filesystem->put_contents( $manifest, wp_json_encode( $manifest_content ) );
		}
	}

	/**
	 * Generate VAPID keys.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	public function generate_vapid_keys() {
		// Check if nonce is valid.
		check_ajax_referer( 'notification-master', 'nonce' );

		$keys = array(
			'public_key'  => '',
			'private_key' => '',
		);

		try {
			// Sent post request to generate VAPID keys.
			$response = wp_remote_post(
				NOTIFICATION_MASTER_SITE . '/wp-json/ntfm/v1/generate-vapid-keys',
				array(
					'body' => array(
						'site_url' => get_site_url(),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message() );
			}

			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );

			if ( ! is_array( $response_data ) || ! isset( $response_data['public_key'], $response_data['private_key'] ) ) {
				throw new \Exception( __( 'Invalid response.', 'notification-master' ) );
			}

			$keys['public_key']  = $response_data['public_key'];
			$keys['private_key'] = $response_data['private_key'];
			$auto_save           = isset( $_POST['autoSave'] ) ? sanitize_text_field( $_POST['autoSave'] ) : 'yes';

			if ( 'yes' === $auto_save ) {
				Settings::update_setting( 'webpush_public_key', $keys['public_key'] );
				Settings::update_setting( 'webpush_private_key', $keys['private_key'] );
			}

			// Send success response.
			wp_send_json_success( $keys );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Register scripts.
	 *
	 * @since 1.4.0
	 */
	public function register_scripts() {
		if ( ! $this->is_configured() ) {
			return;
		}

		wp_enqueue_script(
			'notification-master-webpush',
			NOTIFICATION_MASTER_URL . 'assets/js/webpush.js',
			array( 'wp-api-fetch' ),
			NOTIFICATION_MASTER_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'notification-master-webpush',
			'notificationMasterWebpush',
			array(
				'vapidPublicKey'   => Settings::get_setting( 'webpush_public_key' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'ntfm_webpush' ),
				'serviceWorkerUrl' => NOTIFICATION_MASTER_URL . 'assets/js/service-worker.js',
				'automaticPrompt'  => Settings::get_setting( 'webpush_auto_prompt', false ),
				'unsubscribeText'  => Settings::get_setting( 'normal_button_unsubscribe_text', __( 'Unsubscribe!', 'notification-master' ) ),
				'iOSMessage'       => __( 'Your browser does not support push notifications, if you are using iPhone/iPad, please add this website to your home screen to receive notifications.', 'notification-master' ),
				'deniedMessage'    => __( 'You have denied the permission to send notifications. Please allow it to subscribe to push notifications.', 'notification-master' ),
			)
		);

		// Css
		wp_enqueue_style(
			'notification-master-webpush',
			NOTIFICATION_MASTER_URL . 'assets/css/style.css',
			array(),
			NOTIFICATION_MASTER_VERSION
		);

		// Inline styles.
		$styles = $this->get_inline_styles();

		wp_add_inline_style( 'notification-master-webpush', $styles );
	}

	/**
	 * Maybe add floating button.
	 *
	 * @since 1.4.5
	 */
	public function maybe_add_floating_button() {
		$settings = $this->get_floating_button_settings();

		if ( $settings['enabled'] ) {
			echo $this->get_floating_button_markup( true );
		}
	}

	/**
	 * Add manifest link to head.
	 *
	 * @since 1.4.0
	 */
	public function add_manifest_link() {
		if ( ! $this->is_configured() ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$manifest   = $upload_dir['baseurl'] . '/notification-master/manifest.json';
		if ( ! file_exists( $upload_dir['basedir'] . '/notification-master/manifest.json' ) ) {
			$this->create_manifest_file();
		}

		if ( is_ssl() ) {
			$manifest = str_replace( 'http://', 'https://', $manifest );
		}

		?>
		<link rel="manifest" href="<?php echo esc_url( $manifest ); ?>">
		<?php
	}
}
