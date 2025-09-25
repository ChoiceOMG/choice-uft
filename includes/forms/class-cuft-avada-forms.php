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

        // Enqueue dataLayer utilities first (required by all framework scripts)
        wp_enqueue_script(
            'cuft-dataLayer-utils',
            CUFT_URL . '/assets/cuft-dataLayer-utils.js',
            array(),
            CUFT_VERSION,
            false // Load in header for availability
        );

        // Enqueue UTM utilities
        wp_enqueue_script(
            'cuft-utm-utils',
            CUFT_URL . '/assets/cuft-utm-utils.js',
            array(),
            CUFT_VERSION,
            true
        );

        // Enqueue Avada forms script with dependencies
        wp_enqueue_script(
            'cuft-avada-forms',
            CUFT_URL . '/assets/forms/cuft-avada-forms.js',
            array( 'cuft-dataLayer-utils', 'cuft-utm-utils' ),
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
            'console_logging' => CUFT_Console_Logger::get_console_logging_setting(),
            'generate_lead_enabled' => get_option( 'cuft_generate_lead_enabled', false ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_avada_tracking' )
        ) );
    }
}
