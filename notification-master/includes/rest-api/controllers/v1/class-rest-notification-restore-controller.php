<?php
/**
 * Class Rest_Notification_Restore_Controller
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Undo for "Move to trash": restores notifications to the status they had before.
 */
class Rest_Notification_Restore_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $rest_base = 'notifications/restore';

	/**
	 * Most notifications one request may restore.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	const MAX_IDS = 100;

	/**
	 * Statuses a notification can come back to.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'publish', 'draft' );

	/**
	 * Register routes.
	 *
	 * @since 2.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'ids' => array(
							'type'        => 'array',
							'required'    => true,
							'minItems'    => 1,
							'maxItems'    => self::MAX_IDS,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'description' => __( 'IDs of trashed notifications to restore.', 'notification-master' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Only administrators manage notifications.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Restores each trashed notification to its previous status (core keeps it in _wp_trash_meta_status).
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function restore( $request ) {
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) ) );
		$restored = array();
		$failed   = array();

		add_filter( 'wp_untrash_post_status', array( $this, 'previous_status' ), 10, 3 );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'ntfm_notification' !== $post->post_type || 'trash' !== $post->post_status || ! current_user_can( 'delete_post', $id ) ) {
				$failed[] = $id;
				continue;
			}
			if ( ! wp_untrash_post( $id ) ) {
				$failed[] = $id;
				continue;
			}
			$restored[] = array(
				'id'     => $id,
				'status' => get_post_status( $id ),
			);
		}
		remove_filter( 'wp_untrash_post_status', array( $this, 'previous_status' ), 10 );

		return new WP_REST_Response(
			array(
				'restored' => $restored,
				'failed'   => $failed,
			)
		);
	}

	/**
	 * Status to restore: the one before trashing when it is on/off, else paused (core's default).
	 *
	 * @since 2.0.0
	 *
	 * @param string $new_status      Core's choice ('draft').
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Status before trashing.
	 *
	 * @return string
	 */
	public function previous_status( $new_status, $post_id, $previous_status ) {
		unset( $post_id );
		return in_array( $previous_status, self::STATUSES, true ) ? $previous_status : $new_status;
	}
}
