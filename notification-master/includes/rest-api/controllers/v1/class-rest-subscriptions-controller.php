<?php
/**
 * Class Rest_Subscriptions_Controller
 *
 * @package notification-master-pro
 *
 * @since 1.4.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use Notification_Master\REST_API\Controllers\V1\Rest_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Notification_Master\DB\Models\Subscription_Model;

/**
 * Subscriptions controller class.
 */
class Rest_Subscriptions_Controller extends Rest_Controller {

	/**
	 * Route base.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions';

	/**
	 * Register routes.
	 *
	 * @since 1.4.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscriptions' ),
					'permission_callback' => array( $this, 'get_subscriptions_permissions_check' ),
					'args'                => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => __( 'Current page.', 'notification-master' ),
							'required'    => false,
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => __( 'Number of items per page.', 'notification-master' ),
							'required'    => false,
						),
						'status'   => array(
							'type'        => 'string',
							'description' => __( 'Status.', 'notification-master' ),
							'required'    => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_subscription' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'endpoint' => array(
							'type'        => 'string',
							'description' => __( 'Endpoint.', 'notification-master' ),
							'required'    => true,
						),
						'auth'     => array(
							'type'        => 'string',
							'description' => __( 'Auth.', 'notification-master' ),
							'required'    => true,
						),
						'p256dh'   => array(
							'type'        => 'string',
							'description' => __( 'P256dh.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_subcriptions' ),
					'permission_callback' => array( $this, 'delete_subcriptions_permissions_check' ),
					'args'                => array(
						'ids' => array(
							'type'        => 'array',
							'description' => __( 'Subscription IDs.', 'notification-master' ),
							'required'    => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_subscriptions' ),
					'permission_callback' => array( $this, 'update_subscriptions_permissions_check' ),
					'args'                => array(
						'ids'    => array(
							'type'        => 'array',
							'description' => __( 'Subscription IDs.', 'notification-master' ),
							'required'    => true,
						),
						'status' => array(
							'type'        => 'string',
							'description' => __( 'Status.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Subscription ID.', 'notification-master' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscription' ),
					'permission_callback' => array( $this, 'get_subscription_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_subscription' ),
					'permission_callback' => array( $this, 'update_subscription_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_subscription' ),
					'permission_callback' => array( $this, 'delete_subscription_permissions_check' ),
				),
			)
		);

		// Unsubscribe route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unsubscribe',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'unsubscribe' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'endpoint' => array(
							'type'        => 'string',
							'description' => __( 'Endpoint.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
			)
		);

		// Check subscription status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check-status',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check_subscription_status' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'endpoint' => array(
							'type'        => 'string',
							'description' => __( 'Endpoint.', 'notification-master' ),
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Get subscriptions.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_subscriptions( $request ) {
		$page     = $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10;
		$status   = $request->get_param( 'status' ) ? $request->get_param( 'status' ) : 'all';

		$subscriptions = Subscription_Model::get_rows( $per_page, $page, $status );

		// Add user data.
		foreach ( $subscriptions as $key => $subscription ) {
			if ( ! empty( $subscription->user_id ) ) {
				$user                        = get_user_by( 'ID', $subscription->user_id );
				$subscriptions[ $key ]->user = array(
					'username' => $user->user_login,
					'url'      => get_edit_user_link( $user->ID ),
				);
			}
		}

		$conut = Subscription_Model::get_count();

		$result = array(
			'subscriptions' => $subscriptions,
			'count'         => $conut,
			'chrome'        => Subscription_Model::get_count_by_browser( 'Chrome' ),
			'firefox'       => Subscription_Model::get_count_by_browser( 'Firefox' ),
			'safari'        => Subscription_Model::get_count_by_browser( 'Safari' ),
			'opera'         => Subscription_Model::get_count_by_browser( 'Opera' ),
			'other'         => Subscription_Model::get_count_for_other_browsers(),
			'subscribed'    => Subscription_Model::get_count_by_status( 'subscribed' ),
			'unsubscribed'  => Subscription_Model::get_count_by_status( 'unsubscribed' ),
			'desktop'       => Subscription_Model::get_count_by_device( 'desktop' ),
			'mobile'        => Subscription_Model::get_count_by_device( 'mobile' ),
			'tablet'        => Subscription_Model::get_count_by_device( 'tablet' ),
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get subscriptions permissions check.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_subscriptions_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create subscription.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_subscription( $request ) {
		$endpoint         = sanitize_text_field( $request->get_param( 'endpoint' ) );
		$auth             = sanitize_text_field( $request->get_param( 'auth' ) );
		$p256dh           = sanitize_text_field( $request->get_param( 'p256dh' ) );
		$expiration_time  = sanitize_text_field( $request->get_param( 'expiration_time' ) );
		$content_encoding = sanitize_text_field( $request->get_param( 'contentEncoding' ) );
		$action           = sanitize_text_field( $request->get_param( 'action' ) );
		$action           = $action ? $action : 'automatic';

		if ( empty( $endpoint ) || empty( $auth ) || empty( $p256dh ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		$subscription_data = array(
			'endpoint'         => $endpoint,
			'auth'             => $auth,
			'p256dh'           => $p256dh,
			'expiration_time'  => $expiration_time,
			'content_encoding' => $content_encoding,
		);

		$subscription = Subscription_Model::get_by_endpoint( $endpoint );

		if ( ! empty( $subscription ) ) {
			$update = array(
				'status' => 'subscribed',
			);

			// Check if it's logged in user.
			if ( is_user_logged_in() ) {
				$update['user_id'] = get_current_user_id();
			}

			Subscription_Model::update( $subscription->id, $update );

			// Add action to send webpush.
			do_action( 'notification_master_send_webpush', $subscription_data, true, $action );

			return new WP_REST_Response(
				array(
					'success' => true,
				),
				200
			);
		}

		$subscription = Subscription_Model::insert( $subscription_data );

		// Add action to send webpush.
		do_action( 'notification_master_send_webpush', $subscription_data, false, $action );

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Delete subscriptions.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_subcriptions( $request ) {
		$ids = $request->get_param( 'ids' ) ? $request->get_param( 'ids' ) : array();

		if ( ! empty( $ids ) ) {
			Subscription_Model::delete_by_ids( $ids );
		} else {
			Subscription_Model::delete();
		}

		return new WP_REST_Response( true, 200 );
	}

	/**
	 * Delete subscriptions permissions check.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_subcriptions_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Update subscriptions.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_subscriptions( $request ) {
		$ids    = $request->get_param( 'ids' ) ? $request->get_param( 'ids' ) : array();
		$status = $request->get_param( 'status' ) ? $request->get_param( 'status' ) : '';

		if ( empty( $ids ) || empty( $status ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		Subscription_Model::update_status( $ids, $status );

		return new WP_REST_Response( true, 200 );
	}

	/**
	 * Update subscriptions permissions check.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function update_subscriptions_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get subscription.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_subscription( $request ) {
		$id = $request->get_param( 'id' );

		$subscription = Subscription_Model::get( $id );

		if ( empty( $subscription ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $subscription, 200 );
	}

	/**
	 * Get subscription permissions check.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function get_subscription_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Update subscription.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_subscription( $request ) {
		$id     = $request->get_param( 'id' );
		$status = $request->get_param( 'status' );

		if ( empty( $id ) || empty( $status ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		Subscription_Model::update_status( array( $id ), $status );

		return new WP_REST_Response( true, 200 );
	}

	/**
	 * Update subscription permissions check.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function update_subscription_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Delete subscription.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_subscription( $request ) {
		$id = $request->get_param( 'id' );

		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		Subscription_Model::delete_by_ids( array( $id ) );

		return new WP_REST_Response( true, 200 );
	}

	/**
	 * Delete subscription permissions check.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_subscription_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Unsubscribe.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function unsubscribe( $request ) {
		$endpoint = $request->get_param( 'endpoint' );

		if ( empty( $endpoint ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		$subscription = Subscription_Model::get_by_endpoint( $endpoint );
		if ( empty( $subscription ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
				),
				200
			);
		}

		// Update status.
		Subscription_Model::update_status( array( $subscription->id ), 'unsubscribed' );

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Check subscription status.
	 *
	 * @since 1.4.3
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function check_subscription_status( $request ) {
		$endpoint = $request->get_param( 'endpoint' );

		if ( empty( $endpoint ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid data.', 'notification-master' ), array( 'status' => 400 ) );
		}

		$subscription = Subscription_Model::get_by_endpoint( $endpoint );

		if ( empty( $subscription ) ) {
			return new WP_REST_Response( array( 'status' => 'unsubscribed' ), 200 );
		}

		// Check if it's logged in user.
		if ( is_user_logged_in() ) {
			Subscription_Model::update( $subscription->id, array( 'user_id' => get_current_user_id() ) );
		}

		return new WP_REST_Response(
			array(
				'status' => $subscription->status,
			),
			200
		);
	}
}
