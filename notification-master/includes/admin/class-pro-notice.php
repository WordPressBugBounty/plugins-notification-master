<?php
/**
 * Class Pro_Notice
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Admin;

/**
 * "Update Pro" notice, passed to the app config (rendered as a banner under the top bar).
 */
class Pro_Notice {

	/**
	 * First Pro version built for the 2.0 admin.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const MIN_PRO_VERSION = '2.0.0';

	/**
	 * Notice ID used in the config and by the dismiss routes.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const ID = 'pro_update';

	/**
	 * User meta key that stores the dismissed Pro version.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	const META_KEY = 'ntfm_pro_update_notice_dismissed';

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_filter( 'notification_master_admin_config', array( $this, 'add_to_config' ) );
		add_action( 'wp_ajax_ntfm_dismiss_pro_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Whether the notice applies to the current user and Pro version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function should_show() {
		if ( ! defined( 'NOTIFICATION_MASTER_PRO_VERSION' ) || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( version_compare( NOTIFICATION_MASTER_PRO_VERSION, self::MIN_PRO_VERSION, '>=' ) ) {
			return false;
		}

		return get_user_meta( get_current_user_id(), self::META_KEY, true ) !== NOTIFICATION_MASTER_PRO_VERSION;
	}

	/**
	 * The notice in the config shape, or null.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null
	 */
	public static function get_notice() {
		if ( ! self::should_show() ) {
			return null;
		}

		return array(
			'id'          => self::ID,
			'type'        => 'warning',
			'message'     => __( 'Update Notification Master Pro to 2.0 for the new design.', 'notification-master' ),
			'link_text'   => __( 'Go to Plugins', 'notification-master' ),
			'link_url'    => admin_url( 'plugins.php' ),
			'dismissible' => true,
		);
	}

	/**
	 * Append the notice to NotificationsMasterConfig.notices on the plugin pages.
	 *
	 * @since 2.0.0
	 *
	 * @param array $config Admin config.
	 *
	 * @return array
	 */
	public function add_to_config( $config ) {
		$config['notices'] = isset( $config['notices'] ) && is_array( $config['notices'] ) ? $config['notices'] : array();
		$notice            = Admin::is_plugin_page() ? self::get_notice() : null;

		if ( $notice ) {
			$config['notices'][] = $notice;
		}

		return $config;
	}

	/**
	 * Remember the dismissal for the current user and Pro version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function dismiss() {
		return (bool) update_user_meta( get_current_user_id(), self::META_KEY, defined( 'NOTIFICATION_MASTER_PRO_VERSION' ) ? NOTIFICATION_MASTER_PRO_VERSION : '' );
	}

	/**
	 * AJAX dismiss (action ntfm_dismiss_pro_notice, nonce = config.nonce).
	 *
	 * @since 2.0.0
	 */
	public function ajax_dismiss() {
		check_ajax_referer( 'notification-master', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		self::dismiss();
		wp_send_json_success();
	}
}
