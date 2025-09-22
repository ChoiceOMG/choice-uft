<?php
/**
 * UTM parameter tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_UTM_Tracker {
    
    /**
     * UTM parameters to track
     */
    private $utm_params = array(
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_term',
        'utm_content'
    );
    
    /**
     * Click ID parameters to track
     */
    private $click_id_params = array(
        'click_id',     // Generic click ID parameter
        'gclid',        // Google Ads click ID
        'gbraid',       // Google Ads click ID for iOS app-to-web journeys
        'wbraid',       // Google Ads click ID for web-to-app journeys
        'fbclid',       // Facebook / Instagram (Meta Ads) click ID
        'msclkid',      // Microsoft Advertising (Bing Ads) click ID
        'ttclid',       // TikTok Ads click ID
        'li_fat_id',    // LinkedIn Ads click ID
        'twclid',       // Twitter / X Ads click ID
        'snap_click_id', // Snapchat Ads click ID
        'pclid'         // Pinterest Ads click ID
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_utm_script' ), 1 );
        add_action( 'wp_ajax_cuft_store_utm', array( $this, 'ajax_store_utm' ) );
        add_action( 'wp_ajax_nopriv_cuft_store_utm', array( $this, 'ajax_store_utm' ) );
    }
    
    /**
     * Enqueue UTM tracking script
     */
    public function enqueue_utm_script() {
        wp_enqueue_script(
            'cuft-utm-tracker',
            CUFT_URL . '/assets/cuft-utm-tracker.js',
            array(),
            CUFT_VERSION,
            false // Load in head to capture UTMs early
        );
        
        wp_localize_script( 'cuft-utm-tracker', 'cuftUTM', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_utm_tracking' ),
            'debug' => apply_filters( 'cuft_debug', get_option( 'cuft_debug_enabled', false ) )
        ) );
    }
    
        /**
     * AJAX handler to store tracking data in session
     */
    public function ajax_store_utm() {
        check_ajax_referer( 'cuft_utm_tracking', 'nonce' );
        
        $tracking_data = array();
        
        // Extract UTM parameters
        foreach ( $this->utm_params as $param ) {
            if ( isset( $_POST[ $param ] ) && ! empty( $_POST[ $param ] ) ) {
                $tracking_data[ $param ] = sanitize_text_field( $_POST[ $param ] );
            }
        }
        
        // Extract Click ID parameters
        foreach ( $this->click_id_params as $param ) {
            if ( isset( $_POST[ $param ] ) && ! empty( $_POST[ $param ] ) ) {
                $tracking_data[ $param ] = sanitize_text_field( $_POST[ $param ] );
            }
        }
        
        if ( ! empty( $tracking_data ) ) {
            // Store in session (with cookie fallback)
            if ( ! session_id() ) {
                session_start();
            }
            $_SESSION['cuft_utm_data'] = $tracking_data;
            $_SESSION['cuft_utm_timestamp'] = current_time( 'timestamp' );
            
            // Also store in cookie for persistence
            $this->store_utm_cookie( $tracking_data );
            
            CUFT_Logger::log( 'Tracking data stored for session', CUFT_Logger::DEBUG, $tracking_data );
        }
        
        wp_send_json_success();
    }
    
    /**
     * Store tracking data in cookie
     */
    private function store_utm_cookie( $tracking_data ) {
        $cookie_data = array(
            'utm' => $tracking_data,
            'timestamp' => current_time( 'timestamp' )
        );
        
        // Store for 30 days
        setcookie( 
            'cuft_utm_data', 
            wp_json_encode( $cookie_data ), 
            time() + ( 30 * DAY_IN_SECONDS ), 
            COOKIEPATH, 
            COOKIE_DOMAIN 
        );
    }
    
    /**
     * Get stored UTM data
     */
    public static function get_utm_data() {
        $utm_data = array();
        
        // Try session first
        if ( session_id() && isset( $_SESSION['cuft_utm_data'] ) ) {
            $utm_data = $_SESSION['cuft_utm_data'];
        } else {
            // Fallback to cookie
            if ( isset( $_COOKIE['cuft_utm_data'] ) ) {
                $cookie_data = json_decode( stripslashes( $_COOKIE['cuft_utm_data'] ), true );
                if ( is_array( $cookie_data ) && isset( $cookie_data['utm'] ) ) {
                    // Check if cookie is not too old (30 days)
                    $timestamp = isset( $cookie_data['timestamp'] ) ? $cookie_data['timestamp'] : 0;
                    if ( ( current_time( 'timestamp' ) - $timestamp ) < ( 30 * DAY_IN_SECONDS ) ) {
                        $utm_data = $cookie_data['utm'];
                    }
                }
            }
        }
        
        return $utm_data;
    }
    
    /**
     * Get UTM data for JavaScript
     */
    public static function get_utm_data_for_js() {
        $utm_data = self::get_utm_data();
        return ! empty( $utm_data ) ? $utm_data : null;
    }
    
    /**
     * Clear UTM data
     */
    public static function clear_utm_data() {
        if ( session_id() ) {
            unset( $_SESSION['cuft_utm_data'] );
            unset( $_SESSION['cuft_utm_timestamp'] );
        }
        
        setcookie( 'cuft_utm_data', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
    }
}
