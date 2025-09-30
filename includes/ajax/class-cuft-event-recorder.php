<?php
/**
 * AJAX Event Recorder Handler
 *
 * Handles client-side event recording via AJAX endpoint.
 * Implements fire-and-forget pattern with silent failures.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Event_Recorder {

    /**
     * Valid event types whitelist
     */
    const VALID_EVENT_TYPES = array(
        'phone_click',
        'email_click',
        'form_submit',
        'generate_lead',
    );

    /**
     * Constructor - register AJAX hooks
     */
    public function __construct() {
        add_action( 'wp_ajax_cuft_record_event', array( $this, 'record_event' ) );
        add_action( 'wp_ajax_nopriv_cuft_record_event', array( $this, 'record_event' ) );
    }

    /**
     * Record event via AJAX
     *
     * Handles POST requests to record click tracking events.
     * Contract: /specs/migrations/click-tracking-events/contracts/ajax-endpoint.md
     *
     * @return void Sends JSON response and exits
     */
    public function record_event() {
        // UNCONDITIONAL DEBUG - Always log
        error_log( '=== CUFT Event Recorder: record_event() called ===' );
        error_log( 'CUFT: Received nonce: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'MISSING' ) );
        error_log( 'CUFT: Current user ID: ' . get_current_user_id() );

        try {
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'CUFT Event Recorder: Received nonce: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'MISSING' ) );
                error_log( 'CUFT Event Recorder: Current user ID: ' . get_current_user_id() );
            }

            // Verify nonce for security
            $nonce_check = check_ajax_referer( 'cuft-event-recorder', 'nonce', false );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'CUFT Event Recorder: Nonce check result: ' . var_export( $nonce_check, true ) );
            }

            if ( ! $nonce_check ) {
                wp_send_json_error( array(
                    'message' => 'Security check failed',
                    'debug' => array(
                        'user_id' => get_current_user_id(),
                        'nonce_received' => isset( $_POST['nonce'] ) ? 'yes' : 'no'
                    )
                ), 403 );
                return;
            }

            // Sanitize and validate inputs
            $click_id = isset( $_POST['click_id'] ) ? sanitize_text_field( $_POST['click_id'] ) : '';
            $event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( $_POST['event_type'] ) : '';

            // Validate click_id
            if ( empty( $click_id ) ) {
                wp_send_json_error( array(
                    'message' => 'Missing required parameter: click_id'
                ), 400 );
                return;
            }

            // Validate click_id format (alphanumeric + hyphens/underscores only)
            if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $click_id ) ) {
                wp_send_json_error( array(
                    'message' => 'Invalid click_id format'
                ), 400 );
                return;
            }

            // Validate event_type against whitelist
            if ( ! in_array( $event_type, self::VALID_EVENT_TYPES, true ) ) {
                wp_send_json_error( array(
                    'message' => 'Invalid event type',
                    'allowed_types' => self::VALID_EVENT_TYPES
                ), 400 );
                return;
            }

            // Record event using Click Tracker
            $result = CUFT_Click_Tracker::add_event( $click_id, $event_type );

            if ( $result ) {
                // Get updated event count
                $events = CUFT_Click_Tracker::get_events( $click_id );
                $event_count = is_array( $events ) ? count( $events ) : 0;

                wp_send_json_success( array(
                    'message' => 'Event recorded successfully',
                    'click_id' => $click_id,
                    'event_type' => $event_type,
                    'event_count' => $event_count
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => 'Failed to record event'
                ), 500 );
            }

        } catch ( Exception $e ) {
            // Log error but don't expose details to client
            if ( class_exists( 'CUFT_Logger' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                CUFT_Logger::log( 'error', 'Event recording exception: ' . $e->getMessage() );
            }

            wp_send_json_error( array(
                'message' => 'Internal error'
            ), 500 );
        }
    }

    /**
     * Get valid event types
     *
     * @return array List of valid event types
     */
    public static function get_valid_event_types() {
        return self::VALID_EVENT_TYPES;
    }
}