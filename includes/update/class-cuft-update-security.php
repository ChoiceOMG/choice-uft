<?php
/**
 * Update Security Class
 *
 * Handles security validation for plugin update system.
 * Validates nonces, capabilities, URLs, file modifications, and filesystem permissions.
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
 * Class CUFT_Update_Security
 *
 * Provides security validation methods for update system.
 *
 * @since 3.17.0
 */
class CUFT_Update_Security {

	/**
	 * Allowed GitHub download URL patterns
	 *
	 * @since 3.17.0
	 * @var array
	 */
	private static $allowed_url_patterns = array(
		'#^https://github\.com/ChoiceOMG/choice-uft/releases/download/[^/]+/choice-uft-v[\d.]+\.zip$#',
		'#^https://api\.github\.com/repos/ChoiceOMG/choice-uft/zipball/.+$#',
	);

	/**
	 * Initialize security hooks
	 *
	 * Registers WordPress filters for security validation.
	 *
	 * @since 3.17.0
	 */
	public function __construct() {
		// Hook into upgrade process for security checks
		add_filter( 'upgrader_pre_download', array( $this, 'validate_download_security' ), 10, 3 );
		add_filter( 'upgrader_pre_install', array( $this, 'validate_install_security' ), 5, 2 );
	}

	/**
	 * Validate download security
	 *
	 * Hooks into upgrader_pre_download to validate URL and permissions.
	 *
	 * @since 3.17.0
	 *
	 * @param bool   $return Whether to return early (default false).
	 * @param string $package Download URL.
	 * @param object $upgrader Upgrader instance.
	 * @return bool|WP_Error True to continue, WP_Error to abort.
	 */
	public function validate_download_security( $return, $package, $upgrader ) {
		// Only process CUFT plugin downloads
		if ( ! $this->is_cuft_update( $upgrader ) ) {
			return $return;
		}

		// Validate URL
		$url_validation = self::validate_download_url( $package );
		if ( is_wp_error( $url_validation ) ) {
			return $url_validation;
		}

		// Check DISALLOW_FILE_MODS
		$file_mods_check = self::check_file_mods();
		if ( is_wp_error( $file_mods_check ) ) {
			return $file_mods_check;
		}

		return $return;
	}

	/**
	 * Validate install security
	 *
	 * Hooks into upgrader_pre_install to validate permissions.
	 *
	 * @since 3.17.0
	 *
	 * @param bool  $return Installation response.
	 * @param array $plugin Plugin data.
	 * @return bool|WP_Error True to continue, WP_Error to abort.
	 */
	public function validate_install_security( $return, $plugin ) {
		// Only process CUFT plugin
		if ( ! isset( $plugin['plugin'] ) || dirname( $plugin['plugin'] ) !== 'choice-uft' ) {
			return $return;
		}

		// Check filesystem permissions
		$permissions_check = self::check_filesystem_permissions();
		if ( is_wp_error( $permissions_check ) ) {
			return $permissions_check;
		}

		return $return;
	}

	/**
	 * Check if this is a CUFT update
	 *
	 * @since 3.17.0
	 *
	 * @param object $upgrader Upgrader instance.
	 * @return bool True if CUFT update.
	 */
	private function is_cuft_update( $upgrader ) {
		if ( ! isset( $upgrader->skin ) ) {
			return false;
		}

		if ( ! isset( $upgrader->skin->plugin ) ) {
			return false;
		}

		return dirname( $upgrader->skin->plugin ) === 'choice-uft';
	}

	/**
	 * Validate nonce for update action
	 *
	 * Validates WordPress nonce for update actions.
	 *
	 * @since 3.17.0
	 *
	 * @param string $nonce Nonce to validate.
	 * @param string $action Nonce action (default: 'update-plugin').
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_nonce( $nonce = null, $action = 'update-plugin' ) {
		// If no nonce provided, check $_REQUEST
		if ( $nonce === null ) {
			if ( isset( $_REQUEST['_wpnonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			}
		}

		// Validate nonce
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_NONCE_INVALID,
				array( 'action' => $action )
			);
		}

		return true;
	}

	/**
	 * Check user capability
	 *
	 * Verifies user has update_plugins capability.
	 *
	 * @since 3.17.0
	 *
	 * @param int|null $user_id User ID (default: current user).
	 * @return bool|WP_Error True if allowed, WP_Error if denied.
	 */
	public static function check_capability( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		// Check capability
		if ( ! user_can( $user_id, 'update_plugins' ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_CAPABILITY_DENIED,
				array( 'user_id' => $user_id )
			);
		}

		return true;
	}

	/**
	 * Validate download URL
	 *
	 * Validates download URL matches GitHub CDN pattern.
	 *
	 * @since 3.17.0
	 *
	 * @param string $url Download URL.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_download_url( $url ) {
		// Check URL is not empty
		if ( empty( $url ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array( 'url' => 'empty' )
			);
		}

		// Check URL starts with HTTPS
		if ( strpos( $url, 'https://' ) !== 0 ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array( 'url' => 'not HTTPS' )
			);
		}

		// Check URL matches allowed patterns
		$is_valid = false;
		foreach ( self::$allowed_url_patterns as $pattern ) {
			if ( preg_match( $pattern, $url ) ) {
				$is_valid = true;
				break;
			}
		}

		if ( ! $is_valid ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array(
					'url' => 'pattern mismatch',
					'details' => 'URL must be from github.com/ChoiceOMG/choice-uft',
				)
			);
		}

		// Parse URL for additional validation
		$parsed_url = wp_parse_url( $url );

		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array( 'url' => 'malformed URL' )
			);
		}

		// Check domain is github.com or api.github.com
		$allowed_hosts = array( 'github.com', 'api.github.com' );
		if ( ! in_array( $parsed_url['host'], $allowed_hosts, true ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array( 'url' => 'invalid host: ' . $parsed_url['host'] )
			);
		}

		// Check for query parameters or fragments (not allowed)
		if ( isset( $parsed_url['query'] ) || isset( $parsed_url['fragment'] ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_URL_INVALID,
				array( 'url' => 'query parameters or fragments not allowed' )
			);
		}

		return true;
	}

	/**
	 * Check DISALLOW_FILE_MODS constant
	 *
	 * Checks if file modifications are disabled.
	 *
	 * @since 3.17.0
	 *
	 * @return bool|WP_Error True if allowed, WP_Error if disabled.
	 */
	public static function check_file_mods() {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::SECURITY_FILE_MODS_DISABLED,
				array( 'constant' => 'DISALLOW_FILE_MODS' )
			);
		}

		return true;
	}

	/**
	 * Check filesystem permissions
	 *
	 * Checks write permissions on plugin and backup directories.
	 *
	 * @since 3.17.0
	 *
	 * @return bool|WP_Error True if writable, WP_Error if not.
	 */
	public static function check_filesystem_permissions() {
		$plugin_dir = WP_PLUGIN_DIR . '/choice-uft';
		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'cuft-backups';

		// Check plugin directory permissions
		if ( file_exists( $plugin_dir ) && ! is_writable( $plugin_dir ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::PLUGIN_DIR_NOT_WRITABLE,
				array( 'path' => $plugin_dir )
			);
		}

		// Check plugin parent directory permissions (for new installs)
		if ( ! is_writable( WP_PLUGIN_DIR ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::PLUGIN_DIR_NOT_WRITABLE,
				array( 'path' => WP_PLUGIN_DIR )
			);
		}

		// Check uploads directory permissions (for backup creation)
		if ( ! is_writable( $upload_dir['basedir'] ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::BACKUP_DIR_NOT_WRITABLE,
				array( 'path' => $upload_dir['basedir'] )
			);
		}

		// Check backup directory permissions (if exists)
		if ( file_exists( $backup_dir ) && ! is_writable( $backup_dir ) ) {
			return CUFT_Error_Messages::create_error(
				CUFT_Error_Messages::BACKUP_DIR_NOT_WRITABLE,
				array( 'path' => $backup_dir )
			);
		}

		return true;
	}

	/**
	 * Validate complete update security
	 *
	 * Runs all security checks in sequence.
	 *
	 * @since 3.17.0
	 *
	 * @param string $download_url Download URL.
	 * @param string $nonce Nonce (optional).
	 * @param int    $user_id User ID (optional).
	 * @return bool|WP_Error True if all checks pass, WP_Error on first failure.
	 */
	public static function validate_complete( $download_url, $nonce = null, $user_id = null ) {
		// Check 1: Capability
		$capability_check = self::check_capability( $user_id );
		if ( is_wp_error( $capability_check ) ) {
			return $capability_check;
		}

		// Check 2: Nonce (if provided)
		if ( $nonce !== null ) {
			$nonce_check = self::validate_nonce( $nonce );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}
		}

		// Check 3: Download URL
		$url_check = self::validate_download_url( $download_url );
		if ( is_wp_error( $url_check ) ) {
			return $url_check;
		}

		// Check 4: File modifications allowed
		$file_mods_check = self::check_file_mods();
		if ( is_wp_error( $file_mods_check ) ) {
			return $file_mods_check;
		}

		// Check 5: Filesystem permissions
		$permissions_check = self::check_filesystem_permissions();
		if ( is_wp_error( $permissions_check ) ) {
			return $permissions_check;
		}

		return true;
	}

	/**
	 * Sanitize update context data
	 *
	 * Sanitizes context data before logging or storage.
	 *
	 * @since 3.17.0
	 *
	 * @param array $context Context data.
	 * @return array Sanitized context.
	 */
	public static function sanitize_context( $context ) {
		$sanitized = array();

		// Allowed keys and their sanitization functions
		$allowed_keys = array(
			'version'          => 'sanitize_text_field',
			'previous_version' => 'sanitize_text_field',
			'target_version'   => 'sanitize_text_field',
			'user_id'          => 'absint',
			'timestamp'        => 'sanitize_text_field',
			'trigger_location' => 'sanitize_text_field',
			'error_code'       => 'sanitize_text_field',
			'error_message'    => 'wp_kses_post',
			'expected_size'    => 'absint',
			'actual_size'      => 'absint',
		);

		// Sanitize each field
		foreach ( $allowed_keys as $key => $sanitize_function ) {
			if ( isset( $context[ $key ] ) ) {
				$sanitized[ $key ] = call_user_func( $sanitize_function, $context[ $key ] );
			}
		}

		// Handle path specially (only for administrators)
		if ( isset( $context['path'] ) && current_user_can( 'manage_options' ) ) {
			$sanitized['path'] = sanitize_text_field( $context['path'] );
		}

		return $sanitized;
	}
}
