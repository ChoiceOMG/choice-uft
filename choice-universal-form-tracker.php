<?php
/**
 * Plugin Name:       Choice Universal Form Tracker
 * Description:       Universal form tracking for WordPress - supports Avada, Elementor Pro, Contact Form 7, Ninja Forms, Gravity Forms, and more. Tracks submissions and link clicks via Google Tag Manager's dataLayer.
 * Version:           3.1.0
 * Author:            Choice OMG
 * Author URI:        https://choice.marketing
 * Text Domain:       choice-universal-form-tracker
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CUFT_VERSION', '3.1.0' );
define( 'CUFT_URL', plugins_url( '', __FILE__ ) );
define( 'CUFT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUFT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Choice_Universal_Form_Tracker {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once CUFT_PATH . 'includes/class-cuft-admin.php';
        require_once CUFT_PATH . 'includes/class-cuft-gtm.php';
        require_once CUFT_PATH . 'includes/class-cuft-form-detector.php';
        require_once CUFT_PATH . 'includes/class-cuft-form-tracker.php';
        require_once CUFT_PATH . 'includes/class-cuft-link-tracking.php';
        require_once CUFT_PATH . 'includes/class-cuft-logger.php';
        require_once CUFT_PATH . 'includes/class-cuft-utm-tracker.php';
        require_once CUFT_PATH . 'includes/class-cuft-console-logger.php';
        
        // Form framework handlers
        require_once CUFT_PATH . 'includes/forms/class-cuft-avada-forms.php';
        require_once CUFT_PATH . 'includes/forms/class-cuft-elementor-forms.php';
        require_once CUFT_PATH . 'includes/forms/class-cuft-cf7-forms.php';
        require_once CUFT_PATH . 'includes/forms/class-cuft-ninja-forms.php';
        require_once CUFT_PATH . 'includes/forms/class-cuft-gravity-forms.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if plugin is enabled
        if ( ! apply_filters( 'cuft_enabled', true ) ) {
            return;
        }
        
        // Initialize core components
        if ( is_admin() ) {
            new CUFT_Admin();
        }
        
        new CUFT_GTM();
        new CUFT_Form_Detector();
        new CUFT_Form_Tracker();
        new CUFT_Link_Tracking();
        new CUFT_UTM_Tracker();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if they don't exist
        if ( false === get_option( 'cuft_gtm_id' ) ) {
            add_option( 'cuft_gtm_id', '' );
        }
        if ( false === get_option( 'cuft_debug_enabled' ) ) {
            add_option( 'cuft_debug_enabled', false );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup debug logs older than 30 days
        delete_option( 'cuft_debug_logs' );
    }
}

// Initialize the plugin
Choice_Universal_Form_Tracker::get_instance();
