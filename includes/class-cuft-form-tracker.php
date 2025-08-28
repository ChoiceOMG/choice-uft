<?php
/**
 * Form tracking coordinator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Form_Tracker {
    
    /**
     * Form handlers
     */
    private $handlers = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'initialize_handlers' ) );
    }
    
    /**
     * Initialize form handlers based on detected frameworks
     */
    public function initialize_handlers() {
        $frameworks = CUFT_Form_Detector::get_detected_frameworks();
        
        // Initialize handlers for detected frameworks
        if ( $frameworks['avada'] ) {
            $this->handlers['avada'] = new CUFT_Avada_Forms();
        }
        
        if ( $frameworks['elementor'] ) {
            $this->handlers['elementor'] = new CUFT_Elementor_Forms();
        }
        
        if ( $frameworks['contact_form_7'] ) {
            $this->handlers['contact_form_7'] = new CUFT_CF7_Forms();
        }
        
        if ( $frameworks['ninja_forms'] ) {
            $this->handlers['ninja_forms'] = new CUFT_Ninja_Forms();
        }
        
        if ( $frameworks['gravity_forms'] ) {
            $this->handlers['gravity_forms'] = new CUFT_Gravity_Forms();
        }
        
        CUFT_Logger::log( 'Form tracking initialized for: ' . implode( ', ', array_keys( $this->handlers ) ) );
    }
    
    /**
     * Get active handlers
     */
    public function get_active_handlers() {
        return $this->handlers;
    }
}
