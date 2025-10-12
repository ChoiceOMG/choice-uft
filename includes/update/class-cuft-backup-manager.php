<?php
/**
 * Backup Manager Class
 *
 * Handles automatic backup and rollback for plugin updates.
 * Creates backups before updates, restores on failure, and manages cleanup.
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
 * Class CUFT_Backup_Manager
 *
 * Manages plugin backups and restoration during update process.
 *
 * @since 3.17.0
 */
class CUFT_Backup_Manager {

	/**
	 * Backup directory path
	 *
	 * @since 3.17.0
	 * @var string
	 */
	private $backup_dir;

	/**
	 * Plugin slug
	 *
	 * @since 3.17.0
	 * @var string
	 */
	private $plugin_slug = 'choice-uft';

	/**
	 * Plugin directory path
	 *
	 * @since 3.17.0
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Restore timeout in seconds
	 *
	 * Hard limit for backup restoration operations.
	 *
	 * @since 3.17.0
	 * @var int
	 */
	const RESTORE_TIMEOUT = 10;

	/**
	 * Initialize the backup manager
	 *
	 * Sets up backup directory and registers WordPress hooks.
	 *
	 * @since 3.17.0
	 */
	public function __construct() {
		// Set backup directory path
		$upload_dir = wp_upload_dir();
		$this->backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'cuft-backups';

		// Set plugin directory path
		$this->plugin_dir = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

		// Register hooks
		add_filter( 'upgrader_pre_install', array( $this, 'create_backup_before_update' ), 10, 2 );
		add_filter( 'upgrader_install_package_result', array( $this, 'restore_on_failure' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'delete_backup_on_success' ), 10, 2 );

		// Initialize backup directory
		$this->initialize_backup_directory();
	}

	/**
	 * Initialize backup directory
	 *
	 * Creates backup directory if it doesn't exist and adds security protection.
	 *
	 * @since 3.17.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function initialize_backup_directory() {
		global $wp_filesystem;

		// Initialize filesystem
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return new WP_Error(
				'filesystem_unavailable',
				__( 'WordPress Filesystem API is not available.', 'choice-uft' )
			);
		}

		// Convert to WP_Filesystem path
		$wp_backup_dir = str_replace( ABSPATH, $wp_filesystem->abspath(), $this->backup_dir );

		// Create directory if it doesn't exist
		if ( ! $wp_filesystem->is_dir( $wp_backup_dir ) ) {
			if ( ! $wp_filesystem->mkdir( $wp_backup_dir, FS_CHMOD_DIR ) ) {
				return new WP_Error(
					'backup_dir_create_failed',
					sprintf(
						/* translators: %s: Directory path */
						__( 'Could not create backup directory: %s', 'choice-uft' ),
						$this->backup_dir
					)
				);
			}
		}

		// Add .htaccess to deny direct access
		$htaccess_file = trailingslashit( $this->backup_dir ) . '.htaccess';
		$wp_htaccess = str_replace( ABSPATH, $wp_filesystem->abspath(), $htaccess_file );

		if ( ! $wp_filesystem->exists( $wp_htaccess ) ) {
			$htaccess_content = "# Protect backup files from direct access\n";
			$htaccess_content .= "Order deny,allow\n";
			$htaccess_content .= "Deny from all\n";

			$wp_filesystem->put_contents( $wp_htaccess, $htaccess_content, FS_CHMOD_FILE );
		}

		return true;
	}

	/**
	 * Create backup before update
	 *
	 * Hooks into upgrader_pre_install to create a backup of the current
	 * plugin before WordPress starts the update process.
	 *
	 * @since 3.17.0
	 *
	 * @param bool|WP_Error $return Installation response.
	 * @param array         $plugin Plugin data.
	 * @return bool|WP_Error True on success, WP_Error on failure (aborts update).
	 */
	public function create_backup_before_update( $return, $plugin ) {
		// Only process our plugin
		if ( ! isset( $plugin['plugin'] ) || dirname( $plugin['plugin'] ) !== $this->plugin_slug ) {
			return $return;
		}

		// Log backup start
		error_log( sprintf(
			'CUFT Backup Manager: Creating backup of version %s before update',
			defined( 'CUFT_VERSION' ) ? CUFT_VERSION : 'unknown'
		) );

		// Get current version
		$current_version = defined( 'CUFT_VERSION' ) ? CUFT_VERSION : 'unknown';

		// Create backup
		$backup_result = $this->create_backup( $current_version );

		if ( is_wp_error( $backup_result ) ) {
			// Log error
			error_log( sprintf(
				'CUFT Backup Manager: Backup creation failed - %s',
				$backup_result->get_error_message()
			) );

			// Abort update if backup fails
			return $backup_result;
		}

		// Store backup path in transient for restoration if needed
		set_transient( 'cuft_backup_path', $backup_result, HOUR_IN_SECONDS );

		// Log success
		error_log( sprintf(
			'CUFT Backup Manager: Backup created successfully at %s',
			basename( $backup_result )
		) );

		return $return;
	}

	/**
	 * Create plugin backup
	 *
	 * Creates a ZIP backup of the current plugin directory.
	 *
	 * @since 3.17.0
	 *
	 * @param string $version Current plugin version.
	 * @return string|WP_Error Backup file path on success, WP_Error on failure.
	 */
	public function create_backup( $version ) {
		global $wp_filesystem;

		// Initialize filesystem
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return new WP_Error(
				'filesystem_unavailable',
				__( 'WordPress Filesystem API is not available.', 'choice-uft' )
			);
		}

		// Check if plugin directory exists
		if ( ! is_dir( $this->plugin_dir ) ) {
			return new WP_Error(
				'plugin_dir_not_found',
				sprintf(
					/* translators: %s: Directory path */
					__( 'Plugin directory not found: %s', 'choice-uft' ),
					$this->plugin_dir
				)
			);
		}

		// Check if backup directory is writable
		$wp_backup_dir = str_replace( ABSPATH, $wp_filesystem->abspath(), $this->backup_dir );

		if ( ! $wp_filesystem->is_writable( $wp_backup_dir ) ) {
			return new WP_Error(
				'backup_dir_not_writable',
				sprintf(
					/* translators: %s: Directory path */
					__( 'Backup directory is not writable: %s. Please ensure /wp-content/uploads/ has correct permissions (755).', 'choice-uft' ),
					$this->backup_dir
				)
			);
		}

		// Check available disk space
		$required_space = $this->calculate_directory_size( $this->plugin_dir ) * 2; // 2x for ZIP overhead
		$available_space = @disk_free_space( $this->backup_dir );

		if ( $available_space !== false && $available_space < $required_space ) {
			return new WP_Error(
				'insufficient_disk_space',
				sprintf(
					/* translators: %s: Required space in MB */
					__( 'Insufficient disk space to create backup. Free at least %s MB and try again.', 'choice-uft' ),
					number_format( $required_space / 1048576, 2 )
				)
			);
		}

		// Build backup file path
		$backup_filename = sprintf(
			'choice-uft-%s-backup-%s.zip',
			$version,
			date( 'Y-m-d-His' )
		);
		$backup_path = trailingslashit( $this->backup_dir ) . $backup_filename;

		// Check if ZipArchive is available
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'ziparchive_unavailable',
				__( 'ZipArchive PHP extension is not available. Cannot create backup.', 'choice-uft' )
			);
		}

		// Create ZIP archive
		$zip = new ZipArchive();
		$zip_opened = $zip->open( $backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		if ( $zip_opened !== true ) {
			return new WP_Error(
				'zip_create_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Could not create backup ZIP file: %s', 'choice-uft' ),
					$this->get_zip_error_message( $zip_opened )
				)
			);
		}

		// Add plugin files to ZIP
		$add_result = $this->add_directory_to_zip( $zip, $this->plugin_dir, $this->plugin_slug );

		if ( is_wp_error( $add_result ) ) {
			$zip->close();
			@unlink( $backup_path );
			return $add_result;
		}

		// Close ZIP
		$zip->close();

		// Verify backup was created
		if ( ! file_exists( $backup_path ) ) {
			return new WP_Error(
				'backup_verification_failed',
				__( 'Backup file was not created successfully.', 'choice-uft' )
			);
		}

		// Verify backup size
		$backup_size = filesize( $backup_path );
		if ( $backup_size === 0 ) {
			@unlink( $backup_path );
			return new WP_Error(
				'backup_empty',
				__( 'Backup file is empty.', 'choice-uft' )
			);
		}

		// Set proper permissions
		@chmod( $backup_path, FS_CHMOD_FILE );

		return $backup_path;
	}

	/**
	 * Restore backup on update failure
	 *
	 * Hooks into upgrader_install_package_result to detect failures
	 * and automatically restore from backup.
	 *
	 * @since 3.17.0
	 *
	 * @param array|WP_Error $result Installation result.
	 * @param array          $hook_extra Hook extra data.
	 * @return array|WP_Error Modified result or WP_Error.
	 */
	public function restore_on_failure( $result, $hook_extra ) {
		// Only process our plugin
		if ( ! isset( $hook_extra['plugin'] ) || dirname( $hook_extra['plugin'] ) !== $this->plugin_slug ) {
			return $result;
		}

		// Only restore if installation failed
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		// Log failure
		error_log( sprintf(
			'CUFT Backup Manager: Update failed - %s. Attempting automatic restoration...',
			$result->get_error_message()
		) );

		// Get backup path from transient
		$backup_path = get_transient( 'cuft_backup_path' );

		if ( empty( $backup_path ) || ! file_exists( $backup_path ) ) {
			error_log( 'CUFT Backup Manager: No backup file found. Cannot restore.' );

			// Add restoration failure to original error
			$result->add(
				'restoration_unavailable',
				__( 'Update failed and backup file is not available. Please reinstall plugin manually from GitHub.', 'choice-uft' )
			);

			return $result;
		}

		// Attempt restoration
		$restore_result = $this->restore_backup( $backup_path );

		if ( is_wp_error( $restore_result ) ) {
			error_log( sprintf(
				'CUFT Backup Manager: Restoration failed - %s',
				$restore_result->get_error_message()
			) );

			// Add restoration error to original error
			$result->add(
				'restoration_failed',
				sprintf(
					/* translators: 1: Original error, 2: Restoration error, 3: GitHub URL */
					__( 'Update failed (%1$s) and automatic restoration also failed (%2$s). Please reinstall plugin manually from GitHub: %3$s', 'choice-uft' ),
					$result->get_error_message(),
					$restore_result->get_error_message(),
					'https://github.com/ChoiceOMG/choice-uft/releases/latest'
				)
			);

			return $result;
		}

		// Restoration succeeded
		error_log( 'CUFT Backup Manager: Previous version restored successfully' );

		// Keep backup file for debugging
		// (admin can manually delete it later)

		// Update error message to indicate rollback
		$result->add(
			'update_rolled_back',
			__( 'Update failed but previous version was restored successfully. Plugin is functional.', 'choice-uft' )
		);

		return $result;
	}

	/**
	 * Restore plugin from backup
	 *
	 * Extracts backup ZIP to plugin directory with timeout protection.
	 *
	 * @since 3.17.0
	 *
	 * @param string $backup_path Path to backup ZIP file.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function restore_backup( $backup_path ) {
		global $wp_filesystem;

		// Initialize filesystem
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return new WP_Error(
				'filesystem_unavailable',
				__( 'WordPress Filesystem API is not available.', 'choice-uft' )
			);
		}

		// Verify backup file exists
		if ( ! file_exists( $backup_path ) ) {
			return new WP_Error(
				'backup_not_found',
				__( 'Backup file not found.', 'choice-uft' )
			);
		}

		// Verify backup file is readable
		if ( ! is_readable( $backup_path ) ) {
			return new WP_Error(
				'backup_not_readable',
				__( 'Backup file is not readable.', 'choice-uft' )
			);
		}

		// Check if plugin directory is writable
		$plugin_parent_dir = dirname( $this->plugin_dir );

		if ( ! is_writable( $plugin_parent_dir ) ) {
			return new WP_Error(
				'plugin_dir_not_writable',
				sprintf(
					/* translators: %s: Directory path */
					__( 'Plugin directory is not writable: %s', 'choice-uft' ),
					$plugin_parent_dir
				)
			);
		}

		// Start timeout timer
		$start_time = time();

		// Delete current plugin directory (if exists)
		if ( is_dir( $this->plugin_dir ) ) {
			$wp_plugin_dir = str_replace( ABSPATH, $wp_filesystem->abspath(), $this->plugin_dir );

			if ( ! $wp_filesystem->delete( $wp_plugin_dir, true ) ) {
				return new WP_Error(
					'plugin_dir_delete_failed',
					__( 'Could not delete existing plugin directory for restoration.', 'choice-uft' )
				);
			}
		}

		// Check timeout
		if ( ( time() - $start_time ) >= self::RESTORE_TIMEOUT ) {
			return new WP_Error(
				'restoration_timeout',
				sprintf(
					/* translators: 1: Timeout in seconds, 2: GitHub URL */
					__( 'Restoration exceeded %1$d second timeout. Please reinstall plugin manually from GitHub: %2$s', 'choice-uft' ),
					self::RESTORE_TIMEOUT,
					'https://github.com/ChoiceOMG/choice-uft/releases/latest'
				)
			);
		}

		// Extract backup to plugins directory
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$extract_result = unzip_file( $backup_path, $plugin_parent_dir );

		if ( is_wp_error( $extract_result ) ) {
			return new WP_Error(
				'backup_extraction_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Backup extraction failed: %s', 'choice-uft' ),
					$extract_result->get_error_message()
				)
			);
		}

		// Check timeout again
		if ( ( time() - $start_time ) >= self::RESTORE_TIMEOUT ) {
			return new WP_Error(
				'restoration_timeout',
				sprintf(
					/* translators: 1: Timeout in seconds, 2: GitHub URL */
					__( 'Restoration exceeded %1$d second timeout. Please verify plugin functionality or reinstall from GitHub: %2$s', 'choice-uft' ),
					self::RESTORE_TIMEOUT,
					'https://github.com/ChoiceOMG/choice-uft/releases/latest'
				)
			);
		}

		// Verify plugin directory was restored
		if ( ! is_dir( $this->plugin_dir ) ) {
			return new WP_Error(
				'restoration_verification_failed',
				__( 'Plugin directory was not restored correctly.', 'choice-uft' )
			);
		}

		// Verify main plugin file exists
		$main_plugin_file = $this->plugin_dir . '/choice-universal-form-tracker.php';

		if ( ! file_exists( $main_plugin_file ) ) {
			return new WP_Error(
				'plugin_file_missing',
				__( 'Main plugin file not found after restoration.', 'choice-uft' )
			);
		}

		return true;
	}

	/**
	 * Delete backup on successful update
	 *
	 * Hooks into upgrader_process_complete to delete backup after
	 * successful update (standard WordPress pattern).
	 *
	 * @since 3.17.0
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $hook_extra Hook extra data.
	 */
	public function delete_backup_on_success( $upgrader, $hook_extra ) {
		// Only process plugin updates
		if ( ! isset( $hook_extra['type'] ) || $hook_extra['type'] !== 'plugin' ) {
			return;
		}

		if ( ! isset( $hook_extra['action'] ) || $hook_extra['action'] !== 'update' ) {
			return;
		}

		// Get plugins being updated
		$plugins = isset( $hook_extra['plugins'] ) ? $hook_extra['plugins'] : array();

		if ( isset( $hook_extra['plugin'] ) ) {
			$plugins = array( $hook_extra['plugin'] );
		}

		// Check if our plugin was updated
		$our_plugin_updated = false;
		foreach ( $plugins as $plugin ) {
			if ( dirname( $plugin ) === $this->plugin_slug ) {
				$our_plugin_updated = true;
				break;
			}
		}

		if ( ! $our_plugin_updated ) {
			return;
		}

		// Get backup path from transient
		$backup_path = get_transient( 'cuft_backup_path' );

		if ( empty( $backup_path ) ) {
			return;
		}

		// Delete backup file
		$delete_result = $this->delete_backup( $backup_path );

		if ( $delete_result ) {
			error_log( sprintf(
				'CUFT Backup Manager: Backup deleted successfully after update: %s',
				basename( $backup_path )
			) );
		} else {
			error_log( sprintf(
				'CUFT Backup Manager: Warning - Could not delete backup file: %s',
				basename( $backup_path )
			) );
		}

		// Clear transient
		delete_transient( 'cuft_backup_path' );
	}

	/**
	 * Delete backup file
	 *
	 * Removes a backup file from the filesystem.
	 *
	 * @since 3.17.0
	 *
	 * @param string $backup_path Path to backup file.
	 * @return bool True on success, false on failure.
	 */
	public function delete_backup( $backup_path ) {
		if ( empty( $backup_path ) || ! file_exists( $backup_path ) ) {
			return false;
		}

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( ! empty( $wp_filesystem ) ) {
			$wp_path = str_replace( ABSPATH, $wp_filesystem->abspath(), $backup_path );
			return $wp_filesystem->delete( $wp_path, false );
		}

		// Fallback to PHP unlink
		return @unlink( $backup_path );
	}

	/**
	 * Add directory to ZIP archive recursively
	 *
	 * @since 3.17.0
	 *
	 * @param ZipArchive $zip ZIP archive instance.
	 * @param string     $directory Directory to add.
	 * @param string     $base_name Base name for ZIP paths.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function add_directory_to_zip( $zip, $directory, $base_name ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$file_path = $file->getPathname();
			$relative_path = $base_name . '/' . substr( $file_path, strlen( $directory ) + 1 );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative_path );
			} else {
				if ( ! $zip->addFile( $file_path, $relative_path ) ) {
					return new WP_Error(
						'zip_add_file_failed',
						sprintf(
							/* translators: %s: File path */
							__( 'Could not add file to backup: %s', 'choice-uft' ),
							$relative_path
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Calculate directory size
	 *
	 * @since 3.17.0
	 *
	 * @param string $directory Directory path.
	 * @return int Size in bytes.
	 */
	private function calculate_directory_size( $directory ) {
		$size = 0;

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			$size += $file->getSize();
		}

		return $size;
	}

	/**
	 * Get human-readable ZIP error message
	 *
	 * @since 3.17.0
	 *
	 * @param int $error_code ZipArchive error code.
	 * @return string Error message.
	 */
	private function get_zip_error_message( $error_code ) {
		$messages = array(
			ZipArchive::ER_EXISTS      => __( 'File already exists', 'choice-uft' ),
			ZipArchive::ER_INCONS      => __( 'ZIP archive inconsistent', 'choice-uft' ),
			ZipArchive::ER_INVAL       => __( 'Invalid argument', 'choice-uft' ),
			ZipArchive::ER_MEMORY      => __( 'Memory allocation failure', 'choice-uft' ),
			ZipArchive::ER_NOENT       => __( 'No such file', 'choice-uft' ),
			ZipArchive::ER_NOZIP       => __( 'Not a ZIP archive', 'choice-uft' ),
			ZipArchive::ER_OPEN        => __( "Can't open file", 'choice-uft' ),
			ZipArchive::ER_READ        => __( 'Read error', 'choice-uft' ),
			ZipArchive::ER_SEEK        => __( 'Seek error', 'choice-uft' ),
		);

		return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : __( 'Unknown error', 'choice-uft' );
	}
}
