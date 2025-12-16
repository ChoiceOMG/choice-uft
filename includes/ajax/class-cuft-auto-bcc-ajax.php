<?php
/**
 * Auto-BCC AJAX Handlers
 *
 * Handles AJAX requests for Auto-BCC settings and test email.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Auto_BCC_Ajax {

	/**
	 * Initialize AJAX handlers
	 */
	public function __construct() {
		add_action( 'wp_ajax_cuft_auto_bcc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_cuft_auto_bcc_send_test_email', array( $this, 'send_test_email' ) );
	}

	/**
	 * AJAX handler: Save Auto-BCC settings
	 *
	 * Contract: /specs/010-auto-bcc-everyting/contracts/admin-ajax-save-settings.md
	 */
	public function save_settings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_auto_bcc_save_settings' ) ) {
			wp_send_json_error( array(
				'message' => 'Security verification failed',
			), 403 );
		}

		// Check capability
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array(
				'message' => 'Insufficient permissions',
			), 403 );
		}

		// Get configuration from POST data
		$config = array(
			'enabled' => isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false,
			'bcc_email' => isset( $_POST['bcc_email'] ) ? sanitize_email( $_POST['bcc_email'] ) : '',
			'selected_email_types' => isset( $_POST['selected_email_types'] ) ? (array) $_POST['selected_email_types'] : array(),
			'rate_limit_threshold' => isset( $_POST['rate_limit_threshold'] ) ? absint( $_POST['rate_limit_threshold'] ) : 100,
			'rate_limit_action' => isset( $_POST['rate_limit_action'] ) ? sanitize_text_field( $_POST['rate_limit_action'] ) : 'log_only',
		);

		// Get manager instance
		$manager = CUFT_Auto_BCC_Manager::get_instance();

		// Save configuration
		$result = $manager->save_config( $config );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'errors' => $result->get_error_data( 'invalid_config' ),
			) );
		}

		// Validate WordPress mail function (non-blocking warnings)
		$warnings = $manager->validate_mail_function();

		// Success response
		wp_send_json_success( array(
			'message' => 'Auto-BCC settings saved successfully',
			'config' => $result,
			'warnings' => $warnings,
		) );
	}

	/**
	 * AJAX handler: Send test email
	 *
	 * Contract: /specs/010-auto-bcc-everyting/contracts/admin-ajax-send-test-email.md
	 */
	public function send_test_email() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_auto_bcc_send_test' ) ) {
			wp_send_json_error( array(
				'message' => 'Security verification failed',
			), 403 );
		}

		// Check capability
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array(
				'message' => 'Insufficient permissions',
			), 403 );
		}

		// Get BCC email address
		$bcc_email = isset( $_POST['bcc_email'] ) ? sanitize_email( $_POST['bcc_email'] ) : '';

		if ( empty( $bcc_email ) ) {
			wp_send_json_error( array(
				'message' => 'Email address is required',
				'error' => 'missing_email',
			) );
		}

		// Get manager instance
		$manager = CUFT_Auto_BCC_Manager::get_instance();

		// Send test email
		$result = $manager->send_test_email( $bcc_email );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => $result['message'],
				'subject' => $result['subject'],
				'sent_at' => $result['sent_at'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result['message'],
				'error' => isset( $result['error'] ) ? $result['error'] : 'unknown_error',
			) );
		}
	}
}
