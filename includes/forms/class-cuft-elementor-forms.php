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
        // Check if required WordPress functions exist
        if ( ! function_exists( 'add_action' ) || ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }
        
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'elementor_pro/forms/new_record', array( $this, 'track_submission' ), 10, 2 );

        // Add response filter to inject tracking data into Elementor's response
        add_filter( 'elementor_pro/forms/ajax_response_data', array( $this, 'add_tracking_to_response' ), 10, 2 );
    }
    
    /**
     * Enqueue tracking script
     */
    public function enqueue_scripts() {
        // Check if required functions exist
        if ( ! function_exists( 'wp_enqueue_script' ) || ! class_exists( 'CUFT_Form_Detector' ) ) {
            return;
        }

        if ( ! CUFT_Form_Detector::is_framework_detected( 'elementor' ) ) {
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

        // Enqueue UTM utilities with proper dependency
        wp_enqueue_script(
            'cuft-utm-utils',
            CUFT_URL . '/assets/cuft-utm-utils.js',
            array( 'cuft-utm-tracker' ),
            CUFT_VERSION,
            true
        );

        // Enqueue Elementor forms script with dependencies
        wp_enqueue_script(
            'cuft-elementor-forms',
            CUFT_URL . '/assets/forms/cuft-elementor-forms.js',
            array( 'cuft-dataLayer-utils', 'cuft-utm-utils' ),
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
            
            // Generate lead event if enabled and conditions are met
            if ( get_option( 'cuft_generate_lead_enabled', false ) ) {
                $lead_script = $this->generate_lead_event( $form_data );
                if ( $lead_script ) {
                    wp_add_inline_script( 'cuft-elementor-forms', $lead_script, 'after' );
                }
            }
        }
    }
    
    /**
     * Extract form data from record using native Elementor methods
     */
    private function extract_form_data( $record ) {
        // Get form settings using Elementor's native methods
        $meta = $record->get( 'meta' );
        $form_settings = $meta['form_settings'] ?? array();
        $fields = $record->get( 'fields' );

        // Extract comprehensive form metadata
        $data = array(
            'form_id' => $form_settings['id'] ?? '',
            'form_name' => $form_settings['form_name'] ?? '',
            'custom_id' => $form_settings['custom_id'] ?? '',
            'widget_id' => $record->get( 'meta' )['widget_id'] ?? '',
            'user_email' => '',
            'user_phone' => '',
            'field_data' => array() // Store all field data for frontend use
        );

        // Enhanced field extraction with better type detection
        foreach ( $fields as $field_id => $field ) {
            $field_type = $field['type'] ?? '';
            $field_value = $field['value'] ?? '';

            // Store field data for potential frontend access
            $data['field_data'][$field_id] = array(
                'type' => $field_type,
                'value' => $field_value,
                'raw_value' => $field['raw_value'] ?? $field_value
            );

            // Extract email fields
            if ( 'email' === $field_type ) {
                $data['user_email'] = sanitize_email( $field_value );
            }
            // Extract phone fields - multiple types
            elseif ( in_array( $field_type, array( 'tel', 'phone', 'number' ), true ) ) {
                $data['user_phone'] = preg_replace( '/[^0-9+\-\(\)\s]/', '', $field_value );
            }
            // Also check for email-like values in text fields
            elseif ( 'text' === $field_type && is_email( $field_value ) ) {
                $data['user_email'] = sanitize_email( $field_value );
            }
            // Check for phone-like values in text fields
            elseif ( 'text' === $field_type && preg_match( '/[\d\+\-\(\)\s]{7,}/', $field_value ) ) {
                // Only set if we don't already have a phone number
                if ( empty( $data['user_phone'] ) ) {
                    $data['user_phone'] = preg_replace( '/[^0-9+\-\(\)\s]/', '', $field_value );
                }
            }
        }

        // Clean up phone number
        if ( ! empty( $data['user_phone'] ) ) {
            $data['user_phone'] = trim( $data['user_phone'] );
        }

        return $data;
    }
    
    /**
     * Generate dataLayer push JavaScript
     */
    private function generate_datalayer_push( $data ) {
        $payload = array(
            'event' => 'form_submit',
            'form_type' => 'elementor',
            'form_id' => $data['form_id'],
            'form_name' => $data['form_name'],
            'submitted_at' => gmdate( 'c' ),
            'cuft_tracked' => true,
            'cuft_source' => 'elementor_pro_server',
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
            'cuft_source' => 'elementor_pro_server_lead',
            'form_type' => 'elementor',
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
        
        CUFT_Logger::log_form_submission( 'elementor_generate_lead', $payload );
        
        return 'window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $payload ) . ');';
    }
    
    /**
     * Localize script with configuration
     */
    private function localize_script() {
        wp_localize_script( 'cuft-elementor-forms', 'cuftElementor', array(
            'debug' => apply_filters( 'cuft_debug', false ),
            'console_logging' => CUFT_Console_Logger::get_console_logging_setting(),
            'generate_lead_enabled' => get_option( 'cuft_generate_lead_enabled', false ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_elementor_tracking' ),
            'use_native_methods' => true, // Flag to indicate improved methods
            'field_selectors' => array(
                'email' => 'input[type="email"], input[name*="email"], input[id*="email"]',
                'phone' => 'input[type="tel"], input[name*="phone"], input[name*="tel"], input[id*="phone"], input[id*="tel"]'
            )
        ) );
    }

    /**
     * Add tracking data to Elementor's AJAX response
     */
    public function add_tracking_to_response( $response_data, $record ) {
        // Only add tracking data if GTM is configured
        if ( ! get_option( 'cuft_gtm_id' ) ) {
            return $response_data;
        }

        $form_data = $this->extract_form_data( $record );

        // Add tracking data to response for frontend JavaScript access
        $response_data['cuft_tracking'] = array(
            'form_id' => $form_data['form_id'],
            'form_name' => $form_data['form_name'],
            'widget_id' => $form_data['widget_id'],
            'user_email' => $form_data['user_email'],
            'user_phone' => $form_data['user_phone'],
            'timestamp' => gmdate( 'c' ),
            'source' => 'elementor_pro_native'
        );

        return $response_data;
    }
}
