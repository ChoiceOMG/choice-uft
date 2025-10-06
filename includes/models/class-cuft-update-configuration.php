<?php
/**
 * UpdateConfiguration Model
 *
 * Manages settings for the update system using WordPress options.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT UpdateConfiguration Model
 *
 * Handles update system configuration and settings.
 */
class CUFT_Update_Configuration {

    /**
     * Option key for storing configuration
     */
    const OPTION_KEY = 'cuft_update_config';

    /**
     * Valid check frequencies
     */
    const VALID_FREQUENCIES = array(
        'manual',
        'hourly',
        'twicedaily',
        'daily',
        'weekly'
    );

    /**
     * Get configuration
     *
     * @return array Configuration data
     */
    public static function get() {
        $config = get_option( self::OPTION_KEY, array() );
        return self::validate_config( $config );
    }

    /**
     * Save configuration
     *
     * @param array $config Configuration to save
     * @return bool True on success
     */
    public static function save( $config ) {
        // Validate before saving
        $config = self::validate_config( $config );

        // Add metadata
        $config['last_modified'] = current_time( 'c' );
        $config['modified_by'] = get_current_user_id();

        // Update cron schedule if frequency changed
        $old_config = self::get();
        if ( $old_config['check_frequency'] !== $config['check_frequency'] ||
             $old_config['enabled'] !== $config['enabled'] ) {
            self::update_cron_schedule( $config['enabled'], $config['check_frequency'] );
        }

        return update_option( self::OPTION_KEY, $config );
    }

    /**
     * Update specific configuration fields
     *
     * @param array $fields Fields to update
     * @return bool True on success
     */
    public static function update( $fields ) {
        $config = self::get();

        foreach ( $fields as $key => $value ) {
            $config[ $key ] = $value;
        }

        return self::save( $config );
    }

    /**
     * Reset to default configuration
     *
     * @return bool True on success
     */
    public static function reset() {
        return self::save( self::get_defaults() );
    }

    /**
     * Check if updates are enabled
     *
     * @return bool True if enabled
     */
    public static function is_enabled() {
        $config = self::get();
        return ! empty( $config['enabled'] );
    }

    /**
     * Check if prereleases are included
     *
     * @return bool True if prereleases included
     */
    public static function includes_prereleases() {
        $config = self::get();
        return ! empty( $config['include_prereleases'] );
    }

    /**
     * Check if backup before update is enabled
     *
     * @return bool True if backup enabled
     */
    public static function backup_enabled() {
        $config = self::get();
        return ! empty( $config['backup_before_update'] );
    }

    /**
     * Get check frequency
     *
     * @return string Frequency setting
     */
    public static function get_check_frequency() {
        $config = self::get();
        return $config['check_frequency'];
    }

    /**
     * Get notification email
     *
     * @return string|null Email address or null
     */
    public static function get_notification_email() {
        $config = self::get();
        return ! empty( $config['notification_email'] ) ? $config['notification_email'] : null;
    }

    /**
     * Get GitHub token (decrypted)
     *
     * @return string|null Token or null
     */
    public static function get_github_token() {
        $config = self::get();

        if ( empty( $config['github_token'] ) ) {
            return null;
        }

        // Decrypt token
        return self::decrypt_token( $config['github_token'] );
    }

    /**
     * Set GitHub token (encrypted)
     *
     * @param string|null $token Token to set or null to remove
     * @return bool True on success
     */
    public static function set_github_token( $token ) {
        if ( $token === null || $token === '' ) {
            return self::update( array( 'github_token' => null ) );
        }

        // Encrypt token
        $encrypted = self::encrypt_token( $token );

        return self::update( array( 'github_token' => $encrypted ) );
    }

    /**
     * Enable updates
     *
     * @return bool True on success
     */
    public static function enable() {
        return self::update( array( 'enabled' => true ) );
    }

    /**
     * Disable updates
     *
     * @return bool True on success
     */
    public static function disable() {
        return self::update( array( 'enabled' => false ) );
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private static function get_defaults() {
        return array(
            'enabled' => true,
            'check_frequency' => 'twicedaily',
            'include_prereleases' => false,
            'backup_before_update' => true,
            'notification_email' => get_option( 'admin_email' ),
            'github_token' => null,
            'last_modified' => current_time( 'c' ),
            'modified_by' => get_current_user_id()
        );
    }

    /**
     * Validate configuration
     *
     * @param mixed $config Configuration to validate
     * @return array Validated configuration
     */
    private static function validate_config( $config ) {
        if ( ! is_array( $config ) ) {
            $config = array();
        }

        $defaults = self::get_defaults();
        $config = wp_parse_args( $config, $defaults );

        // Validate booleans
        $config['enabled'] = (bool) $config['enabled'];
        $config['include_prereleases'] = (bool) $config['include_prereleases'];
        $config['backup_before_update'] = (bool) $config['backup_before_update'];

        // Validate frequency
        if ( ! in_array( $config['check_frequency'], self::VALID_FREQUENCIES ) ) {
            $config['check_frequency'] = 'twicedaily';
        }

        // Validate email
        if ( ! empty( $config['notification_email'] ) ) {
            $config['notification_email'] = sanitize_email( $config['notification_email'] );
            if ( ! is_email( $config['notification_email'] ) ) {
                $config['notification_email'] = get_option( 'admin_email' );
            }
        }

        // Validate modified_by
        if ( ! empty( $config['modified_by'] ) ) {
            $config['modified_by'] = intval( $config['modified_by'] );
        }

        return $config;
    }

    /**
     * Update WordPress cron schedule
     *
     * @param bool $enabled Whether updates are enabled
     * @param string $frequency Check frequency
     * @return void
     */
    private static function update_cron_schedule( $enabled, $frequency ) {
        $hook = 'cuft_check_updates';

        // Clear existing schedule
        wp_clear_scheduled_hook( $hook );

        // Schedule new event if enabled and not manual
        if ( $enabled && $frequency !== 'manual' ) {
            wp_schedule_event( time(), $frequency, $hook );
        }
    }

    /**
     * Encrypt token using WordPress salts
     *
     * @param string $token Token to encrypt
     * @return string Encrypted token
     */
    private static function encrypt_token( $token ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // Fallback to base64 encoding (not secure, but better than nothing)
            return base64_encode( $token );
        }

        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );

        $encrypted = openssl_encrypt( $token, 'aes-256-cbc', $key, 0, $iv );

        // Combine IV and encrypted data
        return base64_encode( $iv . '::' . $encrypted );
    }

    /**
     * Decrypt token using WordPress salts
     *
     * @param string $encrypted_token Encrypted token
     * @return string|null Decrypted token or null on failure
     */
    private static function decrypt_token( $encrypted_token ) {
        if ( empty( $encrypted_token ) ) {
            return null;
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            // Fallback from base64 encoding
            return base64_decode( $encrypted_token );
        }

        $key = self::get_encryption_key();
        $data = base64_decode( $encrypted_token );

        if ( strpos( $data, '::' ) === false ) {
            // Old format or invalid
            return base64_decode( $encrypted_token );
        }

        list( $iv, $encrypted ) = explode( '::', $data, 2 );

        $decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, 0, $iv );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Get encryption key from WordPress salts
     *
     * @return string Encryption key
     */
    private static function get_encryption_key() {
        // Use WordPress authentication salts as encryption key
        return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY );
    }

    /**
     * Get configuration for display
     *
     * @return array Formatted configuration
     */
    public static function get_display_config() {
        $config = self::get();

        // Add human-readable labels
        $frequency_labels = array(
            'manual' => 'Manual only',
            'hourly' => 'Every hour',
            'twicedaily' => 'Twice daily',
            'daily' => 'Once daily',
            'weekly' => 'Once weekly'
        );

        $config['check_frequency_label'] = isset( $frequency_labels[ $config['check_frequency'] ] ) ?
                                           $frequency_labels[ $config['check_frequency'] ] :
                                           ucfirst( $config['check_frequency'] );

        // Add next scheduled check
        $config['next_scheduled_check'] = self::get_next_scheduled_check();

        // Add last modified info
        if ( ! empty( $config['last_modified'] ) ) {
            $config['last_modified_human'] = human_time_diff( strtotime( $config['last_modified'] ) ) . ' ago';
        }

        if ( ! empty( $config['modified_by'] ) ) {
            $user = get_userdata( $config['modified_by'] );
            $config['modified_by_name'] = $user ? $user->display_name : 'Unknown';
        }

        // Remove sensitive data from display
        unset( $config['github_token'] );

        // Add token status
        $config['has_github_token'] = ! empty( self::get_github_token() );

        return $config;
    }

    /**
     * Get next scheduled check time
     *
     * @return string|null ISO 8601 timestamp or null
     */
    private static function get_next_scheduled_check() {
        $timestamp = wp_next_scheduled( 'cuft_check_updates' );
        return $timestamp ? date( 'c', $timestamp ) : null;
    }

    /**
     * Export configuration (for backup/migration)
     *
     * @return array Configuration without sensitive data
     */
    public static function export() {
        $config = self::get();

        // Remove sensitive data
        unset( $config['github_token'] );

        return $config;
    }

    /**
     * Import configuration (from backup/migration)
     *
     * @param array $config Configuration to import
     * @return bool True on success
     */
    public static function import( $config ) {
        if ( ! is_array( $config ) ) {
            return false;
        }

        // Don't import sensitive data
        unset( $config['github_token'] );

        return self::save( $config );
    }
}