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
        
        // Push to dataLayer if GTM is configured
        if ( get_option( 'cuft_gtm_id' ) ) {
            wp_add_inline_script( 'cuft-gravity-forms', $this->generate_datalayer_push( $data ), 'after' );

            // generate_lead itself is NOT pushed here. Unlike Elementor/CF7/Ninja,
            // whose forms only ever submit by AJAX (this hook fires on a separate
            // request whose response never carries an enqueued script tag, so
            // their equivalent inline push never reaches the browser), Gravity
            // Forms can complete this same request with a full page render, in
            // which case this inline script DOES print and run. The client-side
            // cuft-gravity-forms.js already pushes generate_lead for every
            // submission (AJAX and full-page) via the shared dataLayer utils, so
            // a server-side push here would double-fire generate_lead for any
            // full-page Gravity submission. Removed 3.28.1; see CHANGELOG.
        }
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
     * Generate dataLayer push JavaScript
     */
    private function generate_datalayer_push( $data ) {
        $payload = array(
            'event' => 'form_submit',
            'form_type' => 'gravity_forms',
            'form_id' => $data['form_id'],
            'form_name' => $data['form_name'],
            'submitted_at' => gmdate( 'c' ),
            'cuft_tracked' => true,
            'cuft_source' => 'gravity_forms_server',
            'page_location' => home_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ),
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
     * Localize script with configuration
     */
    private function localize_script() {
        wp_localize_script( 'cuft-gravity-forms', 'cuftGravity', array(
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
