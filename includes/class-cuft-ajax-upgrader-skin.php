<?php
/**
 * AJAX Upgrader Skin for plugin updates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom upgrader skin for capturing output during AJAX updates
 */
class CUFT_Ajax_Upgrader_Skin extends WP_Upgrader_Skin {

    /**
     * Messages collected during upgrade
     */
    public $messages = array();

    /**
     * Collect feedback messages
     */
    public function feedback( $string, ...$args ) {
        if ( ! empty( $args ) ) {
            $string = vsprintf( $string, $args );
        }
        $this->messages[] = $string;
    }

    /**
     * Empty header (no output needed for AJAX)
     */
    public function header() {}

    /**
     * Empty footer (no output needed for AJAX)
     */
    public function footer() {}

    /**
     * Collect error messages
     */
    public function error( $errors ) {
        if ( is_string( $errors ) ) {
            $this->messages[] = 'Error: ' . $errors;
        } elseif ( is_wp_error( $errors ) ) {
            $this->messages[] = 'Error: ' . $errors->get_error_message();
        }
    }
}