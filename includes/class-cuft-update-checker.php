<?php
/**
 * Update Checker Service
 *
 * Service for checking and managing plugin updates with caching.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT Update Checker Service
 *
 * Handles update checking with intelligent caching and status management.
 */
class CUFT_Update_Checker {

    /**
     * Check for updates
     *
     * @param bool $force Force check (bypass cache)
     * @return array Check results
     */
    public static function check( $force = false ) {
        // Check if already checking
        if ( ! $force && CUFT_Update_Status::is_checking() ) {
            return array(
                'success' => false,
                'error' => 'Update check already in progress',
                'status' => CUFT_Update_Status::get()
            );
        }

        // Check if rate limited
        if ( CUFT_GitHub_API::is_rate_limited() ) {
            return array(
                'success' => false,
                'error' => 'GitHub API rate limit exceeded. Please try again later.',
                'rate_limit' => CUFT_GitHub_API::get_rate_limit()
            );
        }

        // Set checking status
        CUFT_Update_Status::set_checking( true );

        // Log check started
        CUFT_Update_Log::log_check_started();

        try {
            // Perform check
            $result = CUFT_GitHub_API::check_for_updates( $force );

            if ( ! $result['success'] ) {
                // Log error
                CUFT_Update_Log::log_error( $result['error'] );

                // Update status
                CUFT_Update_Status::set_checking( false );

                return $result;
            }

            // Update status with results
            CUFT_Update_Status::set_update_available(
                $result['latest_version'],
                array(
                    'download_url' => $result['download_url'],
                    'changelog' => $result['changelog'],
                    'file_size' => $result['file_size'],
                    'published_date' => $result['published_date'],
                    'is_prerelease' => $result['is_prerelease']
                )
            );

            // Log check completed
            CUFT_Update_Log::log_check_completed(
                $result['latest_version'],
                $result['update_available']
            );

            // Send notification if update available
            if ( $result['update_available'] ) {
                self::send_update_notification( $result );
            }

            return $result;

        } catch ( Exception $e ) {
            // Log error
            CUFT_Update_Log::log_error( 'Update check failed: ' . $e->getMessage() );

            // Clear checking status
            CUFT_Update_Status::set_checking( false );

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'current_version' => CUFT_VERSION
            );
        }
    }

    /**
     * Scheduled check for updates (cron job)
     *
     * @return void
     */
    public static function scheduled_check() {
        // Only run if enabled
        if ( ! CUFT_Update_Configuration::is_enabled() ) {
            return;
        }

        // Run check
        self::check( false );
    }

    /**
     * Get cached update status
     *
     * @return array Update status
     */
    public static function get_status() {
        return CUFT_Update_Status::get_display_status();
    }

    /**
     * Clear update cache
     *
     * @return bool True on success
     */
    public static function clear_cache() {
        CUFT_Update_Status::clear();
        CUFT_GitHub_API::clear_cache();

        return true;
    }

    /**
     * Check if update is available
     *
     * @return bool True if update available
     */
    public static function is_update_available() {
        $status = CUFT_Update_Status::get();
        return ! empty( $status['update_available'] );
    }

    /**
     * Get update information
     *
     * @return array|null Update info or null if no update
     */
    public static function get_update_info() {
        if ( ! self::is_update_available() ) {
            return null;
        }

        $status = CUFT_Update_Status::get();

        return array(
            'current_version' => $status['current_version'],
            'latest_version' => $status['latest_version'],
            'download_url' => isset( $status['download_url'] ) ? $status['download_url'] : null,
            'changelog' => isset( $status['changelog'] ) ? $status['changelog'] : '',
            'file_size' => isset( $status['file_size'] ) ? $status['file_size'] : 'Unknown',
            'published_date' => isset( $status['published_date'] ) ? $status['published_date'] : null
        );
    }

    /**
     * Send update notification email
     *
     * @param array $update_info Update information
     * @return bool True if sent successfully
     */
    private static function send_update_notification( $update_info ) {
        $email = CUFT_Update_Configuration::get_notification_email();

        if ( ! $email ) {
            return false;
        }

        $subject = sprintf(
            '[%s] Plugin Update Available - v%s',
            get_bloginfo( 'name' ),
            $update_info['latest_version']
        );

        $message = sprintf(
            "A new version of Choice Universal Form Tracker is available.\n\n" .
            "Current version: %s\n" .
            "New version: %s\n\n" .
            "What's new:\n%s\n\n" .
            "File size: %s\n" .
            "Published: %s\n\n" .
            "You can update the plugin from your WordPress admin panel:\n%s\n",
            $update_info['current_version'],
            $update_info['latest_version'],
            wp_strip_all_tags( $update_info['changelog'] ),
            $update_info['file_size'],
            $update_info['published_date'],
            admin_url( 'options-general.php?page=choice-universal-form-tracker' )
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: WordPress <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>'
        );

        return wp_mail( $email, $subject, $message, $headers );
    }

    /**
     * Get check frequency label
     *
     * @return string Frequency label
     */
    public static function get_check_frequency_label() {
        $config = CUFT_Update_Configuration::get();
        return $config['check_frequency_label'];
    }

    /**
     * Get next scheduled check time
     *
     * @return string|null Timestamp or null
     */
    public static function get_next_check_time() {
        $timestamp = wp_next_scheduled( 'cuft_check_updates' );

        if ( ! $timestamp ) {
            return null;
        }

        return date( 'c', $timestamp );
    }

    /**
     * Get next scheduled check (human readable)
     *
     * @return string Human readable time
     */
    public static function get_next_check_human() {
        $timestamp = wp_next_scheduled( 'cuft_check_updates' );

        if ( ! $timestamp ) {
            return 'Not scheduled';
        }

        if ( $timestamp > time() ) {
            return 'In ' . human_time_diff( time(), $timestamp );
        } else {
            return 'Overdue by ' . human_time_diff( $timestamp, time() );
        }
    }

    /**
     * Schedule next check
     *
     * @param string|null $frequency Frequency or null for config value
     * @return bool True if scheduled
     */
    public static function schedule_check( $frequency = null ) {
        if ( $frequency === null ) {
            $frequency = CUFT_Update_Configuration::get_check_frequency();
        }

        // Clear existing schedule
        wp_clear_scheduled_hook( 'cuft_check_updates' );

        // Don't schedule if manual only
        if ( $frequency === 'manual' ) {
            return false;
        }

        // Schedule new event
        return wp_schedule_event( time(), $frequency, 'cuft_check_updates' );
    }

    /**
     * Unschedule checks
     *
     * @return bool True if unscheduled
     */
    public static function unschedule_checks() {
        return wp_clear_scheduled_hook( 'cuft_check_updates' );
    }

    /**
     * Test update checker
     *
     * @return array Test results
     */
    public static function test() {
        $results = array();

        // Test GitHub connectivity
        $results['github_api'] = CUFT_GitHub_API::test_connection();

        // Test update check
        $results['update_check'] = self::check( true );

        // Test configuration
        $results['configuration'] = CUFT_Update_Configuration::get_display_config();

        // Test status
        $results['status'] = CUFT_Update_Status::get_display_status();

        // Test rate limit
        $results['rate_limit'] = CUFT_GitHub_API::get_rate_limit();

        return $results;
    }

    /**
     * Get update statistics
     *
     * @return array Statistics
     */
    public static function get_statistics() {
        $status = CUFT_Update_Status::get();
        $config = CUFT_Update_Configuration::get();
        $log_stats = CUFT_Update_Log::get_stats( 'week' );

        return array(
            'current_version' => CUFT_VERSION,
            'latest_version' => isset( $status['latest_version'] ) ? $status['latest_version'] : 'Unknown',
            'update_available' => CUFT_Update_Status::is_update_available(),
            'last_check' => CUFT_Update_Status::get_last_check(),
            'next_check' => self::get_next_check_time(),
            'check_frequency' => $config['check_frequency'],
            'updates_enabled' => $config['enabled'],
            'checks_this_week' => isset( $log_stats['actions'] ) ?
                                  self::count_action( $log_stats['actions'], 'check_completed' ) : 0,
            'errors_this_week' => $log_stats['error_count'],
            'last_update' => isset( $log_stats['last_update'] ) ? $log_stats['last_update'] : null
        );
    }

    /**
     * Count specific action in stats
     *
     * @param array $actions Actions array
     * @param string $action_name Action to count
     * @return int Count
     */
    private static function count_action( $actions, $action_name ) {
        foreach ( $actions as $action ) {
            if ( $action['action'] === $action_name ) {
                return intval( $action['count'] );
            }
        }
        return 0;
    }
}

// Hook scheduled check to cron
add_action( 'cuft_check_updates', array( 'CUFT_Update_Checker', 'scheduled_check' ) );