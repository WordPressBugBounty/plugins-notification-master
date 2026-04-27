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
