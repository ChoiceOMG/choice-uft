<?php
/**
 * BCC Rate Limiter
 *
 * Implements rate limiting for BCC emails using WordPress Transients.
 * Separate from CUFT_Rate_Limiter (update endpoints rate limiting).
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_BCC_Rate_Limiter {

	/**
	 * Check if rate limit allows sending BCC
	 *
	 * @param int $threshold Rate limit threshold (0 = unlimited)
	 * @return bool True if under limit, false if exceeded
	 */
	public static function check_rate_limit( $threshold ) {
		// 0 threshold means unlimited (no rate limiting)
		if ( $threshold <= 0 ) {
			return true;
		}

		$hour_key = self::get_hour_key();
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
}
