<?php
/**
 * UpdateStatus Model
 *
 * Tracks the current update state of the plugin using WordPress site transients.
 * Uses site transients for multisite compatibility - all sites share update status.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT UpdateStatus Model
 *
 * Manages the current update status including version information,
 * check timestamps, and update availability.
 */
class CUFT_Update_Status {

    /**
     * Site transient key for storing update status
     * Note: Uses site_transient for multisite compatibility
     */
    const TRANSIENT_KEY = 'cuft_update_status';

    /**
     * Default transient expiration time (12 hours in seconds)
     * Note: Actual timeout determined by get_context_timeout() method
     */
    const TRANSIENT_EXPIRATION = 43200;

    /**
     * Valid status values
     */
    const VALID_STATUSES = array(
        'idle',
        'checking',
        'update_available',
        'downloading',
        'backing_up',
        'installing',
        'verifying',
        'complete',
        'failed',
        'rolling_back'
    );

    /**
     * Get current update status
     *
     * @return array Update status data
     */
    public static function get() {
        $status = get_site_transient( self::TRANSIENT_KEY );

        if ( false === $status ) {
            $status = self::get_default_status();
        }

        return self::validate_status( $status );
    }

    /**
     * Set update status
     *
     * @param array $status Status data to save
     * @return bool True on success
     */
    public static function set( $status ) {
        // Validate before saving
        $status = self::validate_status( $status );

        // Add timestamp
        $status['last_updated'] = current_time( 'c' );

        // Use context-aware timeout
        $timeout = self::get_context_timeout();

        return set_site_transient( self::TRANSIENT_KEY, $status, $timeout );
    }

    /**
     * Update specific status fields
     *
     * @param array $fields Fields to update
     * @return bool True on success
     */
    public static function update( $fields ) {
        $status = self::get();

        foreach ( $fields as $key => $value ) {
            $status[ $key ] = $value;
        }

        return self::set( $status );
    }

    /**
     * Clear update status (force refresh)
     *
     * @return bool True on success
     */
    public static function clear() {
        return delete_site_transient( self::TRANSIENT_KEY );
    }

    /**
     * Check if an update is available
     *
     * @return bool True if update available
     */
    public static function is_update_available() {
        $status = self::get();
        return ! empty( $status['update_available'] );
    }

    /**
     * Check if an update check is in progress
     *
     * @return bool True if checking
     */
    public static function is_checking() {
        $status = self::get();
        return ! empty( $status['check_in_progress'] );
    }

    /**
     * Get the current version
     *
     * @return string Current version
     */
    public static function get_current_version() {
        return CUFT_VERSION;
    }

    /**
     * Get the latest available version
     *
     * @return string|null Latest version or null if not checked
     */
    public static function get_latest_version() {
        $status = self::get();
        return ! empty( $status['latest_version'] ) ? $status['latest_version'] : null;
    }

    /**
     * Get last check timestamp
     *
     * @return string|null Last check time in ISO 8601 format
     */
    public static function get_last_check() {
        $status = self::get();
        return ! empty( $status['last_check'] ) ? $status['last_check'] : null;
    }

    /**
     * Get next scheduled check timestamp
     *
     * @return string|null Next check time in ISO 8601 format
     */
    public static function get_next_scheduled_check() {
        $timestamp = wp_next_scheduled( 'cuft_check_updates' );
        return $timestamp ? date( 'c', $timestamp ) : null;
    }

    /**
     * Set checking status
     *
     * @param bool $checking Whether checking is in progress
     * @return bool True on success
     */
    public static function set_checking( $checking = true ) {
        return self::update( array(
            'check_in_progress' => $checking,
            'last_check' => $checking ? null : current_time( 'c' )
        ) );
    }

    /**
     * Set update available status
     *
     * @param string $latest_version Latest version available
     * @param array $additional_data Additional data to store
     * @return bool True on success
     */
    public static function set_update_available( $latest_version, $additional_data = array() ) {
        $current_version = self::get_current_version();
        $update_available = version_compare( $current_version, $latest_version, '<' );

        $data = array_merge(
            array(
                'current_version' => $current_version,
                'latest_version' => $latest_version,
                'update_available' => $update_available,
                'last_check' => current_time( 'c' ),
                'check_in_progress' => false
            ),
            $additional_data
        );

        return self::set( $data );
    }

    /**
     * Get default status structure
     *
     * @return array Default status
     */
    private static function get_default_status() {
        return array(
            'current_version' => self::get_current_version(),
            'latest_version' => null,
            'last_check' => null,
            'next_scheduled_check' => self::get_next_scheduled_check(),
            'update_available' => false,
            'check_in_progress' => false,
            'last_updated' => current_time( 'c' )
        );
    }

    /**
     * Validate status data
     *
     * @param mixed $status Status to validate
     * @return array Validated status
     */
    private static function validate_status( $status ) {
        if ( ! is_array( $status ) ) {
            return self::get_default_status();
        }

        $defaults = self::get_default_status();
        $status = wp_parse_args( $status, $defaults );

        // Validate version formats
        if ( ! empty( $status['current_version'] ) && ! self::is_valid_version( $status['current_version'] ) ) {
            $status['current_version'] = self::get_current_version();
        }

        if ( ! empty( $status['latest_version'] ) && ! self::is_valid_version( $status['latest_version'] ) ) {
            $status['latest_version'] = null;
        }

        // Validate timestamps
        if ( ! empty( $status['last_check'] ) && strtotime( $status['last_check'] ) > time() ) {
            $status['last_check'] = current_time( 'c' );
        }

        // Validate booleans
        $status['update_available'] = (bool) $status['update_available'];
        $status['check_in_progress'] = (bool) $status['check_in_progress'];

        // Validate update availability logic
        if ( ! empty( $status['latest_version'] ) ) {
            $status['update_available'] = version_compare(
                $status['current_version'],
                $status['latest_version'],
                '<'
            );
        }

        return $status;
    }

    /**
     * Check if version string is valid
     *
     * @param string $version Version to check
     * @return bool True if valid
     */
    private static function is_valid_version( $version ) {
        // Match semantic versioning: X.Y.Z or vX.Y.Z
        return preg_match( '/^v?\d+\.\d+\.\d+/', $version );
    }

    /**
     * Get status for display
     *
     * @return array Formatted status for display
     */
    public static function get_display_status() {
        $status = self::get();

        // Add human-readable fields
        if ( ! empty( $status['last_check'] ) ) {
            $status['last_check_human'] = human_time_diff( strtotime( $status['last_check'] ) ) . ' ago';
        }

        if ( ! empty( $status['next_scheduled_check'] ) ) {
            $next_time = strtotime( $status['next_scheduled_check'] );
            if ( $next_time > time() ) {
                $status['next_check_human'] = 'in ' . human_time_diff( $next_time );
            }
        }

        if ( ! empty( $status['latest_version'] ) && $status['update_available'] ) {
            $status['update_message'] = sprintf(
                'Version %s is available (current: %s)',
                $status['latest_version'],
                $status['current_version']
            );
        } else {
            $status['update_message'] = 'You have the latest version';
        }

        return $status;
    }

    /**
     * Get context-aware timeout based on current WordPress context
     *
     * Implements WordPress core timing strategies for update checks:
     * - After update completion: immediate (0s)
     * - Update core page: 1 minute (users actively checking)
     * - Plugins page: 1 hour (users viewing plugin list)
     * - Updates page: 1 hour (users on Updates screen)
     * - Default: 12 hours (background checks)
     *
     * @return int Timeout in seconds
     */
    public static function get_context_timeout() {
        $filter = current_filter();
        $action = current_action();

        // Context-aware timeout map based on WordPress core patterns
        $timeouts = array(
            'upgrader_process_complete' => 0,                    // Immediate after update
            'load-update-core.php' => MINUTE_IN_SECONDS,         // 1 minute on Updates page
            'load-plugins.php' => HOUR_IN_SECONDS,               // 1 hour on Plugins page
            'load-update.php' => HOUR_IN_SECONDS,                // 1 hour on Updates page
        );

        // Check current filter first
        if ( isset( $timeouts[ $filter ] ) ) {
            return $timeouts[ $filter ];
        }

        // Check current action
        if ( isset( $timeouts[ $action ] ) ) {
            return $timeouts[ $action ];
        }

        // Default timeout for all other contexts
        return self::TRANSIENT_EXPIRATION;
    }

    /**
     * Check for recent update completion
     *
     * Checks if an update has recently completed and returns the completion data.
     * This helps synchronize status across different interfaces.
     *
     * @return array|null Completion data or null if no recent completion
     */
    public static function get_recent_completion() {
        $completion = get_site_transient( 'cuft_update_completed' );
        
        if ( $completion && is_array( $completion ) ) {
            // Check if completion is recent (within last 5 minutes)
            $completion_time = isset( $completion['timestamp'] ) ? $completion['timestamp'] : 0;
            if ( ( time() - $completion_time ) < 5 * MINUTE_IN_SECONDS ) {
                return $completion;
            }
        }
        
        return null;
    }

    /**
     * Clear update completion transient
     *
     * Removes the update completion transient after it has been processed.
     *
     * @return bool True if cleared successfully
     */
    public static function clear_completion_transient() {
        return delete_site_transient( 'cuft_update_completed' );
    }
}