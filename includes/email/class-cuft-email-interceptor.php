<?php
/**
 * Email Interceptor
 *
 * Intercepts WordPress emails via wp_mail filter and adds BCC header.
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
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CUFT Auto-BCC: Skipping BCC (address already a recipient)' );
				}
				return $args;
			}

			// Check rate limit
			$threshold = CUFT_Auto_BCC_Config::get_rate_limit_threshold();
			$action = CUFT_Auto_BCC_Config::get_rate_limit_action();

			if ( ! CUFT_BCC_Rate_Limiter::check_rate_limit( $threshold ) ) {
				// Rate limit exceeded
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'CUFT Auto-BCC: Rate limit exceeded (%d emails/hour). Action: %s',
						$threshold,
						$action
					) );
				}

				// If action is pause, skip BCC
				if ( 'pause_until_next_period' === $action ) {
					return $args;
				}

				// If action is log_only, continue with BCC
			}

			// Add BCC header
			$args = $this->add_bcc_header( $args, $bcc_email );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'CUFT Auto-BCC: BCC added to %s email (subject: %s)',
					$email_type,
					isset( $args['subject'] ) ? $args['subject'] : 'unknown'
				) );
			}

		} catch ( Exception $e ) {
			// Graceful degradation: Log error but don't block primary email
			error_log( 'CUFT Auto-BCC Error: ' . $e->getMessage() );
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

		// Check CC in headers
		if ( isset( $args['headers'] ) ) {
			$headers = is_array( $args['headers'] ) ? $args['headers'] : array( $args['headers'] );

			foreach ( $headers as $header ) {
				$header_lower = strtolower( $header );

				// Check for CC header
				if ( strpos( $header_lower, 'cc:' ) === 0 ) {
					$cc_email = strtolower( trim( substr( $header, 3 ) ) );
					if ( $cc_email === $bcc_email_lower ) {
						return true;
					}
				}

				// Also check if BCC already exists (shouldn't happen, but safety check)
				if ( strpos( $header_lower, 'bcc:' ) === 0 ) {
					$existing_bcc = strtolower( trim( substr( $header, 4 ) ) );
					if ( $existing_bcc === $bcc_email_lower ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
