<?php
/**
 * Elementor Pro Forms tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Elementor_Forms {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'elementor_pro/forms/new_record', array( $this, 'track_submission' ), 10, 2 );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        if ( ! CUFT_Form_Detector::is_framework_detected( 'elementor' ) ) {
            return;
        }
        
        wp_enqueue_script(
            'cuft-elementor-forms',
            CUFT_URL . '/assets/forms/cuft-elementor-forms.js',
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
     * Track form submission via hook
     */
    public function track_submission( $record, $ajax_handler ) {
        $form_data = $this->extract_form_data( $record );
        CUFT_Logger::log_form_submission( 'elementor', $form_data );
        
        // Push to dataLayer if GTM is configured
        if ( get_option( 'cuft_gtm_id' ) ) {
            wp_add_inline_script( 'cuft-elementor-forms', $this->generate_datalayer_push( $form_data ), 'after' );
        }
    }
    
    /**
     * Extract form data from record
     */
    private function extract_form_data( $record ) {
        $fields = $record->get( 'fields' );
        $form_id = $record->get( 'form_settings' )['id'] ?? '';
        
        $data = array(
            'form_id' => $form_id,
            'form_name' => $record->get( 'form_settings' )['form_name'] ?? '',
            'user_email' => '',
            'user_phone' => ''
        );
        
        // Extract email and phone from fields
        foreach ( $fields as $field ) {
            if ( 'email' === $field['type'] ) {
                $data['user_email'] = sanitize_email( $field['value'] );
            } elseif ( 'tel' === $field['type'] ) {
                $data['user_phone'] = preg_replace( '/[^0-9+]/', '', $field['value'] );
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
            'formType' => 'elementor',
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
        wp_localize_script( 'cuft-elementor-forms', 'cuftElementor', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_elementor_tracking' )
        ) );
    }
}
