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
        
        // Set debug flag
        $debug = apply_filters( 'cuft_debug', get_option( 'cuft_debug_enabled', false ) ) ? 'true' : 'false';
        wp_add_inline_script( 'cuft-link-tracking', 'window.cuftDebug = ' . $debug . ';', 'before' );
    }
}
