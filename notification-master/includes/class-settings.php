<?php
/**
 * Class Settings
 *
 * @package notification-master
 * @since 1.0.0
 */

namespace Notification_Master;

/**
 * Manage settings related functionality.
 */
class Settings {

	/**
	 * Option name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public static $option_name = 'notification_master_settings';

	/**
	 * Get settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $default
	 *
	 * @return array
	 */
	public static function get_settings( $default = array() ) {
		$settings = get_option( self::$option_name, $default );
		return $settings;
	}

	/**
	 * Get setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return $default;
	}

	/**
	 * Update settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings
	 *
	 * @return bool
	 */
	public static function update_settings( $settings ) {
		return update_option( self::$option_name, $settings );
	}

	/**
	 * Update setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public static function update_setting( $key, $value ) {
		$settings         = self::get_settings();
		$settings[ $key ] = $value;
		return self::update_settings( $settings );
	}

	/**
	 * Delete settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function delete_settings() {
		return delete_option( self::$option_name );
	}

	/**
	 * Delete setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function delete_setting( $key ) {
		$settings = self::get_settings();
		if ( isset( $settings[ $key ] ) ) {
			unset( $settings[ $key ] );
			return self::update_settings( $settings );
		}
		return false;
	}
}
