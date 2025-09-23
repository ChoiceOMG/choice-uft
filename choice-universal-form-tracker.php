<?php
/**
 * Plugin Name:       Choice Universal Form Tracker
 * Description:       Universal form tracking for WordPress - supports Avada, Elementor Pro, Contact Form 7, Ninja Forms, Gravity Forms, and more. Tracks submissions and link clicks via Google Tag Manager's dataLayer.
 * Version:           3.8.7
 * Author:            Choice OMG
 * Author URI:        https://choice.marketing
 * Text Domain:       choice-universal-form-tracker
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CUFT_VERSION', '3.8.7' );
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
        // Core includes with error handling
        $includes = array(
            'includes/class-cuft-admin.php',
            'includes/class-cuft-gtm.php',
            'includes/class-cuft-form-detector.php',
            'includes/class-cuft-form-tracker.php',
            'includes/class-cuft-link-tracking.php',
            'includes/class-cuft-logger.php',
            'includes/class-cuft-utm-tracker.php',
            'includes/class-cuft-console-logger.php',
            'includes/class-cuft-github-updater.php',
            'includes/class-cuft-click-tracker.php',
            'includes/class-cuft-click-integration.php',
            'includes/class-cuft-test-forms.php',
            // Form framework handlers
            'includes/forms/class-cuft-avada-forms.php',
            'includes/forms/class-cuft-elementor-forms.php',
            'includes/forms/class-cuft-cf7-forms.php',
            'includes/forms/class-cuft-ninja-forms.php',
            'includes/forms/class-cuft-gravity-forms.php'
        );
        
        foreach ( $includes as $file ) {
            $filepath = CUFT_PATH . $file;
            if ( file_exists( $filepath ) ) {
                require_once $filepath;
            } else {
                // Log missing file error but don't break the plugin
                error_log( "CUFT Warning: Missing file {$file}" );
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        // Initialize GitHub updater
        if ( class_exists( 'CUFT_GitHub_Updater' ) && CUFT_GitHub_Updater::updates_enabled() ) {
            global $cuft_updater;
            $cuft_updater = new CUFT_GitHub_Updater( 
                __FILE__, 
                CUFT_VERSION, 
                'ChoiceOMG', 
                'choice-uft'
            );
        }
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if plugin is enabled
        if ( ! apply_filters( 'cuft_enabled', true ) ) {
            return;
        }
        
        // Check PHP version compatibility
        if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            }
            return;
        }
        
        // Check WordPress version compatibility  
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
            }
            return;
        }
        
        // Initialize core components with error handling
        try {
            if ( is_admin() && class_exists( 'CUFT_Admin' ) ) {
                new CUFT_Admin();
            }
            
            if ( class_exists( 'CUFT_GTM' ) ) {
                new CUFT_GTM();
            }
            
            if ( class_exists( 'CUFT_Form_Detector' ) ) {
                new CUFT_Form_Detector();
            }
            
            if ( class_exists( 'CUFT_Form_Tracker' ) ) {
                new CUFT_Form_Tracker();
            }
            
            if ( class_exists( 'CUFT_Link_Tracking' ) ) {
                new CUFT_Link_Tracking();
            }
            
            if ( class_exists( 'CUFT_UTM_Tracker' ) ) {
                new CUFT_UTM_Tracker();
            }
            
            if ( class_exists( 'CUFT_Click_Tracker' ) ) {
                new CUFT_Click_Tracker();
            }
            
            if ( class_exists( 'CUFT_Click_Integration' ) ) {
                new CUFT_Click_Integration();
            }
            
        } catch ( Exception $e ) {
            error_log( "CUFT Error during initialization: " . $e->getMessage() );
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'init_error_notice' ) );
            }
        }
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
        if ( false === get_option( 'cuft_github_updates_enabled' ) ) {
            add_option( 'cuft_github_updates_enabled', true );
        }
        
        // Create click tracking table
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::create_table();
        }
        
        // Flush rewrite rules to enable webhook endpoints
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup debug logs older than 30 days
        delete_option( 'cuft_debug_logs' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * PHP version compatibility notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> This plugin requires PHP 7.0 or higher. ';
        echo 'You are running PHP ' . PHP_VERSION . '. Please contact your hosting provider to upgrade PHP.';
        echo '</p></div>';
    }
    
    /**
     * WordPress version compatibility notice
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> This plugin requires WordPress 5.0 or higher. ';
        echo 'You are running WordPress ' . get_bloginfo( 'version' ) . '. Please update WordPress.';
        echo '</p></div>';
    }
    
    /**
     * Initialization error notice
     */
    public function init_error_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> Plugin initialization failed. ';
        echo 'Please check error logs for details or contact support.';
        echo '</p></div>';
    }
}

// Initialize the plugin
Choice_Universal_Form_Tracker::get_instance();
