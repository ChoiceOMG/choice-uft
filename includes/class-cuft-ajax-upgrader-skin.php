<?php
/**
 * AJAX Upgrader Skin for CUFT plugin updates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom upgrader skin for AJAX plugin updates
 * This class extends WP_Upgrader_Skin to provide a silent upgrade process
 * suitable for AJAX requests
 */
if ( class_exists( 'WP_Upgrader_Skin' ) && ! class_exists( 'CUFT_Ajax_Upgrader_Skin' ) ) {
    class CUFT_Ajax_Upgrader_Skin extends WP_Upgrader_Skin {

        /**
         * Stores all feedback messages during the upgrade process
         * @var array
         */
        public $messages = array();

        /**
         * Handle feedback messages from the upgrader
         *
         * @param string $string The feedback message
         * @param mixed ...$args Additional arguments for sprintf
         */
        public function feedback( $string, ...$args ) {
            if ( ! empty( $args ) ) {
                $string = vsprintf( $string, $args );
            }
            $this->messages[] = $string;
        }

        /**
         * Silent header - no output for AJAX requests
         */
        public function header() {}

        /**
         * Silent footer - no output for AJAX requests
         */
        public function footer() {}

        /**
         * Handle error messages
         *
         * @param mixed $errors Error message or WP_Error object
         */
        public function error( $errors ) {
            if ( is_string( $errors ) ) {
                $this->messages[] = "Error: " . $errors;
            } elseif ( is_wp_error( $errors ) ) {
                $this->messages[] = "Error: " . $errors->get_error_message();
            }
        }
    }
}