<?php
/**
 * Contact Form 7 tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_CF7_Forms {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wpcf7_mail_sent', array( $this, 'track_submission' ) );
        add_action( 'wpcf7_mail_failed', array( $this, 'track_mail_failed' ) );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        if ( ! CUFT_Form_Detector::is_framework_detected( 'contact_form_7' ) ) {
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

        // Enqueue CF7 forms script with dependencies
        wp_enqueue_script(
            'cuft-cf7-forms',
            CUFT_URL . '/assets/forms/cuft-cf7-forms.js',
            array( 'cuft-dataLayer-utils', 'cuft-utm-utils' ),
            CUFT_VERSION,
            true
        );

        $this->localize_script();
    }
    
    /**
     * Track form submission via CF7 hook
     */
    public function track_submission( $contact_form ) {
        $form_data = $this->extract_form_data( $contact_form );
        CUFT_Logger::log_form_submission( 'contact_form_7', $form_data );

        // No dataLayer script is emitted from PHP. This hook runs on the
        // submission request (AJAX, REST, or the early 'wp' action of a
        // no-JS post), before any front-end script is registered, so a
        // wp_add_inline_script() here never reached a page. The front-end
        // script pushes form_submit, generate_lead and qualify_lead.
    }
    
    /**
     * Log CF7 mail send failures (OPS-2652)
     *
     * CF7 core has no PHP action for validation_failed/spam/acceptance_missing/
     * aborted statuses, only wpcf7_mail_sent and wpcf7_mail_failed. Without this,
     * a failed send is completely invisible server-side.
     */
    public function track_mail_failed( $contact_form ) {
        $form_data = $this->extract_form_data( $contact_form );
        CUFT_Logger::log_error( 'CF7 mail failed to send: contact_form_7', $form_data );
    }

    /**
     * Extract form data from CF7 contact form
     */
    private function extract_form_data( $contact_form ) {
        $submission = WPCF7_Submission::get_instance();
        $posted_data = $submission ? $submission->get_posted_data() : array();
        
        $data = array(
            'form_id' => $contact_form->id(),
            'form_name' => $contact_form->title(),
            'user_email' => '',
            'user_phone' => ''
        );
        
        // Extract email and phone from posted data
        foreach ( $posted_data as $key => $value ) {
            // Skip arrays (checkboxes, multi-select) - get first value if needed
            if ( is_array( $value ) ) {
                $value = ! empty( $value ) ? reset( $value ) : '';
            }

            // Skip non-string values
            if ( ! is_string( $value ) || empty( $value ) ) {
                continue;
            }

            if ( is_email( $value ) ) {
                $data['user_email'] = sanitize_email( $value );
            } elseif ( preg_match( '/phone|tel|mobile/i', $key ) ) {
                $data['user_phone'] = preg_replace( '/[^0-9+]/', '', $value );
            }
        }
        
        return $data;
    }
    
    
    
    /**
     * Localize script with configuration
     */
    private function localize_script() {
        CUFT_Utils::localize( 'cuft-cf7-forms', 'cuftCF7', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'console_logging' => CUFT_Console_Logger::get_console_logging_setting(),
            'generate_lead_enabled' => get_option( 'cuft_generate_lead_enabled', false ),
            'lead_currency' => get_option( 'cuft_lead_currency', 'CAD' ),
            'lead_value' => get_option( 'cuft_lead_value', 100 ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_cf7_tracking' )
        ) );
    }
}
