<?php
/**
 * Notification abilities for Notification Master.
 *
 * Everything that reads or edits a notification post: list, read one, enable
 * or disable, edit text settings on its connections, and create a draft from
 * a starter template. Nothing in this file sends anything.
 *
 * Deliberately absent: deleting a notification, importing raw payloads and
 * editing credentials or recipients. Those stay in wp-admin.
 *
 * @package notification-master
 * @since 1.8.0
 */

namespace Notification_Master\Abilities;

use Notification_Master\Tier;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Integrations\Loader as Integrations_Loader;
use Notification_Master\Scheduling\Scheduler;
use Notification_Master\Templates;

/**
 * Declares the notification abilities.
 *
 * @since 1.8.0
 */
class Notification_Abilities {

	/**
	 * Notification post type.
	 */
	const POST_TYPE = 'ntfm_notification';

	/**
	 * Settings keys an ability may edit, per integration.
	 *
	 * Verified against each integration's get_attributes_schema() (free 1.8.0
	 * and Pro 1.6.1): these are the content keys. Discord's and Slack's message
	 * is an object, webhook/zapier/make body is a list of key/value pairs; all
	 * still count as content. Every key here is also readable through
	 * Redactor::READABLE_KEYS.
	 */
	const TEXT_SETTINGS = array(
		'email'     => array( 'subject', 'message' ),
		'webpush'   => array( 'title', 'message', 'url' ),
		'discord'   => array( 'message' ),
		'webhook'   => array( 'body' ),
		'instagram' => array( 'text', 'alt_text' ),
		'slack'     => array( 'message' ),
		'twilio'    => array( 'message' ),
		'whatsapp'  => array( 'message', 'caption' ),
		'zapier'    => array( 'body' ),
		'make'      => array( 'body' ),
		'facebook'  => array( 'text' ),
		'twitterx'  => array( 'text' ),
	);

	/**
	 * Connection keys that are Pro-only conditions and never editable here.
	 */
	const CONDITION_KEYS = array( 'enable_conditions', 'conditions' );

	/**
	 * Build the ability definitions this provider owns.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module supplying the shared helpers.
	 * @return array Map of ability name => wp_register_ability() args.
	 */
	public static function definitions( $module ) {
		return array(
			'notification-master/list-notifications'      => self::list_notifications( $module ),
			'notification-master/get-notification'        => self::get_notification( $module ),
			'notification-master/set-notification-status' => self::set_notification_status( $module ),
			'notification-master/update-notification'     => self::update_notification( $module ),
			'notification-master/create-notification-from-template' => self::create_notification_from_template( $module ),
		);
	}

	/**
	 * Paged list of notifications with trigger and channels, never settings.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return array Ability args.
	 */
	private static function list_notifications( $module ) {
		return array(
			'label'               => __( 'List notifications', 'notification-master' ),
			'description'         => __( 'Lists notifications one page at a time, newest first, each with its status, trigger and the connections (channels) it sends through. It never returns connection settings: no recipients, no webhook URLs, no credentials and no message bodies. Use notification-master/get-notification for one notification\'s redacted settings. "enabled" is true when the status is publish, which means the notification fires on its trigger and sends to real recipients. "trigger_active" is false when the stored trigger is not registered on this site (its family is disabled in settings or it needs Notification Master Pro), and such a notification never fires. "total" counts every notification matching the status and trigger filters, not only this page. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[status]=publish&input[page]=2&input[per_page]=50.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'status'   => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'any' ),
						'description' => __( 'publish returns enabled notifications, draft returns disabled ones, any returns both. Defaults to any.', 'notification-master' ),
					),
					'trigger'  => array(
						'type'        => 'string',
						'description' => __( 'Return only notifications on this trigger slug, e.g. post-published. Slugs come from notification-master/list-triggers.', 'notification-master' ),
					),
					'page'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Page of results to return. Defaults to 1.', 'notification-master' ),
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'Rows per page, 1 to 100. Defaults to 25.', 'notification-master' ),
					),
				),
				'required'             => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'items'    => array(
						'type'  => 'array',
						'items' => self::row_schema( false ),
					),
					'total'    => array(
						'type'        => 'integer',
						'description' => __( 'Notifications matching the filters across all pages.', 'notification-master' ),
					),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
				),
				'required'             => array( 'items', 'total', 'page', 'per_page' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$params = $module->apply_defaults(
					$input,
					array(
						'status'   => 'any',
						'page'     => 1,
						'per_page' => 25,
					)
				);

				$status      = (string) $params['status'];
				$page        = max( 1, (int) $params['page'] );
				$per_page    = max( 1, min( 100, (int) $params['per_page'] ) );
				$raw_trigger = isset( $params['trigger'] ) ? trim( (string) $params['trigger'] ) : '';
				$trigger     = sanitize_text_field( $raw_trigger );
				$triggers    = Triggers_Loader::get_instance()->get_triggers();
				$statuses    = 'any' === $status ? array( 'publish', 'draft' ) : array( $status );

				// Validate the RAW filter: a value that sanitises to '' or is not a registered slug is refused, never dropped.
				if ( '' !== $raw_trigger && ( '' === $trigger || ! isset( $triggers[ $trigger ] ) ) ) {
					return $module->agent_error(
						'ntfm_invalid_trigger',
						/* translators: %s: trigger slug */
						sprintf( __( 'Unknown trigger "%s".', 'notification-master' ), $trigger ),
						array(
							'status'     => 400,
							'allowed'    => array_values( array_map( 'strval', array_keys( $triggers ) ) ),
							'agent_hint' => __( 'Pass a slug from the allowed list, or omit trigger to list every notification.', 'notification-master' ),
							'recovery'   => array(
								'ability' => 'notification-master/list-triggers',
								'input'   => array(),
							),
						)
					);
				}

				$args = array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => $statuses,
					'numberposts'            => $per_page,
					'offset'                 => ( $page - 1 ) * $per_page,
					'orderby'                => 'ID',
					'order'                  => 'DESC',
					// The default meta cache priming is kept on purpose: it is
					// one query for the whole page and makes every
					// get_post_meta() call in row() free.
					'update_post_term_cache' => false,
					'no_found_rows'          => true,
				);

				if ( '' !== $trigger ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reason: the trigger lives only in post meta and the result set is one page of a small CPT.
					$args['meta_query'] = self::trigger_meta_query( $trigger );
				}

				$posts        = get_posts( $args );
				$integrations = Integrations_Loader::get_instance()->get_integrations();
				$items        = array();

				foreach ( $posts as $post ) {
					$items[] = self::row( $post, $triggers, $integrations, false );
				}

				return array(
					'items'    => $items,
					'total'    => self::count_notifications( $statuses, $trigger ),
					'page'     => $page,
					'per_page' => $per_page,
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/list-notifications' ),
			'meta'                => $module->meta(
				array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				)
			),
		);
	}

	/**
	 * One notification with redacted connection settings.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return array Ability args.
	 */
	private static function get_notification( $module ) {
		$row_schema = self::row_schema( true );

		$row_schema['properties']['merge_tag_groups']        = array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => __( 'Merge tag group slugs the trigger offers, "general" first. Empty when the trigger is not registered. Read the tags themselves with notification-master/list-merge-tags.', 'notification-master' ),
		);
		$row_schema['properties']['pending_scheduled_sends'] = array(
			'type'        => 'integer',
			'description' => __( 'Delayed sends queued for this notification and not yet delivered (pending Action Scheduler rows, counted in one query).', 'notification-master' ),
		);
		$row_schema['required'][]                            = 'merge_tag_groups';
		$row_schema['required'][]                            = 'pending_scheduled_sends';

		return array(
			'label'               => __( 'Get notification', 'notification-master' ),
			'description'         => __( 'Returns one notification: the same row notification-master/list-notifications shows, plus each connection\'s settings and schedule, the merge tag groups its trigger offers and how many delayed sends are still queued for it. Settings are redacted before they leave the site: recipient lists, webhook URLs, headers and API credentials come back as "[redacted]" or "[redacted: N items]" so the agent can tell an empty list from a filled one without reading it. Text settings such as subject, title and message stay readable, so this is where to look before notification-master/update-notification. "schedule" carries the stored delay or merge-tag-relative config together with a one-line "summary"; summary is null when the connection sends immediately. Because this ability is read-only its run endpoint is a GET and the input is read from the query string, so bracket-encode it: ?input[id]=12.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_READ,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Notification id from notification-master/list-notifications.', 'notification-master' ),
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => $row_schema,
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$id   = is_array( $input ) && isset( $input['id'] ) ? (int) $input['id'] : 0;
				$post = self::load_notification( $module, $id );

				if ( is_wp_error( $post ) ) {
					return $post;
				}

				$triggers     = Triggers_Loader::get_instance()->get_triggers();
				$integrations = Integrations_Loader::get_instance()->get_integrations();
				$row          = self::row( $post, $triggers, $integrations, true );

				$groups = array();
				if ( isset( $triggers[ $row['trigger'] ] ) ) {
					$groups = (array) $triggers[ $row['trigger'] ]->get_merge_tags();
					$groups = array_map( 'strval', array_values( $groups ) );
					$groups = array_values( array_unique( array_merge( array( 'general' ), $groups ) ) );
				}

				$row['merge_tag_groups']        = $groups;
				$row['pending_scheduled_sends'] = self::pending_sends_for( $post->ID );

				return $row;
			},
			'permission_callback' => $module->permission_callback( 'notification-master/get-notification' ),
			'meta'                => $module->meta(
				array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				)
			),
		);
	}

	/**
	 * Enable (publish) or disable (draft) a notification.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return array Ability args.
	 */
	private static function set_notification_status( $module ) {
		return array(
			'label'               => __( 'Set notification status', 'notification-master' ),
			'description'         => __( 'Enables a notification by setting it to publish, or disables it by setting it to draft. Enabling makes it fire on the next real event and send to real recipients; disabling also drops already-queued delayed sends at fire time. Nothing is sent by this call itself. Idempotent: asking for the status it already has returns changed false and writes nothing. "pending_scheduled_sends" reports how many delayed sends are queued for it so a human can judge what disabling will drop, or what enabling will let through.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_ACTIONS,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Notification id from notification-master/list-notifications.', 'notification-master' ),
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft' ),
						'description' => __( 'publish enables the notification, draft disables it.', 'notification-master' ),
					),
				),
				'required'             => array( 'id', 'status' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'                      => array( 'type' => 'integer' ),
					'name'                    => array( 'type' => 'string' ),
					'status_before'           => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'status_after'            => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'changed'                 => array(
						'type'        => 'boolean',
						'description' => __( 'False when the notification already had the requested status.', 'notification-master' ),
					),
					'pending_scheduled_sends' => array( 'type' => 'integer' ),
				),
				'required'             => array( 'id', 'name', 'status_before', 'status_after', 'changed', 'pending_scheduled_sends' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$input  = is_array( $input ) ? $input : array();
				$id     = isset( $input['id'] ) ? (int) $input['id'] : 0;
				$status = isset( $input['status'] ) ? (string) $input['status'] : '';
				$post   = self::load_notification( $module, $id );

				if ( is_wp_error( $post ) ) {
					return $post;
				}

				$before  = (string) $post->post_status;
				$changed = false;

				if ( $before !== $status ) {
					$result = wp_update_post(
						array(
							'ID'          => $post->ID,
							'post_status' => $status,
						),
						true
					);

					// A failed core write is the one real failure here, so it
					// is the one place a 500 is honest.
					if ( is_wp_error( $result ) ) {
						return $module->agent_error(
							$result->get_error_code(),
							$result->get_error_message(),
							array(
								'status'     => 500,
								'agent_hint' => 'WordPress refused to update the notification post. Read it again with notification-master/get-notification before retrying.',
							)
						);
					}

					$changed = true;
				}

				return array(
					'id'                      => (int) $post->ID,
					'name'                    => (string) $post->post_title,
					'status_before'           => $before,
					'status_after'            => $status,
					'changed'                 => $changed,
					'pending_scheduled_sends' => self::pending_sends_for( $post->ID ),
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/set-notification-status' ),
			'meta'                => $module->meta(
				array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				)
			),
		);
	}

	/**
	 * Edit a notification's name and the text settings of its connections.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return array Ability args.
	 */
	private static function update_notification( $module ) {
		$editable = array();
		foreach ( self::TEXT_SETTINGS as $slug => $keys ) {
			$editable[] = $slug . ': ' . implode( ', ', $keys );
		}

		/* translators: 1: editable settings keys per channel, 2: channel slugs the free plugin ships */
		$description = __( 'Renames a notification and/or edits its existing connections: toggle a connection on or off, rename it, or change its text settings. Only content keys are editable and the allowed keys per channel are %1$s. Recipients, URLs, headers, credentials and the Pro-only condition keys are refused with ntfm_invalid_setting; change those in wp-admin. Discord\'s message is an object (title, description, content, author, fields) and webhook\'s body is a list of {key, value} pairs; each replaces the stored value whole. Supplied settings are merged over the stored ones and the result is validated against the channel\'s own schema before anything is written, so a refusal means nothing changed. Connection ids come from notification-master/get-notification; an unknown id is refused with ntfm_connection_not_found and the valid ids. New connections cannot be added here. Connections on Pro-only channels (slack, twilio, whatsapp, zapier, make, facebook, twitterx) are refused with ntfm_pro_required on the free plan; with Notification Master Pro active their listed content keys are editable like any other. The channels this plugin ships are %2$s. Writing does not send anything, but an enabled notification uses the new text on its next real event.', 'notification-master' );

		return array(
			'label'               => __( 'Update notification', 'notification-master' ),
			'description'         => sprintf( $description, implode( '; ', $editable ), implode( ', ', Tier::FREE_INTEGRATIONS ) ),
			'category'            => Abilities_Module::CATEGORY_ACTIONS,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'          => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Notification id from notification-master/list-notifications.', 'notification-master' ),
					),
					'name'        => array(
						'type'        => 'string',
						'minLength'   => 1,
						'maxLength'   => 200,
						'description' => __( 'New notification name.', 'notification-master' ),
					),
					'connections' => array(
						'type'                 => 'object',
						'description'          => __( 'Patches keyed by connection id. Each may set enabled, name and/or settings.', 'notification-master' ),
						'additionalProperties' => array(
							'type'                 => 'object',
							'properties'           => array(
								'enabled'  => array(
									'type'        => 'boolean',
									'description' => __( 'Whether this connection sends when the notification fires.', 'notification-master' ),
								),
								'name'     => array(
									'type'        => 'string',
									'description' => __( 'New connection name.', 'notification-master' ),
								),
								'settings' => array(
									'type'                 => 'object',
									'description'          => __( 'Text settings to merge over the stored ones. Only the channel\'s content keys are accepted.', 'notification-master' ),
									'additionalProperties' => true,
								),
							),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'             => array( 'type' => 'integer' ),
					'name'           => array( 'type' => 'string' ),
					'status'         => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'changed_fields' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Dotted paths whose stored value actually changed, e.g. name or connections.email_default.settings.subject. Empty when every supplied value already matched.', 'notification-master' ),
					),
					'connections'    => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'          => array( 'type' => 'string' ),
								'name'        => array( 'type' => 'string' ),
								'integration' => array( 'type' => 'string' ),
								'enabled'     => array( 'type' => 'boolean' ),
							),
							'required'             => array( 'id', 'name', 'integration', 'enabled' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'id', 'name', 'status', 'changed_fields', 'connections' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$input = is_array( $input ) ? $input : array();
				$id    = isset( $input['id'] ) ? (int) $input['id'] : 0;
				$post  = self::load_notification( $module, $id );

				if ( is_wp_error( $post ) ) {
					return $post;
				}

				$has_name = array_key_exists( 'name', $input );
				$patches  = isset( $input['connections'] ) && is_array( $input['connections'] ) ? $input['connections'] : array();
				$has_work = $has_name;

				foreach ( $patches as $patch ) {
					if ( is_array( $patch ) && ! empty( $patch ) ) {
						$has_work = true;
					}
				}

				if ( ! $has_work ) {
					return $module->agent_error(
						'ntfm_nothing_to_update',
						__( 'Neither a name nor a connection change was supplied.', 'notification-master' ),
						array(
							'status'     => 400,
							'agent_hint' => 'Pass "name", or "connections" keyed by connection id with enabled, name and/or settings. Read the current values first with notification-master/get-notification.',
							'recovery'   => array(
								'ability' => 'notification-master/get-notification',
								'input'   => array( 'id' => $post->ID ),
							),
						)
					);
				}

				$name = '';
				if ( $has_name ) {
					$name = sanitize_text_field( (string) $input['name'] );
					if ( '' === $name ) {
						return $module->agent_error(
							'ntfm_invalid_setting',
							__( 'The notification name cannot be empty.', 'notification-master' ),
							array(
								'status'     => 400,
								'key'        => 'name',
								'agent_hint' => 'Pass a non-empty name of at most 200 characters.',
							)
						);
					}
				}

				$connections = get_post_meta( $post->ID, 'connections', true );
				$connections = is_array( $connections ) ? $connections : array();
				$updated     = $connections;
				$changed     = array();

				$integrations = Integrations_Loader::get_instance()->get_integrations();

				// Everything is validated into $updated before a single write,
				// so a refusal half-way through never leaves a partial edit.
				foreach ( $patches as $connection_id => $patch ) {
					$connection_id = (string) $connection_id;
					$patch         = is_array( $patch ) ? $patch : array();

					if ( ! isset( $connections[ $connection_id ] ) || ! is_array( $connections[ $connection_id ] ) ) {
						return $module->agent_error(
							'ntfm_connection_not_found',
							/* translators: %s: connection id */
							sprintf( __( 'This notification has no connection "%s".', 'notification-master' ), $connection_id ),
							array(
								'status'     => 404,
								'valid_ids'  => array_map( 'strval', array_keys( $connections ) ),
								'agent_hint' => 'Use one of valid_ids. Connection ids are the "id" of each item in connections from notification-master/get-notification; new connections cannot be added through this ability.',
								'recovery'   => array(
									'ability' => 'notification-master/get-notification',
									'input'   => array( 'id' => $post->ID ),
								),
							)
						);
					}

					$current = $connections[ $connection_id ];
					$slug    = isset( $current['integration'] ) ? (string) $current['integration'] : '';

					if ( Tier::is_pro_integration( $slug ) && ! Tier::is_pro() ) {
						return Tier::pro_required_error(
							sprintf( 'The "%s" channel', $slug ),
							array(
								'connection_id' => $connection_id,
								'agent_hint'    => 'This connection sends through a Pro-only channel and Notification Master Pro is not active, so it cannot be edited here. Leave it out of connections, or edit a connection on one of: ' . implode( ', ', Tier::FREE_INTEGRATIONS ) . '.',
							)
						);
					}

					if ( ! isset( $integrations[ $slug ] ) ) {
						return $module->agent_error(
							'ntfm_invalid_integration',
							/* translators: %s: integration slug */
							sprintf( __( 'The connection\'s channel "%s" is not registered on this site.', 'notification-master' ), $slug ),
							array(
								'status'        => 400,
								'connection_id' => $connection_id,
								'allowed'       => array_keys( $integrations ),
								'agent_hint'    => 'The stored connection points at a channel no active plugin provides, so its settings cannot be validated. Fix it in wp-admin or edit another connection.',
							)
						);
					}

					$integration = $integrations[ $slug ];

					if ( array_key_exists( 'settings', $patch ) ) {
						$new_settings = is_array( $patch['settings'] ) ? $patch['settings'] : array();
						$allowed      = isset( self::TEXT_SETTINGS[ $slug ] ) ? self::TEXT_SETTINGS[ $slug ] : array();
						$bad_key      = self::find_disallowed_setting( $new_settings, $allowed );

						if ( null !== $bad_key ) {
							return $module->agent_error(
								'ntfm_invalid_setting',
								/* translators: 1: settings key, 2: integration slug, 3: allowed keys */
								sprintf( __( 'The setting "%1$s" cannot be edited on a %2$s connection. Editable keys: %3$s.', 'notification-master' ), $bad_key, $slug, empty( $allowed ) ? '(none)' : implode( ', ', $allowed ) ),
								array(
									'status'        => 400,
									'key'           => $bad_key,
									'allowed'       => $allowed,
									'connection_id' => $connection_id,
									'agent_hint'    => 'Only text content is editable through abilities. Recipients, URLs, headers, credentials and conditions are changed in wp-admin.',
								)
							);
						}

						$current_settings = isset( $current['settings'] ) && is_array( $current['settings'] ) ? $current['settings'] : array();
						$merged           = array_merge( $current_settings, $new_settings );
						$valid            = rest_validate_value_from_schema( $merged, $integration->get_attributes_schema(), 'settings' );

						if ( is_wp_error( $valid ) ) {
							return $module->agent_error(
								'ntfm_invalid_setting',
								$valid->get_error_message(),
								array(
									'status'        => 400,
									'allowed'       => $allowed,
									'connection_id' => $connection_id,
									'agent_hint'    => 'The merged settings failed the channel\'s own schema. Read the stored values with notification-master/get-notification and keep each key\'s type: strings stay strings, discord message stays an object, webhook body stays a list of {key, value}.',
								)
							);
						}

						// Same coercion as Notifications::sanitize_connections() on the REST path.
						$merged = rest_sanitize_value_from_schema( $merged, $integration->get_attributes_schema(), 'settings' );

						foreach ( $new_settings as $key => $value ) {
							if ( ! array_key_exists( $key, $current_settings ) || $current_settings[ $key ] !== $value ) {
								$changed[] = 'connections.' . $connection_id . '.settings.' . $key;
							}
						}

						$updated[ $connection_id ]['settings'] = $merged;
					}

					if ( array_key_exists( 'enabled', $patch ) ) {
						$enabled = rest_sanitize_boolean( $patch['enabled'] );
						$was     = isset( $current['enabled'] ) ? rest_sanitize_boolean( $current['enabled'] ) : true;

						if ( $was !== $enabled ) {
							$changed[] = 'connections.' . $connection_id . '.enabled';
						}

						$updated[ $connection_id ]['enabled'] = $enabled;
					}

					if ( array_key_exists( 'name', $patch ) ) {
						$connection_name = sanitize_text_field( (string) $patch['name'] );
						$was             = isset( $current['name'] ) ? (string) $current['name'] : '';

						if ( $was !== $connection_name ) {
							$changed[] = 'connections.' . $connection_id . '.name';
						}

						$updated[ $connection_id ]['name'] = $connection_name;
					}
				}

				if ( $has_name && $name !== (string) $post->post_title ) {
					// wp_update_post() expects slashed data; without this a backslash in the name is lost.
					$result = wp_update_post(
						wp_slash(
							array(
								'ID'         => $post->ID,
								'post_title' => $name,
							)
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $module->agent_error(
							$result->get_error_code(),
							$result->get_error_message(),
							array(
								'status'     => 500,
								'agent_hint' => 'WordPress refused to update the notification post. Nothing else was written.',
							)
						);
					}

					$changed[] = 'name';
				}

				if ( $updated !== $connections ) {
					update_post_meta( $post->ID, 'connections', $updated );
				}

				$rows = array();
				foreach ( $updated as $connection_id => $connection ) {
					if ( ! is_array( $connection ) ) {
						continue;
					}

					$rows[] = array(
						'id'          => (string) $connection_id,
						'name'        => isset( $connection['name'] ) ? (string) $connection['name'] : '',
						'integration' => isset( $connection['integration'] ) ? (string) $connection['integration'] : '',
						'enabled'     => isset( $connection['enabled'] ) ? rest_sanitize_boolean( $connection['enabled'] ) : true,
					);
				}

				return array(
					'id'             => (int) $post->ID,
					'name'           => (string) get_post_field( 'post_title', $post->ID, 'raw' ),
					'status'         => (string) $post->post_status,
					'changed_fields' => array_values( array_unique( $changed ) ),
					'connections'    => $rows,
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/update-notification' ),
			'meta'                => $module->meta(
				array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				)
			),
		);
	}

	/**
	 * Create a draft notification from a starter template.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @return array Ability args.
	 */
	private static function create_notification_from_template( $module ) {
		return array(
			'label'               => __( 'Create notification from template', 'notification-master' ),
			'description'         => __( 'Creates a new notification from one of the starter templates and always saves it as a draft, so nothing fires until a human (or notification-master/set-notification-status with status publish) enables it. Template ids come from notification-master/list-starter-templates and look like tpl_post-published. Each template ships five connections: email to administrators and web push to every subscriber (both enabled), plus disabled Discord, webhook and Instagram shells whose URLs and credentials still have to be filled in wp-admin. Templates exist only for triggers registered on this site; there are no WooCommerce templates in either tier, so an unknown id answers 400 ntfm_invalid_template with the allowed ids. The reply lists the new id and the next action that would enable it. Nothing is sent by this call.', 'notification-master' ),
			'category'            => Abilities_Module::CATEGORY_ACTIONS,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'template_id' => array(
						'type'        => 'string',
						'description' => __( 'Template id from notification-master/list-starter-templates, e.g. tpl_post-published.', 'notification-master' ),
					),
					'name'        => array(
						'type'        => 'string',
						'minLength'   => 1,
						'maxLength'   => 200,
						'description' => __( 'Name for the new notification. Defaults to the template title.', 'notification-master' ),
					),
				),
				'required'             => array( 'template_id' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'             => array( 'type' => 'integer' ),
					'name'           => array( 'type' => 'string' ),
					'status'         => array(
						'type' => 'string',
						'enum' => array( 'draft' ),
					),
					'trigger'        => array( 'type' => 'string' ),
					'trigger_active' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the template\'s trigger is registered on this site right now.', 'notification-master' ),
					),
					'connections'    => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'id'          => array( 'type' => 'string' ),
								'integration' => array( 'type' => 'string' ),
								'enabled'     => array( 'type' => 'boolean' ),
							),
							'required'             => array( 'id', 'integration', 'enabled' ),
							'additionalProperties' => false,
						),
					),
					'next_actions'   => array(
						'type'        => 'array',
						'description' => __( 'The follow-up call that would enable the new draft.', 'notification-master' ),
						'items'       => array(
							'type'                 => 'object',
							'properties'           => array(
								'ability' => array( 'type' => 'string' ),
								'input'   => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							),
							'required'             => array( 'ability', 'input' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'id', 'name', 'status', 'trigger', 'trigger_active', 'connections', 'next_actions' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = null ) use ( $module ) {
				$input       = is_array( $input ) ? $input : array();
				$template_id = isset( $input['template_id'] ) ? (string) $input['template_id'] : '';
				$template    = null;
				$ids         = array();

				foreach ( Templates::get_templates() as $candidate ) {
					if ( ! is_array( $candidate ) || ! isset( $candidate['id'] ) ) {
						continue;
					}

					$ids[] = (string) $candidate['id'];
					if ( (string) $candidate['id'] === $template_id ) {
						$template = $candidate;
					}
				}

				if ( null === $template ) {
					return $module->agent_error(
						'ntfm_invalid_template',
						/* translators: %s: template id */
						sprintf( __( 'No starter template has the id "%s".', 'notification-master' ), $template_id ),
						array(
							'status'     => 400,
							'allowed'    => $ids,
							'agent_hint' => 'Use one of allowed, or browse them with notification-master/list-starter-templates. Templates exist only for triggers registered on this site.',
							'recovery'   => array(
								'ability' => 'notification-master/list-starter-templates',
								'input'   => array(),
							),
						)
					);
				}

				$trigger = isset( $template['trigger'] ) ? (string) $template['trigger'] : '';

				// Defensive only: Templates::get_family() ships no WooCommerce family today.
				if ( Tier::is_pro_trigger( $trigger ) && ! Tier::is_pro() ) {
					return Tier::pro_required_error(
						sprintf( 'The "%s" trigger', $trigger ),
						array(
							'template_id' => $template_id,
							'agent_hint'  => 'This template fires on a WooCommerce trigger that only Notification Master Pro registers. Pick a template whose trigger is active, see notification-master/list-starter-templates.',
						)
					);
				}

				if ( isset( $input['name'] ) ) {
					$name = sanitize_text_field( (string) $input['name'] );
					if ( '' !== $name ) {
						$template['title'] = $name;
					}
				}

				// Always a draft, whatever the template says.
				$template['status'] = 'draft';

				// The import route runs Notifications::sanitize_connections, so
				// every connection is checked against its channel's schema.
				$result = $module->dispatch(
					'POST',
					'/ntfm/v1/notifications/import',
					array(
						'notifications'  => array( $template ),
						'default_status' => 'draft',
					)
				);

				if ( is_wp_error( $result ) ) {
					$data = (array) $result->get_error_data();
					if ( ! isset( $data['status'] ) ) {
						$data['status'] = 500;
					}
					$data['agent_hint'] = 'The import route refused the template. Nothing was created.';

					return $module->agent_error( $result->get_error_code(), $result->get_error_message(), $data );
				}

				$imported = isset( $result['imported'] ) && is_array( $result['imported'] ) ? $result['imported'] : array();

				if ( empty( $imported ) || ! isset( $imported[0]['id'] ) ) {
					$reason = isset( $result['skipped'][0]['reason'] ) ? (string) $result['skipped'][0]['reason'] : '';

					if ( 'pro_required' === $reason ) {
						return Tier::pro_required_error( sprintf( 'The "%s" trigger', $trigger ), array( 'template_id' => $template_id ) );
					}

					return $module->agent_error(
						'ntfm_import_failed',
						__( 'The notification could not be created from the template.', 'notification-master' ),
						array(
							'status'     => 500,
							'reason'     => $reason,
							'agent_hint' => 'WordPress refused to insert the notification post. Nothing was created; check the site for database errors before retrying.',
						)
					);
				}

				$new_id      = (int) $imported[0]['id'];
				$connections = get_post_meta( $new_id, 'connections', true );
				$connections = is_array( $connections ) ? $connections : array();
				$rows        = array();

				foreach ( $connections as $connection_id => $connection ) {
					if ( ! is_array( $connection ) ) {
						continue;
					}

					$rows[] = array(
						'id'          => (string) $connection_id,
						'integration' => isset( $connection['integration'] ) ? (string) $connection['integration'] : '',
						'enabled'     => isset( $connection['enabled'] ) ? rest_sanitize_boolean( $connection['enabled'] ) : true,
					);
				}

				$triggers = Triggers_Loader::get_instance()->get_triggers();

				return array(
					'id'             => $new_id,
					'name'           => isset( $imported[0]['title'] ) ? (string) $imported[0]['title'] : (string) get_the_title( $new_id ),
					'status'         => 'draft',
					'trigger'        => $trigger,
					'trigger_active' => isset( $triggers[ $trigger ] ),
					'connections'    => $rows,
					'next_actions'   => array(
						array(
							'ability' => 'notification-master/set-notification-status',
							'input'   => array(
								'id'     => $new_id,
								'status' => 'publish',
							),
						),
					),
				);
			},
			'permission_callback' => $module->permission_callback( 'notification-master/create-notification-from-template' ),
			'meta'                => $module->meta(
				array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				)
			),
		);
	}

	/**
	 * Output schema of one notification row.
	 *
	 * @since 1.8.0
	 *
	 * @param bool $with_settings Whether connections carry settings and the full schedule.
	 * @return array JSON schema.
	 */
	private static function row_schema( $with_settings ) {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'             => array( 'type' => 'integer' ),
				'name'           => array( 'type' => 'string' ),
				'status'         => array(
					'type' => 'string',
					'enum' => array( 'publish', 'draft' ),
				),
				'enabled'        => array(
					'type'        => 'boolean',
					'description' => __( 'True when status is publish.', 'notification-master' ),
				),
				'trigger'        => array(
					'type'        => 'string',
					'description' => __( 'Trigger slug stored on the notification; may be empty on an unfinished notification.', 'notification-master' ),
				),
				'trigger_name'   => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'Human name of the trigger, null when it is not registered on this site.', 'notification-master' ),
				),
				'trigger_active' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the trigger is registered right now. A notification on an inactive trigger never fires.', 'notification-master' ),
				),
				'connections'    => array(
					'type'  => 'array',
					'items' => self::connection_schema( $with_settings ),
				),
				'modified'       => array(
					'type'        => 'string',
					'description' => __( 'Last modified time, GMT, as Y-m-d H:i:s.', 'notification-master' ),
				),
			),
			'required'             => array( 'id', 'name', 'status', 'enabled', 'trigger', 'trigger_name', 'trigger_active', 'connections', 'modified' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema of one connection inside a row.
	 *
	 * @since 1.8.0
	 *
	 * @param bool $with_settings Whether settings and the full schedule are included.
	 * @return array JSON schema.
	 */
	private static function connection_schema( $with_settings ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'id'                    => array( 'type' => 'string' ),
				'name'                  => array( 'type' => 'string' ),
				'integration'           => array(
					'type'        => 'string',
					'description' => __( 'Channel slug, e.g. email or webpush.', 'notification-master' ),
				),
				'integration_name'      => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'Human name of the channel, null when no active plugin provides it.', 'notification-master' ),
				),
				'integration_available' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the channel is registered on this site. A connection on an unavailable channel is skipped at send time.', 'notification-master' ),
				),
				'enabled'               => array( 'type' => 'boolean' ),
				'schedule'              => array(
					'type'                 => 'object',
					'properties'           => array(
						'enabled' => array( 'type' => 'boolean' ),
						'mode'    => array(
							'type'        => array( 'string', 'null' ),
							'description' => __( 'delay or merge_tag_relative; null when the connection sends immediately.', 'notification-master' ),
						),
						'summary' => array(
							'type'        => array( 'string', 'null' ),
							'description' => __( 'One line such as "delay 2 hours" or "merge_tag_relative post.published_date +10 minutes"; null when the connection sends immediately.', 'notification-master' ),
						),
					),
					'required'             => array( 'enabled', 'mode', 'summary' ),
					// get-notification merges the stored delay and
					// merge_tag_relative blocks in beside the summary.
					'additionalProperties' => $with_settings,
				),
			),
			'required'             => array( 'id', 'name', 'integration', 'integration_name', 'integration_available', 'enabled', 'schedule' ),
			'additionalProperties' => false,
		);

		if ( $with_settings ) {
			$schema['properties']['settings'] = array(
				'type'                 => 'object',
				'description'          => __( 'Connection settings with recipients, URLs, headers and credentials replaced by "[redacted]" or "[redacted: N items]".', 'notification-master' ),
				'additionalProperties' => true,
			);
			$schema['required'][]             = 'settings';
		}

		return $schema;
	}

	/**
	 * Load a notification post or build the shared 404.
	 *
	 * A trashed or otherwise non publish/draft post is also "not found": the
	 * row's status enum only knows those two states and the plugin's own list
	 * never shows anything else.
	 *
	 * @since 1.8.0
	 *
	 * @param Abilities_Module $module Bootstrap module.
	 * @param int              $id     Post id.
	 * @return \WP_Post|\WP_Error
	 */
	private static function load_notification( $module, $id ) {
		$post = $id > 0 ? get_post( $id ) : null;

		if ( ! $post || self::POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			return $module->agent_error(
				'ntfm_notification_not_found',
				/* translators: %d: notification id */
				sprintf( __( 'No notification exists with id %d.', 'notification-master' ), $id ),
				array(
					'status'     => 404,
					'agent_hint' => 'Call notification-master/list-notifications for valid ids.',
					'recovery'   => array(
						'ability' => 'notification-master/list-notifications',
						'input'   => array(),
					),
				)
			);
		}

		return $post;
	}

	/**
	 * Build one notification row.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post $post          Notification post.
	 * @param array    $triggers      Registered triggers keyed by slug.
	 * @param array    $integrations  Registered integrations keyed by slug.
	 * @param bool     $with_settings Whether to include redacted settings and the full schedule.
	 * @return array Row.
	 */
	private static function row( $post, $triggers, $integrations, $with_settings ) {
		$slug        = (string) get_post_meta( $post->ID, 'trigger', true );
		$connections = get_post_meta( $post->ID, 'connections', true );
		$connections = is_array( $connections ) ? $connections : array();
		$rows        = array();

		foreach ( $connections as $connection_id => $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$rows[] = self::connection_row( (string) $connection_id, $connection, $integrations, $with_settings );
		}

		// Drafts can carry a zero GMT date; fall back to the local timestamp.
		$modified = (string) $post->post_modified_gmt;
		if ( '' === $modified || 0 === strpos( $modified, '0000-00-00' ) ) {
			$modified = (string) get_gmt_from_date( $post->post_modified );
		}

		return array(
			'id'             => (int) $post->ID,
			'name'           => (string) $post->post_title,
			'status'         => (string) $post->post_status,
			'enabled'        => 'publish' === $post->post_status,
			'trigger'        => $slug,
			'trigger_name'   => isset( $triggers[ $slug ] ) ? (string) $triggers[ $slug ]->get_name() : null,
			'trigger_active' => isset( $triggers[ $slug ] ),
			'connections'    => $rows,
			'modified'       => $modified,
		);
	}

	/**
	 * Build one connection row.
	 *
	 * @since 1.8.0
	 *
	 * @param string $connection_id Connection id (the key in the connections meta).
	 * @param array  $connection    Stored connection.
	 * @param array  $integrations  Registered integrations keyed by slug.
	 * @param bool   $with_settings Whether to include redacted settings and the full schedule.
	 * @return array Row.
	 */
	private static function connection_row( $connection_id, array $connection, $integrations, $with_settings ) {
		$slug     = isset( $connection['integration'] ) ? (string) $connection['integration'] : '';
		$schedule = isset( $connection['schedule'] ) && is_array( $connection['schedule'] ) ? $connection['schedule'] : array();
		$summary  = self::schedule_summary( $schedule );

		$row = array(
			'id'                    => $connection_id,
			'name'                  => isset( $connection['name'] ) ? (string) $connection['name'] : '',
			'integration'           => $slug,
			'integration_name'      => isset( $integrations[ $slug ] ) ? (string) $integrations[ $slug ]->name : null,
			'integration_available' => isset( $integrations[ $slug ] ),
			// The processor treats a missing flag as enabled.
			'enabled'               => isset( $connection['enabled'] ) ? rest_sanitize_boolean( $connection['enabled'] ) : true,
			'schedule'              => $summary,
		);

		if ( $with_settings ) {
			$settings        = isset( $connection['settings'] ) ? $connection['settings'] : array();
			$row['settings'] = Redactor::redact_settings( $slug, $settings );
			$row['schedule'] = array_merge( $schedule, $summary );
		}

		return $row;
	}

	/**
	 * Summarise a connection's stored schedule.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed $schedule Stored schedule block.
	 * @return array {enabled bool, mode string|null, summary string|null}.
	 */
	private static function schedule_summary( $schedule ) {
		$off = array(
			'enabled' => false,
			'mode'    => null,
			'summary' => null,
		);

		if ( ! is_array( $schedule ) || ! isset( $schedule['enabled'] ) || ! rest_sanitize_boolean( $schedule['enabled'] ) ) {
			return $off;
		}

		$mode = isset( $schedule['mode'] ) ? (string) $schedule['mode'] : 'delay';

		if ( 'merge_tag_relative' === $mode ) {
			$config = isset( $schedule['merge_tag_relative'] ) && is_array( $schedule['merge_tag_relative'] ) ? $schedule['merge_tag_relative'] : array();
			$tag    = isset( $config['tag'] ) ? trim( (string) $config['tag'], '{} ' ) : '';
			$offset = isset( $config['offset'] ) ? (int) $config['offset'] : 0;
			$unit   = isset( $config['unit'] ) ? (string) $config['unit'] : 'minutes';
			$sign   = isset( $config['direction'] ) && 'before' === $config['direction'] ? '-' : '+';

			return array(
				'enabled' => true,
				'mode'    => $mode,
				'summary' => sprintf( 'merge_tag_relative %s %s%d %s', $tag, $sign, $offset, $unit ),
			);
		}

		if ( 'delay' !== $mode ) {
			// The scheduler dispatches an unknown mode immediately.
			return $off;
		}

		$delay = isset( $schedule['delay'] ) && is_array( $schedule['delay'] ) ? $schedule['delay'] : array();
		$value = isset( $delay['value'] ) ? (int) $delay['value'] : 0;
		$unit  = isset( $delay['unit'] ) ? (string) $delay['unit'] : 'minutes';

		return array(
			'enabled' => true,
			'mode'    => $mode,
			'summary' => sprintf( 'delay %d %s', $value, $unit ),
		);
	}

	/**
	 * Count a notification's queued sends with one query.
	 *
	 * @since 1.8.0
	 *
	 * @param int $notification_id Notification post id.
	 * @return int
	 */
	private static function pending_sends_for( $notification_id ) {
		return (int) Scheduler::get_instance()->count_pending( (int) $notification_id );
	}

	/**
	 * Meta query matching notifications on one trigger.
	 *
	 * @since 1.8.0
	 *
	 * @param string $trigger Trigger slug.
	 * @return array
	 */
	private static function trigger_meta_query( $trigger ) {
		return array(
			array(
				'key'   => 'trigger',
				'value' => $trigger,
			),
		);
	}

	/**
	 * Total notifications for the list filters.
	 *
	 * Without a trigger filter the cached wp_count_posts() answer is summed.
	 * With one, the ids are fetched in a single lean query because the cached
	 * counts know nothing about meta.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $statuses Post statuses being listed.
	 * @param string $trigger  Trigger slug filter, '' for none.
	 * @return int
	 */
	private static function count_notifications( array $statuses, $trigger ) {
		if ( '' !== $trigger ) {
			$ids = get_posts(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => $statuses,
					'numberposts'            => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reason: the trigger lives only in post meta; this is an ids-only count on a small CPT.
					'meta_query'             => self::trigger_meta_query( $trigger ),
				)
			);

			return count( $ids );
		}

		$counts = wp_count_posts( self::POST_TYPE );
		$total  = 0;

		foreach ( $statuses as $status ) {
			$total += isset( $counts->$status ) ? (int) $counts->$status : 0;
		}

		return $total;
	}

	/**
	 * First settings key an agent may not edit, or null when all are allowed.
	 *
	 * Top-level keys must be in the channel's whitelist; the Pro condition
	 * keys are refused at any depth.
	 *
	 * @since 1.8.0
	 *
	 * @param array $settings Requested settings patch.
	 * @param array $allowed  Editable keys for the channel.
	 * @return string|null Offending key path.
	 */
	private static function find_disallowed_setting( array $settings, array $allowed ) {
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;

			if ( in_array( $key, self::CONDITION_KEYS, true ) || ! in_array( $key, $allowed, true ) ) {
				return $key;
			}

			$nested = self::find_condition_key( $value, $key );
			if ( null !== $nested ) {
				return $nested;
			}
		}

		return null;
	}

	/**
	 * Path of the first Pro condition key nested inside a value, or null.
	 *
	 * @since 1.8.0
	 *
	 * @param mixed  $value  Value to walk.
	 * @param string $prefix Dotted path of the value.
	 * @return string|null
	 */
	private static function find_condition_key( $value, $prefix ) {
		if ( ! is_array( $value ) ) {
			return null;
		}

		foreach ( $value as $key => $item ) {
			$path = $prefix . '.' . $key;

			if ( in_array( (string) $key, self::CONDITION_KEYS, true ) ) {
				return $path;
			}

			$nested = self::find_condition_key( $item, $path );
			if ( null !== $nested ) {
				return $nested;
			}
		}

		return null;
	}
}
