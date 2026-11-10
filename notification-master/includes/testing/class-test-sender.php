<?php
/**
 * Class Test_Sender
 *
 * @package notification-master
 *
 * @since 2.0.0
 */

namespace Notification_Master\Testing;

use Notification_Master\Integrations\Loader as Integrations_Loader;
use Notification_Master\Triggers\Loader as Triggers_Loader;
use Notification_Master\Logs\Log_Formatter;
use Notification_Master\Tier;
use WP_Error;

use function Notification_Master\Notification_Master;

/**
 * Sends one connection once with sample merge-tag values ("Send a test").
 */
class Test_Sender {

	/**
	 * Send a test.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args { integration, settings, trigger, notification_id, notification_name, recipient }.
	 *
	 * @return array|WP_Error { success, message, reason, http_code, log_ids, code }.
	 */
	public static function send( $args ) {
		$slug      = isset( $args['integration'] ) ? sanitize_key( $args['integration'] ) : '';
		$settings  = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
		$recipient = isset( $args['recipient'] ) ? trim( (string) $args['recipient'] ) : '';
		$id        = isset( $args['notification_id'] ) ? absint( $args['notification_id'] ) : 0;

		if ( Tier::is_pro_integration( $slug ) && ! Tier::is_pro() ) {
			return Tier::pro_required_error( Log_Formatter::integration_name( $slug ) );
		}

		$integrations = Integrations_Loader::get_instance()->get_integrations();
		if ( ! isset( $integrations[ $slug ] ) ) {
			return new WP_Error( 'ntfm_test_invalid_integration', __( 'This connection type is not available.', 'notification-master' ), array( 'status' => 400 ) );
		}
		$integration = $integrations[ $slug ];

		$trigger = Triggers_Loader::get_instance()->get_trigger( isset( $args['trigger'] ) ? (string) $args['trigger'] : '' );
		if ( ! $trigger ) {
			return new WP_Error( 'ntfm_test_invalid_trigger', __( 'Choose a trigger before sending a test.', 'notification-master' ), array( 'status' => 400 ) );
		}

		if ( '' !== $recipient && ! is_email( $recipient ) ) {
			return new WP_Error( 'ntfm_test_invalid_recipient', __( 'Enter a valid email address.', 'notification-master' ), array( 'status' => 400 ) );
		}

		// "Cannot test" answers are results for the UI, not request errors.
		$can_test = $integration->can_test();
		if ( is_wp_error( $can_test ) ) {
			return self::shape( false, $can_test->get_error_message(), null, null, array(), $can_test->get_error_code() );
		}

		$sample   = Sample_Data::fill( $trigger );
		$settings = $integration->prepare_test_attributes( $settings, $recipient, $sample );
		if ( is_wp_error( $settings ) ) {
			return self::shape( false, $settings->get_error_message(), null, null, array(), $settings->get_error_code() );
		}

		$settings = Merge_Tag_Resolver::resolve( $sample, $settings );
		$name     = isset( $args['notification_name'] ) ? sanitize_text_field( (string) $args['notification_name'] ) : '';
		if ( '' === $name ) {
			$name = $id ? get_the_title( $id ) : __( 'Test', 'notification-master' );
		}

		return self::run( $integration, $settings, new Test_Trigger( $sample ), $id, $name );
	}

	/**
	 * Run the integration once and collect what it logged.
	 *
	 * @since 2.0.0
	 *
	 * @param \Notification_Master\Abstracts\Integration $integration Integration.
	 * @param array                                      $settings    Resolved settings.
	 * @param Test_Trigger                               $trigger     Proxy trigger.
	 * @param int                                        $id          Notification ID.
	 * @param string                                     $name        Notification name.
	 *
	 * @return array|WP_Error
	 */
	private static function run( $integration, $settings, $trigger, $id, $name ) {
		/** This action is documented in includes/connections/class-process.php */
		do_action( 'notification_master_before_process_connection', $settings, $trigger, $id );
		$integration->notification_name = $name;
		$integration->notification_id   = $id;

		$schema = $integration->get_attributes_schema();
		if ( ! empty( $schema['properties'] ) && ! $integration->validate_attributes() ) {
			/** This action is documented in includes/connections/class-process.php */
			do_action( 'notification_master_after_process_connection' );
			return new WP_Error( 'ntfm_test_invalid_settings', __( 'Some required fields are empty or not valid. Fill them in, then send the test again.', 'notification-master' ), array( 'status' => 400 ) );
		}

		$mail_error = null;
		$on_mail    = function ( $error ) use ( &$mail_error ) {
			$mail_error = is_wp_error( $error ) ? $error->get_error_message() : null;
		};
		add_action( 'wp_mail_failed', $on_mail );

		$logger    = Notification_Master()->notification_logger;
		$exception = null;
		$logger->start_capture( array( 'test' => true ) );

		try {
			$integration->process();
		} catch ( \Throwable $e ) {
			$exception = $e->getMessage();
		}

		$entries = $logger->stop_capture();
		remove_action( 'wp_mail_failed', $on_mail );
		/** This action is documented in includes/connections/class-process.php */
		do_action( 'notification_master_after_process_connection' );

		return self::result( $entries, $mail_error, $exception );
	}

	/**
	 * Turn captured log entries into the API result.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $entries    Captured entries.
	 * @param string|null $mail_error wp_mail failure text.
	 * @param string|null $exception  Exception text.
	 *
	 * @return array
	 */
	private static function result( $entries, $mail_error, $exception ) {
		$log_ids = array_map( 'intval', wp_list_pluck( $entries, 'id' ) );

		if ( null !== $exception ) {
			return self::shape( false, __( 'The test stopped with an error.', 'notification-master' ), $exception, null, $log_ids, 'ntfm_test_exception' );
		}

		if ( empty( $entries ) ) {
			return self::shape( false, __( 'Nothing was sent. The connection did not report a result; check the Debug log.', 'notification-master' ), null, null, $log_ids, 'ntfm_test_no_result' );
		}

		// Report the first failure, else the first entry.
		$entry = $entries[0];
		foreach ( $entries as $item ) {
			if ( 'success' !== $item['status'] ) {
				$entry = $item;
				break;
			}
		}

		$content = is_array( $entry['content'] ) ? $entry['content'] : array();
		$code    = Log_Formatter::get_http_code( $content );
		$reason  = Log_Formatter::get_reason( $content );
		$message = isset( $content['message'] ) && is_scalar( $content['message'] ) ? (string) $content['message'] : '';
		$success = 'success' === $entry['status'] && ( null === $code || $code < 400 );

		if ( 'success' === $entry['status'] && ! $success ) {
			/* translators: %d: HTTP status code */
			$message = sprintf( __( 'The server answered with HTTP %d.', 'notification-master' ), $code );
		}

		if ( ! $success && null === $reason && $mail_error ) {
			$reason = $mail_error;
		}

		return self::shape( $success, '' !== $message ? $message : __( 'Test sent.', 'notification-master' ), $reason, $code, $log_ids );
	}

	/**
	 * Build the response array.
	 *
	 * @since 2.0.0
	 *
	 * @param bool        $success Success.
	 * @param string      $message Message.
	 * @param string|null $reason  Remote reason.
	 * @param int|null    $code    HTTP code.
	 * @param int[]       $log_ids Log IDs.
	 * @param string|null $error   Machine code when nothing could be sent.
	 *
	 * @return array
	 */
	private static function shape( $success, $message, $reason, $code, $log_ids, $error = null ) {
		return array(
			'success'   => (bool) $success,
			'message'   => $message,
			'reason'    => $reason,
			'http_code' => $code,
			'log_ids'   => $log_ids,
			'code'      => $error,
		);
	}
}
