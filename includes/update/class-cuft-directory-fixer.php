<?php
/**
 * Directory Naming Fixer for GitHub Plugin Updates
 *
 * Renames extracted plugin directories from GitHub format (choice-uft-v3.17.0)
 * to WordPress format (choice-uft) to ensure correct plugin installation.
 *
 * @package Choice_UFT
 * @subpackage Update
 * @since 3.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CUFT_Directory_Fixer
 *
 * Handles directory renaming during plugin updates to match WordPress expectations.
 */
class CUFT_Directory_Fixer {

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'choice-uft';

	/**
	 * Main plugin file
	 *
	 * @var string
	 */
	const PLUGIN_FILE = 'choice-universal-form-tracker.php';

	/**
	 * Initialize the directory fixer
	 */
	public static function init() {
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_directory_name' ), 10, 4 );
	}

	/**
	 * Fix plugin directory name during update
	 *
	 * @param string      $source        File source location (extracted directory path).
	 * @param string      $remote_source Remote file source location (downloaded ZIP path).
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 * @return string|WP_Error Modified source location or WP_Error on failure.
	 */
	public static function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		// Early exit: not a plugin update.
		if ( ! isset( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return $source;
		}

		// Early exit: plugin not specified.
		if ( ! isset( $hook_extra['plugin'] ) || empty( $hook_extra['plugin'] ) ) {
			return $source;
		}

		// Early exit: not our plugin.
		$plugin_slug = dirname( $hook_extra['plugin'] );
		if ( self::PLUGIN_SLUG !== $plugin_slug ) {
			return $source;
		}

		// Initialize WP_Filesystem if needed.
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			return new WP_Error(
				'filesystem_error',
				__( 'Could not initialize WordPress filesystem.', 'choice-uft' )
			);
		}

		// Get current directory name.
		$source_basename = basename( rtrim( $source, '/' ) );

		// Already correct, no action needed.
		if ( $source_basename === self::PLUGIN_SLUG ) {
			return $source;
		}

		// Validate directory pattern.
		if ( ! self::is_valid_pattern( $source_basename ) ) {
			return new WP_Error(
				'incompatible_plugin_archive',
				__( 'Plugin archive does not contain expected directory structure.', 'choice-uft' )
			);
		}

		// Verify source directory exists.
		if ( ! $wp_filesystem->is_dir( $source ) ) {
			return new WP_Error(
				'source_directory_missing',
				__( 'Extracted plugin directory not found at expected location.', 'choice-uft' )
			);
		}

		// Verify plugin file exists.
		$plugin_file = trailingslashit( $source ) . self::PLUGIN_FILE;
		if ( ! $wp_filesystem->exists( $plugin_file ) ) {
			return new WP_Error(
				'invalid_plugin_structure',
				__( 'Plugin archive does not contain main plugin file.', 'choice-uft' )
			);
		}

		// Build corrected path.
		$corrected_source = trailingslashit( dirname( $source ) ) . self::PLUGIN_SLUG . '/';

		// Rename directory.
		$renamed = $wp_filesystem->move( $source, $corrected_source, true );

		if ( ! $renamed ) {
			return new WP_Error(
				'unable_to_rename_directory',
				sprintf(
					/* translators: 1: old directory name, 2: new directory name */
					__( 'Unable to rename plugin directory from %1$s to %2$s.', 'choice-uft' ),
					$source_basename,
					self::PLUGIN_SLUG
				)
			);
		}

		// Verify rename succeeded.
		if ( ! $wp_filesystem->is_dir( $corrected_source ) ) {
			return new WP_Error(
				'rename_verification_failed',
				__( 'Plugin directory rename did not produce expected result.', 'choice-uft' )
			);
		}

		// Verify plugin file exists at new location.
		$plugin_file_new = trailingslashit( $corrected_source ) . self::PLUGIN_FILE;
		if ( ! $wp_filesystem->exists( $plugin_file_new ) ) {
			return new WP_Error(
				'rename_verification_failed',
				__( 'Plugin file not found after directory rename.', 'choice-uft' )
			);
		}

		return $corrected_source;
	}

	/**
	 * Check if directory name matches valid pattern
	 *
	 * @param string $dir_name Directory name to validate.
	 * @return bool True if pattern is valid, false otherwise.
	 */
	private static function is_valid_pattern( $dir_name ) {
		// Pattern 1: Starts with our slug (versioned, branch, etc.)
		// Examples: choice-uft-v3.17.0, choice-uft-master, choice-uft-3.17.0.
		if ( 0 === strpos( $dir_name, self::PLUGIN_SLUG . '-' ) ) {
			return true;
		}

		// Pattern 2: GitHub commit format (ChoiceOMG-choice-uft-abc1234).
		if ( preg_match( '/^ChoiceOMG-' . preg_quote( self::PLUGIN_SLUG, '/' ) . '-[a-f0-9]{7}$/i', $dir_name ) ) {
			return true;
		}

		// Unrecognized pattern.
		return false;
	}
}

// Initialize directory fixer.
CUFT_Directory_Fixer::init();
