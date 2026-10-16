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
        $console_logging = (bool) CUFT_Console_Logger::get_console_logging_setting();
        wp_add_inline_script( 'cuft-link-tracking', 'window.cuftDebug = ' . CUFT_Utils::js_encode( $console_logging ) . ';', 'before' );

        // The ignore list also travels in cuftConfig. Printing it with this
        // script as well means the click handler never depends on another
        // handle having been printed, so an ignored number is never pushed to
        // the dataLayer.
        if ( class_exists( 'CUFT_Privacy' ) ) {
            wp_add_inline_script(
                'cuft-link-tracking',
                'window.cuftIgnorePhones = ' . CUFT_Utils::js_encode( CUFT_Privacy::get_ignored_phones() ) . ';',
                'before'
            );
        }
    }
}
