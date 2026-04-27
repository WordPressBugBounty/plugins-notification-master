<?php
/**
 * Class Admin
 *
 * @package notification-master
 * @since 1.0.0
 */

namespace Notification_Master\Admin;

use Notification_Master\Utils;
use Notification_Master\Users\Users;
use Notification_Master\DB\Models\Subscription_Model;

/**
 * Manage admin related functionality.
 */
class Admin {

	/**
	 * Admin menu slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $menu_slug = 'ntfm-home';

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var Admin
	 */
	private static $instance;

	/**
	 * Get instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Admin
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Admin ) ) {
			self::$instance = new Admin();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );

		if ( ! class_exists( '_WP_Editors' ) ) {
			require ABSPATH . 'wp-includes/class-wp-editor.php';
		}

		// add_action( 'admin_enqueue_scripts', array( '_WP_Editors', 'force_uncompressed_tinymce' ), 1 );
		// add_action( 'admin_enqueue_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ), 45 );

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Remove notices.
		add_action( 'admin_notices', array( $this, 'remove_notices' ), 1 );

		// Initialize review notice.
		new Review_Notice();

		// Allow SVG uploads for admin users.
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_mime_type' ), 10, 5 );
	}

	/**
	 * Remove Notices.
	 *
	 * @since 1.0.0
	 */
	public function remove_notices() {
		// Check if notification-master is the current page.
		$current_screen = get_current_screen();
		// Check if current screen has a ntfm- prefix.
		if ( strpos( $current_screen->id, 'ntfm-' ) !== false ) {
			// Remove notices.
			remove_all_actions( 'admin_notices' );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		$assets_dir   = NOTIFICATION_MASTER_DIR . 'dist';
		$assets       = file_exists( "$assets_dir/index.asset.php" ) ? require_once "$assets_dir/index.asset.php" : array();
		$dependencies = isset( $assets['dependencies'] ) ? $assets['dependencies'] : array();
		$version      = isset( $assets['version'] ) ? $assets['version'] : NOTIFICATION_MASTER_VERSION;

		wp_register_script(
			'notification-master-admin',
			NOTIFICATION_MASTER_URL . '/dist/index.js',
			$dependencies,
			$version,
			true
		);

		wp_register_style(
			'notification-master-admin',
			NOTIFICATION_MASTER_URL . '/dist/style.css',
			array(
				'wp-components',
				'wp-editor',
			),
			$version
		);

		// Localize script.
		wp_localize_script(
			'notification-master-admin',
			'NotificationsMasterConfig',
			apply_filters(
				'notification_master_admin_config',
				array(
					'adminUrl'           => admin_url(),
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'assetsUrl'          => NOTIFICATION_MASTER_URL . 'assets/',
					'nonce'              => wp_create_nonce( 'notification-master' ),
					'parentPageSlug'     => $this->menu_slug,
					'postTypes'          => Utils::get_post_types(),
					'taxonomies'         => Utils::get_taxonomies(),
					'commentTypes'       => Utils::get_comment_types(),
					'totalNotifications' => Utils::get_total_notifications_count(),
					'triggersGroups'     => apply_filters( 'notification_master_triggers', Utils::get_triggers_groups() ),
					'integrations'       => apply_filters( 'notification_master_integrations', array() ),
					'ntfmSiteUrl'        => NOTIFICATION_MASTER_SITE,
					'isPro'              => apply_filters( 'notification_master_is_pro', false ),
					'userRoles'          => Users::get_instance()->get_roles_options(),
					'subscriptionCount'  => Subscription_Model::get_count(),
				)
			)
		);
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Notification Master', 'notification-master' ),
			__( 'Notification Master', 'notification-master' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_admin_page' ),
			'data:image/svg+xml;base64,' . base64_encode(
				'<svg xmlns="http://www.w3.org/2000/svg" version="1.2" width="80" height="80" viewBox="0 0 80 80">
					<g id="solid_logo" data-name="solid logo" transform="translate(-798.232 -2542.232)">
						<circle id="Ellipse_1" data-name="Ellipse 1" cx="40" cy="40" r="40" transform="translate(798.232 2542.232)" fill="#eee"/>
						<path id="Subtraction_6" data-name="Subtraction 6" d="M38.073,34.55H2.029A2.024,2.024,0,0,1,.118,33.016a2.426,2.426,0,0,1,.868-2.744l2.949-2.011A8.382,8.382,0,0,0,7.38,21.349V12.474A13.3,13.3,0,0,1,10.6,3.654,10.289,10.289,0,0,1,18.359,0h3.385a10.288,10.288,0,0,1,7.762,3.654,13.3,13.3,0,0,1,3.215,8.821v8.875a8.383,8.383,0,0,0,3.445,6.912l2.95,2.011a2.426,2.426,0,0,1,.868,2.744A2.023,2.023,0,0,1,38.073,34.55ZM12.545,23.837a3.683,3.683,0,0,0,.906,2c.993.888,2.486.888,5.459.888h2.283c2.974,0,4.466,0,5.459-.888a3.687,3.687,0,0,0,.906-2ZM11.672,15.7h0a10.312,10.312,0,0,0,.117,1.98l.193,1.792q.05.467.1.919l0,.027c.068.652.132,1.267.206,1.844h15.53c.076-.593.141-1.222.21-1.888q.046-.442.095-.9l.192-1.79,0-.022a10.049,10.049,0,0,0,.115-1.961h.019a1.576,1.576,0,1,0-1.192-.546,10.479,10.479,0,0,0-1.46,1.325c-.521.519-.781.778-1.071.819a.879.879,0,0,1-.472-.066c-.269-.119-.447-.439-.8-1.077l-1.879-3.368-.008-.015c-.2-.365-.4-.709-.561-.975a2.1,2.1,0,1,0-1.906,0c-.166.266-.358.61-.561.975l-.008.015L16.65,16.156c-.355.638-.533.958-.8,1.077a.879.879,0,0,1-.472.066c-.289-.041-.549-.3-1.067-.814a10.51,10.51,0,0,0-1.465-1.33,1.574,1.574,0,1,0-1.192.546h.018Z" transform="translate(818.277 2567.194)"/>
						<circle id="Ellipse_2" data-name="Ellipse 2" cx="3.011" cy="3.011" r="3.011" transform="translate(835.317 2558.39)"/>
						<circle id="Ellipse_3" data-name="Ellipse 3" cx="4.843" cy="4.843" r="4.843" transform="translate(833.485 2596.902)"/>
					</g>
				</svg>'
			),
			2
		);

		// Add submenus.
		add_submenu_page(
			$this->menu_slug,
			__( 'Home', 'notification-master' ),
			__( 'Home', 'notification-master' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Notifications', 'notification-master' ),
			__( 'Notifications', 'notification-master' ),
			'manage_options',
			'ntfm-notifications',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Settings', 'notification-master' ),
			__( 'Settings', 'notification-master' ),
			'manage_options',
			'ntfm-settings',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Subscriptions', 'notification-master' ),
			__( 'Subscriptions', 'notification-master' ),
			'manage_options',
			'ntfm-webpush-subscriptions',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Notification Log', 'notification-master' ),
			__( 'Notification Log', 'notification-master' ),
			'manage_options',
			'ntfm-notification-log',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Debug Log', 'notification-master' ),
			__( 'Debug Log', 'notification-master' ),
			'manage_options',
			'ntfm-debug-log',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 * This will render just the admin page wrapper and the content will be rendered by React.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {

		do_action( 'notification_master_before_admin_page' );

		\_WP_Editors::enqueue_default_editor();
		wp_enqueue_media();
		wp_enqueue_script( 'notification-master-admin' );
		wp_enqueue_style( 'notification-master-admin' );

		// Load "Poppins" font.
		wp_enqueue_style(
			'notification-master-poppins-font',
			'https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap',
			array(),
			'1.0.0'
		);
		?>
		<div id="notification-master-admin"></div>
		<?php

		do_action( 'notification_master_after_admin_page' );
	}

	/**
	 * Allow SVG uploads for users with manage_options capability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $mimes Existing mime types.
	 *
	 * @return array Modified mime types.
	 */
	public function allow_svg_upload( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}

		return $mimes;
	}

	/**
	 * Fix SVG mime type validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data     File data array.
	 * @param string $file     Full path to the file.
	 * @param string $filename The name of the file.
	 * @param array  $mimes    Array of mime types.
	 * @param string $real_mime The actual mime type or empty if unknown.
	 *
	 * @return array Modified file data.
	 */
	public function fix_svg_mime_type( $data, $file, $filename, $mimes, $real_mime ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$wp_filetype = wp_check_filetype( $filename, $mimes );

		if ( 'svg' === $wp_filetype['ext'] || 'svgz' === $wp_filetype['ext'] ) {
			$data['ext']  = $wp_filetype['ext'];
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}
}
