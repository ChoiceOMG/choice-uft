<?php
/**
 * Plugin Installation State Model
 *
 * Data model for Plugin Installation State cached in transient.
 * Represents the current state of plugin installation and update availability.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes/Models
 * @since      3.19.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT_Plugin_Installation_State class.
 *
 * Manages cached plugin installation state including version information
 * and update availability. Cache expires after 5 minutes.
 *
 * @since 3.19.0
 */
class CUFT_Plugin_Installation_State {

	/**
	 * Transient key for plugin installation state
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'cuft_plugin_installation_state';

	/**
	 * Cache TTL in seconds (300 = 5 minutes)
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	/**
	 * Get cached plugin installation state
	 *
	 * @return array|null State array or null if cache expired/not set.
	 */
	public static function get() {
		$state = get_transient( self::TRANSIENT_KEY );

		if ( false === $state ) {
			return null;
		}

		// Validate cache is still valid (double-check TTL)
		if ( ! self::is_cache_valid( $state ) ) {
			self::clear();
			return null;
		}

		return $state;
	}

	/**
	 * Set plugin installation state cache
	 *
	 * @param string $installed_version     Current installed version.
	 * @param string $github_latest_version Latest version from GitHub.
	 * @return array The cached state data.
	 */
	public static function set( $installed_version, $github_latest_version ) {
		$state = array(
			'installed_version'      => $installed_version,
			'github_latest_version'  => $github_latest_version,
			'last_check_timestamp'   => time(),
			'update_available'       => version_compare( $github_latest_version, $installed_version, '>' ),
			'cache_ttl'              => self::CACHE_TTL,
		);

		set_transient( self::TRANSIENT_KEY, $state, self::CACHE_TTL );

		return $state;
	}

	/**
	 * Clear plugin installation state cache
	 *
	 * @return bool True if transient was deleted.
	 */
	public static function clear() {
		return delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Check if cache is still valid
	 *
	 * @param array|null $state State data (optional, will fetch if not provided).
	 * @return bool True if cache exists and not expired, false otherwise.
	 */
	public static function is_cache_valid( $state = null ) {
		if ( null === $state ) {
			$state = get_transient( self::TRANSIENT_KEY );
		}

		if ( false === $state || ! is_array( $state ) ) {
			return false;
		}

		if ( ! isset( $state['last_check_timestamp'] ) || ! isset( $state['cache_ttl'] ) ) {
			return false;
		}

		$elapsed = time() - $state['last_check_timestamp'];

		return $elapsed < $state['cache_ttl'];
	}
}
