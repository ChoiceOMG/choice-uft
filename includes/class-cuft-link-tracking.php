<?php
/**
 * Link tracking functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Link_Tracking {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
    }
    
    /**
     * Enqueue link tracking scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'cuft-link-tracking',
            CUFT_URL . '/assets/cuft-links.js',
            array(),
            CUFT_VERSION,
            true
        );
        
        // Set console logging flag
        $console_logging = CUFT_Console_Logger::get_console_logging_setting() ? 'true' : 'false';
        wp_add_inline_script( 'cuft-link-tracking', 'window.cuftDebug = ' . $console_logging . ';', 'before' );
    }
}
