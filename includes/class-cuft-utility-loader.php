<?php
/**
 * CUFT Utility Systems Loader
 *
 * Manages loading and initialization of utility systems for the
 * Choice Universal Form Tracker plugin.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CUFT_Utility_Loader
 *
 * Handles loading and initialization of utility systems including:
 * - Error Boundary System
 * - Performance Monitor System
 * - Observer Cleanup System
 * - Retry Logic System
 */
class CUFT_Utility_Loader {

    /**
     * Utility system files mapping
     */
    private $utility_files = array(
        'error-boundary' => 'cuft-error-boundary.js',
        'performance-monitor' => 'cuft-performance-monitor.js',
        'observer-cleanup' => 'cuft-observer-cleanup.js',
        'retry-logic' => 'cuft-retry-logic.js'
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_utility_systems' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_utility_systems' ), 5 );
    }

    /**
     * Enqueue utility system scripts
     */
    public function enqueue_utility_systems() {
        // Check if utility systems should be loaded
        if ( ! $this->should_load_utilities() ) {
            return;
        }

        $plugin_url = plugins_url( 'assets/', dirname( __FILE__ ) );

        // Load utility systems in dependency order
        foreach ( $this->utility_files as $handle => $filename ) {
            $script_path = CUFT_PATH . 'assets/' . $filename;

            if ( file_exists( $script_path ) ) {
                wp_enqueue_script(
                    'cuft-' . $handle,
                    $plugin_url . $filename,
                    array(), // No jQuery dependency
                    CUFT_VERSION,
                    true // Load in footer
                );

                // Add script attributes for better performance
                add_filter( 'script_loader_tag', array( $this, 'add_script_attributes' ), 10, 3 );
            } else {
                error_log( "CUFT Warning: Utility system file missing: {$filename}" );
            }
        }

        // Add utility system initialization
        $this->add_utility_initialization();
    }

    /**
     * Add script attributes for utility systems
     */
    public function add_script_attributes( $tag, $handle, $src ) {
        // Only modify CUFT utility scripts
        if ( strpos( $handle, 'cuft-' ) !== 0 || ! in_array( str_replace( 'cuft-', '', $handle ), array_keys( $this->utility_files ) ) ) {
            return $tag;
        }

        // Add defer attribute for non-blocking loading
        if ( ! is_admin() ) {
            $tag = str_replace( ' src', ' defer src', $tag );
        }

        return $tag;
    }

    /**
     * Add utility system initialization script
     */
    private function add_utility_initialization() {
        $init_script = "
        // CUFT Utility Systems Initialization
        (function() {
            'use strict';

            // Wait for utility systems to be available
            function checkUtilitySystems() {
                var systems = {
                    errorBoundary: window.cuftErrorBoundary,
                    performanceMonitor: window.cuftPerformanceMonitor,
                    observerCleanup: window.cuftObserverCleanup,
                    retryLogic: window.cuftRetryLogic
                };

                var loaded = Object.keys(systems).filter(function(key) {
                    return !!systems[key];
                });

                if (loaded.length > 0 && window.console && window.console.log) {
                    console.log('[CUFT] Utility systems loaded:', loaded.join(', '));
                }

                // Enable performance monitoring if available
                if (systems.performanceMonitor) {
                    try {
                        systems.performanceMonitor.enableAutoReporting();
                    } catch (e) {
                        // Silent failure for optional feature
                    }
                }

                return loaded.length;
            }

            // Check immediately and with fallback
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkUtilitySystems);
            } else {
                setTimeout(checkUtilitySystems, 100);
            }
        })();
        ";

        wp_add_inline_script( 'cuft-error-boundary', $init_script );
    }

    /**
     * Check if utility systems should be loaded
     */
    private function should_load_utilities() {
        // Check feature flags
        $feature_flags = get_option( 'cuft_feature_flags', array() );

        // Default to enabled if no feature flags set (gradual rollout)
        $utility_systems_enabled = isset( $feature_flags['utility_systems'] )
            ? $feature_flags['utility_systems']
            : true;

        // Allow filtering for advanced control
        return apply_filters( 'cuft_load_utility_systems', $utility_systems_enabled );
    }

    /**
     * Get utility system status
     */
    public function get_utility_status() {
        $status = array();

        foreach ( $this->utility_files as $handle => $filename ) {
            $script_path = CUFT_PATH . 'assets/' . $filename;
            $status[ $handle ] = array(
                'file' => $filename,
                'exists' => file_exists( $script_path ),
                'enabled' => $this->should_load_utilities(),
                'size' => file_exists( $script_path ) ? filesize( $script_path ) : 0
            );
        }

        return $status;
    }

    /**
     * Get utility system performance metrics (if available)
     */
    public function get_performance_metrics() {
        // This would be called via AJAX to get client-side performance data
        // For now, return server-side file information
        return array(
            'utility_systems_loaded' => $this->should_load_utilities(),
            'file_status' => $this->get_utility_status(),
            'total_size' => array_sum( array_column( $this->get_utility_status(), 'size' ) ),
            'load_time' => microtime( true ) // Basic timing
        );
    }
}

// Initialize utility loader
if ( ! function_exists( 'cuft_get_utility_loader' ) ) {
    /**
     * Get utility loader instance
     */
    function cuft_get_utility_loader() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new CUFT_Utility_Loader();
        }
        return $instance;
    }
}