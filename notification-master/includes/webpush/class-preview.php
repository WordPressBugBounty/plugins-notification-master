<?php
/**
 * Class Preview
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\WebPush;

use Notification_Master\REST_API\Controllers\V1\Rest_Settings_Controller;
use Notification_Master\Settings;

/**
 * Renders the subscribe button and floating bell exactly as the front end would, for unsaved settings.
 * Settings are swapped in memory with the `pre_option_*` filter, so the real Loader code paths run unchanged.
 */
class Preview {

	/**
	 * Setting key prefixes the preview accepts (button and bell only).
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	const PREFIXES = array( 'normal_button_', 'floating_button_', 'enable_floating_button' );

	/**
	 * Settings used while rendering.
	 *
	 * @since 2.0.0
	 *
	 * @var array|null
	 */
	private static $override = null;

	/**
	 * Keep only button/bell keys from a posted patch.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $patch Posted settings.
	 * @return array
	 */
	public static function filter_patch( $patch ) {
		if ( ! is_array( $patch ) ) {
			return array();
		}

		$clean = array();
		foreach ( $patch as $key => $value ) {
			foreach ( self::PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					$clean[ $key ] = $value;
					break;
				}
			}
		}

		return $clean;
	}

	/**
	 * Saved settings with the patch applied through the same typing/clamping as a save.
	 * Keys the site never saved stay absent (the loader treats a saved opacity differently).
	 *
	 * @since 2.0.0
	 *
	 * @param array $patch Button/bell keys.
	 * @return array Settings as get_option() would return them after the save.
	 */
	public static function settings_for( array $patch ) {
		$controller = new Rest_Settings_Controller();
		$schema     = $controller->get_schema();
		$stored     = Settings::get_settings( array() );
		$stored     = is_array( $stored ) ? $stored : array();
		$merged     = $controller->apply_patch( wp_parse_args( $stored, $schema['default'] ), $patch, $schema );

		foreach ( array_keys( $patch ) as $key ) {
			if ( array_key_exists( $key, $merged ) ) {
				$stored[ $key ] = $merged[ $key ];
			}
		}

		// The markup only prints on a configured site; a placeholder lets the preview work before keys exist.
		foreach ( array( 'webpush_public_key', 'webpush_private_key' ) as $key ) {
			if ( empty( $stored[ $key ] ) ) {
				$stored[ $key ] = 'preview';
			}
		}

		return $stored;
	}

	/**
	 * Short-circuit get_option() for the plugin settings while rendering.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public static function override_option() {
		return self::$override;
	}

	/**
	 * Render both widgets with unsaved settings.
	 *
	 * @since 2.0.0
	 *
	 * @param array $patch Unsaved button/bell settings.
	 * @return array { button, bell, head, stylesheets, inline_css, base_url, context_url }.
	 */
	public static function render( array $patch ) {
		self::$override = self::settings_for( self::filter_patch( $patch ) );
		$filter         = 'pre_option_' . Settings::$option_name;
		add_filter( $filter, array( __CLASS__, 'override_option' ) );

		$context = self::context_post_id();

		try {
			$loader = Loader::get_instance();
			$button = $loader->get_button_markup();
			$bell   = $loader->get_floating_button_markup( true );
			$styles = self::front_styles( $context );
		} finally {
			remove_filter( $filter, array( __CLASS__, 'override_option' ) );
			self::$override = null;
		}

		return array_merge(
			array(
				'button'      => trim( $button ),
				'bell'        => trim( $bell ),
				'base_url'    => trailingslashit( home_url() ),
				'context_url' => $context ? (string) get_permalink( $context ) : '',
				'lang'        => get_bloginfo( 'language' ),
				'dir'         => is_rtl() ? 'rtl' : 'ltr',
			),
			$styles
		);
	}

	/**
	 * A published page that shows the subscribe button: its <head> and wrappers are the real context of the button.
	 *
	 * @since 2.0.0
	 *
	 * @return int Post ID, or 0 when no page uses the shortcode.
	 */
	private static function context_post_id() {
		// Cached 5 minutes; a cached page that no longer qualifies is looked up again.
		$cached = get_transient( 'ntfm_preview_context_post' );
		if ( false !== $cached ) {
			$post = (int) $cached > 0 ? get_post( (int) $cached ) : null;
			if ( 0 === (int) $cached || ( $post && 'publish' === $post->post_status && false !== strpos( $post->post_content, 'notification-master-subscribe-btn' ) ) ) {
				return (int) $cached;
			}
		}

		$found = self::find_context_post_id();
		set_transient( 'ntfm_preview_context_post', $found, 5 * MINUTE_IN_SECONDS );

		return $found;
	}

	/**
	 * Search for a published post that contains the subscribe button shortcode.
	 *
	 * @since 2.0.0
	 *
	 * @return int Post ID or 0.
	 */
	private static function find_context_post_id() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				's'              => 'notification-master-subscribe-btn',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return $query->posts ? (int) $query->posts[0] : 0;
	}

	/**
	 * Main query for the context page, so conditional enqueues and body classes match that page.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id Context page ID.
	 * @return \WP_Query
	 */
	private static function context_query( $post_id ) {
		$post = get_post( $post_id );
		$args = 'page' === $post->post_type
			? array( 'page_id' => $post_id )
			: array(
				'p'         => $post_id,
				'post_type' => $post->post_type,
			);

		return new \WP_Query( $args );
	}

	/**
	 * What the front end prints in <head>, without scripts (theme fonts and styles, global styles, this plugin + its inline CSS).
	 * Runs `wp_head` on fresh style/script queues, then restores the globals.
	 *
	 * @since 2.0.0
	 *
	 * @param int $context Context page ID (0 = none).
	 * @return array { head, stylesheets, inline_css, body_class }.
	 */
	private static function front_styles( $context ) {
		global $wp_styles, $wp_scripts, $wp_query, $wp_the_query, $post;

		$saved = array( $wp_styles, $wp_scripts, $wp_query, $wp_the_query, $post );
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- swapped for this render, restored below.
		$wp_styles  = new \WP_Styles();
		$wp_scripts = new \WP_Scripts();
		if ( $context ) {
			$wp_query     = self::context_query( $context );
			$wp_the_query = $wp_query;
			$post         = get_post( $context );
			setup_postdata( $post );
		}
		// phpcs:enable

		ob_start();
		try {
			// The whole front-end <head>: enqueued styles, global styles, font faces, theme <link> tags, custom CSS.
			do_action( 'wp_head' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		} catch ( \Throwable $e ) {
			// A theme/plugin callback failed outside a page request: keep what printed so far.
			unset( $e );
		}
		// Styles only: the preview iframe never runs scripts (no <script> tags, no inline on* handlers).
		$head = (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) ob_get_clean() );
		$head = (string) preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $head );

		$stylesheets = array();
		foreach ( (array) $wp_styles->done as $handle ) {
			$src = isset( $wp_styles->registered[ $handle ] ) ? $wp_styles->registered[ $handle ]->src : '';
			if ( $src ) {
				$stylesheets[] = $wp_styles->_css_href( $src, $wp_styles->registered[ $handle ]->ver, $handle );
			}
		}
		$inline = $wp_styles->get_data( 'notification-master-webpush', 'after' );
		// Visitors see the button: drop the classes only a logged-in admin gets.
		$body_class = $context ? implode( ' ', array_diff( get_body_class(), array( 'logged-in', 'admin-bar', 'no-customize-support' ) ) ) : '';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.
		list( $wp_styles, $wp_scripts, $wp_query, $wp_the_query, $post ) = $saved;
		if ( $post instanceof \WP_Post ) {
			setup_postdata( $post );
		}

		return array(
			'head'        => $head,
			'stylesheets' => array_values( array_filter( $stylesheets ) ),
			'inline_css'  => is_array( $inline ) ? implode( "\n", $inline ) : '',
			'body_class'  => $body_class,
		);
	}
}
