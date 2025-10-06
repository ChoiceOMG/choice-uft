<?php
/**
 * Filesystem Handler Service
 *
 * Safe wrapper for WordPress filesystem operations during updates.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT Filesystem Handler
 *
 * Provides safe filesystem operations using WP_Filesystem.
 */
class CUFT_Filesystem_Handler {

    /**
     * WP_Filesystem instance
     *
     * @var WP_Filesystem_Base
     */
    private static $filesystem = null;

    /**
     * Initialize WP_Filesystem
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function init() {
        if ( self::$filesystem !== null ) {
            return true;
        }

        global $wp_filesystem;

        // Load WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Initialize filesystem
        $credentials = self::get_credentials();

        if ( ! WP_Filesystem( $credentials ) ) {
            return new WP_Error(
                'filesystem_init_failed',
                'Failed to initialize WordPress filesystem'
            );
        }

        self::$filesystem = $wp_filesystem;

        return true;
    }

    /**
     * Get filesystem credentials
     *
     * @return array|bool Credentials or false
     */
    private static function get_credentials() {
        // Try to get credentials from request
        $url = wp_nonce_url( admin_url(), 'cuft-filesystem-credentials' );

        if ( false === ( $credentials = request_filesystem_credentials( $url, '', false, false, null ) ) ) {
            // Direct filesystem access
            return false;
        }

        return $credentials;
    }

    /**
     * Get WP_Filesystem instance
     *
     * @return WP_Filesystem_Base|null Filesystem instance
     */
    private static function get_filesystem() {
        if ( self::$filesystem === null ) {
            $result = self::init();
            if ( is_wp_error( $result ) ) {
                return null;
            }
        }

        return self::$filesystem;
    }

    /**
     * Check if file exists
     *
     * @param string $file File path
     * @return bool True if exists
     */
    public static function exists( $file ) {
        $fs = self::get_filesystem();
        return $fs && $fs->exists( $file );
    }

    /**
     * Read file contents
     *
     * @param string $file File path
     * @return string|false File contents or false on failure
     */
    public static function get_contents( $file ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $file ) ) {
            return false;
        }

        return $fs->get_contents( $file );
    }

    /**
     * Write file contents
     *
     * @param string $file File path
     * @param string $contents Contents to write
     * @param int $mode File permissions (optional)
     * @return bool True on success
     */
    public static function put_contents( $file, $contents, $mode = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return false;
        }

        return $fs->put_contents( $file, $contents, $mode );
    }

    /**
     * Delete file or directory
     *
     * @param string $file File or directory path
     * @param bool $recursive Delete recursively
     * @return bool True on success
     */
    public static function delete( $file, $recursive = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $file ) ) {
            return false;
        }

        return $fs->delete( $file, $recursive );
    }

    /**
     * Copy file or directory
     *
     * @param string $source Source path
     * @param string $destination Destination path
     * @param bool $overwrite Overwrite if exists
     * @return bool True on success
     */
    public static function copy( $source, $destination, $overwrite = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $source ) ) {
            return false;
        }

        // Check if destination exists
        if ( ! $overwrite && $fs->exists( $destination ) ) {
            return false;
        }

        return $fs->copy( $source, $destination, $overwrite );
    }

    /**
     * Move/rename file or directory
     *
     * @param string $source Source path
     * @param string $destination Destination path
     * @param bool $overwrite Overwrite if exists
     * @return bool True on success
     */
    public static function move( $source, $destination, $overwrite = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $source ) ) {
            return false;
        }

        return $fs->move( $source, $destination, $overwrite );
    }

    /**
     * Create directory
     *
     * @param string $path Directory path
     * @param int $chmod Permissions (optional)
     * @return bool True on success
     */
    public static function mkdir( $path, $chmod = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return false;
        }

        // Check if already exists
        if ( $fs->exists( $path ) ) {
            return true;
        }

        return $fs->mkdir( $path, $chmod );
    }

    /**
     * Remove directory
     *
     * @param string $path Directory path
     * @param bool $recursive Remove recursively
     * @return bool True on success
     */
    public static function rmdir( $path, $recursive = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $path ) ) {
            return false;
        }

        return $fs->rmdir( $path, $recursive );
    }

    /**
     * Get directory listing
     *
     * @param string $path Directory path
     * @param bool $include_hidden Include hidden files
     * @param bool $recursive List recursively
     * @return array|false Array of files or false on failure
     */
    public static function dirlist( $path, $include_hidden = true, $recursive = false ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $path ) ) {
            return false;
        }

        return $fs->dirlist( $path, $include_hidden, $recursive );
    }

    /**
     * Check if path is writable
     *
     * @param string $path Path to check
     * @return bool True if writable
     */
    public static function is_writable( $path ) {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return false;
        }

        return $fs->is_writable( $path );
    }

    /**
     * Check if path is readable
     *
     * @param string $path Path to check
     * @return bool True if readable
     */
    public static function is_readable( $path ) {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return false;
        }

        return $fs->is_readable( $path );
    }

    /**
     * Get file size
     *
     * @param string $file File path
     * @return int|false File size in bytes or false
     */
    public static function size( $file ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $file ) ) {
            return false;
        }

        return $fs->size( $file );
    }

    /**
     * Get file modification time
     *
     * @param string $file File path
     * @return int|false Timestamp or false
     */
    public static function mtime( $file ) {
        $fs = self::get_filesystem();

        if ( ! $fs || ! $fs->exists( $file ) ) {
            return false;
        }

        return $fs->mtime( $file );
    }

    /**
     * Extract ZIP archive
     *
     * @param string $file ZIP file path
     * @param string $to Destination directory
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function unzip( $file, $to ) {
        // Load unzip function
        if ( ! function_exists( 'unzip_file' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Verify file exists
        if ( ! self::exists( $file ) ) {
            return new WP_Error( 'file_not_found', 'ZIP file not found' );
        }

        // Create destination directory if needed
        if ( ! self::exists( $to ) ) {
            self::mkdir( $to );
        }

        // Extract
        $result = unzip_file( $file, $to );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Verify directory is writable and create if needed
     *
     * @param string $path Directory path
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function verify_writable_directory( $path ) {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return new WP_Error( 'filesystem_unavailable', 'Filesystem not available' );
        }

        // Create if doesn't exist
        if ( ! $fs->exists( $path ) ) {
            if ( ! self::mkdir( $path ) ) {
                return new WP_Error( 'mkdir_failed', 'Failed to create directory: ' . $path );
            }
        }

        // Verify writable
        if ( ! $fs->is_writable( $path ) ) {
            return new WP_Error( 'not_writable', 'Directory not writable: ' . $path );
        }

        return true;
    }

    /**
     * Get plugin directory path
     *
     * @return string Plugin directory path
     */
    public static function get_plugin_dir() {
        return WP_PLUGIN_DIR . '/choice-uft';
    }

    /**
     * Get temporary directory path
     *
     * @return string Temporary directory path
     */
    public static function get_temp_dir() {
        return get_temp_dir();
    }

    /**
     * Get WordPress upgrade directory
     *
     * @return string Upgrade directory path
     */
    public static function get_upgrade_dir() {
        return WP_CONTENT_DIR . '/upgrade';
    }

    /**
     * Create temporary file
     *
     * @param string $prefix File prefix
     * @return string|false Temporary file path or false
     */
    public static function tempnam( $prefix = 'cuft_' ) {
        return wp_tempnam( $prefix );
    }

    /**
     * Get available disk space
     *
     * @param string $directory Directory to check
     * @return int|false Available bytes or false
     */
    public static function get_free_space( $directory = null ) {
        if ( $directory === null ) {
            $directory = self::get_plugin_dir();
        }

        $fs = self::get_filesystem();

        if ( ! $fs ) {
            // Fallback to native function
            return function_exists( 'disk_free_space' ) ? @disk_free_space( $directory ) : false;
        }

        return $fs->free_space( $directory );
    }

    /**
     * Check if there's enough space for update
     *
     * @param int $required_bytes Required space in bytes
     * @return bool|WP_Error True if enough space, WP_Error if not
     */
    public static function check_disk_space( $required_bytes ) {
        $free_space = self::get_free_space();

        if ( $free_space === false ) {
            // Can't determine, allow update to proceed
            return true;
        }

        // Add 20% buffer
        $required_with_buffer = $required_bytes * 1.2;

        if ( $free_space < $required_with_buffer ) {
            return new WP_Error(
                'insufficient_space',
                sprintf(
                    'Insufficient disk space. Required: %s, Available: %s',
                    size_format( $required_with_buffer ),
                    size_format( $free_space )
                )
            );
        }

        return true;
    }

    /**
     * Clean up temporary files
     *
     * @param string $pattern File pattern (optional)
     * @return int Number of files deleted
     */
    public static function cleanup_temp_files( $pattern = 'cuft_*' ) {
        $temp_dir = self::get_temp_dir();
        $deleted = 0;

        if ( ! self::exists( $temp_dir ) ) {
            return 0;
        }

        $files = glob( $temp_dir . '/' . $pattern );

        if ( ! is_array( $files ) ) {
            return 0;
        }

        foreach ( $files as $file ) {
            // Only delete files older than 1 hour
            if ( self::mtime( $file ) < ( time() - HOUR_IN_SECONDS ) ) {
                if ( self::delete( $file ) ) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Get filesystem method
     *
     * @return string Filesystem method (direct, ssh, ftpext, ftpsockets)
     */
    public static function get_method() {
        $fs = self::get_filesystem();

        if ( ! $fs ) {
            return 'unknown';
        }

        return $fs->method;
    }

    /**
     * Test filesystem operations
     *
     * @return array Test results
     */
    public static function test() {
        $results = array();

        // Test initialization
        $init_result = self::init();
        $results['init'] = ! is_wp_error( $init_result );
        $results['method'] = self::get_method();

        // Test plugin directory
        $plugin_dir = self::get_plugin_dir();
        $results['plugin_dir_exists'] = self::exists( $plugin_dir );
        $results['plugin_dir_writable'] = self::is_writable( $plugin_dir );

        // Test upgrade directory
        $upgrade_dir = self::get_upgrade_dir();
        $results['upgrade_dir_exists'] = self::exists( $upgrade_dir );
        $results['upgrade_dir_writable'] = self::is_writable( $upgrade_dir );

        // Test disk space
        $results['free_space'] = self::get_free_space();
        $results['free_space_formatted'] = $results['free_space'] !== false ?
                                           size_format( $results['free_space'] ) : 'Unknown';

        // Test temp directory
        $temp_dir = self::get_temp_dir();
        $results['temp_dir'] = $temp_dir;
        $results['temp_dir_writable'] = self::is_writable( $temp_dir );

        return $results;
    }
}