<?php
/**
 * Email Interceptor
 *
 * Intercepts WordPress emails via `wp_mail` filter and intelligently adds BCC headers
 * to form submission notifications. Includes duplicate detection, rate limiting, and
 * email type filtering.
 *
 * ## Features
 *
 * - **Smart Detection**: Only BCCs form submission emails (configurable types)
 * - **Duplicate Prevention**: Skips BCC if address is already in TO/CC
 * - **Rate Limiting**: Enforces hourly limits with configurable actions
 * - **Graceful Degradation**: Logs errors but never blocks primary email
 *
 * ## Usage
 *
 * ```php
 * // Initialize email interceptor
 * $interceptor = new CUFT_Email_Interceptor();
 * $interceptor->init();
 *
 * // The interceptor automatically processes all emails sent via wp_mail()
 * // No additional code needed - just configure via CUFT_Auto_BCC_Config
 * ```
 *
 * ## Filter Priority
 *
 * Runs at priority 10 on `wp_mail` filter:
 * - **Before SMTP plugins** (usually priority 20+)
 * - **Before tracking injector** (priority 15)
 *
 * This ensures BCC headers are added before SMTP plugins process the email.
 *
 * ## Configuration
 *
 * Controlled via CUFT_Auto_BCC_Config:
 * - `enabled`: Feature on/off
 * - `bcc_email`: Target BCC address
 * - `selected_email_types`: Array of email types to BCC
 * - `rate_limit_threshold`: Max emails per hour
 * - `rate_limit_action`: 'pause_until_next_period' or 'log_only'
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Email_Interceptor {

	/**
	 * Initialize email interceptor
	 *
	 * Registers wp_mail filter at priority 10 (before SMTP plugins at 20+).
	 */
	public function init() {
		add_filter( 'wp_mail', array( $this, 'intercept_email' ), 10, 1 );
	}

	/**
	 * Intercept email and add BCC header if conditions met
	 *
	 * @param array $args WordPress email arguments (to, subject, message, headers, attachments)
	 * @return array Modified email arguments
	 */
	public function intercept_email( $args ) {
		try {
			// Check if feature is enabled
			if ( ! CUFT_Auto_BCC_Config::is_enabled() ) {
				return $args;
			}

			// Get BCC email address
			$bcc_email = CUFT_Auto_BCC_Config::get_bcc_email();
			if ( empty( $bcc_email ) ) {
				return $args;
			}

			// Detect email type
			$email_type = CUFT_Email_Type_Detector::detect_type( $args );

			// Check if this email type is selected for BCC
			if ( ! CUFT_Auto_BCC_Config::is_email_type_selected( $email_type ) ) {
				return $args;
			}

			// Check for duplicate (BCC address already in TO/CC)
			if ( $this->is_bcc_duplicate( $bcc_email, $args ) ) {
				self::log_debug( 'Skipping BCC (address already a recipient)' );
				return $args;
			}

			// Check rate limit
			$threshold = CUFT_Auto_BCC_Config::get_rate_limit_threshold();
			$action = CUFT_Auto_BCC_Config::get_rate_limit_action();

			if ( ! CUFT_BCC_Rate_Limiter::check_rate_limit( $threshold ) ) {
				// Rate limit exceeded
				self::log_debug( sprintf(
					'Rate limit exceeded (%d emails/hour). Action: %s',
					$threshold,
					$action
				) );

				// If action is pause, skip BCC
				if ( 'pause_until_next_period' === $action ) {
					return $args;
				}

				// If action is log_only, continue with BCC
			}

			// Add BCC header
			$args = $this->add_bcc_header( $args, $bcc_email );

			self::log_debug( sprintf(
				'BCC added to %s email (subject: %s)',
				$email_type,
				isset( $args['subject'] ) ? $args['subject'] : 'unknown'
			) );

		} catch ( Exception $e ) {
			// Graceful degradation: Log error but don't block primary email
			self::log_error( 'Email interception failed: ' . $e->getMessage() );
		}

		return $args;
	}

	/**
	 * Add BCC header to email arguments
	 *
	 * @param array  $args      Email arguments
	 * @param string $bcc_email BCC email address
	 * @return array Modified email arguments
	 */
	private function add_bcc_header( $args, $bcc_email ) {
		$bcc_header = 'Bcc: ' . $bcc_email;

		// Ensure headers is an array
		if ( ! isset( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		if ( is_string( $args['headers'] ) ) {
			$args['headers'] = array( $args['headers'] );
		}

		// Add BCC header
		$args['headers'][] = $bcc_header;

		return $args;
	}

	/**
	 * Check if BCC address is already a recipient (TO or CC)
	 *
	 * @param string $bcc_email BCC email address
	 * @param array  $args      Email arguments
	 * @return bool True if BCC is duplicate, false otherwise
	 */
	private function is_bcc_duplicate( $bcc_email, $args ) {
		$bcc_email_lower = strtolower( trim( $bcc_email ) );

		// Check TO address
		if ( isset( $args['to'] ) ) {
			$to_addresses = is_array( $args['to'] ) ? $args['to'] : array( $args['to'] );

			foreach ( $to_addresses as $to ) {
				if ( strtolower( trim( $to ) ) === $bcc_email_lower ) {
					return true;
				}
			}
		}

		// Check CC and BCC in headers
		if ( isset( $args['headers'] ) ) {
			$headers = is_array( $args['headers'] ) ? $args['headers'] : array( $args['headers'] );

			foreach ( $headers as $header ) {
				$header_lower = strtolower( $header );

				// Check for CC header (may contain multiple addresses)
				if ( strpos( $header_lower, 'cc:' ) === 0 ) {
					$cc_addresses = substr( $header, 3 );
					if ( $this->email_list_contains( $cc_addresses, $bcc_email_lower ) ) {
						return true;
					}
				}

				// Also check if BCC already exists (may contain multiple addresses)
				if ( strpos( $header_lower, 'bcc:' ) === 0 ) {
					$bcc_addresses = substr( $header, 4 );
					if ( $this->email_list_contains( $bcc_addresses, $bcc_email_lower ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if email list contains specific address
	 *
	 * Handles comma-separated email lists and extracts addresses from "Name <email>" format.
	 *
	 * @param string $email_list Comma-separated email addresses
	 * @param string $search_email Email to search for (lowercase)
	 * @return bool True if found, false otherwise
	 */
	private function email_list_contains( $email_list, $search_email ) {
		// Split by comma
		$addresses = explode( ',', $email_list );

		foreach ( $addresses as $address ) {
			// Extract email from "Name <email@example.com>" format
			if ( preg_match( '/<([^>]+)>/', $address, $matches ) ) {
				$email = strtolower( trim( $matches[1] ) );
			} else {
				$email = strtolower( trim( $address ) );
			}

			if ( $email === $search_email ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Log error message with consistent formatting
	 *
	 * @param string $message Error message
	 */
	private static function log_error( $message ) {
		error_log( 'CUFT Auto-BCC [ERROR]: ' . $message );
	}

	/**
	 * Log debug message (only if WP_DEBUG enabled)
	 *
	 * @param string $message Debug message
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CUFT Auto-BCC [DEBUG]: ' . $message );
		}
	}
}
