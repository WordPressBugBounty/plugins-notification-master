<?php
/**
 * Catalog abilities for Notification Master.
 *
 * Everything in this file describes what the plugin CAN do on this site and
 * changes nothing: which triggers exist, which merge tags a trigger offers,
 * which delivery channels are installed or locked behind Pro, which starter
 * templates can be imported, and what the settings currently say.
 *
 * Credentials never leave through here. The VAPID key pair is dropped from the
 * settings output and channel settings are only described by key name.
 *
 * @package notification-master
 * @since 1.8.0
 */

namespace Notification_Master\Abilities;

use Notification_Master\Tier;
use Notification_Master\Utils;
use Notification_Master\Settings;
use Notification_Master\Templates;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;
use Notification_Master\Integrations\Loader as Integrations_Loader;
use Notification_Master\WebPush\Loader as WebPush_Loader;
use Notification_Master\REST_API\Controllers\V1\Rest_Settings_Controller;

/**
 * Declares Notification Master's read-only catalog abilities.
 *
 * @since 1.8.0
 */
class Catalog_Abilities {

	/**
	 * Build the ability definitions this provider owns.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module supplying the shared helpers.
	 * @return array Map of ability name => wp_register_ability() args.
	 */
	public static function definitions( $module ) {
		// Every ability here is a pure read, so they share one annotation set.
		// readonly => true routes /run to GET, hence the bracket-encoding note
		// at the end of every description.
		$meta = $module->meta(
			array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			)
		);

		return array(
			'notification-master/list-triggers'          => self::list_triggers( $module, $meta ),
			'notification-master/list-merge-tags'        => self::list_merge_tags( $module, $meta ),
			'notification-master/list-channels'          => self::list_channels( $module, $meta ),
			'notification-master/list-starter-templates' => self::list_starter_templates( $module, $meta ),
			'notification-master/get-settings'           => self::get_settings( $module, $meta ),
		);
	}

	/**
	 * Every trigger the site can react to, with usage counts.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_triggers( $module, array $meta ) {
		return array(
			'label'               => __( 'List triggers', 'notification-master' ),
			'description'         => __( 'Lists every trigger (site event) Notification Master can react to, grouped the way the admin screen groups them: one group per enabled post type, taxonomy and comment type, plus media, user, theme, plugin and privacy, and the WooCommerce order, product and review groups only when Notification Master Pro is licensed and WooCommerce is active. Each trigger carries its slug (the exact value a notification stores), the merge-tag groups its content may use, how many notifications currently use it (notifications_active = published and firing, notifications_draft = disabled) and whether it is a Pro trigger. A trigger family switched off in the settings is absent here even if notifications still reference it; list-notifications reports that as trigger_active false. Pass group to narrow the list to one group slug; an unknown group returns an empty triggers list while the groups map always names every valid slug. Changes nothing. Read-only, so the run endpoint is a GET with bracket-encoded input: ?input[group]=post.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'group' => array(
						'type'        => 'string',
						'description' => __( 'Return only the triggers in this group slug, e.g. "post", "page", "comment", "user". Omit for every group.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'groups'   => array(
						'type'                 => 'object',
						'description'          => __( 'Every trigger group on this site, keyed by group slug.', 'notification-master' ),
						'additionalProperties' => array(
							'type'                 => 'object',
							'properties'           => array(
								'label' => array( 'type' => 'string' ),
							),
							'required'             => array( 'label' ),
							'additionalProperties' => false,
						),
					),
					'triggers' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'slug'                 => array( 'type' => 'string' ),
								'name'                 => array( 'type' => 'string' ),
								'description'          => array( 'type' => 'string' ),
								'group'                => array( 'type' => 'string' ),
								'group_label'          => array( 'type' => array( 'string', 'null' ) ),
								'merge_tag_groups'     => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
								'notifications_active' => array( 'type' => 'integer' ),
								'notifications_draft'  => array( 'type' => 'integer' ),
								'pro'                  => array( 'type' => 'boolean' ),
							),
							'required'             => array( 'slug', 'name', 'description', 'group', 'group_label', 'merge_tag_groups', 'notifications_active', 'notifications_draft', 'pro' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'groups', 'triggers' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults( $input, array( 'group' => '' ) );
				$filter = (string) $params['group'];

				$triggers = Triggers_Loader::get_instance()->get_triggers();

				/** This filter is documented in includes/admin/class-admin.php */
				$groups = apply_filters( 'notification_master_triggers', Utils::get_triggers_groups() );
				$groups = is_array( $groups ) ? $groups : array();
				$counts = self::get_trigger_counts();

				$group_map = array();
				foreach ( $groups as $slug => $group ) {
					$group_map[ (string) $slug ] = array(
						'label' => isset( $group['label'] ) ? (string) $group['label'] : (string) $slug,
					);
				}

				$items = array();
				foreach ( $triggers as $slug => $trigger ) {
					$group = (string) $trigger->get_group();
					if ( '' !== $filter && $filter !== $group ) {
						continue;
					}

					$slug        = (string) $slug;
					$merge_tags  = $trigger->get_merge_tags();
					$slug_counts = isset( $counts[ $slug ] ) ? $counts[ $slug ] : array();

					$items[] = array(
						'slug'                 => $slug,
						'name'                 => (string) $trigger->get_name(),
						'description'          => (string) $trigger->get_description(),
						'group'                => $group,
						'group_label'          => isset( $group_map[ $group ] ) ? $group_map[ $group ]['label'] : null,
						'merge_tag_groups'     => is_array( $merge_tags ) ? array_values( array_map( 'strval', $merge_tags ) ) : array(),
						'notifications_active' => isset( $slug_counts['publish'] ) ? (int) $slug_counts['publish'] : 0,
						'notifications_draft'  => isset( $slug_counts['draft'] ) ? (int) $slug_counts['draft'] : 0,
						'pro'                  => Tier::is_pro_trigger( $slug ),
					);
				}

				return array(
					'groups'   => self::as_map( $group_map ),
					'triggers' => $items,
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-triggers' ),
			'meta'                => $meta,
		);
	}

	/**
	 * The merge tags one trigger offers, group by group.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_merge_tags( $module, array $meta ) {
		return array(
			'label'               => __( 'List merge tags', 'notification-master' ),
			'description'         => __( 'Lists the merge tags a notification on the given trigger may use in its subject, message, URL or body, written as {{group.key}}, for example {{post.title}} or {{general.blogname}}. Groups come back keyed by group slug with the shared "general" group first. Each group has a label, is_advanced, available (advanced groups such as ACF fields need Notification Master Pro, so available is false for them on the free plan), the plugin the group depends on (null when none) and whether that plugin is active, and its tags keyed by tag key with label, description and type (null when untyped; "datetime" tags can drive a connection schedule). The trigger slug is checked here: an unknown slug answers 400 ntfm_invalid_trigger with the allowed slugs, and a Pro WooCommerce trigger on the free plan answers 403 ntfm_pro_required. Changes nothing. Read-only, so the run endpoint is a GET with bracket-encoded input: ?input[trigger]=post-published.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'trigger' => array(
						'type'        => 'string',
						'description' => __( 'Trigger slug exactly as list-triggers reports it, e.g. "post-published".', 'notification-master' ),
					),
				),
				'required'             => array( 'trigger' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'trigger' => array( 'type' => 'string' ),
					'groups'  => array(
						'type'                 => 'object',
						'description'          => __( 'Merge-tag groups keyed by slug, "general" first. Each is {label, is_advanced, available, depends_on_plugin, plugin_dependency_active, merge_tags: {key: {label, description, type}}}.', 'notification-master' ),
						'additionalProperties' => true,
					),
				),
				'required'             => array( 'trigger', 'groups' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$slug     = is_array( $input ) && isset( $input['trigger'] ) ? trim( (string) $input['trigger'] ) : '';
				$loader   = Triggers_Loader::get_instance();
				$triggers = $loader->get_triggers();
				$trigger  = '' === $slug ? null : $loader->get_trigger( $slug );

				if ( null === $trigger ) {
					if ( Tier::is_pro_trigger( $slug ) && ! Tier::is_pro() ) {
						return Tier::pro_required_error(
							/* translators: %s: trigger slug */
							sprintf( __( 'The "%s" trigger', 'notification-master' ), $slug ),
							array( 'agent_hint' => __( 'WooCommerce triggers are part of Notification Master Pro. On this install only the free triggers are available; call notification-master/list-triggers for the list.', 'notification-master' ) )
						);
					}

					return $module->agent_error(
						'ntfm_invalid_trigger',
						/* translators: %s: trigger slug */
						sprintf( __( 'Unknown trigger "%s".', 'notification-master' ), $slug ),
						array(
							'status'     => 400,
							'allowed'    => array_values( array_map( 'strval', array_keys( $triggers ) ) ),
							'agent_hint' => __( 'Pass a slug from the allowed list. A trigger whose family is switched off in the settings, or a Pro trigger when WooCommerce is inactive, is not registered and so not listed.', 'notification-master' ),
							'recovery'   => array(
								'ability' => 'notification-master/list-triggers',
								'input'   => array(),
							),
						)
					);
				}

				// Same assembly as Rest_Notification_Controller::get_merge_tags().
				$group_slugs = $trigger->get_merge_tags();
				$group_slugs = is_array( $group_slugs ) ? $group_slugs : array();
				array_unshift( $group_slugs, 'general' );

				$is_pro = Tier::is_pro();
				$groups = array();

				foreach ( array_unique( $group_slugs ) as $group_slug ) {
					$group = Merge_Tags_Loader::get_instance()->get_group( $group_slug );
					if ( ! $group ) {
						continue;
					}

					$dependency = (string) $group->get_plugin_dependency();
					$tags       = array();

					foreach ( (array) $group->get_merge_tags() as $key => $tag ) {
						$tags[ (string) $key ] = self::describe_merge_tag( $key, $tag );
					}

					$groups[ (string) $group_slug ] = array(
						'label'                    => (string) $group->get_name(),
						'is_advanced'              => (bool) $group->is_advanced(),
						'available'                => $is_pro || ! $group->is_advanced(),
						'depends_on_plugin'        => '' === $dependency ? null : $dependency,
						'plugin_dependency_active' => (bool) $group->is_plugin_dependency_active(),
						'merge_tags'               => self::as_map( $tags ),
					);
				}

				return array(
					'trigger' => $slug,
					'groups'  => self::as_map( $groups ),
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-merge-tags' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Every delivery channel, installed or locked, with usage counts.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_channels( $module, array $meta ) {
		return array(
			'label'               => __( 'List channels', 'notification-master' ),
			'description'         => __( 'Lists the delivery channels (integrations) a notification connection can use. The free plugin ships webpush, email, webhook, discord and instagram; facebook, twitterx, twilio, slack, zapier, make and whatsapp need Notification Master Pro and on the free plan are listed with available false and requires "Notification Master Pro", so an agent can tell a locked channel from an unknown one. Each channel reports its connection settings keys, which of those hold credentials or recipient lists (secret_keys: always redacted elsewhere and never writable through update-notification), whether it is configured where that is knowable (webpush: both VAPID keys set; instagram: an account connected; null for channels configured per connection) and how many connections across the first 100 notifications use it and how many of those are enabled. Never returns a credential. Takes no input. Read-only, so the run endpoint is a GET; with no input the bracket-encoded query string (?input[...]) stays empty.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'channels' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'slug'                => array( 'type' => 'string' ),
								'name'                => array( 'type' => 'string' ),
								'description'         => array( 'type' => 'string' ),
								'available'           => array( 'type' => 'boolean' ),
								'pro'                 => array( 'type' => 'boolean' ),
								'requires'            => array( 'type' => array( 'string', 'null' ) ),
								'configured'          => array( 'type' => array( 'boolean', 'null' ) ),
								'settings_keys'       => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
								'secret_keys'         => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
								'connections_total'   => array( 'type' => 'integer' ),
								'connections_enabled' => array( 'type' => 'integer' ),
							),
							'required'             => array( 'slug', 'name', 'description', 'available', 'pro', 'requires', 'configured', 'settings_keys', 'secret_keys', 'connections_total', 'connections_enabled' ),
							'additionalProperties' => false,
						),
					),
					'is_pro'   => array( 'type' => 'boolean' ),
				),
				'required'             => array( 'channels', 'is_pro' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the input schema is an empty object, so there is nothing to read.
				$is_pro = Tier::is_pro();
				$counts = self::count_connections();

				// get_integrations() has no side effect; get_integration() logs
				// an invalid_integration row for a missing slug, so never call it.
				$integrations = Integrations_Loader::get_instance()->get_integrations();
				$channels     = array();

				foreach ( $integrations as $slug => $integration ) {
					$slug   = (string) $slug;
					$schema = $integration->get_attributes_schema();
					$keys   = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? array_keys( $schema['properties'] ) : array();
					$keys   = array_values( array_map( 'strval', $keys ) );
					$pro    = Tier::is_pro_integration( $slug );
					// A registered Pro channel is still refused on save when the licence lapsed.
					$available = ! $pro || $is_pro;

					$channels[] = array(
						'slug'                => $slug,
						'name'                => (string) $integration->name,
						'description'         => (string) $integration->description,
						'available'           => $available,
						'pro'                 => $pro,
						'requires'            => $available ? null : 'Notification Master Pro',
						'configured'          => self::is_channel_configured( $slug ),
						'settings_keys'       => $keys,
						'secret_keys'         => Redactor::secret_keys( $slug, $keys ),
						'connections_total'   => isset( $counts[ $slug ]['total'] ) ? (int) $counts[ $slug ]['total'] : 0,
						'connections_enabled' => isset( $counts[ $slug ]['enabled'] ) ? (int) $counts[ $slug ]['enabled'] : 0,
					);
				}

				if ( ! $is_pro ) {
					foreach ( self::pro_integration_names() as $slug => $name ) {
						if ( isset( $integrations[ $slug ] ) ) {
							continue;
						}

						$channels[] = array(
							'slug'                => $slug,
							'name'                => $name,
							/* translators: %s: channel name */
							'description'         => sprintf( __( 'Send notifications to %s. Available in Notification Master Pro.', 'notification-master' ), $name ),
							'available'           => false,
							'pro'                 => true,
							'requires'            => 'Notification Master Pro',
							'configured'          => null,
							'settings_keys'       => array(),
							'secret_keys'         => array(),
							'connections_total'   => isset( $counts[ $slug ]['total'] ) ? (int) $counts[ $slug ]['total'] : 0,
							'connections_enabled' => isset( $counts[ $slug ]['enabled'] ) ? (int) $counts[ $slug ]['enabled'] : 0,
						);
					}
				}

				return array(
					'channels' => $channels,
					'is_pro'   => $is_pro,
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-channels' ),
			'meta'                => $meta,
		);
	}

	/**
	 * The starter templates create-notification-from-template can import.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function list_starter_templates( $module, array $meta ) {
		return array(
			'label'               => __( 'List starter templates', 'notification-master' ),
			'description'         => __( 'Lists the ready-made starter notifications that notification-master/create-notification-from-template can import: one per registered trigger, each pre-wired with an email and a web push connection (enabled) plus discord, webhook and instagram shells (disabled). Returns each template id (pass it as template_id), title, trigger slug, group label, whether that trigger is registered right now, and one {id, integration, enabled} summary per connection. HTML bodies and connection settings are left out on purpose; import a template and read it with get-notification to see them. Pass group to narrow the list to one trigger group slug such as "post" or "user". Changes nothing. Read-only, so the run endpoint is a GET with bracket-encoded input: ?input[group]=post.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'group' => array(
						'type'        => 'string',
						'description' => __( 'Return only templates whose trigger belongs to this group slug. Omit for every group.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'templates' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'             => array( 'type' => 'string' ),
								'title'          => array( 'type' => 'string' ),
								'trigger'        => array( 'type' => 'string' ),
								'group_label'    => array( 'type' => array( 'string', 'null' ) ),
								'trigger_active' => array( 'type' => 'boolean' ),
								'channels'       => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'id'          => array( 'type' => 'string' ),
											'integration' => array( 'type' => 'string' ),
											'enabled'     => array( 'type' => 'boolean' ),
										),
										'required'   => array( 'id', 'integration', 'enabled' ),
										'additionalProperties' => false,
									),
								),
							),
							'required'             => array( 'id', 'title', 'trigger', 'group_label', 'trigger_active', 'channels' ),
							'additionalProperties' => false,
						),
					),
					'total'     => array( 'type' => 'integer' ),
				),
				'required'             => array( 'templates', 'total' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults( $input, array( 'group' => '' ) );
				$filter = (string) $params['group'];

				$triggers  = Triggers_Loader::get_instance()->get_triggers();
				$templates = Templates::get_templates();
				$items     = array();

				foreach ( (array) $templates as $template ) {
					if ( ! is_array( $template ) ) {
						continue;
					}

					$group = isset( $template['group'] ) ? (string) $template['group'] : '';
					if ( '' !== $filter && $filter !== $group ) {
						continue;
					}

					$trigger  = isset( $template['trigger'] ) ? (string) $template['trigger'] : '';
					$channels = array();

					if ( isset( $template['connections'] ) && is_array( $template['connections'] ) ) {
						foreach ( $template['connections'] as $connection_id => $connection ) {
							if ( ! is_array( $connection ) ) {
								continue;
							}

							$channels[] = array(
								'id'          => (string) $connection_id,
								'integration' => isset( $connection['integration'] ) ? (string) $connection['integration'] : '',
								'enabled'     => self::to_bool( isset( $connection['enabled'] ) ? $connection['enabled'] : false ),
							);
						}
					}

					$items[] = array(
						'id'             => isset( $template['id'] ) ? (string) $template['id'] : '',
						'title'          => isset( $template['title'] ) ? (string) $template['title'] : '',
						'trigger'        => $trigger,
						'group_label'    => isset( $template['group_label'] ) && '' !== (string) $template['group_label'] ? (string) $template['group_label'] : null,
						'trigger_active' => '' !== $trigger && isset( $triggers[ $trigger ] ),
						'channels'       => $channels,
					);
				}

				return array(
					'templates' => $items,
					'total'     => count( $items ),
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-starter-templates' ),
			'meta'                => $meta,
		);
	}

	/**
	 * The plugin settings, minus the VAPID key pair.
	 *
	 * Only the keys the callback casts are typed in the output schema; the
	 * button appearance keys are stored as saved (often numeric strings), so
	 * they pass through under additionalProperties rather than risk a
	 * validation failure.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param array            $meta   Shared read-only meta block.
	 * @return array Ability args.
	 */
	private static function get_settings( $module, array $meta ) {
		return array(
			'label'               => __( 'Get settings', 'notification-master' ),
			'description'         => __( 'Reads the Notification Master settings merged over their defaults: which trigger families are on (post_status_change_trigger, taxonomy_term_change_trigger, comment_change_trigger, user_change_trigger, theme_change_trigger, plugin_change_trigger, media_change_trigger, privacy_trigger and, only when Pro is licensed, woocommerce_change_trigger), which post types, taxonomies and comment types fire triggers, log retention in days (delete_logs_every for the debug log, notifications_delete_logs_every for the send log), whether background processing is on, and the web push subscribe-button appearance options. The VAPID key pair is never returned; webpush_configured says whether both keys are set. is_pro says whether Notification Master Pro is licensed. There is deliberately no write counterpart: changing these re-registers triggers and can silently stop live notifications, so it stays a human decision in wp-admin. Takes no input. Read-only, so the run endpoint is a GET; with no input the bracket-encoded query string (?input[...]) stays empty.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'enable_background_processing'    => array( 'type' => 'boolean' ),
					'post_status_change_trigger'      => array( 'type' => 'boolean' ),
					'taxonomy_term_change_trigger'    => array( 'type' => 'boolean' ),
					'comment_change_trigger'          => array( 'type' => 'boolean' ),
					'user_change_trigger'             => array( 'type' => 'boolean' ),
					'theme_change_trigger'            => array( 'type' => 'boolean' ),
					'plugin_change_trigger'           => array( 'type' => 'boolean' ),
					'media_change_trigger'            => array( 'type' => 'boolean' ),
					'privacy_trigger'                 => array( 'type' => 'boolean' ),
					'woocommerce_change_trigger'      => array(
						'type'        => 'boolean',
						'description' => __( 'Present only when Notification Master Pro is licensed.', 'notification-master' ),
					),
					'post_types'                      => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'taxonomies'                      => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'comment_types'                   => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'delete_logs_every'               => array( 'type' => 'integer' ),
					'notifications_delete_logs_every' => array( 'type' => 'integer' ),
					'webpush_configured'              => array( 'type' => 'boolean' ),
					'is_pro'                          => array( 'type' => 'boolean' ),
				),
				'required'             => array( 'webpush_configured', 'is_pro' ),
				'additionalProperties' => true,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- the input schema is an empty object, so there is nothing to read.
				// get_schema() is a plain method and the controller has no
				// constructor, so instantiating it registers nothing.
				$schema   = ( new Rest_Settings_Controller() )->get_schema();
				$defaults = isset( $schema['default'] ) && is_array( $schema['default'] ) ? $schema['default'] : array();
				$stored   = Settings::get_settings();
				$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );

				// The key pair never leaves the site; a boolean says enough.
				unset( $settings['webpush_private_key'], $settings['webpush_public_key'] );

				$is_pro = Tier::is_pro();
				if ( ! $is_pro ) {
					foreach ( Tier::PRO_SETTINGS as $key ) {
						unset( $settings[ $key ] );
					}
				}

				foreach ( $settings as $key => $value ) {
					if ( 'enable_background_processing' === $key || '_trigger' === substr( (string) $key, -8 ) ) {
						$settings[ $key ] = self::to_bool( $value );
					}
				}

				foreach ( array( 'post_types', 'taxonomies', 'comment_types' ) as $key ) {
					$settings[ $key ] = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? array_values( array_map( 'strval', $settings[ $key ] ) ) : array();
				}

				foreach ( array( 'delete_logs_every', 'notifications_delete_logs_every' ) as $key ) {
					$settings[ $key ] = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
				}

				$settings['webpush_configured'] = (bool) WebPush_Loader::get_instance()->is_configured();
				$settings['is_pro']             = $is_pro;

				return $settings;
			},
			'permission_callback' => $module->permission_callback( 'notification-master/get-settings' ),
			'meta'                => $meta,
		);
	}

	/**
	 * Published and draft notification counts per trigger, in one query.
	 *
	 * Replaces Trigger::has_notifications(), which costs one query per trigger.
	 *
	 * @since 1.8.0
	 *
	 * @return array Map of trigger slug => array( 'publish' => int, 'draft' => int ).
	 */
	private static function get_trigger_counts() {
		global $wpdb;

		// No user input reaches the SQL, so there is nothing to prepare.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped count over post meta; no API covers it and the result is per request.
			"SELECT pm.meta_value AS trigger_slug, p.post_status, COUNT(*) AS total
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = 'trigger'
				AND p.post_type = 'ntfm_notification'
				AND p.post_status IN ( 'publish', 'draft' )
			GROUP BY pm.meta_value, p.post_status"
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			if ( ! isset( $row->trigger_slug, $row->post_status ) ) {
				continue;
			}

			$slug = (string) $row->trigger_slug;
			if ( ! isset( $counts[ $slug ] ) ) {
				$counts[ $slug ] = array(
					'publish' => 0,
					'draft'   => 0,
				);
			}

			$counts[ $slug ][ (string) $row->post_status ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Connection totals per integration, from one pass over the notifications.
	 *
	 * Two queries: the id list and one meta-cache prime. Capped at 100
	 * notifications, which is the same cap list-notifications uses.
	 *
	 * @since 1.8.0
	 *
	 * @return array Map of integration slug => array( 'total' => int, 'enabled' => int ).
	 */
	private static function count_connections() {
		$ids = get_posts(
			array(
				'post_type'              => 'ntfm_notification',
				'post_status'            => array( 'publish', 'draft' ),
				'numberposts'            => 100,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
			)
		);

		$counts = array();
		if ( empty( $ids ) ) {
			return $counts;
		}

		// fields => ids skips the meta prime, so do it once here.
		update_meta_cache( 'post', $ids );

		foreach ( $ids as $id ) {
			$connections = get_post_meta( $id, 'connections', true );
			if ( ! is_array( $connections ) ) {
				continue;
			}

			foreach ( $connections as $connection ) {
				if ( ! is_array( $connection ) || empty( $connection['integration'] ) ) {
					continue;
				}

				$slug = (string) $connection['integration'];
				if ( ! isset( $counts[ $slug ] ) ) {
					$counts[ $slug ] = array(
						'total'   => 0,
						'enabled' => 0,
					);
				}

				++$counts[ $slug ]['total'];
				if ( self::to_bool( isset( $connection['enabled'] ) ? $connection['enabled'] : false ) ) {
					++$counts[ $slug ]['enabled'];
				}
			}
		}

		return $counts;
	}

	/**
	 * Whether a channel is set up site-wide, where that is knowable.
	 *
	 * @since 1.8.0
	 *
	 * @param string $slug Integration slug.
	 * @return bool|null Null for channels configured per connection.
	 */
	private static function is_channel_configured( $slug ) {
		if ( 'webpush' === $slug ) {
			return (bool) WebPush_Loader::get_instance()->is_configured();
		}

		if ( 'instagram' === $slug ) {
			return ! empty( get_option( 'notification_master_instagram_tokens' ) );
		}

		return null;
	}

	/**
	 * Display names of the Pro-only channels.
	 *
	 * Hard-coded on purpose: the free loader exposes these names only through
	 * the notification_master_integrations admin filter, which carries icons
	 * and schemas we do not want here.
	 *
	 * @since 1.8.0
	 *
	 * @return array Map of integration slug => name.
	 */
	private static function pro_integration_names() {
		return array(
			'facebook' => __( 'Facebook', 'notification-master' ),
			'twitterx' => __( 'X', 'notification-master' ),
			'twilio'   => __( 'Twilio', 'notification-master' ),
			'slack'    => __( 'Slack', 'notification-master' ),
			'zapier'   => __( 'Zapier', 'notification-master' ),
			'make'     => __( 'Make', 'notification-master' ),
			'whatsapp' => __( 'WhatsApp', 'notification-master' ),
		);
	}

	/**
	 * Normalise one merge tag definition to {label, description, type}.
	 *
	 * Groups are inconsistent: some declare 'label', others 'name'.
	 *
	 * @since 1.8.0
	 *
	 * @param string $key Tag key.
	 * @param mixed  $tag Tag definition as the group declares it.
	 * @return array
	 */
	private static function describe_merge_tag( $key, $tag ) {
		$tag = is_array( $tag ) ? $tag : array();

		if ( isset( $tag['label'] ) && '' !== (string) $tag['label'] ) {
			$label = (string) $tag['label'];
		} elseif ( isset( $tag['name'] ) && '' !== (string) $tag['name'] ) {
			$label = (string) $tag['name'];
		} else {
			$label = (string) $key;
		}

		return array(
			'label'       => $label,
			'description' => isset( $tag['description'] ) ? (string) $tag['description'] : '',
			'type'        => isset( $tag['type'] ) && '' !== (string) $tag['type'] ? (string) $tag['type'] : null,
		);
	}

	/**
	 * Keep an assoc array a JSON object even when it is empty.
	 *
	 * @since 1.8.0
	 *
	 * @param array $map Assoc array.
	 * @return array|object
	 */
	private static function as_map( array $map ) {
		return empty( $map ) ? new \stdClass() : $map;
	}

	/**
	 * Cast a stored flag to bool, honouring "false"/"0" strings from REST saves.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}
}
