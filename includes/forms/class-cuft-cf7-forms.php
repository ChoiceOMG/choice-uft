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
        
        // Push to dataLayer if GTM is configured
        if ( get_option( 'cuft_gtm_id' ) ) {
            wp_add_inline_script( 'cuft-cf7-forms', $this->generate_datalayer_push( $form_data ), 'after' );
            
            // Generate lead event if enabled and conditions are met
            if ( get_option( 'cuft_generate_lead_enabled', false ) ) {
                $lead_script = $this->generate_lead_event( $form_data );
                if ( $lead_script ) {
                    wp_add_inline_script( 'cuft-cf7-forms', $lead_script, 'after' );
                }
            }
        }
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
     * Generate dataLayer push JavaScript
     */
    private function generate_datalayer_push( $data ) {
        $payload = array(
            'event' => 'form_submit',
            'form_type' => 'contact_form_7',
            'form_id' => $data['form_id'],
            'form_name' => $data['form_name'],
            'submitted_at' => gmdate( 'c' ),
            'cuft_tracked' => true,
            'cuft_source' => 'contact_form_7_server',
            'page_location' => home_url( $_SERVER['REQUEST_URI'] ),
            'page_title' => get_the_title(),
            'language' => get_locale()
        );
        
        if ( ! empty( $data['user_email'] ) ) {
            $payload['user_email'] = $data['user_email'];
        }
        if ( ! empty( $data['user_phone'] ) ) {
            $payload['user_phone'] = $data['user_phone'];
        }
        
        // Add UTM data if available
        $utm_data = CUFT_UTM_Tracker::get_utm_data();
        if ( ! empty( $utm_data ) ) {
            foreach ( $utm_data as $key => $value ) {
                $payload[ $key ] = $value;
            }
        }
        
        return 'window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $payload ) . ');';
    }
    
    /**
     * Generate lead event if conditions are met
     */
    private function generate_lead_event( $data ) {
        // Check if we have email and UTM campaign
        if ( empty( $data['user_email'] ) ) {
            return false;
        }
        
        $utm_data = CUFT_UTM_Tracker::get_utm_data();
        if ( empty( $utm_data['utm_campaign'] ) ) {
            return false;
        }
        
        $payload = array(
            'event' => 'generate_lead',
            'currency' => 'USD',
            'value' => 0,
            'cuft_tracked' => true,
            'cuft_source' => 'contact_form_7_server_lead',
            'form_type' => 'contact_form_7',
            'form_id' => $data['form_id'],
            'form_name' => $data['form_name'],
            'page_location' => home_url( $_SERVER['REQUEST_URI'] ),
            'page_title' => get_the_title(),
            'language' => get_locale(),
            'submitted_at' => gmdate( 'c' )
        );
        
        // Add UTM data
        foreach ( $utm_data as $key => $value ) {
            $payload[ $key ] = $value;
        }
        
        CUFT_Logger::log_form_submission( 'cf7_generate_lead', $payload );
        
        return 'window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $payload ) . ');';
    }
    
    /**
     * Localize script with configuration
     */
    private function localize_script() {
        wp_localize_script( 'cuft-cf7-forms', 'cuftCF7', array(
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
