<?php
/**
 * Class Log_Formatter
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Logs;

use WP_Error;

/**
 * Turns a notification log row into the REST shape with flat helper fields.
 */
class Log_Formatter {

	/**
	 * Cached integration names keyed by slug.
	 *
	 * @since 2.0.0
	 *
	 * @var array|null
	 */
	private static $names = null;

	/**
	 * Format one DB row; keeps the 1.x keys and adds helpers.
	 *
	 * @since 2.0.0
	 *
	 * @param object $row DB row.
	 *
	 * @return array
	 */
	public static function format_row( $row ) {
		$content = maybe_unserialize( $row->content );
		$data    = is_array( $content ) ? $content : array();

		return array(
			'id'                => $row->id,
			'integration'       => $row->integration,
			'status'            => $row->status,
			'content'           => $content,
			'date'              => $row->created_at,
			'integration_name'  => self::integration_name( $row->integration ),
			'notification_id'   => isset( $data['notification_id'] ) ? (int) $data['notification_id'] : null,
			'notification_name' => isset( $data['notification_name'] ) ? (string) $data['notification_name'] : null,
			'trigger'           => isset( $data['trigger'] ) ? (string) $data['trigger'] : null,
			'trigger_name'      => isset( $data['trigger_name'] ) ? (string) $data['trigger_name'] : null,
			'message'           => isset( $data['message'] ) && is_scalar( $data['message'] ) ? (string) $data['message'] : null,
			'reason'            => self::get_reason( $data ),
			'http_code'         => self::get_http_code( $data ),
			'recipient'         => self::get_recipient( $data ),
			'test'              => ! empty( $data['test'] ),
		);
	}

	/**
	 * Get a readable integration name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Integration slug.
	 *
	 * @return string
	 */
	public static function integration_name( $slug ) {
		if ( null === self::$names ) {
			self::$names = array();
			/** This filter is documented in includes/admin/class-admin.php */
			$integrations = apply_filters( 'notification_master_integrations', array() );
			foreach ( (array) $integrations as $key => $integration ) {
				if ( is_array( $integration ) && isset( $integration['name'] ) ) {
					self::$names[ $key ] = (string) $integration['name'];
				}
			}
		}

		return isset( self::$names[ $slug ] ) ? self::$names[ $slug ] : ucfirst( (string) $slug );
	}

	/**
	 * Get the HTTP status code a send got back, if any.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Log content.
	 *
	 * @return int|null
	 */
	public static function get_http_code( $data ) {
		foreach ( array( 'http_code', 'response_code', 'status_code' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
				return (int) $data[ $key ];
			}
		}

		$response = isset( $data['response'] ) ? $data['response'] : null;
		if ( is_array( $response ) && isset( $response['response']['code'] ) && is_numeric( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return null;
	}

	/**
	 * Get the remote error text, if any.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Log content.
	 *
	 * @return string|null
	 */
	public static function get_reason( $data ) {
		$response = isset( $data['response'] ) ? $data['response'] : null;

		if ( $response instanceof WP_Error ) {
			return $response->get_error_message();
		}

		if ( isset( $data['error'] ) && is_string( $data['error'] ) && '' !== $data['error'] ) {
			return $data['error'];
		}

		if ( is_array( $response ) ) {
			// A wp_remote_* array: read the error out of its body.
			if ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
				$code = self::get_http_code( $data );
				if ( null === $code || $code < 400 ) {
					return null;
				}

				$decoded = json_decode( $response['body'], true );
				$text    = is_array( $decoded ) ? self::error_text( $decoded ) : null;

				return $text ? $text : self::short( wp_strip_all_tags( $response['body'] ) );
			}

			// An already decoded API body (Instagram).
			return self::error_text( $response );
		}

		return null;
	}

	/**
	 * Get the recipient: emails, webhook URL or a masked push endpoint.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data Log content.
	 *
	 * @return string|null
	 */
	public static function get_recipient( $data ) {
		if ( ! empty( $data['emails'] ) ) {
			$emails = is_array( $data['emails'] ) ? $data['emails'] : array( $data['emails'] );
			return implode( ', ', array_map( 'strval', array_filter( $emails, 'is_scalar' ) ) );
		}

		foreach ( array( 'webhook', 'url' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				return $data[ $key ];
			}
		}

		if ( ! empty( $data['subscription'] ) && is_string( $data['subscription'] ) ) {
			return self::mask_endpoint( $data['subscription'] );
		}

		return null;
	}

	/**
	 * Mask a push endpoint to host + last 6 characters.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint Endpoint URL.
	 *
	 * @return string
	 */
	public static function mask_endpoint( $endpoint ) {
		$host = wp_parse_url( $endpoint, PHP_URL_HOST );

		return ( $host ? $host : '' ) . '/…' . substr( $endpoint, -6 );
	}

	/**
	 * Pull an error message out of a decoded API body.
	 *
	 * @since 2.0.0
	 *
	 * @param array $body Decoded body.
	 *
	 * @return string|null
	 */
	private static function error_text( $body ) {
		if ( isset( $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
			return self::short( $body['error']['message'] );
		}

		foreach ( array( 'message', 'error', 'error_description', 'detail' ) as $key ) {
			if ( isset( $body[ $key ] ) && is_string( $body[ $key ] ) && '' !== $body[ $key ] ) {
				return self::short( $body[ $key ] );
			}
		}

		return null;
	}

	/**
	 * Trim text to 200 characters.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text.
	 *
	 * @return string|null
	 */
	private static function short( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		if ( '' === $text ) {
			return null;
		}

		return mb_strlen( $text ) > 200 ? mb_substr( $text, 0, 197 ) . '...' : $text;
	}
}
