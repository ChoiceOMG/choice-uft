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
        'gbraid',          // Google Ads (iOS app-to-web)
        'wbraid',          // Google Ads (web-to-app)
        'fbclid',          // Meta (Facebook / Instagram) Ads
        'rdt_cid',         // Reddit Ads
        'msclkid',         // Microsoft Ads
        'ttclid',          // TikTok
        'li_fat_id',       // LinkedIn
        'twclid',          // X (Twitter)
        'snap_click_id',   // Snapchat
        'pclid',           // Pinterest (current parameter name)
        'click_id',        // Generic
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
     *
     * Reads the ad platform's click ID from the landing URL (the ad network
     * builds that link, so it cannot carry a nonce). The value is sanitized,
     * limited to 255 characters (the click_id column), and written only as
     * one click row plus the visitor's own cuft_click_id cookie.
     */
    public function capture_click_ids() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        
        foreach ( $this->click_id_params as $param ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Ad click ID (gclid, fbclid, ...) on the landing URL; the ad platform builds that link and cannot carry a nonce.
            $click_id = isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
            if ( ! empty( $click_id ) && strlen( $click_id ) <= 255 ) {
                $platform = $this->get_platform_from_param( $param );
                
                $this->track_click_id( $click_id, $platform );
                break; // Only process the first click ID found
            }
        }
    }
    
    /**
     * UTM parameters on the current request's URL.
     *
     * Taken as one set: when the URL carries any of them, the cookie's
     * values from an earlier campaign are not mixed in.
     *
     * @return array utm_* => value, only the parameters present.
     */
    private static function get_landing_utm_data() {
        $utm_data = array();
        foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $param ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Campaign parameters on the landing URL; the ad platform builds that link and cannot carry a nonce.
            if ( isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
                $value = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                if ( '' !== $value ) {
                    $utm_data[ $param ] = $value;
                }
            }
        }
        return $utm_data;
    }

    /**
     * Track click ID with UTM data
     */
    private function track_click_id( $click_id, $platform ) {
        if ( ! class_exists( 'CUFT_Click_Tracker' ) || ! class_exists( 'CUFT_UTM_Tracker' ) ) {
            return;
        }
        
        // UTMs on the landing URL belong to this click. The cuft_utm_data
        // cookie is written by the browser only after this page loads, so on
        // the landing request it holds an earlier visit's values or nothing.
        $utm_data = self::get_landing_utm_data();
        if ( empty( $utm_data ) ) {
            $utm_data = CUFT_UTM_Tracker::get_utm_data();
        }
        
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
            'additional_data' => wp_json_encode( array(
                'referrer' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
                'landing_page' => isset( $_SERVER['REQUEST_URI'] ) ? home_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : home_url( '/' ),
                'timestamp' => current_time( 'mysql', true )
            ) )
        );
        
        // Track the click
        $result = CUFT_Click_Tracker::track_click( $click_id, $tracking_data );
        
        // Store click ID in cookie for form submissions
        if ( $result !== false ) {
            // A theme or plugin that prints before the wp action leaves no
            // way to send the cookie; skip it rather than raise a warning on
            // every ad landing. The row is already written.
            if ( ! headers_sent() ) {
                setcookie( 'cuft_click_id', $click_id, array(
                    'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
                    'path'     => COOKIEPATH,
                    'domain'   => COOKIE_DOMAIN,
                    'secure'   => is_ssl(),
                    'httponly'  => false, // Read by client-side JS for event replay
                    'samesite' => 'Lax',
                ) );
            }
            $_COOKIE['cuft_click_id'] = $click_id; // Make available in current request

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
        
        // Nothing about the visitor goes into the page. A full-page cache
        // would serve this HTML to everyone, so a click ID printed here would
        // attach one ad visitor's click to every later visitor's leads. The
        // script reads the visitor's own cuft_click_id cookie instead.
        CUFT_Utils::localize( 'cuft-click-integration', 'cuftClickData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_click_nonce' )
        ) );
    }
    
    /**
     * Get current click ID from cookie
     */
    public static function get_current_click_id() {
        return isset( $_COOKIE['cuft_click_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['cuft_click_id'] ) ) : '';
    }

    /**
     * Clear current click ID
     */
    public static function clear_current_click_id() {
        setcookie( 'cuft_click_id', '', array(
            'expires'  => time() - 3600,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly'  => false,
            'samesite' => 'Lax',
        ) );
        unset( $_COOKIE['cuft_click_id'] );
    }
}
