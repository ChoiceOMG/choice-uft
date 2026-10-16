<?php
/**
 * Gravity Forms tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Gravity_Forms {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'gform_after_submission', array( $this, 'track_submission' ), 10, 2 );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        if ( ! CUFT_Form_Detector::is_framework_detected( 'gravity_forms' ) ) {
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

        // Enqueue Gravity forms script with dependencies
        wp_enqueue_script(
            'cuft-gravity-forms',
            CUFT_URL . '/assets/forms/cuft-gravity-forms.js',
            array( 'cuft-dataLayer-utils', 'cuft-utm-utils' ),
            CUFT_VERSION,
            true
        );

        $this->localize_script();
    }
    
    /**
     * Track form submission via Gravity Forms hook
     */
    public function track_submission( $entry, $form ) {
        $data = $this->extract_form_data( $entry, $form );
        CUFT_Logger::log_form_submission( 'gravity_forms', $data );

        // No dataLayer script is emitted from PHP. This hook runs on the
        // submission request (AJAX, or Gravity Forms' own processing on the
        // 'wp' action for a full-page post, which runs before
        // wp_enqueue_scripts), before any front-end script is registered, so a
        // wp_add_inline_script() here never reached a page. The front-end
        // script pushes form_submit, generate_lead and qualify_lead.
    }
    
    /**
     * Extract form data from Gravity Forms entry
     */
    private function extract_form_data( $entry, $form ) {
        $data = array(
            'form_id' => $form['id'],
            'form_name' => $form['title'],
            'user_email' => '',
            'user_phone' => ''
        );
        
        // Extract email and phone from form fields
        foreach ( $form['fields'] as $field ) {
            $field_value = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
            
            if ( 'email' === $field->type && is_email( $field_value ) ) {
                $data['user_email'] = sanitize_email( $field_value );
            } elseif ( 'phone' === $field->type && ! empty( $field_value ) ) {
                $data['user_phone'] = preg_replace( '/[^0-9+]/', '', $field_value );
            }
        }
        
        return $data;
    }
    
    /**
     * Localize script with configuration
     */
    private function localize_script() {
        CUFT_Utils::localize( 'cuft-gravity-forms', 'cuftGravity', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'console_logging' => CUFT_Console_Logger::get_console_logging_setting(),
            'generate_lead_enabled' => get_option( 'cuft_generate_lead_enabled', false ),
            'lead_currency' => get_option( 'cuft_lead_currency', 'CAD' ),
            'lead_value' => get_option( 'cuft_lead_value', 100 ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_gravity_tracking' )
        ) );
    }
}
