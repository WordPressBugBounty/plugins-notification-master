<?php
/**
 * Abilities bootstrap for Notification Master.
 *
 * Registers the plugin's two ability categories and every ability declared by
 * the provider classes in this directory. This layer owns no business logic:
 * each callback either calls an existing plugin method or dispatches to one
 * of the plugin's own REST routes.
 *
 * Nothing in includes/abilities/ names a core Abilities class. Every call goes
 * through the bare wp_* functions behind function_exists(), so on a WordPress
 * without the Abilities API these files are inert class definitions and the
 * plugin's "Requires at least: 4.9" header stays honest.
 *
 * @package notification-master
 * @since 1.8.0
 */

namespace Notification_Master\Abilities;

/**
 * Registers Notification Master's ability categories and abilities.
 *
 * @since 1.8.0
 */
class Abilities_Module {

	/**
	 * Category for read-only abilities. Nothing in it changes anything.
	 */
	const CATEGORY_READ = 'notification-master-read';

	/**
	 * Category for abilities that enable, edit, create or cancel.
	 *
	 * The split is deliberate: a reviewer can fetch this one category over
	 * REST and see the exact list of everything that writes.
	 */
	const CATEGORY_ACTIONS = 'notification-master-actions';

	/**
	 * Provider classes, each exposing a static definitions( $module ) method.
	 *
	 * A provider whose class is missing is skipped, so this list may name a
	 * class before its file ships.
	 */
	const PROVIDERS = array(
		Notification_Abilities::class,
		Catalog_Abilities::class,
		Delivery_Abilities::class,
	);

	/**
	 * Constructor. Takes no dependencies; everything is resolved lazily.
	 *
	 * @since 1.8.0
	 */
	public function __construct() {
		// Registered before the feature guard so the command can explain WHY
		// nothing registered on a WordPress older than 6.9.
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'notification-master abilities', array( $this, 'cli_manifest' ) );
		}

		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		// Race-free: Plugin::get_instance() runs at include time
		// (notifications-master.php), before plugins_loaded and init, and both
		// core registries are lazy singletons that cannot exist yet. So these
		// one-shot actions are always attached before they fire.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register both ability categories.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function register_categories() {
		wp_register_ability_category(
			self::CATEGORY_READ,
			array(
				'label'       => __( 'Notification Master (Read)', 'notification-master' ),
				'description' => __( 'Read-only inspection of notifications, triggers, channels, scheduled sends, logs and subscriber counts. Nothing in this category changes or sends anything.', 'notification-master' ),
			)
		);

		wp_register_ability_category(
			self::CATEGORY_ACTIONS,
			array(
				'label'       => __( 'Notification Master (Actions)', 'notification-master' ),
				'description' => __( 'Operations that enable, edit, create or cancel notifications and scheduled sends. Everything here writes.', 'notification-master' ),
			)
		);
	}

	/**
	 * Register every ability declared by the available providers.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function register_abilities() {
		// An unregistered category silently kills every ability assigned to
		// it, so bail loudly rather than register a half-empty surface.
		if ( ! wp_has_ability_category( self::CATEGORY_READ ) || ! wp_has_ability_category( self::CATEGORY_ACTIONS ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: 1: read category slug, 2: actions category slug */
					esc_html__( 'Notification Master abilities were not registered because the "%1$s" or "%2$s" category is missing.', 'notification-master' ),
					esc_html( self::CATEGORY_READ ),
					esc_html( self::CATEGORY_ACTIONS )
				),
				NOTIFICATION_MASTER_VERSION
			);
			return;
		}

		$expected = array();

		foreach ( self::PROVIDERS as $provider ) {
			if ( ! class_exists( $provider ) ) {
				continue;
			}

			$definitions = $provider::definitions( $this );
			if ( ! is_array( $definitions ) ) {
				continue;
			}

			foreach ( $definitions as $name => $args ) {
				$expected[] = $name;
				wp_register_ability( $name, $args );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->assert_invariants( $expected );
		}
	}

	/**
	 * Build the permission callback for one ability.
	 *
	 * Always a bare bool, never a WP_Error and never a side effect: a REST
	 * /run request runs the permission check twice. The filter is the site
	 * owner's lever for a locked-down MCP user - it can only raise the bar.
	 *
	 * @since 1.8.0
	 *
	 * @param string $ability_name Ability name, passed to the filter.
	 * @return callable Permission callback.
	 */
	public function permission_callback( $ability_name ) {
		return static function ( $input = null ) use ( $ability_name ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- core invokes the callback with the normalized input.
			// manage_options is a floor, not a default.
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			/**
			 * Filters the capability required to run a Notification Master ability.
			 *
			 * Applied on top of 'manage_options', never instead of it, so the
			 * filter can only narrow access further.
			 *
			 * @since 1.8.0
			 *
			 * @param string $capability   Capability name. Default 'manage_options'.
			 * @param string $ability_name Ability being checked.
			 */
			return current_user_can( apply_filters( 'notification_master_ability_capability', 'manage_options', $ability_name ) );
		};
	}

	/**
	 * Build the meta block for one ability.
	 *
	 * Core rejects a non-boolean flag, so every value here is a real bool.
	 * show_in_rest is explicit because public -> show_in_rest is 7.1 behaviour,
	 * and meta.mcp.public is explicit because inheriting it from meta.public is
	 * only adapter 0.6.0+.
	 *
	 * @since 1.8.0
	 *
	 * @param array $annotations Annotation flags: readonly, destructive, idempotent.
	 * @param bool  $metered     Whether the ability sends to real people or services.
	 * @return array Meta array for wp_register_ability().
	 */
	public function meta( array $annotations, $metered = false ) {
		$readonly    = ! empty( $annotations['readonly'] );
		$destructive = ! empty( $annotations['destructive'] );
		$idempotent  = ! empty( $annotations['idempotent'] );

		return array(
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => ( ! $destructive && ! $metered ),
			),
		);
	}

	/**
	 * Run one of the plugin's own REST routes internally.
	 *
	 * Dispatching instead of calling a controller directly keeps the route's
	 * arg schema, its sanitize and validate callbacks in the path for free.
	 *
	 * @since 1.8.0
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route path, e.g. '/ntfm/v1/notifications/import'.
	 * @param array  $params Request parameters.
	 * @return mixed Response data, or WP_Error on failure.
	 */
	public function dispatch( $method, $route, array $params = array() ) {
		$request = new \WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Merge an ability input over its defaults.
	 *
	 * Core applies ONLY the top-level `default` while normalizing input; the
	 * per-property defaults inside `properties` are never applied. This is the
	 * single place that quirk is handled, so providers never repeat it.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $input    Raw ability input.
	 * @param array $defaults Default values.
	 * @return array Merged input.
	 */
	public function apply_defaults( $input, array $defaults ) {
		return wp_parse_args( is_array( $input ) ? $input : array(), $defaults );
	}

	/**
	 * Build an agent-friendly error.
	 *
	 * Execute-side only. Permission errors stay bare and generic, because the
	 * run controller returns them verbatim to the HTTP caller. A call site that
	 * omits `status` gets 400, because an agent error is a refusal, not a crash.
	 *
	 * @since 1.8.0
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 * @param array  $data    Optional data, may carry `agent_hint` and
	 *                        `recovery` => array( 'ability' => ..., 'input' => ... ).
	 * @return \WP_Error
	 */
	public function agent_error( $code, $message, array $data = array() ) {
		// Core's run route reads data['status'] for the HTTP code. Without one
		// every expected refusal answers 500, and MCP and HTTP clients treat a
		// 5xx as a server crash. An agent error is a refusal, so 400 is the floor.
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = 400;
		}

		return new \WP_Error( $code, $message, $data );
	}

	/**
	 * Print the manifest of registered Notification Master abilities.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp notification-master abilities
	 *     wp notification-master abilities --format=json
	 *
	 * @since 1.8.0
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function cli_manifest( $args, $assoc_args ) {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			\WP_CLI::warning( 'The Abilities API requires WordPress 6.9 or newer. No Notification Master abilities are registered on this install.' );
			return;
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$items  = array();

		foreach ( $this->get_registered_abilities() as $name => $ability ) {
			$meta        = $ability->get_meta();
			$annotations = $this->meta_array( $meta, 'annotations' );
			$mcp         = $this->meta_array( $meta, 'mcp' );

			// The filter adds a capability on top of the manage_options floor,
			// so print both when it returns something else.
			$filtered   = apply_filters( 'notification_master_ability_capability', 'manage_options', $name );
			$capability = 'manage_options' === $filtered ? 'manage_options' : 'manage_options + ' . $filtered;

			$items[] = array(
				'name'        => $name,
				'category'    => $ability->get_category(),
				'capability'  => $capability,
				'readonly'    => empty( $annotations['readonly'] ) ? 'no' : 'yes',
				'destructive' => empty( $annotations['destructive'] ) ? 'no' : 'yes',
				'idempotent'  => empty( $annotations['idempotent'] ) ? 'no' : 'yes',
				'mcp'         => empty( $mcp['public'] ) ? 'off' : 'on',
			);
		}

		if ( empty( $items ) ) {
			\WP_CLI::warning( 'No Notification Master abilities are registered.' );
			return;
		}

		\WP_CLI\Utils\format_items(
			$format,
			$items,
			array( 'name', 'category', 'capability', 'readonly', 'destructive', 'idempotent', 'mcp' )
		);
	}

	/**
	 * Check the registration invariants this design depends on.
	 *
	 * Debug-only. Registration failures are otherwise invisible: core reports
	 * them through _doing_it_wrong and returns null, so a typo produces a
	 * silently missing ability rather than an error.
	 *
	 * @since 1.8.0
	 *
	 * @param array $expected Ability names that should now be registered.
	 * @return void
	 */
	private function assert_invariants( array $expected ) {
		$categories = array( self::CATEGORY_READ, self::CATEGORY_ACTIONS );

		foreach ( $expected as $name ) {
			if ( ! wp_has_ability( $name ) ) {
				$this->report_invariant( $name, __( 'was not registered.', 'notification-master' ) );
				continue;
			}

			$ability = wp_get_ability( $name );
			if ( ! $ability ) {
				continue;
			}

			$meta        = $ability->get_meta();
			$annotations = $this->meta_array( $meta, 'annotations' );
			$readonly    = ! empty( $annotations['readonly'] );
			$destructive = ! empty( $annotations['destructive'] );
			$idempotent  = ! empty( $annotations['idempotent'] );

			$schema   = $ability->get_input_schema();
			$required = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

			// destructive + idempotent routes /run to DELETE, whose input is
			// never JSON-decoded, so an object-shaped input cannot survive.
			if ( $destructive && $idempotent ) {
				$this->report_invariant( $name, __( 'is annotated both destructive and idempotent.', 'notification-master' ) );
			}

			if ( $readonly && $destructive ) {
				$this->report_invariant( $name, __( 'is annotated both readonly and destructive.', 'notification-master' ) );
			}

			if ( $destructive ) {
				$mcp_public = isset( $meta['mcp']['public'] ) ? $meta['mcp']['public'] : null;
				if ( false !== $mcp_public ) {
					$this->report_invariant( $name, __( 'is destructive but is not opted out of MCP.', 'notification-master' ) );
				}

				if ( ! $this->has_confirmation_field( $required ) ) {
					$this->report_invariant( $name, __( 'is destructive but requires no confirmation field.', 'notification-master' ) );
				}
			}

			if ( ! in_array( $ability->get_category(), $categories, true ) ) {
				$this->report_invariant( $name, __( 'is not in a Notification Master category.', 'notification-master' ) );
			}
		}
	}

	/**
	 * Registered abilities under the 'notification-master/' prefix.
	 *
	 * @since 1.8.0
	 *
	 * @return array Ability objects keyed by name.
	 */
	private function get_registered_abilities() {
		$prefix  = 'notification-master/';
		$matched = array();

		foreach ( wp_get_abilities() as $name => $ability ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				$matched[ $name ] = $ability;
			}
		}

		return $matched;
	}

	/**
	 * Read one array-valued key out of an ability's meta.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $meta Ability meta.
	 * @param string $key  Key to read.
	 * @return array The nested array, or an empty one.
	 */
	private function meta_array( array $meta, $key ) {
		return isset( $meta[ $key ] ) && is_array( $meta[ $key ] ) ? $meta[ $key ] : array();
	}

	/**
	 * Whether a required-field list contains a confirmation field.
	 *
	 * @since 1.8.0
	 *
	 * @param array $required Required field names.
	 * @return bool
	 */
	private function has_confirmation_field( array $required ) {
		foreach ( $required as $field ) {
			if ( is_string( $field ) && strpos( $field, 'confirm' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Report one broken invariant.
	 *
	 * @since 1.8.0
	 *
	 * @param string $name    Ability name.
	 * @param string $problem What is wrong with it.
	 * @return void
	 */
	private function report_invariant( $name, $problem ) {
		_doing_it_wrong(
			'Notification_Master\Abilities\Abilities_Module::assert_invariants',
			sprintf(
				/* translators: 1: ability name, 2: description of the problem */
				esc_html__( 'Ability "%1$s" %2$s', 'notification-master' ),
				esc_html( $name ),
				esc_html( $problem )
			),
			NOTIFICATION_MASTER_VERSION
		);
	}
}
