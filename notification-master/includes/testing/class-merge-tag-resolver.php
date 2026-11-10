<?php
/**
 * Class Merge_Tag_Resolver
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Testing;

use Notification_Master\Abstracts\Trigger;
use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;

/**
 * Resolves merge tags one by one and keeps a tag as typed when it has no sample value.
 */
class Merge_Tag_Resolver {

	/**
	 * Resolve every tag in a value (string or nested array).
	 *
	 * @since 2.0.0
	 *
	 * @param Trigger $trigger Sample trigger.
	 * @param mixed   $value   Setting value.
	 *
	 * @return mixed
	 */
	public static function resolve( Trigger $trigger, $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::resolve( $trigger, $item );
			}
			return $value;
		}

		if ( ! is_string( $value ) || false === strpos( $value, '{{' ) ) {
			return $value;
		}

		if ( ! preg_match_all( '/{{[a-z0-9_\-]+\.[^}]*}}/i', $value, $matches ) ) {
			return $value;
		}

		foreach ( array_unique( $matches[0] ) as $tag ) {
			$resolved = self::resolve_tag( $trigger, $tag );
			if ( null !== $resolved ) {
				$value = str_replace( $tag, $resolved, $value );
			}
		}

		return $value;
	}

	/**
	 * Resolve one tag; null when there is no sample value.
	 *
	 * @since 2.0.0
	 *
	 * @param Trigger $trigger Sample trigger.
	 * @param string  $tag     Full tag, e.g. {{post.title}}.
	 *
	 * @return string|null
	 */
	private static function resolve_tag( Trigger $trigger, $tag ) {
		// Missing sample objects make some tag callbacks warn; a test must not break on that.
		set_error_handler( '__return_true' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restored below.

		try {
			$resolved = Merge_Tags_Loader::get_instance()->process_merge_tags( $trigger, $tag );
		} catch ( \Throwable $e ) {
			$resolved = null;
		}

		restore_error_handler();

		if ( ! is_scalar( $resolved ) ) {
			return null;
		}

		$resolved = (string) $resolved;

		return ( '' === $resolved || $resolved === $tag ) ? null : $resolved;
	}
}
