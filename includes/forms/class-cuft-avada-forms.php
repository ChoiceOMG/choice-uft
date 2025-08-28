<?php
/**
 * Avada/Fusion Forms tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Avada_Forms {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        if ( ! CUFT_Form_Detector::is_framework_detected( 'avada' ) ) {
            return;
        }
        
        wp_enqueue_script(
            'cuft-avada-forms',
            CUFT_URL . '/assets/forms/cuft-avada-forms.js',
            array( 'cuft-utm-utils' ),
            CUFT_VERSION,
            true
        );
        
        // Enqueue UTM utilities
        wp_enqueue_script(
            'cuft-utm-utils',
            CUFT_URL . '/assets/cuft-utm-utils.js',
            array(),
            CUFT_VERSION,
            true
        );
        
        $this->localize_script();
    }
    
    /**
     * Localize script with configuration
     */
    private function localize_script() {
        wp_localize_script( 'cuft-avada-forms', 'cuftAvada', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_avada_tracking' )
        ) );
    }
}
