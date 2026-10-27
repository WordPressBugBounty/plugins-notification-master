<?php
/**
 * Class Redactor
 *
 * @package notification-master
 *
 * @since 1.8.0
 */

namespace Notification_Master\Abilities;

/**
 * Masks secrets and personal data before ability output leaves the site.
 *
 * Stateless and dependency-free. The Notification and Delivery providers
 * both call it, so every masking rule lives here and nowhere else.
 */
class Redactor {

	/**
	 * Connection setting keys that are plain content, per integration slug.
	 * Everything not listed here is masked; an unknown slug masks every key.
	 * Verified against each integration's get_attributes_schema() (free 1.8.0, Pro 1.6.1).
	 *
	 * @since 1.8.0
	 */
	const READABLE_KEYS = array(
		'email'     => array( 'subject', 'message' ),
		'webpush'   => array( 'send_to', 'title', 'message', 'icon', 'image', 'url', 'urgency' ),
		'discord'   => array( 'message' ),
		'webhook'   => array( 'method', 'body_format', 'body', 'show_empty_fields' ),
		'instagram' => array( 'text', 'alt_text', 'image_url' ),
		'slack'     => array( 'message' ),
		'twilio'    => array( 'type', 'message' ),
		'whatsapp'  => array( 'message_type', 'message', 'image_url', 'caption' ),
		'zapier'    => array( 'body' ),
		'make'      => array( 'body' ),
		'facebook'  => array( 'text', 'post_link' ),
		'twitterx'  => array( 'text', 'media' ),
	);

	/**
	 * Log content and push subscription keys that hold credentials or PII.
	 *
	 * @since 1.8.0
	 */
	const SECRET_KEYS = array(
		'url',
		'endpoint',
		'subscription',
		'auth',
		'p256dh',
		'headers',
		'emails',
		'excluded_emails',
		'specific_users',
		'access_token',
		'access_token_secret',
		'app_id',
		'app_secret',
		'page_id',
		'account_sid',
		'auth_token',
		'sms_number',
		'whatsapp_number',
		'to_number',
		'to',
		'phone_number_id',
		'consumer_key',
		'consumer_secret',
		'ip_address',
		'user_agent',
	);

	/**
	 * Keep only the integration's readable content keys; mask everything else.
	 *
	 * @since 1.8.0
	 *
	 * @param string $integration Integration slug.
	 * @param mixed  $settings    Connection settings as stored in post meta.
	 * @return array
	 */
	public static function redact_settings( $integration, $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$readable = self::readable_keys( $integration );
		$redacted = array();

		foreach ( $settings as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $readable, true ) ) {
				$redacted[ $key ] = $value;
				continue;
			}

			// Keep the size so an agent can tell an empty list from a filled one.
			$redacted[ $key ] = is_array( $value ) ? sprintf( '[redacted: %d items]', count( $value ) ) : '[redacted]';
		}

		return $redacted;
	}

	/**
	 * Readable setting keys for an integration; empty for an unknown slug.
	 *
	 * @since 1.8.0
	 *
	 * @param string $integration Integration slug.
	 * @return string[]
	 */
	public static function readable_keys( $integration ) {
		$slug = strtolower( (string) $integration );

		return isset( self::READABLE_KEYS[ $slug ] ) ? self::READABLE_KEYS[ $slug ] : array();
	}

	/**
	 * The subset of an integration's schema keys that get-notification masks.
	 *
	 * @since 1.8.0
	 *
	 * @param string $integration Integration slug.
	 * @param array  $keys        Top-level keys of the integration's settings schema.
	 * @return string[]
	 */
	public static function secret_keys( $integration, array $keys ) {
		return array_values( array_diff( array_map( 'strval', $keys ), self::readable_keys( $integration ) ) );
	}

	/**
	 * Recursively mask secret keys and email addresses in free-form content.
	 *
	 * The return type mirrors the input: arrays (and objects, cast to arrays)
	 * come back as arrays, strings as strings, scalars and null unchanged.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $value Log content of any shape.
	 * @return mixed
	 */
	public static function redact_content( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$redacted = array();

			foreach ( $value as $key => $item ) {
				// A secret key is masked whole; never descend into it.
				$redacted[ $key ] = self::has_secret_key( $key ) ? '[redacted]' : self::redact_content( $item );
			}

			return $redacted;
		}

		if ( is_string( $value ) ) {
			return self::redact_text( $value );
		}

		return $value;
	}

	/**
	 * Replace every email address in a string with '[email]'.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $text Text to mask; non-strings are returned unchanged.
	 * @return mixed
	 */
	public static function redact_text( $text ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}

		$masked = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $text );

		// A regex failure must over-redact, never leak.
		return null === $masked ? '[redacted]' : $masked;
	}

	/**
	 * Whether a content key is in SECRET_KEYS (case-insensitive).
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $key Array key.
	 * @return bool
	 */
	public static function has_secret_key( $key ) {
		return in_array( strtolower( (string) $key ), self::SECRET_KEYS, true );
	}
}
