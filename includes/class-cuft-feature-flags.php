<?php
/**
 * Feature Flags Manager for Choice Universal Form Tracker
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Feature_Flags
 * @since      4.0.0
 */

// Exit if accessed directly
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Feature Flags class for managing constitutional compliance migration
 */
class CUFT_Feature_Flags {

    /**
     * Constructor
     */
    public function __construct() {
        // Enqueue feature flags script very early
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_feature_flags' ), 0 );
    }

    /**
     * Enqueue feature flags script
     */
    public function enqueue_feature_flags() {
        // Enqueue feature flags script
        wp_enqueue_script(
            'cuft-feature-flags',
            CUFT_URL . '/assets/cuft-feature-flags.js',
            array(),
            CUFT_VERSION,
            false // Load in head to initialize flags early
        );

        // Pass configuration from WordPress options
        $flags = array(
            'silentFrameworkDetection' => true, // Phase 1 now enabled
            'strictGenerateLeadRules' => true,  // Already implemented in dataLayer utils
            'debugMode' => apply_filters( 'cuft_debug', get_option( 'cuft_debug_enabled', false ) )
        );

        // Allow filter to modify flags
        $flags = apply_filters( 'cuft_feature_flags', $flags );

        wp_localize_script( 'cuft-feature-flags', 'cuftMigrationConfig', $flags );
    }
}