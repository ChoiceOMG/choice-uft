<?php
/**
 * BCC Rate Limiter
 *
 * Implements rate limiting for BCC emails using WordPress Transients with database-level
 * locking to prevent race conditions during concurrent requests.
 *
 * This is separate from CUFT_Rate_Limiter which handles update endpoint rate limiting.
 *
 * ## Usage Example
 *
 * ```php
 * // Check if sending is allowed (threshold = 100 emails/hour)
 * if ( CUFT_BCC_Rate_Limiter::check_rate_limit( 100 ) ) {
 *     // Send email with BCC
 *     wp_mail( $to, $subject, $message, array( 'Bcc: test@example.com' ) );
 * } else {
 *     // Rate limit exceeded - skip BCC or log
 *     error_log( 'BCC rate limit exceeded' );
 * }
 *
 * // Get current count for this hour
 * $count = CUFT_BCC_Rate_Limiter::get_current_count();
 *
 * // Reset counter (for testing)
 * CUFT_BCC_Rate_Limiter::reset_count();
 *
 * // Clean up old transients
 * $deleted = CUFT_BCC_Rate_Limiter::cleanup_old_transients();
 * ```
 *
 * ## How It Works
 *
 * - Counters are stored as hourly transients: `cuft_bcc_rate_limit_YYYY-MM-DD-HH`
 * - Uses MySQL GET_LOCK() to prevent race conditions between concurrent requests
 * - Transients auto-expire after 1 hour
 * - Old transients are cleaned up automatically (once per day)
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_BCC_Rate_Limiter {

	/**
	 * Database lock timeout in seconds
	 */
	const LOCK_TIMEOUT_SECONDS = 2;

	/**
	 * Check if rate limit allows sending BCC
	 *
	 * Uses database-level locking to prevent race conditions during concurrent requests.
	 *
	 * @param int $threshold Rate limit threshold (0 = unlimited)
	 * @return bool True if under limit, false if exceeded
	 */
	public static function check_rate_limit( $threshold ) {
		global $wpdb;

		// 0 threshold means unlimited (no rate limiting)
		if ( $threshold <= 0 ) {
			return true;
		}

		$hour_key = self::get_hour_key();
		$option_name = '_transient_' . $hour_key;

		// Use database lock to prevent race conditions
		$lock_name = 'cuft_rate_limit_lock';

		// Acquire lock
		$lock_result = $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, %d)", $lock_name, self::LOCK_TIMEOUT_SECONDS ) );

		if ( ! $lock_result ) {
			// Could not acquire lock - assume rate limit exceeded for safety
			return false;
		}

		try {
			// Get current count (now protected by lock)
			$current_count = get_transient( $hour_key );

			// First email this hour
			if ( false === $current_count ) {
				set_transient( $hour_key, 1, HOUR_IN_SECONDS );
				return true;
			}

			// Check if limit exceeded
			if ( $current_count >= $threshold ) {
				return false; // Rate limit exceeded
			}

			// Increment counter
			set_transient( $hour_key, $current_count + 1, HOUR_IN_SECONDS );
			return true;

		} finally {
			// Always release lock
			$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
		}
	}

	/**
	 * Get current BCC count for this hour
	 *
	 * @return int Current count (0 if no emails sent this hour)
	 */
	public static function get_current_count() {
		$hour_key = self::get_hour_key();
		$count = get_transient( $hour_key );

		return ( false === $count ) ? 0 : absint( $count );
	}

	/**
	 * Reset rate limit counter (for testing purposes)
	 *
	 * @return bool True on success
	 */
	public static function reset_count() {
		$hour_key = self::get_hour_key();
		return delete_transient( $hour_key );
	}

	/**
	 * Get transient key for current hour
	 *
	 * Format: cuft_bcc_rate_limit_YYYY-MM-DD-HH
	 * Uses UTC to avoid timezone/DST issues.
	 *
	 * @return string Transient key
	 */
	private static function get_hour_key() {
		return 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
	}

	/**
	 * Increment rate limit counter
	 *
	 * @return int New count value
	 */
	public static function increment_count() {
		$hour_key = self::get_hour_key();
		$current_count = get_transient( $hour_key );

		if ( false === $current_count ) {
			set_transient( $hour_key, 1, HOUR_IN_SECONDS );
			return 1;
		}

		$new_count = $current_count + 1;
		set_transient( $hour_key, $new_count, HOUR_IN_SECONDS );
		return $new_count;
	}

	/**
	 * Clean up old rate limiter transients
	 *
	 * Removes rate limiter transients older than 24 hours.
	 * Called automatically during plugin initialization.
	 *
	 * @return int Number of transients deleted
	 */
	public static function cleanup_old_transients() {
		global $wpdb;

		// Find all rate limiter transients
		$pattern = '_transient_cuft_bcc_rate_limit_%';
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		$deleted_count = 0;
		$current_time = current_time( 'timestamp' );

		foreach ( $transients as $transient_name ) {
			// Extract date from transient name (format: _transient_cuft_bcc_rate_limit_YYYY-MM-DD-HH)
			if ( preg_match( '/_transient_cuft_bcc_rate_limit_(\d{4}-\d{2}-\d{2}-\d{2})$/', $transient_name, $matches ) ) {
				$transient_date = $matches[1];
				$transient_timestamp = strtotime( $transient_date . ':00:00' );

				// Delete if older than 24 hours
				if ( ( $current_time - $transient_timestamp ) > ( 24 * HOUR_IN_SECONDS ) ) {
					$transient_key = str_replace( '_transient_', '', $transient_name );
					delete_transient( $transient_key );
					$deleted_count++;
				}
			}
		}

		return $deleted_count;
	}
}
