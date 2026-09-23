<?php
/**
 * Debug logging functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Logger {
    
    /**
     * Log levels
     */
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Maximum log entries
     */
    const MAX_LOGS = 1000;
    
    /**
     * Log an entry
     */
    public static function log( $message, $level = self::INFO, $context = array() ) {
        if ( ! get_option( 'cuft_debug_enabled', false ) ) {
            return;
        }
        
        $logs = get_option( 'cuft_debug_logs', array() );
        
        $entry = array(
            'timestamp' => current_time( 'mysql' ),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''
        );
        
        // Add to beginning of array
        array_unshift( $logs, $entry );
        
        // Keep only the latest entries
        if ( count( $logs ) > self::MAX_LOGS ) {
            $logs = array_slice( $logs, 0, self::MAX_LOGS );
        }
        
        update_option( 'cuft_debug_logs', $logs );
    }
    
    /**
     * Write a diagnostic line to the PHP error log, only under WP_DEBUG.
     *
     * The single place in the plugin that calls error_log(). Production sites
     * (WP_DEBUG off) write nothing; a developer who turns WP_DEBUG on gets the
     * same diagnostics the plugin has always produced.
     *
     * @param string $message Message to write.
     */
    public static function debug_log( $message ) {
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return;
        }
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Gated on WP_DEBUG above; this is the plugin's only error_log() call.
        error_log( (string) $message );
    }

    /**
     * Get logs
     */
    public static function get_logs( $limit = 100 ) {
        $logs = get_option( 'cuft_debug_logs', array() );
        return array_slice( $logs, 0, $limit );
    }
    
    /**
     * Clear logs
     */
    public static function clear_logs() {
        delete_option( 'cuft_debug_logs' );
    }
    
    /**
     * Log form submission
     */
    public static function log_form_submission( $form_type, $form_data ) {
        self::log( "Form submission tracked: {$form_type}", self::INFO, $form_data );
    }
    
    /**
     * Log framework detection
     */
    public static function log_framework_detection( $framework, $detected ) {
        $status = $detected ? 'detected' : 'not found';
        self::log( "Framework {$framework}: {$status}", self::DEBUG );
    }
    
    /**
     * Log error
     */
    public static function log_error( $message, $context = array() ) {
        self::log( $message, self::ERROR, $context );
    }
}
