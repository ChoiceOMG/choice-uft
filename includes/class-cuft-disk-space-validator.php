<?php
/**
 * Disk Space Validator
 *
 * Utility to check available disk space before force reinstall operations.
 * Validates that sufficient space exists for backup, download, and extraction.
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
 * CUFT_Disk_Space_Validator class.
 *
 * Provides disk space validation to prevent failed installations due to
 * insufficient storage. Requires 3x plugin size by default (backup + download + extraction).
 *
 * @since 3.19.0
 */
class CUFT_Disk_Space_Validator {

	/**
	 * Get directory size recursively
	 *
	 * Handles permission errors gracefully by skipping inaccessible directories.
	 *
	 * @param string $path Directory path to measure.
	 * @return int Total size in bytes, or 0 if directory cannot be accessed.
	 */
	public static function get_directory_size( $path ) {
		if ( ! is_dir( $path ) || ! is_readable( $path ) ) {
			return 0;
		}

		$total_size = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ( $iterator as $file ) {
				try {
					if ( $file->isFile() && $file->isReadable() ) {
						$total_size += $file->getSize();
					}
				} catch ( Exception $e ) {
					// Skip files we can't read
					continue;
				}
			}
		} catch ( UnexpectedValueException $e ) {
			// Permission denied on subdirectory - log and continue with what we have
			if ( function_exists( 'error_log' ) ) {
				error_log( sprintf( 'CUFT: Permission denied scanning directory %s: %s', $path, $e->getMessage() ) );
			}
			// Return current size - better than failing completely
			return $total_size > 0 ? $total_size : 1048576; // Fallback to 1MB minimum estimate
		} catch ( Exception $e ) {
			// Other errors - return fallback estimate
			return 1048576; // 1MB minimum estimate to allow operation to proceed
		}

		// If we got zero bytes, something is wrong - use minimum estimate
		return $total_size > 0 ? $total_size : 1048576;
	}

	/**
	 * Get available disk space
	 *
	 * @param string $path Path to check available space for.
	 * @return int|false Available space in bytes, or false on error.
	 */
	public static function get_available_space( $path ) {
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$free_space = @disk_free_space( $path );

		return false === $free_space ? false : $free_space;
	}

	/**
	 * Validate space for force reinstall operation
	 *
	 * Checks if sufficient disk space exists for backup, download, and extraction.
	 * Default multiplier is 3x plugin size. Handles permission errors gracefully by
	 * using fallback estimates when necessary.
	 *
	 * @param string $plugin_path        Path to plugin directory.
	 * @param int    $required_multiplier Space multiplier (default 3 = 3x plugin size).
	 * @return bool|WP_Error True if sufficient space, WP_Error if insufficient.
	 */
	public static function validate_space_for_reinstall( $plugin_path, $required_multiplier = 3 ) {
		// Get plugin size (with fallback handling for permission errors)
		$plugin_size = self::get_directory_size( $plugin_path );

		// If we got the minimum fallback estimate (1MB), that means we encountered permission errors
		// but still want to allow the operation if there's sufficient space
		$is_estimate = ( 1048576 === $plugin_size );

		if ( 0 === $plugin_size ) {
			// Only fail if we truly couldn't read anything at all
			return new WP_Error(
				'cannot_determine_plugin_size',
				__( 'Unable to access plugin directory. Check file permissions.', 'choice-universal-form-tracker' )
			);
		}

		// Calculate required space
		$required_space = $plugin_size * $required_multiplier;

		// Get available space (check wp-content directory)
		$available_space = self::get_available_space( WP_CONTENT_DIR );

		if ( false === $available_space ) {
			return new WP_Error(
				'cannot_determine_disk_space',
				__( 'Unable to determine available disk space. Cannot validate.', 'choice-universal-form-tracker' )
			);
		}

		// Check if sufficient space exists
		if ( $available_space < $required_space ) {
			$message = $is_estimate
				? sprintf(
					/* translators: 1: required space in MB, 2: available space in MB */
					__( 'Insufficient disk space. At least %1$s needed. Currently available: %2$s. (Note: Plugin size estimated due to permission restrictions)', 'choice-universal-form-tracker' ),
					size_format( $required_space, 2 ),
					size_format( $available_space, 2 )
				)
				: sprintf(
					/* translators: 1: required space in MB, 2: available space in MB */
					__( 'Insufficient disk space to create backup. Free at least %1$s and try again. Currently available: %2$s.', 'choice-universal-form-tracker' ),
					size_format( $required_space, 2 ),
					size_format( $available_space, 2 )
				);

			return new WP_Error(
				'insufficient_disk_space',
				$message,
				array(
					'required_space_mb'  => round( $required_space / ( 1024 * 1024 ), 2 ),
					'available_space_mb' => round( $available_space / ( 1024 * 1024 ), 2 ),
					'is_estimate'        => $is_estimate,
				)
			);
		}

		return true;
	}
}
