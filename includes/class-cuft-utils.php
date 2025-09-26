<?php
/**
 * Utility functions for Choice Universal Form Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Utils {

    /**
     * Check if a feature flag is enabled
     *
     * @param string $feature_name Feature flag name
     * @param int $user_id Optional user ID for user-specific flags
     * @return bool True if feature is enabled
     */
    public static function is_feature_enabled( $feature_name, $user_id = null ) {
        $feature_flags = get_option( 'cuft_feature_flags', array() );

        if ( ! isset( $feature_flags[ $feature_name ] ) ) {
            return false; // Feature not defined, default to disabled
        }

        $flag_config = $feature_flags[ $feature_name ];

        // Simple boolean flag
        if ( is_bool( $flag_config ) ) {
            return $flag_config;
        }

        // Array configuration with rollout percentage
        if ( is_array( $flag_config ) ) {
            $enabled = isset( $flag_config['enabled'] ) ? $flag_config['enabled'] : false;

            if ( ! $enabled ) {
                return false;
            }

            // Check rollout percentage
            if ( isset( $flag_config['rollout_percentage'] ) ) {
                $percentage = (int) $flag_config['rollout_percentage'];

                if ( $percentage >= 100 ) {
                    return true;
                }

                if ( $percentage <= 0 ) {
                    return false;
                }

                // Use consistent hash for user-based rollout
                if ( $user_id ) {
                    $hash_input = $feature_name . '_' . $user_id;
                } else {
                    // Use current user or IP-based hash
                    $current_user_id = get_current_user_id();
                    if ( $current_user_id ) {
                        $hash_input = $feature_name . '_' . $current_user_id;
                    } else {
                        // Fallback to IP address for anonymous users
                        $ip_address = self::get_client_ip();
                        $hash_input = $feature_name . '_' . $ip_address;
                    }
                }

                $hash = crc32( $hash_input );
                $user_percentage = abs( $hash ) % 100;

                return $user_percentage < $percentage;
            }

            return true;
        }

        return false;
    }

    /**
     * Set feature flag
     *
     * @param string $feature_name Feature flag name
     * @param bool|array $config Feature configuration
     * @return bool Success status
     */
    public static function set_feature_flag( $feature_name, $config ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $feature_flags = get_option( 'cuft_feature_flags', array() );
        $feature_flags[ $feature_name ] = $config;

        $result = update_option( 'cuft_feature_flags', $feature_flags );

        // Log flag change
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', "Feature flag updated: {$feature_name}", array(
                'config' => $config,
                'user' => get_current_user_id()
            ) );
        }

        return $result;
    }

    /**
     * Get all feature flags
     *
     * @return array Feature flags configuration
     */
    public static function get_feature_flags() {
        return get_option( 'cuft_feature_flags', array() );
    }

    /**
     * Initialize default feature flags
     */
    public static function init_default_feature_flags() {
        $existing_flags = get_option( 'cuft_feature_flags', false );

        if ( $existing_flags === false ) {
            // Initialize with default flags
            $default_flags = array(
                'click_event_tracking' => array(
                    'enabled' => false,
                    'rollout_percentage' => 0,
                    'description' => 'Enable click tracking with event chronology'
                ),
                'enhanced_admin_interface' => array(
                    'enabled' => false,
                    'rollout_percentage' => 0,
                    'description' => 'Show enhanced admin interface with event timeline'
                )
            );

            update_option( 'cuft_feature_flags', $default_flags );
        }
    }

    /**
     * Get client IP address (same as in CUFT_Click_Tracker)
     *
     * @return string Client IP address
     */
    public static function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( $_SERVER[ $key ] );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Validate JSON structure
     *
     * @param string $json JSON string to validate
     * @param array $expected_schema Expected schema structure
     * @return bool True if valid
     */
    public static function validate_json_schema( $json, $expected_schema = null ) {
        if ( empty( $json ) ) {
            return false;
        }

        $data = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        // Basic validation - ensure it's an array for events
        if ( $expected_schema === 'events' ) {
            if ( ! is_array( $data ) ) {
                return false;
            }

            // Validate each event structure
            foreach ( $data as $event ) {
                if ( ! is_array( $event ) ||
                     ! isset( $event['event'] ) ||
                     ! isset( $event['timestamp'] ) ||
                     ! is_string( $event['event'] ) ||
                     ! is_string( $event['timestamp'] ) ) {
                    return false;
                }

                // Validate event types
                $valid_events = array( 'phone_click', 'email_click', 'form_submit', 'generate_lead', 'status_update' );
                if ( ! in_array( $event['event'], $valid_events ) ) {
                    return false;
                }

                // Validate timestamp format (basic ISO 8601 check)
                if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $event['timestamp'] ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Format timestamp for display
     *
     * @param string $timestamp ISO timestamp
     * @return string Formatted timestamp
     */
    public static function format_timestamp( $timestamp ) {
        try {
            $dt = new DateTime( $timestamp );
            return $dt->format( 'M j, Y g:i A' );
        } catch ( Exception $e ) {
            return $timestamp;
        }
    }

    /**
     * Get event type display name
     *
     * @param string $event_type Event type key
     * @return string Display name
     */
    public static function get_event_display_name( $event_type ) {
        $display_names = array(
            'phone_click' => 'Phone Click',
            'email_click' => 'Email Click',
            'form_submit' => 'Form Submit',
            'generate_lead' => 'Generate Lead',
            'status_update' => 'Status Update'
        );

        return isset( $display_names[ $event_type ] ) ? $display_names[ $event_type ] : $event_type;
    }

    /**
     * Get event type icon/emoji
     *
     * @param string $event_type Event type key
     * @return string Icon/emoji
     */
    public static function get_event_icon( $event_type ) {
        $icons = array(
            'phone_click' => '📞',
            'email_click' => '📧',
            'form_submit' => '📝',
            'generate_lead' => '⭐',
            'status_update' => '🔄'
        );

        return isset( $icons[ $event_type ] ) ? $icons[ $event_type ] : '●';
    }

    /**
     * Debug log helper
     *
     * @param string $message Log message
     * @param mixed $data Additional data to log
     */
    public static function debug_log( $message, $data = null ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $log_message = '[CUFT] ' . $message;

        if ( $data !== null ) {
            $log_message .= ' | Data: ' . print_r( $data, true );
        }

        error_log( $log_message );
    }

    /**
     * Check if events column exists in click tracking table
     *
     * @return bool True if events column exists
     */
    public static function has_events_column() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        $column_exists = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'events'
        ) );

        return ! empty( $column_exists );
    }

    /**
     * Get system info for debugging
     *
     * @return array System information
     */
    public static function get_system_info() {
        global $wpdb;

        return array(
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->get_var( 'SELECT VERSION()' ),
            'cuft_version' => defined( 'CUFT_VERSION' ) ? CUFT_VERSION : 'unknown',
            'has_events_column' => self::has_events_column(),
            'feature_flags' => self::get_feature_flags(),
            'active_theme' => get_option( 'current_theme' ),
            'is_multisite' => is_multisite(),
            'site_url' => site_url(),
            'admin_email' => get_option( 'admin_email' )
        );
    }
}