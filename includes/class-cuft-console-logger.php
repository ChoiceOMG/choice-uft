<?php
/**
 * Console logging utilities
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Console_Logger {
    
    /**
     * Determine if console logging should be enabled
     */
    public static function should_enable_console_logging() {
        $console_logging = get_option( 'cuft_console_logging', 'no' );
        
        switch ( $console_logging ) {
            case 'yes':
                return true;
                
            case 'admin_only':
                return current_user_can( 'administrator' );
                
            case 'no':
            default:
                return false;
        }
    }
    
    /**
     * Get console logging setting for JavaScript
     */
    public static function get_console_logging_setting() {
        return self::should_enable_console_logging();
    }
}
