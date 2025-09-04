<?php
/**
 * Click tracking integration with UTM tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Click_Integration {
    
    /**
     * Common click ID parameter names
     */
    private $click_id_params = array(
        'gclid',           // Google Ads
        'fbclid',          // Facebook
        'msclkid',         // Microsoft Ads
        'ttclid',          // TikTok
        'li_fat_id',       // LinkedIn
        'twclid',          // Twitter
        'pinclid',         // Pinterest
        'click_id',        // Generic
        'clickid',         // Generic alternative
        'cid'              // Short form
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_hooks' ) );
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Capture click IDs on page load
        add_action( 'wp', array( $this, 'capture_click_ids' ) );
        
        // Add click ID to form submissions
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_click_tracking_script' ) );
    }
    
    /**
     * Capture click IDs from URL parameters
     */
    public function capture_click_ids() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        
        foreach ( $this->click_id_params as $param ) {
            if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
                $click_id = sanitize_text_field( $_GET[ $param ] );
                $platform = $this->get_platform_from_param( $param );
                
                $this->track_click_id( $click_id, $platform );
                break; // Only process the first click ID found
            }
        }
    }
    
    /**
     * Track click ID with UTM data
     */
    private function track_click_id( $click_id, $platform ) {
        if ( ! class_exists( 'CUFT_Click_Tracker' ) || ! class_exists( 'CUFT_UTM_Tracker' ) ) {
            return;
        }
        
        // Get current UTM data
        $utm_data = CUFT_UTM_Tracker::get_utm_data();
        
        // Prepare tracking data
        $tracking_data = array(
            'platform' => $platform,
            'campaign' => isset( $utm_data['utm_campaign'] ) ? $utm_data['utm_campaign'] : '',
            'utm_source' => isset( $utm_data['utm_source'] ) ? $utm_data['utm_source'] : '',
            'utm_medium' => isset( $utm_data['utm_medium'] ) ? $utm_data['utm_medium'] : '',
            'utm_campaign' => isset( $utm_data['utm_campaign'] ) ? $utm_data['utm_campaign'] : '',
            'utm_term' => isset( $utm_data['utm_term'] ) ? $utm_data['utm_term'] : '',
            'utm_content' => isset( $utm_data['utm_content'] ) ? $utm_data['utm_content'] : '',
            'qualified' => 0, // Default to unqualified
            'score' => 0,     // Default score
            'additional_data' => json_encode( array(
                'referrer' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '',
                'landing_page' => home_url( add_query_arg( null, null ) ),
                'timestamp' => current_time( 'mysql', true )
            ) )
        );
        
        // Track the click
        $result = CUFT_Click_Tracker::track_click( $click_id, $tracking_data );
        
        // Store click ID in session for form submissions
        if ( $result !== false ) {
            if ( ! session_id() ) {
                session_start();
            }
            $_SESSION['cuft_click_id'] = $click_id;
            
            // Log the tracking
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Click ID captured: ' . $click_id, 'info', array(
                    'platform' => $platform,
                    'utm_data' => $utm_data
                ) );
            }
        }
    }
    
    /**
     * Get platform name from parameter
     */
    private function get_platform_from_param( $param ) {
        $platform_map = array(
            'gclid' => 'google',
            'fbclid' => 'facebook',
            'msclkid' => 'microsoft',
            'ttclid' => 'tiktok',
            'li_fat_id' => 'linkedin',
            'twclid' => 'twitter',
            'pinclid' => 'pinterest'
        );
        
        return isset( $platform_map[ $param ] ) ? $platform_map[ $param ] : 'unknown';
    }
    
    /**
     * Enqueue click tracking integration script
     */
    public function enqueue_click_tracking_script() {
        if ( ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }
        
        wp_enqueue_script(
            'cuft-click-integration',
            CUFT_URL . '/assets/cuft-click-integration.js',
            array(),
            CUFT_VERSION,
            true
        );
        
        // Pass click ID to JavaScript
        $click_id = '';
        if ( session_id() && isset( $_SESSION['cuft_click_id'] ) ) {
            $click_id = $_SESSION['cuft_click_id'];
        }
        
        wp_localize_script( 'cuft-click-integration', 'cuftClickData', array(
            'click_id' => $click_id,
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_click_nonce' )
        ) );
    }
    
    /**
     * Get current click ID from session
     */
    public static function get_current_click_id() {
        if ( ! session_id() ) {
            session_start();
        }
        
        return isset( $_SESSION['cuft_click_id'] ) ? $_SESSION['cuft_click_id'] : '';
    }
    
    /**
     * Clear current click ID from session
     */
    public static function clear_current_click_id() {
        if ( ! session_id() ) {
            session_start();
        }
        
        unset( $_SESSION['cuft_click_id'] );
    }
}
