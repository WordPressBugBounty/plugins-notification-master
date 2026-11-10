<?php
/**
 * Review Notice for Notification Master.
 *
 * @package notification-master
 */

namespace Notification_Master\Admin;

/**
 * Review notice class.
 */
class Review_Notice {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the notice.
	 */
	private function init() {
		// Show notice on admin pages.
		add_action( 'admin_notices', array( $this, 'show_review_notice' ) );

		// Handle AJAX dismiss.
		add_action( 'wp_ajax_ntfm_dismiss_review_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	/**
	 * Check if notice is dismissed.
	 *
	 * @return bool
	 */
	private function is_dismissed() {
		$dismissed_time = get_user_meta( get_current_user_id(), 'ntfm_review_notice_dismissed', true );
		// If never dismissed, show it.
		if ( empty( $dismissed_time ) ) {
			return false;
		}

		// If dismissed, check if 7 days have passed (for "Maybe Later").
		$days_passed = ( time() - intval( $dismissed_time ) ) / DAY_IN_SECONDS;

		return $days_passed < 7;
	}

	/**
	 * Check if permanently dismissed.
	 *
	 * @return bool
	 */
	private function is_permanently_dismissed() {
		return (bool) get_user_meta( get_current_user_id(), 'ntfm_review_notice_permanent_dismissed', true );
	}

	/**
	 * Check if user has been using plugin for at least 7 days.
	 *
	 * @return bool
	 */
	private function is_eligible() {
		$user_id    = get_current_user_id();
		$first_seen = get_user_meta( $user_id, 'ntfm_review_notice_first_seen', true );

		// If never set, set it now and don't show notice yet.
		if ( empty( $first_seen ) ) {
			update_user_meta( $user_id, 'ntfm_review_notice_first_seen', time() );
			return false;
		}

		// Check if 7 days have passed.
		$days_passed = ( time() - intval( $first_seen ) ) / DAY_IN_SECONDS;

		return $days_passed >= 7;
	}

	/**
	 * Show review notice.
	 */
	public function show_review_notice() {
		// Don't show if permanently dismissed.
		if ( $this->is_permanently_dismissed() ) {
			return;
		}

		// Don't show if user hasn't been using plugin for 7 days yet.
		if ( ! $this->is_eligible() ) {
			return;
		}

		// Don't show if temporarily dismissed.
		if ( $this->is_dismissed() ) {
			return;
		}

		$logo_url = NOTIFICATION_MASTER_URL . 'assets/logo.png';

		?>
		<div class="notice notice-info is-dismissible ntfm-review-notice" data-dismissible="ntfm-review-notice">
			<div class="ntfm-review-notice__content">
				<div class="ntfm-review-notice__logo">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Notification Master', 'notification-master' ); ?>" />
				</div>
				<div class="ntfm-review-notice__text">
					<h3 style="margin: 0 0 0.5rem 0; font-size: 16px; font-weight: 600;">
						<?php esc_html_e( 'Enjoying Notification Master?', 'notification-master' ); ?>
					</h3>
					<p style="margin: 0;">
						<?php
						echo wp_kses_post(
							__( 'If you like this plugin, please leave me a <strong>★★★★★</strong> rating to support continued development. Thanks a bunch!', 'notification-master' )
						);
						?>
					</p>
				</div>
				<div class="ntfm-review-notice__actions">
					<a href="https://wordpress.org/support/plugin/notification-master/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="button button-primary ntfm-review-notice__rate-button">
						<?php esc_html_e( 'Rate Plugin', 'notification-master' ); ?>
					</a>
					<button type="button" class="button button-secondary ntfm-review-notice__maybe-later" data-action="maybe-later">
						<?php esc_html_e( 'Maybe Later', 'notification-master' ); ?>
					</button>
					<button type="button" class="button button-link ntfm-review-notice__already-did" data-action="already-did">
						<?php esc_html_e( 'I already did this', 'notification-master' ); ?>
					</button>
				</div>
			</div>
		</div>
		<style>
			.ntfm-review-notice {
				border-left-color: #e67a18 !important;
			}
			.ntfm-review-notice__content {
				display: flex;
				align-items: center;
				gap: 1rem;
				padding: 0.5rem 0;
			}
			.ntfm-review-notice__logo {
				flex-shrink: 0;
			}
			.ntfm-review-notice__logo img {
				width: 50px;
				height: 50px;
				border-radius: 8px;
			}
			.ntfm-review-notice__text {
				flex: 1;
			}
			.ntfm-review-notice__text strong {
				color: #e67a18;
				font-size: 14px;
				letter-spacing: 1px;
			}
			.ntfm-review-notice__actions {
				flex-shrink: 0;
				display: flex;
				gap: 0.5rem;
			}

			@media (max-width: 782px) {
				.ntfm-review-notice__content {
					flex-direction: column;
					align-items: flex-start;
					gap: 0.75rem;
				}
				.ntfm-review-notice__actions {
					width: 100%;
					flex-direction: column;
				}
				.ntfm-review-notice__actions .button {
					width: 100%;
					text-align: center;
				}
			}
		</style>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Handle "Maybe Later" button click - temporary dismiss (7 days)
			$('.ntfm-review-notice__maybe-later').on('click', function(e) {
				e.preventDefault();
				var $notice = $(this).closest('.ntfm-review-notice');
				
				$.post(ajaxurl, {
					action: 'ntfm_dismiss_review_notice',
					type: 'temporary',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ntfm_dismiss_notice' ) ); ?>'
				}, function() {
					$notice.fadeOut();
				});
			});

			// Handle "I already did this" button click - permanent dismiss
			$('.ntfm-review-notice__already-did').on('click', function(e) {
				e.preventDefault();
				var $notice = $(this).closest('.ntfm-review-notice');
				
				$.post(ajaxurl, {
					action: 'ntfm_dismiss_review_notice',
					type: 'permanent',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ntfm_dismiss_notice' ) ); ?>'
				}, function() {
					$notice.fadeOut();
				});
			});

			// Handle temporary dismiss (X button) - temporary dismiss (7 days)
			$(document).on('click', '.ntfm-review-notice .notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'ntfm_dismiss_review_notice',
					type: 'temporary',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ntfm_dismiss_notice' ) ); ?>'
				});
			});

			// Handle "Rate Plugin" button click - temporary dismiss (7 days)
			$('.ntfm-review-notice__rate-button').on('click', function() {
				$.post(ajaxurl, {
					action: 'ntfm_dismiss_review_notice',
					type: 'temporary',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ntfm_dismiss_notice' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX dismiss.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'ntfm_dismiss_notice', 'nonce' );

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'permanent';

		if ( 'permanent' === $type ) {
			// Permanently dismiss.
			update_user_meta( get_current_user_id(), 'ntfm_review_notice_permanent_dismissed', true );
		} else {
			// Temporarily dismiss (show again in 7 days).
			update_user_meta( get_current_user_id(), 'ntfm_review_notice_dismissed', time() );
		}

		wp_send_json_success();
	}
}
