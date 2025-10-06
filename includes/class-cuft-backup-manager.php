<?php
/**
 * Backup Manager Service
 *
 * Handles plugin backup and rollback operations for safe updates.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT Backup Manager
 *
 * Creates backups before updates and manages rollback operations.
 */
class CUFT_Backup_Manager {

    /**
     * Backup directory name
     */
    const BACKUP_DIR = 'cuft-backups';

    /**
     * Maximum backups to keep
     */
    const MAX_BACKUPS = 3;

    /**
     * Create backup of current plugin
     *
     * @param string $label Backup label (optional)
     * @return array|WP_Error Backup info or WP_Error on failure
     */
    public static function create_backup( $label = '' ) {
        // Initialize filesystem
        $fs_result = CUFT_Filesystem_Handler::init();
        if ( is_wp_error( $fs_result ) ) {
            return $fs_result;
        }

        // Get plugin directory
        $plugin_dir = CUFT_Filesystem_Handler::get_plugin_dir();
        if ( ! CUFT_Filesystem_Handler::exists( $plugin_dir ) ) {
            return new WP_Error( 'plugin_dir_not_found', 'Plugin directory not found' );
        }

        // Create backup directory
        $backup_root = self::get_backup_directory();
        $verify_result = CUFT_Filesystem_Handler::verify_writable_directory( $backup_root );
        if ( is_wp_error( $verify_result ) ) {
            return $verify_result;
        }

        // Generate backup name
        $timestamp = time();
        $version = CUFT_VERSION;
        $backup_name = sprintf(
            'backup-%s-%s-%d',
            sanitize_file_name( $version ),
            sanitize_file_name( $label ?: 'auto' ),
            $timestamp
        );

        $backup_path = $backup_root . '/' . $backup_name;

        // Check disk space
        $plugin_size = self::get_directory_size( $plugin_dir );
        $space_check = CUFT_Filesystem_Handler::check_disk_space( $plugin_size );
        if ( is_wp_error( $space_check ) ) {
            return $space_check;
        }

        // Copy plugin directory to backup
        if ( ! self::copy_directory( $plugin_dir, $backup_path ) ) {
            return new WP_Error( 'backup_failed', 'Failed to copy plugin files' );
        }

        // Create backup metadata
        $metadata = array(
            'name' => $backup_name,
            'path' => $backup_path,
            'version' => $version,
            'timestamp' => $timestamp,
            'label' => $label,
            'size' => self::get_directory_size( $backup_path ),
            'file_count' => self::count_files( $backup_path )
        );

        // Save metadata
        self::save_backup_metadata( $backup_name, $metadata );

        // Log backup creation
        CUFT_Update_Log::log( 'backup_created', 'success', array(
            'details' => sprintf( 'Backup created: %s (%s)', $backup_name, size_format( $metadata['size'] ) ),
            'version_from' => $version
        ) );

        // Cleanup old backups
        self::cleanup_old_backups();

        return $metadata;
    }

    /**
     * Restore from backup
     *
     * @param string $backup_name Backup name to restore
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function restore_backup( $backup_name ) {
        // Initialize filesystem
        $fs_result = CUFT_Filesystem_Handler::init();
        if ( is_wp_error( $fs_result ) ) {
            return $fs_result;
        }

        // Get backup metadata
        $metadata = self::get_backup_metadata( $backup_name );
        if ( ! $metadata ) {
            return new WP_Error( 'backup_not_found', 'Backup not found: ' . $backup_name );
        }

        $backup_path = $metadata['path'];
        if ( ! CUFT_Filesystem_Handler::exists( $backup_path ) ) {
            return new WP_Error( 'backup_files_missing', 'Backup files not found' );
        }

        // Get plugin directory
        $plugin_dir = CUFT_Filesystem_Handler::get_plugin_dir();

        // Create temporary backup of current state (in case restore fails)
        $temp_backup = self::create_backup( 'pre-restore' );
        if ( is_wp_error( $temp_backup ) ) {
            return $temp_backup;
        }

        // Remove current plugin directory
        if ( CUFT_Filesystem_Handler::exists( $plugin_dir ) ) {
            if ( ! CUFT_Filesystem_Handler::delete( $plugin_dir, true ) ) {
                return new WP_Error( 'delete_failed', 'Failed to remove current plugin files' );
            }
        }

        // Restore from backup
        if ( ! self::copy_directory( $backup_path, $plugin_dir ) ) {
            // Attempt to restore temp backup
            self::copy_directory( $temp_backup['path'], $plugin_dir );

            return new WP_Error( 'restore_failed', 'Failed to restore backup files' );
        }

        // Log restore
        CUFT_Update_Log::log( 'rollback_completed', 'success', array(
            'details' => sprintf( 'Restored from backup: %s', $backup_name ),
            'version_to' => $metadata['version']
        ) );

        // Delete temp backup
        if ( isset( $temp_backup['path'] ) ) {
            CUFT_Filesystem_Handler::delete( $temp_backup['path'], true );
            self::delete_backup_metadata( $temp_backup['name'] );
        }

        return true;
    }

    /**
     * Automatic rollback to latest backup
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function auto_rollback() {
        $backups = self::get_backups();

        if ( empty( $backups ) ) {
            return new WP_Error( 'no_backups', 'No backups available for rollback' );
        }

        // Get most recent backup
        $latest_backup = reset( $backups );

        // Log rollback attempt
        CUFT_Update_Log::log( 'rollback_started', 'info', array(
            'details' => sprintf( 'Automatic rollback to: %s', $latest_backup['name'] ),
            'version_to' => $latest_backup['version']
        ) );

        return self::restore_backup( $latest_backup['name'] );
    }

    /**
     * Get list of available backups
     *
     * @return array Array of backup metadata
     */
    public static function get_backups() {
        $backup_root = self::get_backup_directory();

        if ( ! CUFT_Filesystem_Handler::exists( $backup_root ) ) {
            return array();
        }

        $dirs = CUFT_Filesystem_Handler::dirlist( $backup_root, false, false );

        if ( ! is_array( $dirs ) ) {
            return array();
        }

        $backups = array();

        foreach ( $dirs as $dir_name => $dir_info ) {
            if ( $dir_info['type'] !== 'd' ) {
                continue;
            }

            $metadata = self::get_backup_metadata( $dir_name );
            if ( $metadata ) {
                $backups[] = $metadata;
            }
        }

        // Sort by timestamp (newest first)
        usort( $backups, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    /**
     * Delete backup
     *
     * @param string $backup_name Backup name to delete
     * @return bool True on success
     */
    public static function delete_backup( $backup_name ) {
        $metadata = self::get_backup_metadata( $backup_name );

        if ( ! $metadata ) {
            return false;
        }

        // Delete backup directory
        if ( CUFT_Filesystem_Handler::exists( $metadata['path'] ) ) {
            CUFT_Filesystem_Handler::delete( $metadata['path'], true );
        }

        // Delete metadata
        self::delete_backup_metadata( $backup_name );

        return true;
    }

    /**
     * Cleanup old backups (keep only MAX_BACKUPS)
     *
     * @return int Number of backups deleted
     */
    public static function cleanup_old_backups() {
        $backups = self::get_backups();
        $deleted = 0;

        if ( count( $backups ) <= self::MAX_BACKUPS ) {
            return 0;
        }

        // Remove oldest backups
        $to_delete = array_slice( $backups, self::MAX_BACKUPS );

        foreach ( $to_delete as $backup ) {
            if ( self::delete_backup( $backup['name'] ) ) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get backup directory path
     *
     * @return string Backup directory path
     */
    private static function get_backup_directory() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/' . self::BACKUP_DIR;
    }

    /**
     * Get backup metadata file path
     *
     * @param string $backup_name Backup name
     * @return string Metadata file path
     */
    private static function get_metadata_file( $backup_name ) {
        return self::get_backup_directory() . '/' . $backup_name . '/metadata.json';
    }

    /**
     * Save backup metadata
     *
     * @param string $backup_name Backup name
     * @param array $metadata Metadata to save
     * @return bool True on success
     */
    private static function save_backup_metadata( $backup_name, $metadata ) {
        $file = self::get_metadata_file( $backup_name );
        $json = wp_json_encode( $metadata, JSON_PRETTY_PRINT );

        return CUFT_Filesystem_Handler::put_contents( $file, $json );
    }

    /**
     * Get backup metadata
     *
     * @param string $backup_name Backup name
     * @return array|null Metadata or null if not found
     */
    private static function get_backup_metadata( $backup_name ) {
        $file = self::get_metadata_file( $backup_name );

        if ( ! CUFT_Filesystem_Handler::exists( $file ) ) {
            return null;
        }

        $json = CUFT_Filesystem_Handler::get_contents( $file );
        if ( ! $json ) {
            return null;
        }

        return json_decode( $json, true );
    }

    /**
     * Delete backup metadata
     *
     * @param string $backup_name Backup name
     * @return bool True on success
     */
    private static function delete_backup_metadata( $backup_name ) {
        $file = self::get_metadata_file( $backup_name );

        if ( CUFT_Filesystem_Handler::exists( $file ) ) {
            return CUFT_Filesystem_Handler::delete( $file );
        }

        return true;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return bool True on success
     */
    private static function copy_directory( $source, $destination ) {
        // Create destination directory
        if ( ! CUFT_Filesystem_Handler::mkdir( $destination ) ) {
            return false;
        }

        // Get directory listing
        $items = CUFT_Filesystem_Handler::dirlist( $source, false, false );

        if ( ! is_array( $items ) ) {
            return false;
        }

        foreach ( $items as $item_name => $item_info ) {
            $source_path = $source . '/' . $item_name;
            $dest_path = $destination . '/' . $item_name;

            if ( $item_info['type'] === 'd' ) {
                // Recursively copy directory
                if ( ! self::copy_directory( $source_path, $dest_path ) ) {
                    return false;
                }
            } else {
                // Copy file
                if ( ! CUFT_Filesystem_Handler::copy( $source_path, $dest_path, true ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get directory size in bytes
     *
     * @param string $directory Directory path
     * @return int Size in bytes
     */
    private static function get_directory_size( $directory ) {
        $size = 0;

        $items = CUFT_Filesystem_Handler::dirlist( $directory, false, true );

        if ( ! is_array( $items ) ) {
            return 0;
        }

        foreach ( $items as $item_name => $item_info ) {
            if ( $item_info['type'] === 'f' ) {
                $size += isset( $item_info['size'] ) ? intval( $item_info['size'] ) : 0;
            }
        }

        return $size;
    }

    /**
     * Count files in directory
     *
     * @param string $directory Directory path
     * @return int File count
     */
    private static function count_files( $directory ) {
        $count = 0;

        $items = CUFT_Filesystem_Handler::dirlist( $directory, false, true );

        if ( ! is_array( $items ) ) {
            return 0;
        }

        foreach ( $items as $item_info ) {
            if ( $item_info['type'] === 'f' ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verify backup integrity
     *
     * @param string $backup_name Backup name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function verify_backup( $backup_name ) {
        $metadata = self::get_backup_metadata( $backup_name );

        if ( ! $metadata ) {
            return new WP_Error( 'metadata_missing', 'Backup metadata not found' );
        }

        if ( ! CUFT_Filesystem_Handler::exists( $metadata['path'] ) ) {
            return new WP_Error( 'files_missing', 'Backup files not found' );
        }

        // Verify file count
        $actual_count = self::count_files( $metadata['path'] );
        if ( $actual_count !== $metadata['file_count'] ) {
            return new WP_Error(
                'file_count_mismatch',
                sprintf( 'File count mismatch. Expected: %d, Found: %d', $metadata['file_count'], $actual_count )
            );
        }

        return true;
    }

    /**
     * Get backup statistics
     *
     * @return array Statistics
     */
    public static function get_statistics() {
        $backups = self::get_backups();
        $total_size = 0;

        foreach ( $backups as $backup ) {
            $total_size += $backup['size'];
        }

        return array(
            'total_backups' => count( $backups ),
            'total_size' => $total_size,
            'total_size_formatted' => size_format( $total_size ),
            'oldest_backup' => ! empty( $backups ) ? end( $backups ) : null,
            'newest_backup' => ! empty( $backups ) ? reset( $backups ) : null,
            'backup_directory' => self::get_backup_directory()
        );
    }
}