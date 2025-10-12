<?php
/**
 * Update Validator Class
 *
 * Handles download validation for plugin updates including file size
 * verification and ZIP format validation.
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
 * Class CUFT_Update_Validator
 *
 * Validates downloaded update packages before installation.
 *
 * @since 3.17.0
 */
class CUFT_Update_Validator {

	/**
	 * File size tolerance percentage
	 *
	 * Allows ±5% variance for compression differences.
	 *
	 * @since 3.17.0
	 * @var float
	 */
	const SIZE_TOLERANCE = 0.05; // ±5%

	/**
	 * Plugin slug
	 *
	 * @since 3.17.0
	 * @var string
	 */
	private $plugin_slug = 'choice-uft';

	/**
	 * Initialize the validator
	 *
	 * Registers WordPress hooks for validation.
	 *
	 * @since 3.17.0
	 */
	public function __construct() {
		// Hook into upgrade process before installation
		add_filter( 'upgrader_pre_install', array( $this, 'validate_download' ), 10, 2 );

		// Register daily cleanup cron job
		add_action( 'cuft_cleanup_orphaned_downloads', array( $this, 'cleanup_orphaned_downloads' ) );

		// Schedule cron job if not already scheduled
		if ( ! wp_next_scheduled( 'cuft_cleanup_orphaned_downloads' ) ) {
			wp_schedule_event( time(), 'daily', 'cuft_cleanup_orphaned_downloads' );
		}
	}

	/**
	 * Validate downloaded package before installation
	 *
	 * Hooks into upgrader_pre_install filter to validate the downloaded
	 * ZIP file before WordPress extracts and installs it.
	 *
	 * @since 3.17.0
	 *
	 * @param bool|WP_Error $return Installation response.
	 * @param array         $plugin Plugin data.
	 * @return bool|WP_Error True if validation passes, WP_Error on failure.
	 */
	public function validate_download( $return, $plugin ) {
		// Only process our plugin
		if ( ! isset( $plugin['plugin'] ) || dirname( $plugin['plugin'] ) !== $this->plugin_slug ) {
			return $return;
		}

		// Get download package path from upgrader
		global $wp_filesystem;

		// The package path is stored in the upgrader's skin feedback
		// We need to access the upgrader instance to get the package path
		// This is available in the global $wp_upgrader during the upgrade process
		$package_path = $this->get_package_path();

		if ( ! $package_path || ! file_exists( $package_path ) ) {
			// Cannot validate if package path is not available
			// Let WordPress handle this error
			return $return;
		}

		// Validate file size
		$size_validation = $this->validate_file_size( $package_path );
		if ( is_wp_error( $size_validation ) ) {
			$this->cleanup_invalid_download( $package_path );
			return $size_validation;
		}

		// Validate ZIP format
		$zip_validation = $this->validate_zip_format( $package_path );
		if ( is_wp_error( $zip_validation ) ) {
			$this->cleanup_invalid_download( $package_path );
			return $zip_validation;
		}

		return $return;
	}

	/**
	 * Get package path from global upgrader
	 *
	 * Accesses the global $wp_upgrader to retrieve the download package path.
	 *
	 * @since 3.17.0
	 *
	 * @return string|false Package path or false if not available.
	 */
	private function get_package_path() {
		global $wp_upgrader;

		if ( empty( $wp_upgrader ) || ! isset( $wp_upgrader->skin ) ) {
			return false;
		}

		// Try to get package from skin's options
		if ( isset( $wp_upgrader->skin->options['package'] ) ) {
			return $wp_upgrader->skin->options['package'];
		}

		// Fallback: check upgrader's result
		if ( isset( $wp_upgrader->result ) && is_array( $wp_upgrader->result ) ) {
			if ( isset( $wp_upgrader->result['source'] ) ) {
				return $wp_upgrader->result['source'];
			}
		}

		return false;
	}

	/**
	 * Validate file size with ±5% tolerance
	 *
	 * Compares downloaded file size with expected size, allowing for
	 * compression variance.
	 *
	 * @since 3.17.0
	 *
	 * @param string $file_path Path to downloaded file.
	 * @param int    $expected_size Expected file size in bytes (optional).
	 * @return bool|WP_Error True if valid, WP_Error on mismatch.
	 */
	public function validate_file_size( $file_path, $expected_size = null ) {
		// Get actual file size
		$actual_size = filesize( $file_path );

		if ( $actual_size === false ) {
			return new WP_Error(
				'file_size_unknown',
				__( 'Could not determine downloaded file size.', 'choice-uft' )
			);
		}

		// If expected size not provided, try to get it from GitHub API
		if ( $expected_size === null ) {
			$expected_size = $this->get_expected_file_size();
		}

		// If we still don't have expected size, skip size validation
		// (fallback: let ZIP validation catch corrupted files)
		if ( $expected_size === null ) {
			return true;
		}

		// Calculate tolerance range
		$min_size = $expected_size * ( 1 - self::SIZE_TOLERANCE );
		$max_size = $expected_size * ( 1 + self::SIZE_TOLERANCE );

		// Check if actual size is within tolerance
		if ( $actual_size < $min_size || $actual_size > $max_size ) {
			return new WP_Error(
				'file_size_mismatch',
				sprintf(
					/* translators: 1: Expected size in MB, 2: Actual size in MB */
					__( 'Download verification failed: File size mismatch. Expected %1$s MB, got %2$s MB. Please try again.', 'choice-uft' ),
					number_format( $expected_size / 1048576, 2 ),
					number_format( $actual_size / 1048576, 2 )
				)
			);
		}

		return true;
	}

	/**
	 * Get expected file size from GitHub API
	 *
	 * Retrieves the expected file size from cached plugin metadata.
	 *
	 * @since 3.17.0
	 *
	 * @return int|null Expected file size in bytes or null if unavailable.
	 */
	private function get_expected_file_size() {
		// Check if plugin info class exists
		if ( ! class_exists( 'CUFT_Plugin_Info' ) ) {
			return null;
		}

		// Get cached plugin info
		$plugin_info = get_transient( 'cuft_plugin_info' );

		if ( empty( $plugin_info ) || ! isset( $plugin_info['download_size'] ) ) {
			return null;
		}

		return (int) $plugin_info['download_size'];
	}

	/**
	 * Validate ZIP format
	 *
	 * Verifies that the downloaded file is a valid ZIP archive.
	 *
	 * @since 3.17.0
	 *
	 * @param string $file_path Path to ZIP file.
	 * @return bool|WP_Error True if valid ZIP, WP_Error on failure.
	 */
	public function validate_zip_format( $file_path ) {
		// Check if file exists
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'zip_not_found',
				__( 'Downloaded file not found.', 'choice-uft' )
			);
		}

		// Check if file is readable
		if ( ! is_readable( $file_path ) ) {
			return new WP_Error(
				'zip_not_readable',
				__( 'Downloaded file is not readable.', 'choice-uft' )
			);
		}

		// Method 1: Check ZIP magic number (fast check)
		$file_handle = fopen( $file_path, 'rb' );
		if ( $file_handle === false ) {
			return new WP_Error(
				'zip_open_failed',
				__( 'Could not open downloaded file for validation.', 'choice-uft' )
			);
		}

		$magic_number = fread( $file_handle, 4 );
		fclose( $file_handle );

		// ZIP files start with "PK\x03\x04" or "PK\x05\x06" (empty archive)
		if ( substr( $magic_number, 0, 2 ) !== 'PK' ) {
			return new WP_Error(
				'invalid_zip_format',
				__( 'Downloaded file is not a valid ZIP archive.', 'choice-uft' )
			);
		}

		// Method 2: Try to open with ZipArchive if available
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			$result = $zip->open( $file_path, ZipArchive::CHECKCONS );

			if ( $result !== true ) {
				$error_message = $this->get_zip_error_message( $result );

				// Log detailed error
				error_log( sprintf(
					'CUFT Update Validator: ZIP validation failed for %s - %s (Error code: %d)',
					basename( $file_path ),
					$error_message,
					$result
				) );

				return new WP_Error(
					'zip_validation_failed',
					sprintf(
						/* translators: %s: Error message */
						__( 'Downloaded ZIP file validation failed: %s', 'choice-uft' ),
						$error_message
					)
				);
			}

			// Verify ZIP contains at least one file
			if ( $zip->numFiles === 0 ) {
				$zip->close();
				return new WP_Error(
					'zip_empty',
					__( 'Downloaded ZIP file is empty.', 'choice-uft' )
				);
			}

			$zip->close();
		}

		// Method 3: Use WordPress's unzip_file with dry-run
		// This is the most reliable method as it uses the same code that will extract the ZIP
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Test extraction to a temporary directory
		$temp_dir = get_temp_dir() . 'cuft-zip-test-' . uniqid();

		// Use WordPress function with error suppression (we'll check the return value)
		$extract_result = @unzip_file( $file_path, $temp_dir );

		// Clean up test extraction
		if ( is_dir( $temp_dir ) ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				WP_Filesystem();
			}
			if ( ! empty( $wp_filesystem ) ) {
				$wp_filesystem->delete( $temp_dir, true );
			}
		}

		// Check extraction result
		if ( is_wp_error( $extract_result ) ) {
			return new WP_Error(
				'zip_extraction_test_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'ZIP file validation failed: %s', 'choice-uft' ),
					$extract_result->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Get human-readable ZIP error message
	 *
	 * Converts ZipArchive error codes to readable messages.
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

	/**
	 * Clean up invalid download immediately
	 *
	 * Deletes a failed download file immediately after validation failure.
	 *
	 * @since 3.17.0
	 *
	 * @param string $file_path Path to file to delete.
	 * @return bool True on success, false on failure.
	 */
	public function cleanup_invalid_download( $file_path ) {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return false;
		}

		// Log cleanup action
		error_log( sprintf(
			'CUFT Update Validator: Cleaning up invalid download: %s',
			basename( $file_path )
		) );

		// Use WordPress Filesystem API for safe deletion
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( ! empty( $wp_filesystem ) ) {
			$wp_path = str_replace( ABSPATH, $wp_filesystem->abspath(), $file_path );
			return $wp_filesystem->delete( $wp_path, false );
		}

		// Fallback to PHP unlink if WP_Filesystem not available
		return @unlink( $file_path );
	}

	/**
	 * Clean up orphaned download files
	 *
	 * Scheduled via WP-Cron to run daily. Removes CUFT download files
	 * older than 24 hours from the WordPress temp directory.
	 *
	 * @since 3.17.0
	 *
	 * @return int Number of files deleted.
	 */
	public function cleanup_orphaned_downloads() {
		global $wp_filesystem;

		// Initialize filesystem
		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			error_log( 'CUFT Update Validator: Could not initialize WP_Filesystem for cleanup' );
			return 0;
		}

		// Get temp directory
		$temp_dir = get_temp_dir();
		$wp_temp_dir = str_replace( ABSPATH, $wp_filesystem->abspath(), $temp_dir );

		// Check if temp directory exists and is readable
		if ( ! $wp_filesystem->is_dir( $wp_temp_dir ) ) {
			return 0;
		}

		// Get directory listing
		$files = $wp_filesystem->dirlist( $wp_temp_dir, false );

		if ( empty( $files ) ) {
			return 0;
		}

		$deleted_count = 0;
		$cutoff_time = time() - DAY_IN_SECONDS;

		foreach ( $files as $file => $file_info ) {
			// Only process CUFT-related files
			if ( strpos( $file, 'choice-uft' ) === false && strpos( $file, 'cuft-' ) === false ) {
				continue;
			}

			// Only process ZIP files
			if ( substr( $file, -4 ) !== '.zip' ) {
				continue;
			}

			// Check file age
			if ( $file_info['lastmodunix'] < $cutoff_time ) {
				$file_path = trailingslashit( $wp_temp_dir ) . $file;

				if ( $wp_filesystem->delete( $file_path, false ) ) {
					$deleted_count++;
					error_log( sprintf(
						'CUFT Update Validator: Cleaned up orphaned file: %s (age: %d hours)',
						$file,
						( time() - $file_info['lastmodunix'] ) / 3600
					) );
				}
			}
		}

		if ( $deleted_count > 0 ) {
			error_log( sprintf(
				'CUFT Update Validator: Cleanup complete - removed %d orphaned file(s)',
				$deleted_count
			) );
		}

		return $deleted_count;
	}

	/**
	 * Deactivate cron job on plugin deactivation
	 *
	 * Called during plugin deactivation to clean up scheduled tasks.
	 *
	 * @since 3.17.0
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'cuft_cleanup_orphaned_downloads' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cuft_cleanup_orphaned_downloads' );
		}
	}
}
