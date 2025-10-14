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
	 * @param string $path Directory path to measure.
	 * @return int Total size in bytes.
	 */
	public static function get_directory_size( $path ) {
		if ( ! is_dir( $path ) ) {
			return 0;
		}

		$total_size = 0;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$total_size += $file->getSize();
				}
			}
		} catch ( Exception $e ) {
			// If we can't iterate, return 0
			return 0;
		}

		return $total_size;
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
	 * Default multiplier is 3x plugin size.
	 *
	 * @param string $plugin_path        Path to plugin directory.
	 * @param int    $required_multiplier Space multiplier (default 3 = 3x plugin size).
	 * @return bool|WP_Error True if sufficient space, WP_Error if insufficient.
	 */
	public static function validate_space_for_reinstall( $plugin_path, $required_multiplier = 3 ) {
		// Get plugin size
		$plugin_size = self::get_directory_size( $plugin_path );

		if ( 0 === $plugin_size ) {
			return new WP_Error(
				'cannot_determine_plugin_size',
				__( 'Unable to determine plugin size. Cannot validate disk space.', 'choice-universal-form-tracker' )
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
			return new WP_Error(
				'insufficient_disk_space',
				sprintf(
					/* translators: 1: required space in MB, 2: available space in MB */
					__( 'Insufficient disk space to create backup. Free at least %1$s and try again. Currently available: %2$s.', 'choice-universal-form-tracker' ),
					size_format( $required_space, 2 ),
					size_format( $available_space, 2 )
				),
				array(
					'required_space_mb'  => round( $required_space / ( 1024 * 1024 ), 2 ),
					'available_space_mb' => round( $available_space / ( 1024 * 1024 ), 2 ),
				)
			);
		}

		return true;
	}
}
