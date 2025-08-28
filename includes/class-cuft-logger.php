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
            'url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : ''
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
