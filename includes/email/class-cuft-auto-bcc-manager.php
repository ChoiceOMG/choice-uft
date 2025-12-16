<?php
/**
 * Auto-BCC Manager
 *
 * Main orchestrator for Auto-BCC feature. Coordinates all services.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Auto_BCC_Manager {

	/**
	 * Singleton instance
	 *
	 * @var CUFT_Auto_BCC_Manager
	 */
	private static $instance = null;

	/**
	 * Email interceptor instance
	 *
	 * @var CUFT_Email_Interceptor
	 */
	private $interceptor;

	/**
	 * Get singleton instance
	 *
	 * @return CUFT_Auto_BCC_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		// Constructor intentionally left empty
		// Call init() explicitly after instantiation
	}

	/**
	 * Initialize Auto-BCC feature
	 *
	 * Sets up email interceptor and all related services.
	 */
	public function init() {
		// Initialize email interceptor
		$this->interceptor = new CUFT_Email_Interceptor();
		$this->interceptor->init();
	}

	/**
	 * Get current configuration
	 *
	 * @return array Configuration array
	 */
	public function get_config() {
		return CUFT_Auto_BCC_Config::get_config();
	}

	/**
	 * Save configuration with validation
	 *
	 * @param array $config Configuration array to save
	 * @return array|WP_Error Saved config or error object
	 */
	public function save_config( $config ) {
		// Validate configuration
		$validated = CUFT_Auto_BCC_Validator::validate_config( $config );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		// Save configuration
		$saved = CUFT_Auto_BCC_Config::save_config( $validated );

		if ( ! $saved ) {
			return new WP_Error(
				'save_failed',
				'Failed to save Auto-BCC configuration'
			);
		}

		return $validated;
	}

	/**
	 * Send test email to BCC address
	 *
	 * @param string $bcc_email Email address to send test to
	 * @return array Success/failure result with message
	 */
	public function send_test_email( $bcc_email ) {
		// Validate email address
		$email_valid = CUFT_Auto_BCC_Validator::validate_email( $bcc_email );

		if ( is_wp_error( $email_valid ) ) {
			return array(
				'success' => false,
				'message' => $email_valid->get_error_message(),
				'error' => 'invalid_email',
			);
		}

		// Prepare test email
		$subject = '[CUFT Test Email] Auto-BCC Feature Test';
		$message = "This is a test email from the Choice Universal Form Tracker Auto-BCC feature.\n\n";
		$message .= "If you received this email, the Auto-BCC functionality is working correctly.\n\n";
		$message .= "Test sent at: " . current_time( 'mysql' ) . "\n";
		$message .= "WordPress site: " . get_bloginfo( 'name' ) . " (" . get_bloginfo( 'url' ) . ")\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Send email
		$sent = wp_mail( $bcc_email, $subject, $message, $headers );

		if ( ! $sent ) {
			return array(
				'success' => false,
				'message' => 'Failed to send test email',
				'error' => 'wp_mail() returned false',
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Test email sent successfully to %s', $bcc_email ),
			'subject' => $subject,
			'sent_at' => time(),
		);
	}

	/**
	 * Validate WordPress mail function availability
	 *
	 * @return array Array of warning messages (empty if no warnings)
	 */
	public function validate_mail_function() {
		$warnings = array();

		// Check if wp_mail function exists
		if ( ! function_exists( 'wp_mail' ) ) {
			$warnings[] = 'WordPress mail function is not available.';
		}

		// Check if PHPMailer class exists
		if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) && ! class_exists( 'PHPMailer' ) ) {
			$warnings[] = 'PHPMailer class not found. Email sending may fail.';
		}

		// Check if SMTP plugin is configured (optional)
		$smtp_configured = defined( 'WPMS_ON' ) || defined( 'POSTMAN_EMAIL_LOG_ENABLED' );
		if ( ! $smtp_configured ) {
			$warnings[] = 'No SMTP plugin detected. Default wp_mail() may not work on all hosts.';
		}

		return $warnings;
	}

	/**
	 * Get rate limit status
	 *
	 * @return array Rate limit information
	 */
	public function get_rate_limit_status() {
		$threshold = CUFT_Auto_BCC_Config::get_rate_limit_threshold();
		$current_count = CUFT_BCC_Rate_Limiter::get_current_count();

		return array(
			'threshold' => $threshold,
			'current_count' => $current_count,
			'remaining' => max( 0, $threshold - $current_count ),
			'percentage' => ( $threshold > 0 ) ? round( ( $current_count / $threshold ) * 100 ) : 0,
		);
	}

	/**
	 * Reset rate limit counter (for testing/debugging)
	 *
	 * @return bool True on success
	 */
	public function reset_rate_limit() {
		return CUFT_BCC_Rate_Limiter::reset_count();
	}
}
