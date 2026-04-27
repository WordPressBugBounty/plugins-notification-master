<?php
/**
 * Class Email Integration
 *
 * This class is responsible for sending email notifications.
 *
 * @package notification-master
 *
 * @since 1.0.0
 */

namespace Notification_Master\Integrations;

use Notification_Master\Abstracts\Integration;
use Notification_Master\Users\Users;
use Notification_Master\Utils;
use Notification_Master\Triggers\Loader as Triggers_Loader;

use function Notification_Master\Notification_Master;

/**
 * Email Integration class.
 */
class Email_Integration extends Integration {

	/**
	 * Name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'Email';

	/**
	 * Slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'email';

	/**
	 * Description.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $description = 'Send email notifications.';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Add ajax action.
		add_action( 'wp_ajax_notification_master_send_test_notification', array( $this, 'ajax_handler' ) );
	}

	/**
	 * Ajax handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_handler() {
		// Check if nonce is valid.
		check_ajax_referer( 'notification-master', 'nonce' );

		// Check if user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'notification-master' ),
				)
			);
		}

		// Get the email.
		$email = sanitize_email( $_POST['email'] ?? '' );
		if ( empty( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Email is required.', 'notification-master' ),
				)
			);
		}

		// Get the subject.
		$subject = sanitize_text_field( $_POST['subject'] ?? '' );
		if ( empty( $subject ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Subject is required.', 'notification-master' ),
				)
			);
		}

		// Get the body.
		$body = $_POST['body'] ? stripslashes( $_POST['body'] ) : '';
		if ( empty( $body ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Body is required.', 'notification-master' ),
				)
			);
		}

		$body = $this->get_email_body( $body );
		// Get the headers.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email.
		$result = wp_mail( $email, $subject, $body, $headers );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Email sent successfully.', 'notification-master' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to send email.', 'notification-master' ),
				)
			);
		}
	}

	/**
	 * Get email body.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html_body HTML body.
	 *
	 * @return string
	 */
	public function get_email_body( $html_body ) {
		// Prepare the data to be passed to the template
		$template_data = array(
			'body' => $html_body,
		);

		// Use WordPress load_template to load the email template and pass data
		ob_start();
		load_template( NOTIFICATION_MASTER_DIR . 'includes/email/template.php', false, $template_data );
		$email_content = ob_get_clean();

		return $email_content;
	}

	/**
	 * Process.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		$valid = $this->validate_attributes();
		if ( ! $valid ) {
			Notification_Master()->logger->error(
				'email_integration_invalid_attributes',
				$this->prepare_response(
					array(
						'message'    => __( 'Invalid attributes.', 'notification-master' ),
						'attributes' => $this->attributes,
					)
				)
			);
			return;
		}

		// This function is used to get the values of merge tags and sanitize them.
		$this->process_attributes();

		$emails          = $this->attributes['emails'];
		$excluded_emails = $this->attributes['excluded_emails'];
		$emails          = array_values( array_diff( $emails, $excluded_emails ) );
		$subject         = $this->attributes['subject'];
		$message         = $this->attributes['message'];

		$this->process_batch(
			array(
				'emails'        => $emails,
				'current_index' => 0,
				'subject'       => $subject,
				'message'       => $message,
				'args'          => array(
					'notification_name' => $this->notification_name,
					'trigger'           => $this->trigger->get_slug(),
				),
			)
		);
	}

	/**
	 * Send email.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $emails  Emails.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @param array  $headers Headers.
	 */
	public function send_notification( $emails, $subject, $message, $headers ) {
		// Do action before send email.
		do_action( 'notification_master_before_send_email', $emails, $subject, $message, $headers );

		$message = $this->get_email_body( $message );
		// Send email.
		$result = wp_mail( $emails, $subject, $message, $headers );
		if ( ! $result ) {
			Notification_Master()->notification_logger->error(
				$this->slug,
				$this->prepare_response(
					array(
						'message' => __( 'Failed to send email.', 'notification-master' ),
						'emails'  => $emails,
						'subject' => $subject,
						'body'    => $message,
						'headers' => $headers,
					)
				)
			);
		} else {
			Notification_Master()->notification_logger->success(
				$this->slug,
				$this->prepare_response(
					array(
						'message' => __( 'Email sent successfully.', 'notification-master' ),
						'emails'  => $emails,
						'subject' => $subject,
						'body'    => $message,
						'headers' => $headers,
					)
				)
			);
		}

		// Do action after send email.
		do_action( 'notification_master_after_send_email', $result, $emails, $subject, $message, $headers );
	}

	/**
	 * Process patch.
	 *
	 * @since 1.4.6
	 *
	 * @param array $data Data.
	 *
	 * @return void
	 */
	public function process_batch( $data ) {
		$emails        = $data['emails'] ?? array();
		$current_index = $data['current_index'] ?? 0;
		$subject       = $data['subject'];
		$message       = $data['message'];
		$args          = $data['args'] ?? array();

		if ( empty( $emails ) || empty( $subject ) || empty( $message ) || empty( $args ) ) {
			return;
		}

		if ( ! $this->notification_name ) {
			$this->notification_name = $args['notification_name'];
		}

		if ( ! $this->trigger ) {
			$this->trigger = Triggers_Loader::get_instance()->get_trigger( $args['trigger'] );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		$emails_chunk = array_slice( $emails, $current_index, 10 );
		foreach ( $emails_chunk as $email ) {
			$this->send_notification( $email, $subject, $message, $headers );
			++$current_index;
		}

		if ( $current_index < count( $emails ) ) {
			$data['current_index'] = $current_index;
			Utils::enqueue_async_action( $this->slug, $data );
		}
	}

	/**
	 * Get attributes schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_attributes_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'emails'          => array(
					'type'                          => 'array',
					'required'                      => true,
					'items'                         => array(
						'type' => array( 'string', 'object' ),
					),
					'sanitize_and_prepare_callback' => array( $this, 'sanitize_emails' ),
				),
				'excluded_emails' => array(
					'type'                          => 'array',
					'required'                      => false,
					'items'                         => array(
						'type' => array( 'string', 'object' ),
					),
					'sanitize_and_prepare_callback' => array( $this, 'sanitize_emails' ),
				),
				'subject'         => array(
					'type'     => 'string',
					'required' => true,
					'format'   => 'text-field',
				),
				'message'         => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		);
	}

	/**
	 * Sanitize emails.
	 *
	 * @since 1.0.0
	 *
	 * @param array $emails Emails.
	 *
	 * @return array
	 */
	public function sanitize_emails( $emails ) {
		if ( empty( $emails ) ) {
			return array();
		}

		$sanitized_emails = array();

		foreach ( $emails as $email ) {
			if ( is_string( $email ) ) {
				$email = array(
					'type'  => 'custom',
					'value' => $email,
				);
			}

			$type  = $email['type'];
			$value = $email['value'];
			if ( empty( $value ) || empty( $type ) ) {
				continue;
			}

			switch ( $type ) {
				case 'custom':
					if ( ! is_email( $value ) ) {
						continue 2;
					}
					$sanitized_emails[] = sanitize_email( $value );
					break;
				case 'role':
					$role = $value['value'];
					if ( ! empty( $role ) ) {
						$users            = Users::get_instance();
						$emails           = $users->get_users_emails_by_role( $role );
						$sanitized_emails = array_merge( $sanitized_emails, $emails );
					}
					break;
				case 'user':
					$user_id = $value['value'];
					if ( ! empty( $user_id ) ) {
						$user               = get_user_by( 'ID', $user_id );
						$sanitized_emails[] = $user->user_email;
					}
					break;
			}
		}

		$sanitized_emails = array_unique( $sanitized_emails );

		return $sanitized_emails;
	}
}
