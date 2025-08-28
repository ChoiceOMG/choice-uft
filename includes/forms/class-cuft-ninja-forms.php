<?php
/**
 * Ninja Forms tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Ninja_Forms {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'ninja_forms_after_submission', array( $this, 'track_submission' ) );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        if ( ! CUFT_Form_Detector::is_framework_detected( 'ninja_forms' ) ) {
            return;
        }
        
        wp_enqueue_script(
            'cuft-ninja-forms',
            CUFT_URL . '/assets/forms/cuft-ninja-forms.js',
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
     * Track form submission via Ninja Forms hook
     */
    public function track_submission( $form_data ) {
        $data = $this->extract_form_data( $form_data );
        CUFT_Logger::log_form_submission( 'ninja_forms', $data );
        
        // Push to dataLayer if GTM is configured
        if ( get_option( 'cuft_gtm_id' ) ) {
            wp_add_inline_script( 'cuft-ninja-forms', $this->generate_datalayer_push( $data ), 'after' );
        }
    }
    
    /**
     * Extract form data from Ninja Forms submission
     */
    private function extract_form_data( $form_data ) {
        $form_id = isset( $form_data['form_id'] ) ? $form_data['form_id'] : '';
        $fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();
        
        $data = array(
            'form_id' => $form_id,
            'form_name' => $this->get_form_title( $form_id ),
            'user_email' => '',
            'user_phone' => ''
        );
        
        // Extract email and phone from fields
        foreach ( $fields as $field ) {
            $field_type = isset( $field['type'] ) ? $field['type'] : '';
            $field_value = isset( $field['value'] ) ? $field['value'] : '';
            
            if ( 'email' === $field_type && is_email( $field_value ) ) {
                $data['user_email'] = sanitize_email( $field_value );
            } elseif ( 'phone' === $field_type && ! empty( $field_value ) ) {
                $data['user_phone'] = preg_replace( '/[^0-9+]/', '', $field_value );
            }
        }
        
        return $data;
    }
    
    /**
     * Get form title by ID
     */
    private function get_form_title( $form_id ) {
        if ( function_exists( 'ninja_forms_get_form_by_id' ) ) {
            $form = ninja_forms_get_form_by_id( $form_id );
            return isset( $form['settings']['title'] ) ? $form['settings']['title'] : '';
        }
        return '';
    }
    
    /**
     * Generate dataLayer push JavaScript
     */
    private function generate_datalayer_push( $data ) {
        $payload = array(
            'event' => 'form_submit',
            'formType' => 'ninja_forms',
            'formId' => $data['form_id'],
            'formName' => $data['form_name'],
            'submittedAt' => gmdate( 'c' )
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
        wp_localize_script( 'cuft-ninja-forms', 'cuftNinja', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_ninja_tracking' )
        ) );
    }
}
