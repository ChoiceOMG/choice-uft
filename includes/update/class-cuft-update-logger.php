<?php
/**
 * Update History Logger
 *
 * Logs plugin update attempts to Feature 007's update history system.
 * Maintains FIFO log with last 5 entries.
 *
 * @package Choice_UFT
 * @subpackage Update
 * @since 3.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CUFT_Update_Logger
 *
 * Hooks into WordPress update process to log update history.
 */
class CUFT_Update_Logger {

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'choice-uft';

	/**
	 * Main plugin file basename
	 *
	 * @var string
	 */
	const PLUGIN_BASENAME = 'choice-uft/choice-universal-form-tracker.php';

	/**
	 * Log option key (from Feature 007)
	 *
	 * @var string
	 */
	const LOG_OPTION_KEY = 'cuft_update_log';

	/**
	 * Maximum log entries (FIFO)
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 5;

	/**
	 * Initialize the update logger
	 */
	public static function init() {
		add_action( 'upgrader_process_complete', array( __CLASS__, 'log_update_completion' ), 10, 2 );
	}

	/**
	 * Log update completion
	 *
	 * Fires after WordPress completes a plugin update.
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $hook_extra Extra data passed to the action.
	 */
	public static function log_update_completion( $upgrader, $hook_extra ) {
		// Early exit: not a plugin update.
		if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		// Early exit: not an update action.
		if ( ! isset( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
			return;
		}

		// Early exit: plugins not specified.
		if ( ! isset( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
			return;
		}

		// Check if our plugin was updated.
		$our_plugin_updated = false;
		foreach ( $hook_extra['plugins'] as $plugin ) {
			if ( self::PLUGIN_BASENAME === $plugin || dirname( $plugin ) === self::PLUGIN_SLUG ) {
				$our_plugin_updated = true;
				break;
			}
		}

		// Early exit: not our plugin.
		if ( ! $our_plugin_updated ) {
			return;
		}

		// Get version information.
		$previous_version = self::get_previous_version();
		$current_version  = self::get_current_version();

		// Determine status.
		$status = self::determine_update_status( $upgrader, $hook_extra );

		// Get user information.
		$user    = wp_get_current_user();
		$user_id = $user->ID;

		// Detect trigger location.
		$trigger_location = self::detect_trigger_location();

		// Create log entry.
		$entry = array(
			'timestamp'        => current_time( 'c' ),
			'user_id'          => $user_id,
			'user_display_name' => $user->display_name,
			'previous_version' => $previous_version,
			'target_version'   => $current_version,
			'status'           => $status,
			'trigger_location' => $trigger_location,
			'error_message'    => self::get_error_message( $upgrader ),
		);

		// Add to log (FIFO).
		self::add_log_entry( $entry );
	}

	/**
	 * Get previous version (before update)
	 *
	 * @return string Previous version or 'unknown'.
	 */
	private static function get_previous_version() {
		// Try to get from transient (set before update).
		$previous = get_transient( 'cuft_version_before_update' );

		if ( false !== $previous ) {
			// Clear transient after use.
			delete_transient( 'cuft_version_before_update' );
			return $previous;
		}

		// Fallback: use current version (may not be accurate if update succeeded).
		if ( defined( 'CUFT_VERSION' ) ) {
			return CUFT_VERSION;
		}

		return 'unknown';
	}

	/**
	 * Get current version (after update)
	 *
	 * @return string Current version or 'unknown'.
	 */
	private static function get_current_version() {
		if ( defined( 'CUFT_VERSION' ) ) {
			return CUFT_VERSION;
		}

		return 'unknown';
	}

	/**
	 * Determine update status
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $hook_extra Extra data.
	 * @return string Status: 'complete', 'failed', or 'rolled_back'.
	 */
	private static function determine_update_status( $upgrader, $hook_extra ) {
		// Check if upgrader has error result.
		if ( is_wp_error( $upgrader->result ) ) {
			return 'failed';
		}

		// Check skin errors (if available).
		if ( isset( $upgrader->skin ) && is_wp_error( $upgrader->skin->result ) ) {
			return 'failed';
		}

		// Check for rollback indicator (custom implementations may set this).
		if ( isset( $hook_extra['rollback'] ) && true === $hook_extra['rollback'] ) {
			return 'rolled_back';
		}

		// Default: success.
		return 'complete';
	}

	/**
	 * Get error message from upgrader
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @return string|null Error message or null if no error.
	 */
	private static function get_error_message( $upgrader ) {
		// Check upgrader result.
		if ( is_wp_error( $upgrader->result ) ) {
			return $upgrader->result->get_error_message();
		}

		// Check skin result.
		if ( isset( $upgrader->skin ) && is_wp_error( $upgrader->skin->result ) ) {
			return $upgrader->skin->result->get_error_message();
		}

		return null;
	}

	/**
	 * Detect trigger location
	 *
	 * @return string Trigger location: 'plugins_page', 'updates_page', 'wp_cli', 'bulk_update', 'auto_update', or 'unknown'.
	 */
	private static function detect_trigger_location() {
		// Check if WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp_cli';
		}

		// Check HTTP referer.
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			if ( strpos( $referer, 'plugins.php' ) !== false ) {
				// Check if bulk update.
				if ( isset( $_REQUEST['action'] ) && 'update-selected' === $_REQUEST['action'] ) {
					return 'bulk_update';
				}

				return 'plugins_page';
			}

			if ( strpos( $referer, 'update-core.php' ) !== false ) {
				return 'updates_page';
			}
		}

		// Check if automatic update.
		if ( did_action( 'wp_maybe_auto_update' ) ) {
			return 'auto_update';
		}

		return 'unknown';
	}

	/**
	 * Add log entry to update history (FIFO)
	 *
	 * @param array $entry Log entry data.
	 */
	private static function add_log_entry( $entry ) {
		// Get existing log.
		$log = get_option( self::LOG_OPTION_KEY, array() );

		// Ensure log is an array.
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Add new entry to beginning (most recent first).
		array_unshift( $log, $entry );

		// Enforce FIFO limit.
		$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

		// Save log.
		update_option( self::LOG_OPTION_KEY, $log );
	}

	/**
	 * Get update history
	 *
	 * Public method for retrieving update history.
	 *
	 * @param int $limit Maximum number of entries to return.
	 * @return array Array of log entries.
	 */
	public static function get_history( $limit = 5 ) {
		$log = get_option( self::LOG_OPTION_KEY, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_slice( $log, 0, $limit );
	}

	/**
	 * Clear update history
	 *
	 * Public method for clearing update history (for testing or cleanup).
	 */
	public static function clear_history() {
		delete_option( self::LOG_OPTION_KEY );
	}
}

// Initialize update logger.
CUFT_Update_Logger::init();
