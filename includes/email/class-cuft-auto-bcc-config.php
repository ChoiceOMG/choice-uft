<?php
/**
 * Auto-BCC Configuration Model
 *
 * Manages Auto-BCC feature configuration using WordPress Options API.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Auto_BCC_Config {

	/**
	 * Option name for configuration storage
	 */
	const OPTION_NAME = 'cuft_auto_bcc_config';

	/**
	 * Default rate limit threshold (emails per hour)
	 */
	const DEFAULT_RATE_LIMIT = 100;

	/**
	 * Maximum rate limit threshold (emails per hour)
	 */
	const MAX_RATE_LIMIT = 10000;

	/**
	 * Get default configuration
	 *
	 * @return array Default configuration values
	 */
	public static function get_defaults() {
		return array(
			'enabled'               => false,
			'bcc_email'             => '',
			'selected_email_types'  => array(),
			'rate_limit_threshold'  => self::DEFAULT_RATE_LIMIT,
			'rate_limit_action'     => 'log_only',
			'last_modified'         => 0,
			'last_modified_by'      => 0,
		);
	}

	/**
	 * Get configuration from WordPress options
	 *
	 * Validates configuration values on load and corrects invalid data.
	 *
	 * @return array Configuration array
	 */
	public static function get_config() {
		$config = get_option( self::OPTION_NAME, self::get_defaults() );

		// Merge with defaults to ensure all keys exist
		$config = wp_parse_args( $config, self::get_defaults() );

		// Validate and sanitize configuration
		$config = self::validate_config( $config );

		return $config;
	}

	/**
	 * Validate and sanitize configuration values
	 *
	 * @param array $config Configuration to validate
	 * @return array Validated configuration
	 */
	private static function validate_config( $config ) {
		// Validate enabled flag
		$config['enabled'] = (bool) $config['enabled'];

		// Validate BCC email address
		if ( ! empty( $config['bcc_email'] ) ) {
			$config['bcc_email'] = sanitize_email( $config['bcc_email'] );
			if ( ! is_email( $config['bcc_email'] ) ) {
				$config['bcc_email'] = '';
				$config['enabled'] = false; // Disable if email is invalid
			}
		}

		// Validate selected email types (must be array)
		if ( ! is_array( $config['selected_email_types'] ) ) {
			$config['selected_email_types'] = array();
		}

		// Validate rate limit threshold (must be non-negative integer)
		$config['rate_limit_threshold'] = absint( $config['rate_limit_threshold'] );
		if ( $config['rate_limit_threshold'] < 0 || $config['rate_limit_threshold'] > self::MAX_RATE_LIMIT ) {
			$config['rate_limit_threshold'] = self::DEFAULT_RATE_LIMIT; // Reset to default if out of range
		}

		// Validate rate limit action
		$valid_actions = array( 'log_only', 'pause_until_next_period' );
		if ( ! in_array( $config['rate_limit_action'], $valid_actions, true ) ) {
			$config['rate_limit_action'] = 'log_only';
		}

		// Validate timestamps
		$config['last_modified'] = absint( $config['last_modified'] );
		$config['last_modified_by'] = absint( $config['last_modified_by'] );

		return $config;
	}

	/**
	 * Save configuration to WordPress options
	 *
	 * Automatically sets last_modified timestamp and last_modified_by user ID.
	 *
	 * @param array $config Configuration array to save
	 * @return bool True on success, false on failure
	 */
	public static function save_config( $config ) {
		// Merge with existing config to preserve fields not being updated
		$existing = self::get_config();
		$config = wp_parse_args( $config, $existing );

		// Automatically set last_modified timestamp and user
		$config['last_modified'] = time();
		$config['last_modified_by'] = get_current_user_id();

		// Save to WordPress options
		return update_option( self::OPTION_NAME, $config );
	}

	/**
	 * Delete configuration from WordPress options
	 *
	 * @return bool True on success, false on failure
	 */
	public static function delete_config() {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Check if feature is enabled
	 *
	 * @return bool True if enabled, false otherwise
	 */
	public static function is_enabled() {
		$config = self::get_config();
		return ! empty( $config['enabled'] );
	}

	/**
	 * Get BCC email address
	 *
	 * @return string BCC email address (empty if not set)
	 */
	public static function get_bcc_email() {
		$config = self::get_config();
		return isset( $config['bcc_email'] ) ? $config['bcc_email'] : '';
	}

	/**
	 * Get selected email types
	 *
	 * @return array Selected email type identifiers
	 */
	public static function get_selected_email_types() {
		$config = self::get_config();
		return isset( $config['selected_email_types'] ) ? $config['selected_email_types'] : array();
	}

	/**
	 * Get rate limit threshold
	 *
	 * @return int Rate limit threshold (0 = unlimited)
	 */
	public static function get_rate_limit_threshold() {
		$config = self::get_config();
		return isset( $config['rate_limit_threshold'] ) ? absint( $config['rate_limit_threshold'] ) : self::DEFAULT_RATE_LIMIT;
	}

	/**
	 * Get rate limit action
	 *
	 * @return string Rate limit action (log_only or pause_until_next_period)
	 */
	public static function get_rate_limit_action() {
		$config = self::get_config();
		$action = isset( $config['rate_limit_action'] ) ? $config['rate_limit_action'] : 'log_only';

		// Validate action
		if ( ! in_array( $action, array( 'log_only', 'pause_until_next_period' ), true ) ) {
			$action = 'log_only';
		}

		return $action;
	}

	/**
	 * Check if an email type is selected
	 *
	 * @param string $type Email type identifier
	 * @return bool True if type is selected, false otherwise
	 */
	public static function is_email_type_selected( $type ) {
		$selected_types = self::get_selected_email_types();
		return in_array( $type, $selected_types, true );
	}
}
