<?php
/**
 * Error Messages Class
 *
 * Provides standardized error messages for plugin update system.
 * All error messages include what went wrong + corrective action + relevant context.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Update
 * @since 3.17.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class CUFT_Error_Messages
 *
 * Centralizes error message templates for update system.
 *
 * @since 3.17.0
 */
class CUFT_Error_Messages {

	/**
	 * Download failure messages
	 *
	 * @since 3.17.0
	 */
	const DOWNLOAD_FAILURE = 'download_failure';
	const DOWNLOAD_SIZE_MISMATCH = 'download_size_mismatch';
	const DOWNLOAD_TIMEOUT = 'download_timeout';
	const DOWNLOAD_NETWORK_ERROR = 'network_error';

	/**
	 * Extraction failure messages
	 *
	 * @since 3.17.0
	 */
	const EXTRACTION_FAILURE = 'extraction_failure';
	const EXTRACTION_INVALID_ZIP = 'invalid_zip_format';
	const EXTRACTION_CORRUPTED_ZIP = 'corrupted_zip_file';
	const EXTRACTION_TIMEOUT = 'extraction_timeout';

	/**
	 * Permission error messages
	 *
	 * @since 3.17.0
	 */
	const PERMISSION_DENIED = 'permission_denied';
	const PLUGIN_DIR_NOT_WRITABLE = 'plugin_dir_not_writable';
	const BACKUP_DIR_NOT_WRITABLE = 'backup_dir_not_writable';
	const TEMP_DIR_NOT_WRITABLE = 'temp_dir_not_writable';

	/**
	 * Disk space error messages
	 *
	 * @since 3.17.0
	 */
	const DISK_SPACE_INSUFFICIENT = 'disk_space_insufficient';
	const DISK_SPACE_CHECK_FAILED = 'disk_space_check_failed';

	/**
	 * Backup error messages
	 *
	 * @since 3.17.0
	 */
	const BACKUP_CREATION_FAILED = 'backup_creation_failed';
	const BACKUP_NOT_FOUND = 'backup_not_found';
	const BACKUP_CORRUPTED = 'backup_corrupted';
	const BACKUP_DELETION_FAILED = 'backup_deletion_failed';

	/**
	 * Restoration error messages
	 *
	 * @since 3.17.0
	 */
	const RESTORATION_FAILED = 'restoration_failed';
	const RESTORATION_TIMEOUT = 'restoration_timeout';
	const RESTORATION_VERIFICATION_FAILED = 'restoration_verification_failed';

	/**
	 * Version error messages
	 *
	 * @since 3.17.0
	 */
	const VERSION_MISMATCH = 'version_mismatch';
	const VERSION_DETECTION_FAILED = 'version_detection_failed';

	/**
	 * Security error messages
	 *
	 * @since 3.17.0
	 */
	const SECURITY_NONCE_INVALID = 'security_nonce_invalid';
	const SECURITY_CAPABILITY_DENIED = 'capability_denied';
	const SECURITY_URL_INVALID = 'url_validation_failed';
	const SECURITY_FILE_MODS_DISABLED = 'file_mods_disabled';

	/**
	 * Get error message template
	 *
	 * Returns user-friendly error message with corrective action.
	 *
	 * @since 3.17.0
	 *
	 * @param string $error_code Error code constant.
	 * @param array  $context Additional context variables (version, path, size, etc).
	 * @return string Formatted error message.
	 */
	public static function get_message( $error_code, $context = array() ) {
		$messages = array(
			// Download failures
			self::DOWNLOAD_FAILURE => sprintf(
				/* translators: 1: GitHub URL */
				__( 'Download failed. Please check your internet connection and try again. If the problem persists, download manually from: %1$s', 'choice-uft' ),
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),
			self::DOWNLOAD_SIZE_MISMATCH => sprintf(
				/* translators: 1: Expected size, 2: Actual size */
				__( 'Download verification failed: File size mismatch. Expected %1$s, got %2$s. Please try again.', 'choice-uft' ),
				isset( $context['expected_size'] ) ? size_format( $context['expected_size'] ) : 'unknown',
				isset( $context['actual_size'] ) ? size_format( $context['actual_size'] ) : 'unknown'
			),
			self::DOWNLOAD_TIMEOUT => __( 'Download timed out. Please check your internet connection and try again.', 'choice-uft' ),
			self::DOWNLOAD_NETWORK_ERROR => __( 'Network error during download. Please check your internet connection and try again.', 'choice-uft' ),

			// Extraction failures
			self::EXTRACTION_FAILURE => __( 'Could not extract plugin update. Please try again or download manually from GitHub.', 'choice-uft' ),
			self::EXTRACTION_INVALID_ZIP => __( 'Downloaded file is not a valid ZIP archive. Please try again.', 'choice-uft' ),
			self::EXTRACTION_CORRUPTED_ZIP => __( 'Downloaded file appears to be corrupted. Please try again.', 'choice-uft' ),
			self::EXTRACTION_TIMEOUT => __( 'Extraction took too long and was aborted. Please try again.', 'choice-uft' ),

			// Permission errors
			self::PERMISSION_DENIED => sprintf(
				/* translators: %s: Required permission (e.g., 755) */
				__( 'Permission denied. Please ensure your plugin directory has proper permissions (recommended: %s).', 'choice-uft' ),
				'755'
			),
			self::PLUGIN_DIR_NOT_WRITABLE => sprintf(
				/* translators: %s: Directory path */
				__( 'Plugin directory is not writable: %s. Please check file permissions or contact your hosting provider.', 'choice-uft' ),
				isset( $context['path'] ) ? esc_html( $context['path'] ) : '/wp-content/plugins/'
			),
			self::BACKUP_DIR_NOT_WRITABLE => sprintf(
				/* translators: %s: Directory path */
				__( 'Cannot create backup directory: %s. Please ensure /wp-content/uploads/ has correct permissions (755).', 'choice-uft' ),
				isset( $context['path'] ) ? esc_html( $context['path'] ) : '/wp-content/uploads/cuft-backups/'
			),
			self::TEMP_DIR_NOT_WRITABLE => __( 'Temporary directory is not writable. Please contact your hosting provider.', 'choice-uft' ),

			// Disk space errors
			self::DISK_SPACE_INSUFFICIENT => sprintf(
				/* translators: %s: Required space in MB */
				__( 'Insufficient disk space to create backup. Free at least %s MB and try again.', 'choice-uft' ),
				isset( $context['required_space'] ) ? number_format( $context['required_space'] / 1048576, 2 ) : 'unknown'
			),
			self::DISK_SPACE_CHECK_FAILED => __( 'Could not check available disk space. Please ensure you have sufficient space and try again.', 'choice-uft' ),

			// Backup errors
			self::BACKUP_CREATION_FAILED => __( 'Backup creation failed. Update aborted to prevent data loss. Please try again.', 'choice-uft' ),
			self::BACKUP_NOT_FOUND => sprintf(
				/* translators: %s: GitHub URL */
				__( 'Backup file not found. Cannot restore previous version. Please reinstall manually from: %s', 'choice-uft' ),
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),
			self::BACKUP_CORRUPTED => sprintf(
				/* translators: %s: GitHub URL */
				__( 'Backup file is corrupted. Cannot restore previous version. Please reinstall manually from: %s', 'choice-uft' ),
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),
			self::BACKUP_DELETION_FAILED => __( 'Warning: Could not delete backup file. You may manually delete it from /wp-content/uploads/cuft-backups/', 'choice-uft' ),

			// Restoration errors
			self::RESTORATION_FAILED => sprintf(
				/* translators: 1: Error details, 2: GitHub URL */
				__( 'Update failed and automatic restoration also failed (%1$s). Please reinstall plugin manually from: %2$s', 'choice-uft' ),
				isset( $context['details'] ) ? esc_html( $context['details'] ) : 'unknown error',
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),
			self::RESTORATION_TIMEOUT => sprintf(
				/* translators: 1: Timeout in seconds, 2: GitHub URL */
				__( 'Restoration exceeded %1$d second timeout. Please reinstall plugin manually from: %2$s', 'choice-uft' ),
				isset( $context['timeout'] ) ? absint( $context['timeout'] ) : 10,
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),
			self::RESTORATION_VERIFICATION_FAILED => sprintf(
				/* translators: %s: GitHub URL */
				__( 'Restoration completed but verification failed. Please check plugin functionality or reinstall from: %s', 'choice-uft' ),
				'https://github.com/ChoiceOMG/choice-uft/releases/latest'
			),

			// Version errors
			self::VERSION_MISMATCH => sprintf(
				/* translators: 1: Expected version, 2: Actual version */
				__( 'Version mismatch after update. Expected %1$s, got %2$s. Please verify plugin functionality.', 'choice-uft' ),
				isset( $context['expected_version'] ) ? esc_html( $context['expected_version'] ) : 'unknown',
				isset( $context['actual_version'] ) ? esc_html( $context['actual_version'] ) : 'unknown'
			),
			self::VERSION_DETECTION_FAILED => __( 'Could not detect plugin version. Please verify plugin functionality.', 'choice-uft' ),

			// Security errors
			self::SECURITY_NONCE_INVALID => __( 'Security check failed. Please refresh the page and try again.', 'choice-uft' ),
			self::SECURITY_CAPABILITY_DENIED => __( 'You do not have permission to update plugins. Please contact an administrator.', 'choice-uft' ),
			self::SECURITY_URL_INVALID => __( 'Invalid download URL. Security check failed. Update aborted.', 'choice-uft' ),
			self::SECURITY_FILE_MODS_DISABLED => __( 'File modifications are disabled on this site (DISALLOW_FILE_MODS). Please contact your administrator.', 'choice-uft' ),
		);

		// Return message if exists, otherwise generic error
		if ( isset( $messages[ $error_code ] ) ) {
			return $messages[ $error_code ];
		}

		return sprintf(
			/* translators: 1: Error code, 2: GitHub URL */
			__( 'An unexpected error occurred (%1$s). Please try again or contact support. Manual download: %2$s', 'choice-uft' ),
			esc_html( $error_code ),
			'https://github.com/ChoiceOMG/choice-uft/releases/latest'
		);
	}

	/**
	 * Log error to update history
	 *
	 * Logs error message to Feature 007's update history log with context.
	 *
	 * @since 3.17.0
	 *
	 * @param string $error_code Error code constant.
	 * @param array  $context Error context (version, user, timestamp, etc).
	 * @return bool True if logged, false otherwise.
	 */
	public static function log_error( $error_code, $context = array() ) {
		// Get error message
		$error_message = self::get_message( $error_code, $context );

		// Build log entry
		$log_entry = array(
			'timestamp'      => current_time( 'mysql' ),
			'status'         => 'failed',
			'error_code'     => $error_code,
			'error_message'  => $error_message,
			'previous_version' => isset( $context['previous_version'] ) ? $context['previous_version'] : ( defined( 'CUFT_VERSION' ) ? CUFT_VERSION : 'unknown' ),
			'target_version' => isset( $context['target_version'] ) ? $context['target_version'] : 'unknown',
			'user_id'        => get_current_user_id(),
			'user_display_name' => wp_get_current_user()->display_name,
			'trigger_location' => isset( $context['trigger_location'] ) ? $context['trigger_location'] : self::detect_trigger_location(),
		);

		// Add optional context fields
		if ( isset( $context['expected_size'] ) ) {
			$log_entry['expected_size'] = $context['expected_size'];
		}

		if ( isset( $context['actual_size'] ) ) {
			$log_entry['actual_size'] = $context['actual_size'];
		}

		if ( isset( $context['path'] ) ) {
			// Never expose server paths to non-administrators (PII protection)
			if ( current_user_can( 'manage_options' ) ) {
				$log_entry['path'] = $context['path'];
			}
		}

		// Get existing update history
		$update_history = get_option( 'cuft_update_history', array() );

		if ( ! is_array( $update_history ) ) {
			$update_history = array();
		}

		// Add new entry (FIFO - keep last 5)
		array_unshift( $update_history, $log_entry );
		$update_history = array_slice( $update_history, 0, 5 );

		// Save updated history
		$saved = update_option( 'cuft_update_history', $update_history, false );

		// Also log to PHP error_log for server-side debugging
		$severity = self::get_error_severity( $error_code );
		error_log( sprintf(
			'CUFT %s: %s (code: %s, user: %d, version: %s → %s)',
			$severity,
			$error_message,
			$error_code,
			$log_entry['user_id'],
			$log_entry['previous_version'],
			$log_entry['target_version']
		) );

		return $saved;
	}

	/**
	 * Get error severity level
	 *
	 * @since 3.17.0
	 *
	 * @param string $error_code Error code.
	 * @return string Severity level (CRITICAL, ERROR, WARNING).
	 */
	private static function get_error_severity( $error_code ) {
		// CRITICAL: Update failed and restoration also failed
		$critical_errors = array(
			self::RESTORATION_FAILED,
			self::RESTORATION_TIMEOUT,
			self::BACKUP_NOT_FOUND,
			self::BACKUP_CORRUPTED,
		);

		if ( in_array( $error_code, $critical_errors, true ) ) {
			return 'CRITICAL';
		}

		// WARNING: Non-critical errors that don't prevent functionality
		$warning_errors = array(
			self::BACKUP_DELETION_FAILED,
			self::DISK_SPACE_CHECK_FAILED,
		);

		if ( in_array( $error_code, $warning_errors, true ) ) {
			return 'WARNING';
		}

		// Default: ERROR
		return 'ERROR';
	}

	/**
	 * Detect trigger location
	 *
	 * Determines where the update was triggered from.
	 *
	 * @since 3.17.0
	 *
	 * @return string Trigger location.
	 */
	private static function detect_trigger_location() {
		// Check if WP-CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp_cli';
		}

		// Check current screen
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen ) {
				if ( $screen->id === 'plugins' ) {
					return 'plugins_page';
				}

				if ( $screen->id === 'update-core' ) {
					return 'updates_page';
				}
			}
		}

		// Check bulk update
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'update-selected' ) {
			return 'bulk_update';
		}

		// Default
		return 'unknown';
	}

	/**
	 * Create WP_Error from error code
	 *
	 * Convenience method to create WP_Error with standardized message.
	 *
	 * @since 3.17.0
	 *
	 * @param string $error_code Error code constant.
	 * @param array  $context Error context.
	 * @param bool   $log_error Whether to log error (default: true).
	 * @return WP_Error WordPress error object.
	 */
	public static function create_error( $error_code, $context = array(), $log_error = true ) {
		// Get error message
		$error_message = self::get_message( $error_code, $context );

		// Log error if requested
		if ( $log_error ) {
			self::log_error( $error_code, $context );
		}

		// Create and return WP_Error
		return new WP_Error( $error_code, $error_message, $context );
	}

	/**
	 * Display admin notice for error
	 *
	 * Displays error message as WordPress admin notice.
	 *
	 * @since 3.17.0
	 *
	 * @param string $error_code Error code constant.
	 * @param array  $context Error context.
	 * @param string $notice_type Notice type (error, warning, info).
	 */
	public static function display_admin_notice( $error_code, $context = array(), $notice_type = 'error' ) {
		$message = self::get_message( $error_code, $context );

		add_action( 'admin_notices', function() use ( $message, $notice_type ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
				esc_attr( $notice_type ),
				esc_html__( 'Plugin Update Failed', 'choice-uft' ),
				wp_kses_post( $message )
			);
		} );
	}
}
