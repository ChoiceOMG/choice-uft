<?php
/**
 * Update Installer Service
 *
 * Handles the complete update installation process with automatic rollback.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT Update Installer
 *
 * Manages the entire update process from download to installation.
 */
class CUFT_Update_Installer {

    /**
     * Current update ID
     *
     * @var string
     */
    private $update_id;

    /**
     * Target version
     *
     * @var string
     */
    private $target_version;

    /**
     * Backup created
     *
     * @var array|null
     */
    private $backup = null;

    /**
     * Constructor
     *
     * @param string $update_id Update ID
     * @param string $target_version Target version
     */
    public function __construct( $update_id, $target_version ) {
        $this->update_id = $update_id;
        $this->target_version = $target_version;
    }

    /**
     * Execute update process
     *
     * @param bool $create_backup Whether to create backup
     * @return array|WP_Error Update result or WP_Error on failure
     */
    public function execute( $create_backup = true ) {
        try {
            // Initialize progress
            CUFT_Update_Progress::start( 'Starting update process...' );

            // Step 1: Check prerequisites
            $this->update_progress( 'checking', 5, 'Checking system requirements...' );
            $prereq_check = $this->check_prerequisites();
            if ( is_wp_error( $prereq_check ) ) {
                return $this->handle_failure( $prereq_check );
            }

            // Step 2: Get download URL
            $this->update_progress( 'checking', 10, 'Fetching update information...' );
            $download_url = $this->get_download_url();
            if ( is_wp_error( $download_url ) ) {
                return $this->handle_failure( $download_url );
            }

            // Step 3: Download package
            $this->update_progress( 'downloading', 20, 'Downloading update package...' );
            $package_file = $this->download_package( $download_url );
            if ( is_wp_error( $package_file ) ) {
                return $this->handle_failure( $package_file );
            }

            // Step 4: Verify download
            $this->update_progress( 'downloading', 40, 'Verifying download...' );
            $verify_result = $this->verify_package( $package_file );
            if ( is_wp_error( $verify_result ) ) {
                $this->cleanup_temp_file( $package_file );
                return $this->handle_failure( $verify_result );
            }

            // Step 5: Create backup
            if ( $create_backup ) {
                $this->update_progress( 'backing_up', 50, 'Creating backup...' );
                $backup_result = $this->create_backup();
                if ( is_wp_error( $backup_result ) ) {
                    $this->cleanup_temp_file( $package_file );
                    return $this->handle_failure( $backup_result );
                }
            }

            // Step 6: Extract package
            $this->update_progress( 'installing', 60, 'Extracting update files...' );
            $extract_result = $this->extract_package( $package_file );
            if ( is_wp_error( $extract_result ) ) {
                $this->cleanup_temp_file( $package_file );
                return $this->handle_failure( $extract_result );
            }

            // Step 7: Install files
            $this->update_progress( 'installing', 75, 'Installing update...' );
            $install_result = $this->install_files( $extract_result );
            if ( is_wp_error( $install_result ) ) {
                $this->cleanup_temp_files( $package_file, $extract_result );
                return $this->handle_failure( $install_result );
            }

            // Step 8: Verify installation
            $this->update_progress( 'verifying', 90, 'Verifying installation...' );
            $verify_install = $this->verify_installation();
            if ( is_wp_error( $verify_install ) ) {
                return $this->handle_failure( $verify_install );
            }

            // Step 9: Cleanup
            $this->update_progress( 'verifying', 95, 'Cleaning up...' );
            $this->cleanup_temp_files( $package_file, $extract_result );

            // Step 10: Complete
            $this->update_progress( 'complete', 100, 'Update completed successfully!' );

            // Log success
            CUFT_Update_Log::log( 'install_completed', 'success', array(
                'details' => sprintf( 'Successfully updated from %s to %s', CUFT_VERSION, $this->target_version ),
                'version_from' => CUFT_VERSION,
                'version_to' => $this->target_version
            ) );

            // Clear all update-related caches
            self::invalidate_all_caches();

            // Set update completion transient for synchronization
            self::set_update_completion_transient($this->target_version);

            return array(
                'success' => true,
                'old_version' => CUFT_VERSION,
                'new_version' => $this->target_version,
                'backup_created' => $this->backup,
                'message' => 'Update completed successfully'
            );

        } catch ( Exception $e ) {
            return $this->handle_failure( new WP_Error( 'update_exception', $e->getMessage() ) );
        }
    }

    /**
     * Check prerequisites for update
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function check_prerequisites() {
        // Check if update already in progress
        if ( CUFT_Update_Progress::is_in_progress() ) {
            return new WP_Error( 'update_in_progress', 'Another update is already in progress' );
        }

        // Initialize filesystem
        $fs_result = CUFT_Filesystem_Handler::init();
        if ( is_wp_error( $fs_result ) ) {
            return $fs_result;
        }

        // Check plugin directory writable
        $plugin_dir = CUFT_Filesystem_Handler::get_plugin_dir();
        if ( ! CUFT_Filesystem_Handler::is_writable( $plugin_dir ) ) {
            return new WP_Error( 'not_writable', 'Plugin directory is not writable' );
        }

        // Check disk space (estimate 10MB needed)
        $space_check = CUFT_Filesystem_Handler::check_disk_space( 10 * MB_IN_BYTES );
        if ( is_wp_error( $space_check ) ) {
            return $space_check;
        }

        return true;
    }

    /**
     * Get download URL for target version
     *
     * @return string|WP_Error Download URL or WP_Error
     */
    private function get_download_url() {
        $url = CUFT_GitHub_API::get_download_url( $this->target_version );

        if ( ! $url ) {
            $error_msg = sprintf(
                'Failed to get download URL for version %s. GitHub API may be unavailable or version does not exist.',
                $this->target_version
            );
            error_log( 'CUFT Update Error: ' . $error_msg );
            return new WP_Error( 'download_url_failed', $error_msg );
        }

        // Verify URL is accessible
        if ( ! CUFT_GitHub_API::verify_download_url( $url ) ) {
            $error_msg = sprintf(
                'Download URL is not accessible: %s. Please check GitHub release assets.',
                $url
            );
            error_log( 'CUFT Update Error: ' . $error_msg );
            return new WP_Error( 'url_not_accessible', $error_msg );
        }

        return $url;
    }

    /**
     * Download update package
     *
     * @param string $url Download URL
     * @return string|WP_Error Temporary file path or WP_Error
     */
    private function download_package( $url ) {
        // Log download started
        CUFT_Update_Log::log( 'download_started', 'info', array(
            'details' => 'Downloading update from: ' . $url,
            'version_to' => $this->target_version
        ) );

        // Create temporary file
        $temp_file = CUFT_Filesystem_Handler::tempnam( 'cuft_update_' );

        if ( ! $temp_file ) {
            return new WP_Error( 'temp_file_failed', 'Failed to create temporary file' );
        }

        // Download
        $result = CUFT_GitHub_API::download_file( $url, $temp_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Log download completed
        $file_size = CUFT_Filesystem_Handler::size( $temp_file );
        CUFT_Update_Log::log( 'download_completed', 'success', array(
            'details' => sprintf( 'Downloaded %s', size_format( $file_size ) ),
            'version_to' => $this->target_version
        ) );

        return $temp_file;
    }

    /**
     * Verify downloaded package
     *
     * @param string $file Package file path
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function verify_package( $file ) {
        if ( ! CUFT_Filesystem_Handler::exists( $file ) ) {
            return new WP_Error( 'file_not_found', 'Downloaded file not found' );
        }

        // Check file size
        $file_size = CUFT_Filesystem_Handler::size( $file );
        if ( $file_size < 1000 ) {
            return new WP_Error( 'file_too_small', 'Downloaded file appears to be corrupted (too small)' );
        }

        // Verify it's a valid ZIP
        $verify_result = CUFT_GitHub_API::verify_download( $file );
        if ( ! $verify_result ) {
            return new WP_Error( 'invalid_zip', 'Downloaded file is not a valid ZIP archive' );
        }

        return true;
    }

    /**
     * Create backup before update
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function create_backup() {
        $backup = CUFT_Backup_Manager::create_backup( 'pre-update-' . $this->target_version );

        if ( is_wp_error( $backup ) ) {
            return $backup;
        }

        $this->backup = $backup;
        return true;
    }

    /**
     * Extract package to temporary directory
     *
     * @param string $package_file Package file path
     * @return string|WP_Error Extracted directory path or WP_Error
     */
    private function extract_package( $package_file ) {
        $temp_dir = CUFT_Filesystem_Handler::get_temp_dir() . '/cuft_update_' . time();

        // Create temp directory
        if ( ! CUFT_Filesystem_Handler::mkdir( $temp_dir ) ) {
            return new WP_Error( 'mkdir_failed', 'Failed to create temporary directory' );
        }

        // Extract
        $result = CUFT_Filesystem_Handler::unzip( $package_file, $temp_dir );

        if ( is_wp_error( $result ) ) {
            CUFT_Filesystem_Handler::delete( $temp_dir, true );
            return $result;
        }

        return $temp_dir;
    }

    /**
     * Install extracted files
     *
     * @param string $source_dir Source directory
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function install_files( $source_dir ) {
        // Find the plugin directory within extracted files
        $plugin_source = $this->find_plugin_source( $source_dir );

        if ( is_wp_error( $plugin_source ) ) {
            return $plugin_source;
        }

        // Get destination
        $plugin_dir = CUFT_Filesystem_Handler::get_plugin_dir();

        // Remove current plugin files (backup already created)
        if ( CUFT_Filesystem_Handler::exists( $plugin_dir ) ) {
            if ( ! CUFT_Filesystem_Handler::delete( $plugin_dir, true ) ) {
                return new WP_Error( 'delete_failed', 'Failed to remove old plugin files' );
            }
        }

        // Copy new files
        if ( ! $this->copy_directory( $plugin_source, $plugin_dir ) ) {
            // Attempt rollback
            if ( $this->backup ) {
                CUFT_Backup_Manager::restore_backup( $this->backup['name'] );
            }

            return new WP_Error( 'copy_failed', 'Failed to copy new plugin files' );
        }

        return true;
    }

    /**
     * Find plugin source directory in extracted files
     *
     * @param string $source_dir Source directory
     * @return string|WP_Error Plugin directory or WP_Error
     */
    private function find_plugin_source( $source_dir ) {
        // Check if source_dir directly contains plugin files
        if ( CUFT_Filesystem_Handler::exists( $source_dir . '/choice-universal-form-tracker.php' ) ) {
            return $source_dir;
        }

        // Check for choice-uft subdirectory
        if ( CUFT_Filesystem_Handler::exists( $source_dir . '/choice-uft/choice-universal-form-tracker.php' ) ) {
            return $source_dir . '/choice-uft';
        }

        // Look for any directory containing the main plugin file
        $items = CUFT_Filesystem_Handler::dirlist( $source_dir, false, false );

        if ( is_array( $items ) ) {
            foreach ( $items as $item_name => $item_info ) {
                if ( $item_info['type'] === 'd' ) {
                    $potential_source = $source_dir . '/' . $item_name;
                    if ( CUFT_Filesystem_Handler::exists( $potential_source . '/choice-universal-form-tracker.php' ) ) {
                        return $potential_source;
                    }
                }
            }
        }

        return new WP_Error( 'plugin_source_not_found', 'Could not find plugin files in extracted package' );
    }

    /**
     * Copy directory (wrapper for backup manager method)
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return bool True on success
     */
    private function copy_directory( $source, $destination ) {
        // Use reflection to access private method from backup manager
        try {
            $reflection = new ReflectionClass( 'CUFT_Backup_Manager' );
            $method = $reflection->getMethod( 'copy_directory' );
            $method->setAccessible( true );
            return $method->invoke( null, $source, $destination );
        } catch ( Exception $e ) {
            return false;
        }
    }

    /**
     * Verify installation
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function verify_installation() {
        $plugin_dir = CUFT_Filesystem_Handler::get_plugin_dir();

        // Check main plugin file exists
        $main_file = $plugin_dir . '/choice-universal-form-tracker.php';
        if ( ! CUFT_Filesystem_Handler::exists( $main_file ) ) {
            return new WP_Error( 'main_file_missing', 'Main plugin file not found after installation' );
        }

        // Check critical directories exist
        $critical_dirs = array( 'includes', 'assets' );
        foreach ( $critical_dirs as $dir ) {
            if ( ! CUFT_Filesystem_Handler::exists( $plugin_dir . '/' . $dir ) ) {
                return new WP_Error( 'critical_dir_missing', "Critical directory missing: {$dir}" );
            }
        }

        return true;
    }

    /**
     * Handle update failure
     *
     * @param WP_Error $error Error object
     * @return WP_Error Error with rollback status
     */
    private function handle_failure( $error ) {
        // Build detailed error message
        $error_code = $error->get_error_code();
        $error_message = $error->get_error_message();
        $detailed_message = sprintf(
            '[%s] %s (Update ID: %s, Target: %s)',
            $error_code,
            $error_message,
            $this->update_id,
            $this->target_version
        );

        // Set failed progress with detailed message
        CUFT_Update_Progress::set_failed( $detailed_message );

        // Log detailed error
        CUFT_Update_Log::log_error( $error_message, array(
            'error_code' => $error_code,
            'update_id' => $this->update_id,
            'version_to' => $this->target_version,
            'timestamp' => current_time( 'mysql' ),
            'user_id' => get_current_user_id()
        ) );

        // Log to PHP error log for debugging
        error_log( 'CUFT Update Failed: ' . $detailed_message );

        // Attempt rollback if backup exists
        if ( $this->backup ) {
            CUFT_Update_Progress::set_rolling_back( 'Attempting to restore previous version...' );

            $rollback_result = CUFT_Backup_Manager::restore_backup( $this->backup['name'] );

            if ( is_wp_error( $rollback_result ) ) {
                // Rollback failed
                $error->add( 'rollback_failed', 'Automatic rollback also failed: ' . $rollback_result->get_error_message() );
            } else {
                // Rollback successful
                $error->add( 'rollback_complete', 'Previous version has been restored' );
            }
        }

        // Clear caches even on failure to ensure fresh state
        self::invalidate_all_caches();

        return $error;
    }

    /**
     * Cleanup temporary file
     *
     * @param string $file File path
     * @return void
     */
    private function cleanup_temp_file( $file ) {
        if ( $file && CUFT_Filesystem_Handler::exists( $file ) ) {
            CUFT_Filesystem_Handler::delete( $file );
        }
    }

    /**
     * Cleanup temporary files
     *
     * @param string $package_file Package file path
     * @param string $extract_dir Extracted directory path
     * @return void
     */
    private function cleanup_temp_files( $package_file, $extract_dir ) {
        $this->cleanup_temp_file( $package_file );

        if ( $extract_dir && CUFT_Filesystem_Handler::exists( $extract_dir ) ) {
            CUFT_Filesystem_Handler::delete( $extract_dir, true );
        }
    }

    /**
     * Update progress
     *
     * @param string $status Status
     * @param int $percentage Percentage
     * @param string $message Message
     * @return void
     */
    private function update_progress( $status, $percentage, $message ) {
        CUFT_Update_Progress::set( $status, $percentage, $message );
    }

    /**
     * Invalidate all update-related caches
     *
     * Clears all caches that might contain stale update information
     * after a successful update to ensure fresh data is displayed.
     *
     * @return void
     */
    private static function invalidate_all_caches() {
        // Clear update status and progress
        CUFT_Update_Status::clear();
        CUFT_Update_Progress::clear();

        // Clear GitHub API caches
        if ( class_exists( 'CUFT_GitHub_API' ) ) {
            CUFT_GitHub_API::clear_cache();
        }

        // Clear WordPress update transients
        delete_site_transient( 'update_plugins' );
        delete_site_transient( 'update_themes' );
        delete_site_transient( 'update_core' );

        // Clear plugin cache
        wp_clean_plugins_cache();

        // Clear any custom transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cuft_%' OR option_name LIKE '_transient_timeout_cuft_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_cuft_%' OR option_name LIKE '_site_transient_timeout_cuft_%'" );

        // Clear object cache if available
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        // Log cache invalidation
        if ( class_exists( 'CUFT_Update_Log' ) ) {
            CUFT_Update_Log::log( 'cache_invalidated', 'info', array(
                'details' => 'All update-related caches cleared after successful update'
            ) );
        }
    }

    /**
     * Set update completion transient
     *
     * Creates a transient that indicates an update has just completed.
     * This helps synchronize status across different interfaces.
     *
     * @param string $version The version that was updated to
     * @return void
     */
    private static function set_update_completion_transient( $version ) {
        $completion_data = array(
            'completed_at' => current_time( 'c' ),
            'version' => $version,
            'user_id' => get_current_user_id(),
            'timestamp' => time()
        );

        // Set transient for 1 hour to allow for interface synchronization
        set_site_transient( 'cuft_update_completed', $completion_data, HOUR_IN_SECONDS );

        // Log completion
        if ( class_exists( 'CUFT_Update_Log' ) ) {
            CUFT_Update_Log::log( 'update_completed', 'success', array(
                'details' => "Update completed successfully to version {$version}",
                'version_to' => $version,
                'user_id' => get_current_user_id()
            ) );
        }
    }
}