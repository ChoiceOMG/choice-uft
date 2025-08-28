<?php
/**
 * Form framework detection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Form_Detector {
    
    /**
     * Detected frameworks
     */
    private static $detected_frameworks = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'detect_frameworks' ) );
    }
    
    /**
     * Detect available form frameworks
     */
    public function detect_frameworks() {
        if ( null !== self::$detected_frameworks ) {
            return self::$detected_frameworks;
        }
        
        self::$detected_frameworks = array(
            'avada' => $this->detect_avada(),
            'elementor' => $this->detect_elementor(),
            'contact_form_7' => $this->detect_contact_form_7(),
            'ninja_forms' => $this->detect_ninja_forms(),
            'gravity_forms' => $this->detect_gravity_forms()
        );
        
        // Log detection results
        foreach ( self::$detected_frameworks as $framework => $detected ) {
            CUFT_Logger::log_framework_detection( $framework, $detected );
        }
        
        return self::$detected_frameworks;
    }
    
    /**
     * Get detected frameworks
     */
    public static function get_detected_frameworks() {
        if ( null === self::$detected_frameworks ) {
            $instance = new self();
            $instance->detect_frameworks();
        }
        return self::$detected_frameworks;
    }
    
    /**
     * Check if framework is detected
     */
    public static function is_framework_detected( $framework ) {
        $frameworks = self::get_detected_frameworks();
        return isset( $frameworks[ $framework ] ) && $frameworks[ $framework ];
    }
    
    /**
     * Detect Avada/Fusion forms
     */
    private function detect_avada() {
        return class_exists( 'FusionBuilder' ) || 
               function_exists( 'avada_render_form_element' ) ||
               wp_get_theme()->get( 'Name' ) === 'Avada';
    }
    
    /**
     * Detect Elementor Pro forms
     */
    private function detect_elementor() {
        return class_exists( 'ElementorPro\Plugin' ) && 
               class_exists( 'ElementorPro\Modules\Forms\Module' );
    }
    
    /**
     * Detect Contact Form 7
     */
    private function detect_contact_form_7() {
        return class_exists( 'WPCF7' ) || function_exists( 'wpcf7' );
    }
    
    /**
     * Detect Ninja Forms
     */
    private function detect_ninja_forms() {
        return class_exists( 'Ninja_Forms' ) || function_exists( 'ninja_forms_get_form_by_id' );
    }
    
    /**
     * Detect Gravity Forms
     */
    private function detect_gravity_forms() {
        return class_exists( 'GFForms' ) || class_exists( 'GFAPI' );
    }
    
    /**
     * Get framework display names
     */
    public static function get_framework_names() {
        return array(
            'avada' => 'Avada/Fusion Forms',
            'elementor' => 'Elementor Pro Forms',
            'contact_form_7' => 'Contact Form 7',
            'ninja_forms' => 'Ninja Forms',
            'gravity_forms' => 'Gravity Forms'
        );
    }
    
    /**
     * Get framework status for admin display
     */
    public static function get_framework_status() {
        $frameworks = self::get_detected_frameworks();
        $names = self::get_framework_names();
        $status = array();
        
        foreach ( $names as $key => $name ) {
            $status[] = array(
                'name' => $name,
                'detected' => isset( $frameworks[ $key ] ) && $frameworks[ $key ],
                'key' => $key
            );
        }
        
        return $status;
    }
}
