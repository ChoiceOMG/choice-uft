<?php
/**
 * Cache Clearing Utility
 *
 * Utility to clear WordPress plugin update cache and force immediate
 * recognition of new versions.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes
 * @since      3.19.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT_Cache_Clearer class.
 *
 * Clears WordPress plugin update cache to force immediate recognition
 * of new plugin versions after manual checks or force reinstalls.
 *
 * @since 3.19.0
 */
class CUFT_Cache_Clearer {

	/**
	 * Clear WordPress plugin update cache
	 *
	 * Deletes the update_plugins transient and cleans the plugins cache,
	 * forcing WordPress to recognize new versions immediately.
	 *
	 * @return bool True on success.
	 */
	public static function clear_plugin_cache() {
		// Delete the update_plugins site transient
		delete_site_transient( 'update_plugins' );

		// Clean plugins cache
		wp_clean_plugins_cache( true );

		// Log action if debug mode enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CUFT: Plugin update cache cleared at ' . current_time( 'mysql' ) );
		}

		return true;
	}

	/**
	 * Clear plugin cache for specific plugin
	 *
	 * Clears full cache and optionally triggers WordPress to recheck for updates.
	 * This is useful after force reinstall to ensure WordPress recognizes the new version.
	 *
	 * @param string $plugin_basename Plugin basename (e.g., 'choice-uft/choice-universal-form-tracker.php').
	 * @return bool True on success.
	 */
	public static function clear_plugin_cache_for_specific_plugin( $plugin_basename ) {
		// Clear full cache
		self::clear_plugin_cache();

		// Trigger WordPress to recheck for plugin updates
		// This forces WordPress to query the update API again
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		// Log action if debug mode enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'CUFT: Plugin cache cleared for %s at %s', $plugin_basename, current_time( 'mysql' ) ) );
		}

		return true;
	}
}
