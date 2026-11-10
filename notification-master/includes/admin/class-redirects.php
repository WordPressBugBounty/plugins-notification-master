<?php
/**
 * Class Redirects
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Admin;

/**
 * Sends old admin page slugs (1.x menu) to the new tabs.
 */
class Redirects {

	/**
	 * Old slug => [ new slug, app path or '' ].
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	const MAP = array(
		'ntfm-home'                  => array( 'notification-master', '' ),
		'ntfm-notification-log'      => array( 'ntfm-log', '' ),
		'ntfm-debug-log'             => array( 'ntfm-log', '/debug' ),
		'ntfm-webpush-subscriptions' => array( 'ntfm-webpush', '/subscribers' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		// Last admin_menu callback: Freemius pages exist and core's access check (which dies on unknown slugs) has not run.
		add_action( 'admin_menu', array( $this, 'maybe_redirect' ), PHP_INT_MAX );
	}

	/**
	 * Redirect when the request is an old slug.
	 *
	 * @since 2.0.0
	 */
	public function maybe_redirect() {
		global $pagenow;

		if ( 'admin.php' !== $pagenow || wp_doing_ajax() ) {
			return;
		}

		$target = self::get_target_url( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a GET page view.
		if ( ! $target ) {
			return;
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Build the new URL for an old-slug query, or null.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query Query args ($_GET).
	 *
	 * @return string|null
	 */
	public static function get_target_url( $query ) {
		$page = isset( $query['page'] ) ? sanitize_key( $query['page'] ) : '';
		if ( ! isset( self::MAP[ $page ] ) ) {
			return null;
		}

		// Old Pro's Freemius opt-in may register its own page on this slug; leave it alone.
		if ( 'ntfm-home' === $page && self::is_registered_page( $page ) ) {
			return null;
		}

		list( $new_page, $path ) = self::MAP[ $page ];

		$args = array();
		foreach ( (array) $query as $key => $value ) {
			if ( 'page' === $key || ! is_scalar( $value ) ) {
				continue;
			}
			$args[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}

		$args = array_merge( array( 'page' => $new_page ), $args );
		if ( '' !== $path ) {
			$args['path'] = $path;
		}

		return add_query_arg( rawurlencode_deep( $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Whether some code registered an admin page for a slug.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Page slug.
	 *
	 * @return bool
	 */
	private static function is_registered_page( $slug ) {
		global $_registered_pages;

		if ( ! is_array( $_registered_pages ) ) {
			return false;
		}

		return isset( $_registered_pages[ get_plugin_page_hookname( $slug, '' ) ] ) || isset( $_registered_pages[ get_plugin_page_hookname( $slug, 'admin.php' ) ] );
	}
}
