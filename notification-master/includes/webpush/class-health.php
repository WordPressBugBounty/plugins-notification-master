<?php
/**
 * Class Health
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\WebPush;

use Notification_Master\DB\Models\Subscription_Model;
use Notification_Master\Settings;
use Notification_Master\Stats\Period;

/**
 * Web Push health checks: HTTPS, VAPID keys, service worker, subscribers.
 */
class Health {

	/**
	 * Run every check.
	 *
	 * @since 2.0.0
	 *
	 * @return array { ok, status, checked_at, timezone, checks, subscribers }.
	 */
	public static function run() {
		$subscribers = (int) Subscription_Model::get_count_by_status( 'subscribed' );
		$checks      = array(
			self::check_https(),
			self::check_keys(),
			self::check_service_worker(),
			self::check_server(),
			self::check_subscribers( $subscribers ),
		);

		$blocking = array_filter(
			$checks,
			function ( $check ) {
				return ! $check['ok'] && 'subscribers' !== $check['id'];
			}
		);
		$all_ok   = count( array_filter( wp_list_pluck( $checks, 'ok' ) ) ) === count( $checks );
		$status   = $blocking ? 'error' : ( $all_ok ? 'ok' : 'warning' );

		return array(
			'ok'          => $all_ok,
			'status'      => $status,
			'checked_at'  => current_time( 'mysql' ),
			'timezone'    => Period::timezone(),
			'checks'      => $checks,
			'subscribers' => $subscribers,
		);
	}

	/**
	 * Browsers allow push only on HTTPS (and localhost).
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function check_https() {
		$url   = home_url();
		$https = 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
		$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
		$local = in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true );

		if ( $https ) {
			$detail = __( 'Browsers only allow push on secure sites. This site uses HTTPS.', 'notification-master' );
		} elseif ( $local ) {
			$detail = __( 'This site runs on localhost, where browsers allow push without HTTPS. A live site needs HTTPS.', 'notification-master' );
		} else {
			$detail = __( 'Browsers only allow push on secure sites. Switch your site address to https:// to use Web Push.', 'notification-master' );
		}

		return self::check( 'https', __( 'HTTPS', 'notification-master' ), $https || $local, $detail );
	}

	/**
	 * Both VAPID keys saved and in the right format.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function check_keys() {
		$public  = (string) Settings::get_setting( 'webpush_public_key', '' );
		$private = (string) Settings::get_setting( 'webpush_private_key', '' );
		$label   = __( 'VAPID keys', 'notification-master' );

		if ( '' === $public || '' === $private ) {
			return self::check( 'vapid_keys', $label, false, __( 'The keys that sign your push messages are missing. Generate them below.', 'notification-master' ) );
		}

		if ( ! self::is_valid_key( $public, 65, "\x04" ) || ! self::is_valid_key( $private, 32 ) ) {
			return self::check( 'vapid_keys', $label, false, __( 'The saved keys are not in the right format. Generate new keys below.', 'notification-master' ) );
		}

		return self::check( 'vapid_keys', $label, true, __( 'The keys that sign your push messages are saved.', 'notification-master' ) );
	}

	/**
	 * The service worker file loads from the site as JavaScript.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function check_service_worker() {
		$label = __( 'Service worker', 'notification-master' );
		$url   = NOTIFICATION_MASTER_URL . 'assets/js/service-worker.js';

		// Loopback request; local sites often use self-signed certificates.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 2,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			/* translators: 1: error message, 2: service worker URL */
			return self::check( 'service_worker', $label, false, sprintf( __( 'Your server could not load the service worker (%1$s). Open %2$s in your browser to check it.', 'notification-master' ), $response->get_error_message(), $url ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );

		if ( 200 !== $code ) {
			/* translators: 1: HTTP status code, 2: service worker URL */
			return self::check( 'service_worker', $label, false, sprintf( __( 'The service worker returned HTTP %1$d. Check that %2$s opens in your browser.', 'notification-master' ), $code, $url ) );
		}

		if ( '' !== $type && false === stripos( $type, 'javascript' ) ) {
			/* translators: %s: content type */
			return self::check( 'service_worker', $label, false, sprintf( __( 'The service worker is served as "%s". Browsers need a JavaScript content type.', 'notification-master' ), $type ) );
		}

		return self::check( 'service_worker', $label, true, __( 'The browser can load it from your site.', 'notification-master' ) );
	}

	/**
	 * PHP can encrypt push payloads (needed extensions, EC keys via OpenSSL or GMP).
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function check_server() {
		$label   = __( 'Server support', 'notification-master' );
		$missing = array_values(
			array_filter(
				array( 'openssl', 'curl', 'mbstring' ),
				function ( $extension ) {
					return ! extension_loaded( $extension );
				}
			)
		);

		if ( $missing ) {
			/* translators: %s: comma-separated PHP extension names */
			return self::check( 'server', $label, false, sprintf( __( 'PHP is missing these extensions: %s. Ask your host to enable them.', 'notification-master' ), implode( ', ', $missing ) ) );
		}

		// The push library falls back to GMP when OpenSSL cannot make P-256 keys.
		if ( ! extension_loaded( 'gmp' ) && ! self::openssl_can_make_ec_key() ) {
			return self::check( 'server', $label, false, __( 'PHP cannot create the keys that encrypt push messages. Ask your host to enable the GMP extension or fix the OpenSSL setup.', 'notification-master' ) );
		}

		return self::check( 'server', $label, true, __( 'PHP can encrypt and send push messages.', 'notification-master' ) );
	}

	/**
	 * Whether OpenSSL can create a P-256 key here.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private static function openssl_can_make_ec_key() {
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return false;
		}

		$key = @openssl_pkey_new( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure is the answer we want.
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);

		return false !== $key;
	}

	/**
	 * At least one active subscriber.
	 *
	 * @since 2.0.0
	 *
	 * @param int $count Active subscribers.
	 *
	 * @return array
	 */
	public static function check_subscribers( $count ) {
		$detail = $count > 0
			/* translators: %s: number of subscribers */
			? sprintf( _n( '%s active subscriber.', '%s active subscribers.', $count, 'notification-master' ), number_format_i18n( $count ) )
			: __( 'No one has subscribed yet. Add the subscribe button or the floating bell to your site.', 'notification-master' );

		return self::check( 'subscribers', __( 'Subscribers', 'notification-master' ), $count > 0, $detail );
	}

	/**
	 * Whether a base64url key decodes to the expected length (and first byte).
	 *
	 * @since 2.0.0
	 *
	 * @param string $key    Key.
	 * @param int    $length Expected byte length.
	 * @param string $first  Expected first byte, or ''.
	 *
	 * @return bool
	 */
	public static function is_valid_key( $key, $length, $first = '' ) {
		if ( ! preg_match( '/^[A-Za-z0-9_\-]+=*$/', $key ) ) {
			return false;
		}

		$raw = base64_decode( strtr( rtrim( $key, '=' ), '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- VAPID keys are base64url.

		return false !== $raw && strlen( $raw ) === $length && ( '' === $first || $raw[0] === $first );
	}

	/**
	 * Build one check row.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id     Check ID.
	 * @param string $label  Label.
	 * @param bool   $ok     Passed.
	 * @param string $detail Detail text.
	 *
	 * @return array
	 */
	private static function check( $id, $label, $ok, $detail ) {
		return array(
			'id'     => $id,
			'label'  => $label,
			'ok'     => (bool) $ok,
			'detail' => $detail,
		);
	}
}
