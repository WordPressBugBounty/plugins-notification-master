<?php
/**
 * Class Rest_Controller
 *
 * @package notification-master
 * @since 1.0.0
 */

namespace Notification_Master\REST_API\Controllers\V1;

use WP_REST_Controller;

/**
 * Base class for rest controllers.
 */
class Rest_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $namespace = 'ntfm/v1';

	/**
	 * Version.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $version = 'v1';

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $rest_base = '';
}
