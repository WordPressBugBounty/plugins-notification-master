<?php
/**
 * Class Cache_Purge
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\WebPush;

use Notification_Master\Settings;

/**
 * Purges page caches when a Web Push setting that changes the front end is saved.
 */
class Cache_Purge {

	/**
	 * Setting key prefixes the front end reads (button, floating bell).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	const PREFIXES = array( 'normal_button_', 'floating_button_', 'enable_floating_button' );

	/**
	 * Other keys printed into the page (public key and auto prompt in the localized script).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	const KEYS = array( 'webpush_public_key', 'webpush_auto_prompt' );

	/**
	 * Hook the settings option and the purge.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_option_' . Settings::$option_name, array( __CLASS__, 'on_settings_added' ), 10, 2 );
		add_action( 'update_option_' . Settings::$option_name, array( __CLASS__, 'on_settings_updated' ), 10, 2 );
		add_action( 'notification_master_webpush_settings_changed', array( __CLASS__, 'purge' ) );
	}

	/**
	 * First save of the settings option.
	 *
	 * @since 2.0.0
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 *
	 * @return void
	 */
	public static function on_settings_added( $option, $value ) {
		self::on_settings_updated( array(), $value );
	}

	/**
	 * Fire the changed action when a front-end Web Push setting changed.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 *
	 * @return void
	 */
	public static function on_settings_updated( $old_value, $new_value ) {
		$changed = self::changed_keys( $old_value, $new_value );
		if ( empty( $changed ) ) {
			return;
		}

		/**
		 * Fires after a Web Push setting that changes the front end is saved.
		 *
		 * The plugin purges known page caches on it; hosts can hook their own purge here.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $changed   Changed setting keys.
		 * @param array    $old_value Old settings.
		 * @param array    $new_value New settings.
		 */
		do_action( 'notification_master_webpush_settings_changed', $changed, (array) $old_value, (array) $new_value );
	}

	/**
	 * Front-end Web Push keys whose value differs between two settings arrays.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $old_value Old settings.
	 * @param mixed $new_value New settings.
	 *
	 * @return string[]
	 */
	public static function changed_keys( $old_value, $new_value ) {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();
		$changed   = array();

		foreach ( array_keys( $old_value + $new_value ) as $key ) {
			if ( ! self::is_front_end_key( (string) $key ) ) {
				continue;
			}
			$before = array_key_exists( $key, $old_value ) ? self::normalize( $old_value[ $key ] ) : null;
			$after  = array_key_exists( $key, $new_value ) ? self::normalize( $new_value[ $key ] ) : null;
			if ( $before !== $after ) {
				$changed[] = (string) $key;
			}
		}

		return $changed;
	}

	/**
	 * Whether the front end reads this setting.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Setting key.
	 *
	 * @return bool
	 */
	public static function is_front_end_key( $key ) {
		if ( in_array( $key, self::KEYS, true ) ) {
			return true;
		}
		foreach ( self::PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make "10" and 10 (or true and "1") compare equal, so a re-typed value is not a change.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	private static function normalize( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'normalize' ), $value );
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return is_scalar( $value ) ? (string) $value : $value;
	}

	/**
	 * Purge every known page cache that is present. Object cache is left alone.
	 *
	 * @since 2.0.0
	 *
	 * @return string[] Names of the caches purged.
	 */
	public static function purge() {
		$purged = array();
		foreach ( self::purgers() as $name => $purger ) {
			if ( ! $purger['present']() ) {
				continue;
			}
			try {
				$purger['run']();
				$purged[] = $name;
			} catch ( \Throwable $e ) {
				// A cache plugin error must not break the settings save.
				continue;
			}
		}

		return $purged;
	}

	/**
	 * Known page caches: a presence check and a purge call each.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{present: callable, run: callable}>
	 */
	private static function purgers() {
		$action = function ( $hook ) {
			return array(
				'present' => function () use ( $hook ) {
					return (bool) has_action( $hook );
				},
				'run'     => function () use ( $hook ) {
					do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Third-party purge hooks.
				},
			);
		};
		$func   = function ( $name, $run ) {
			return array(
				'present' => function () use ( $name ) {
					return function_exists( $name );
				},
				'run'     => $run,
			);
		};

		return array(
			'wp-rocket'        => $func( 'rocket_clean_domain', 'rocket_clean_domain' ),
			'w3-total-cache'   => $func( 'w3tc_flush_all', 'w3tc_flush_all' ),
			'litespeed'        => $action( 'litespeed_purge_all' ),
			'wp-super-cache'   => $func(
				'wp_cache_clear_cache',
				function () {
					wp_cache_clear_cache( is_multisite() ? get_current_blog_id() : 0 );
				}
			),
			'wp-optimize'      => array(
				'present' => function () {
					return function_exists( 'WP_Optimize' ) && class_exists( 'WPO_Page_Cache' );
				},
				'run'     => function () {
					WP_Optimize()->get_page_cache()->purge();
				},
			),
			'siteground'       => $func( 'sg_cachepress_purge_cache', 'sg_cachepress_purge_cache' ),
			'wp-fastest-cache' => $action( 'wpfc_clear_all_cache' ),
			'breeze'           => $action( 'breeze_clear_all_cache' ),
			'hummingbird'      => $action( 'wphb_clear_page_cache' ),
			'cache-enabler'    => $action( 'cache_enabler_clear_site_cache' ),
			'nginx-helper'     => $action( 'rt_nginx_helper_purge_all' ),
		);
	}
}
