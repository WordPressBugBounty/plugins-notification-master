<?php
/**
 * Class Tier
 *
 * Free/Pro facts shared by the REST layer and the abilities layer.
 *
 * @package notification-master
 * @since 1.8.0
 */

namespace Notification_Master;

use WP_Error;

/**
 * Answers "is Pro active" and names the channels, triggers and settings Free may not use.
 */
class Tier {

	/**
	 * Integration slugs the free plugin ships.
	 *
	 * @since 1.8.0
	 *
	 * @var array
	 */
	const FREE_INTEGRATIONS = array( 'webpush', 'email', 'webhook', 'discord', 'instagram' );

	/**
	 * Integration slugs only Pro registers. Mirrors Integrations\Loader::load_pro_integrations().
	 *
	 * @since 1.8.0
	 *
	 * @var array
	 */
	const PRO_INTEGRATIONS = array( 'facebook', 'twitterx', 'twilio', 'slack', 'zapier', 'make', 'whatsapp' );

	/**
	 * Slug prefixes of Pro's WooCommerce triggers (order_*, product_*, review_*).
	 *
	 * @since 1.8.0
	 *
	 * @var array
	 */
	const PRO_TRIGGER_PREFIXES = array( 'order_', 'product_', 'review_' );

	/**
	 * Settings keys only Pro reads.
	 *
	 * @since 1.8.0
	 *
	 * @var array
	 */
	const PRO_SETTINGS = array( 'woocommerce_change_trigger' );

	/**
	 * Whether the Pro tier is active.
	 *
	 * Pro attaches the filter only when ntfmp_fs()->can_use_premium_code(),
	 * so a lapsed or missing licence counts as Free.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'notification_master_is_pro', false );
	}

	/**
	 * Whether an integration slug belongs to Pro.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $slug Integration slug.
	 *
	 * @return bool
	 */
	public static function is_pro_integration( $slug ) {
		return in_array( (string) $slug, self::PRO_INTEGRATIONS, true );
	}

	/**
	 * Whether a trigger slug belongs to Pro (prefix match).
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $slug Trigger slug.
	 *
	 * @return bool
	 */
	public static function is_pro_trigger( $slug ) {
		$slug = (string) $slug;

		foreach ( self::PRO_TRIGGER_PREFIXES as $prefix ) {
			if ( 0 === strpos( $slug, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * First connection id that needs Pro, or null.
	 *
	 * A connection needs Pro when its integration is a Pro slug, or when it
	 * uses conditions. Always null when Pro is active.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $connections Connections keyed by connection id.
	 *
	 * @return string|null
	 */
	public static function pro_only_connection_key( $connections ) {
		if ( self::is_pro() || ! is_array( $connections ) ) {
			return null;
		}

		foreach ( $connections as $connection_id => $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$integration = isset( $connection['integration'] ) ? $connection['integration'] : '';
			if ( self::is_pro_integration( $integration ) ) {
				return (string) $connection_id;
			}

			if ( isset( $connection['enable_conditions'] ) ) {
				// Tolerate "true"/"false" strings from REST input.
				$enabled = function_exists( 'rest_sanitize_boolean' )
					? rest_sanitize_boolean( $connection['enable_conditions'] )
					: (bool) $connection['enable_conditions'];
				if ( $enabled ) {
					return (string) $connection_id;
				}
			}

			if ( ! empty( $connection['conditions'] ) && is_array( $connection['conditions'] ) ) {
				return (string) $connection_id;
			}
		}

		return null;
	}

	/**
	 * First Pro-only settings key the request would change, or null.
	 *
	 * With $current, a key whose value equals the stored one is tolerated so the
	 * free settings page can post its whole state back. Without $current,
	 * presence alone is enough.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed      $settings Settings from the request.
	 * @param array|null $current  Stored settings merged over defaults.
	 *
	 * @return string|null
	 */
	public static function pro_only_setting_key( $settings, $current = null ) {
		if ( self::is_pro() || ! is_array( $settings ) ) {
			return null;
		}

		foreach ( self::PRO_SETTINGS as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			if ( ! is_array( $current ) ) {
				return $key;
			}

			$stored = array_key_exists( $key, $current ) ? $current[ $key ] : null;
			if ( self::setting_differs( $settings[ $key ], $stored ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Drop the Pro-only keys from a settings patch on Free.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $settings Settings from the request.
	 *
	 * @return mixed
	 */
	public static function strip_pro_settings( $settings ) {
		if ( self::is_pro() || ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( self::PRO_SETTINGS as $key ) {
			unset( $settings[ $key ] );
		}

		return $settings;
	}

	/**
	 * Whether an incoming value differs from the stored one (booleans compared loosely).
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $incoming Request value.
	 * @param mixed $stored   Stored value.
	 *
	 * @return bool
	 */
	private static function setting_differs( $incoming, $stored ) {
		if ( is_bool( $incoming ) || is_bool( $stored ) ) {
			return rest_sanitize_boolean( $incoming ) !== rest_sanitize_boolean( $stored );
		}

		return $incoming !== $stored;
	}

	/**
	 * Build the 403 refusal used whenever Free asks for a Pro feature.
	 *
	 * @since 1.8.0
	 *
	 * @param string $what  Feature name shown to the caller.
	 * @param array  $extra Extra error data merged over status/feature.
	 *
	 * @return WP_Error
	 */
	public static function pro_required_error( $what, array $extra = array() ) {
		return new WP_Error(
			'ntfm_pro_required',
			/* translators: %s: feature name */
			sprintf( __( '%s needs Notification Master Pro.', 'notification-master' ), $what ),
			array_merge(
				array(
					'status'  => 403,
					'feature' => $what,
				),
				$extra
			)
		);
	}
}
