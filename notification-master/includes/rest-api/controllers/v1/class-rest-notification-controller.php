<?php
/**
 * Class Rest_Notification_Controller
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\REST_API\Controllers\V1\Rest_Controller;
use Notification_Master\Merge_Tags\Loader as Merge_Tags_Loader;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Templates;
use Notification_Master\Notifications;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Notification controller class.
 */
class Rest_Notification_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'notifications';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/merge-tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_merge_tags' ),
					'permission_callback' => array( $this, 'get_merge_tags_permissions_check' ),
					'args'                => array(
						'trigger' => array(
							'type'        => 'string',
							'description' => __( 'Trigger.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
			)
		);

		// Starter templates library.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'tools_permissions_check' ),
				),
			)
		);

		// Export all notifications as a portable JSON payload.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_notifications' ),
					'permission_callback' => array( $this, 'tools_permissions_check' ),
				),
			)
		);

		// Import notifications (from an export file or the starter library).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_notifications' ),
					'permission_callback' => array( $this, 'tools_permissions_check' ),
					'args'                => array(
						'notifications'  => array(
							'type'        => 'array',
							'required'    => true,
							'description' => __( 'Notifications to import.', 'notification-master' ),
						),
						'default_status' => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft' ),
							'default'     => 'draft',
							'description' => __( 'Status applied to imported notifications that do not specify one.', 'notification-master' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check for the import/export/templates tools.
	 *
	 * @since 1.7.1
	 *
	 * @return bool
	 */
	public function tools_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the starter templates library.
	 *
	 * @since 1.7.1
	 *
	 * @return WP_REST_Response
	 */
	public function get_templates() {
		return new WP_REST_Response( Templates::get_templates() );
	}

	/**
	 * Export every notification to a portable structure.
	 *
	 * @since 1.7.1
	 *
	 * @return WP_REST_Response
	 */
	public function export_notifications() {
		$posts = get_posts(
			array(
				'post_type'   => 'ntfm_notification',
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			)
		);

		$notifications = array();
		foreach ( $posts as $post ) {
			$connections     = get_post_meta( $post->ID, 'connections', true );
			$notifications[] = array(
				'title'       => $post->post_title,
				'status'      => $post->post_status,
				'trigger'     => (string) get_post_meta( $post->ID, 'trigger', true ),
				'connections' => ! empty( $connections ) ? $connections : array(),
			);
		}

		return new WP_REST_Response(
			array(
				'schema'        => 'notification-master/notifications',
				'version'       => NOTIFICATION_MASTER_VERSION,
				'site_url'      => home_url(),
				'count'         => count( $notifications ),
				'notifications' => $notifications,
			)
		);
	}

	/**
	 * Import notifications from a list of definitions.
	 *
	 * @since 1.7.1
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_notifications( $request ) {
		$items          = $request->get_param( 'notifications' );
		$default_status = $request->get_param( 'default_status' );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return new WP_Error(
				'ntfm_import_empty',
				__( 'No notifications were provided to import.', 'notification-master' ),
				array( 'status' => 400 )
			);
		}

		$registered = Triggers_Loader::get_instance()->get_triggers();
		$imported   = array();
		$skipped    = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$trigger = isset( $item['trigger'] ) ? sanitize_text_field( $item['trigger'] ) : '';
			$title   = isset( $item['title'] ) && '' !== trim( (string) $item['title'] )
				? sanitize_text_field( $item['title'] )
				: __( 'Imported notification', 'notification-master' );

			$status = isset( $item['status'] ) ? $item['status'] : $default_status;
			if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
				$status = 'draft';
			}

			$connections = isset( $item['connections'] ) && is_array( $item['connections'] )
				? Notifications::get_instance()->sanitize_connections( $item['connections'] )
				: array();

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'ntfm_notification',
					'post_title'  => $title,
					'post_status' => $status,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$skipped[] = array(
					'title'  => $title,
					'reason' => $post_id->get_error_message(),
				);
				continue;
			}

			update_post_meta( $post_id, 'trigger', $trigger );
			update_post_meta( $post_id, 'connections', $connections );

			$imported[] = array(
				'id'             => $post_id,
				'title'          => $title,
				'trigger'        => $trigger,
				'status'         => $status,
				'trigger_active' => '' === $trigger || isset( $registered[ $trigger ] ),
			);
		}

		return new WP_REST_Response(
			array(
				'imported_count' => count( $imported ),
				'skipped_count'  => count( $skipped ),
				'imported'       => $imported,
				'skipped'        => $skipped,
			)
		);
	}

	/**
	 * Get merge tags.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_merge_tags( $request ) {
		$trigger_slug = $request->get_param( 'trigger' );
		$trigger      = Triggers_Loader::get_instance()->get_trigger( $trigger_slug );

		if ( ! $trigger ) {
			$general_merge_group = Merge_Tags_Loader::get_instance()->get_group( 'general' );
			return new WP_REST_Response(
				array(
					'general' => array(
						'label'      => $general_merge_group->get_name(),
						'merge_tags' => $general_merge_group->get_merge_tags(),
					),
				)
			);
		}

		$merge_tags         = array();
		$trigger_merge_tags = $trigger->get_merge_tags();
		array_unshift( $trigger_merge_tags, 'general' );

		foreach ( $trigger_merge_tags as $group_slug ) {
			$merge_tags_group = Merge_Tags_Loader::get_instance()->get_group( $group_slug );
			if ( ! $merge_tags_group ) {
				continue;
			}

			$merge_tags[ $group_slug ] = array(
				'label'                    => $merge_tags_group->get_name(),
				'is_advanced'              => $merge_tags_group->is_advanced(),
				'depends_on_plugin'        => $merge_tags_group->get_plugin_dependency(),
				'plugin_dependency_active' => $merge_tags_group->is_plugin_dependency_active(),
				'merge_tags'               => $merge_tags_group->get_merge_tags(),
			);
		}

		return new WP_REST_Response( $merge_tags );
	}

	/**
	 * Get merge tags permissions check.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_merge_tags_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
