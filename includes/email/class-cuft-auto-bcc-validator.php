<?php
/**
 * Auto-BCC Configuration Validator
 *
 * Validates and sanitizes Auto-BCC configuration data.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Auto_BCC_Validator {

	/**
	 * Valid email type identifiers
	 *
	 * @var array
	 */
	private static $valid_email_types = array(
		'form_submission',
		'user_registration',
		'password_reset',
		'comment_notification',
		'admin_notification',
		'other',
	);

	/**
	 * Validate entire configuration array
	 *
	 * @param array $config Configuration to validate
	 * @return array|WP_Error Valid config or error object
	 */
	public static function validate_config( $config ) {
		$errors = array();

		// Validate enabled field
		if ( ! isset( $config['enabled'] ) ) {
			$errors[] = 'enabled field is required';
		}

		// Validate BCC email (required when enabled is true)
		if ( ! empty( $config['enabled'] ) ) {
			if ( empty( $config['bcc_email'] ) ) {
				$errors[] = 'Email address is required when Auto-BCC is enabled';
			} else {
				$email_valid = self::validate_email( $config['bcc_email'] );
				if ( is_wp_error( $email_valid ) ) {
					$errors[] = $email_valid->get_error_message();
				}
			}
		}

		// Validate selected email types
		if ( isset( $config['selected_email_types'] ) && ! empty( $config['selected_email_types'] ) ) {
			if ( ! is_array( $config['selected_email_types'] ) ) {
				$errors[] = 'selected_email_types must be an array';
			}
		}

		// Validate rate limit threshold
		if ( isset( $config['rate_limit_threshold'] ) ) {
			$threshold_valid = self::validate_rate_limit( $config['rate_limit_threshold'] );
			if ( is_wp_error( $threshold_valid ) ) {
				$errors[] = $threshold_valid->get_error_message();
			}
		}

		// Validate rate limit action
		if ( isset( $config['rate_limit_action'] ) ) {
			if ( ! in_array( $config['rate_limit_action'], array( 'log_only', 'pause_until_next_period' ), true ) ) {
				$errors[] = 'Invalid rate limit action. Must be log_only or pause_until_next_period';
			}
		}

		// Return errors if any
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_config', implode( '; ', $errors ), array( 'errors' => $errors ) );
		}

		// Sanitize and return valid config
		return self::sanitize_config( $config );
	}

	/**
	 * Validate email address
	 *
	 * @param string $email Email to validate
	 * @return true|WP_Error
	 */
	public static function validate_email( $email ) {
		// Sanitize first
		$email = sanitize_email( $email );

		// Check if valid email
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				'Invalid email address format. Please enter a valid email.'
			);
		}

		// Check max length (RFC 5321)
		if ( strlen( $email ) > 254 ) {
			return new WP_Error(
				'invalid_email',
				'Email address is too long (maximum 254 characters)'
			);
		}

		return true;
	}

	/**
	 * Validate email types array
	 *
	 * @param array $types Email types to validate
	 * @return array Filtered valid types
	 */
	public static function validate_email_types( $types ) {
		if ( ! is_array( $types ) ) {
			return array();
		}

		// Filter to valid types only
		$valid_types = array_intersect( $types, self::$valid_email_types );

		// Remove duplicates
		$valid_types = array_unique( $valid_types );

		return $valid_types;
	}

	/**
	 * Validate rate limit threshold
	 *
	 * @param int $threshold Rate limit threshold
	 * @return true|WP_Error
	 */
	public static function validate_rate_limit( $threshold ) {
		$threshold = absint( $threshold );

		// Check range (0-10,000)
		if ( $threshold < 0 || $threshold > 10000 ) {
			return new WP_Error(
				'invalid_rate_limit',
				'Rate limit must be between 0 and 10,000.'
			);
		}

		return true;
	}

	/**
	 * Sanitize configuration
	 *
	 * @param array $config Raw configuration
	 * @return array Sanitized configuration
	 */
	public static function sanitize_config( $config ) {
		$sanitized = array();

		// Sanitize enabled (boolean)
		$sanitized['enabled'] = ! empty( $config['enabled'] );

		// Sanitize BCC email
		$sanitized['bcc_email'] = isset( $config['bcc_email'] ) ? sanitize_email( $config['bcc_email'] ) : '';

		// Sanitize selected email types
		$sanitized['selected_email_types'] = isset( $config['selected_email_types'] )
			? self::validate_email_types( $config['selected_email_types'] )
			: array();

		// Sanitize rate limit threshold
		$sanitized['rate_limit_threshold'] = isset( $config['rate_limit_threshold'] )
			? absint( $config['rate_limit_threshold'] )
			: 100;

		// Sanitize rate limit action
		$action = isset( $config['rate_limit_action'] ) ? sanitize_text_field( $config['rate_limit_action'] ) : 'log_only';
		if ( ! in_array( $action, array( 'log_only', 'pause_until_next_period' ), true ) ) {
			$action = 'log_only';
		}
		$sanitized['rate_limit_action'] = $action;

		// Preserve last_modified fields (automatically set by config model)
		if ( isset( $config['last_modified'] ) ) {
			$sanitized['last_modified'] = absint( $config['last_modified'] );
		}
		if ( isset( $config['last_modified_by'] ) ) {
			$sanitized['last_modified_by'] = absint( $config['last_modified_by'] );
		}

		return $sanitized;
	}

	/**
	 * Get list of valid email types
	 *
	 * @return array Valid email type identifiers
	 */
	public static function get_valid_email_types() {
		return self::$valid_email_types;
	}
}
