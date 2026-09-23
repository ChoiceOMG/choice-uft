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
        'qualify_lead',
        'disqualify_lead',
        'working_lead',
        'close_convert_lead',
        'close_unconvert_lead',
        'score_updated',
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
        try {
            // Verify nonce. The nonce is printed into cuftConfig for every visitor
            // (logged-out visitors share the user-0 nonce), so this check stops
            // cross-site request forgery for logged-in users and casual scripted
            // abuse; it is not authentication.
            $nonce_check = check_ajax_referer( 'cuft-event-recorder', 'nonce', false );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::debug_log( 'CUFT Event Recorder: nonce ' . ( isset( $_POST['nonce'] ) ? 'received' : 'MISSING' ) . ', user ' . get_current_user_id() . ', check result ' . wp_json_encode( $nonce_check ) );
            }

            if ( ! $nonce_check ) {
                wp_send_json_error( array(
                    'message' => 'Security check failed',
                ), 403 );
                return;
            }

            // Sanitize and validate inputs (nonce verified above).
            $click_id     = isset( $_POST['click_id'] ) ? sanitize_text_field( wp_unslash( $_POST['click_id'] ) ) : '';
            $event_type   = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
            $ga_client_id = isset( $_POST['ga_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ga_client_id'] ) ) : '';
            if ( ! empty( $ga_client_id ) && ! preg_match( '/^\d+\.\d+$/', $ga_client_id ) ) {
                $ga_client_id = ''; // Invalid format; discard
            }

            // Validate click_id
            if ( empty( $click_id ) ) {
                wp_send_json_error( array(
                    'message' => 'Missing required parameter: click_id'
                ), 400 );
                return;
            }

            // Validate click_id format (alphanumeric + common click ID characters)
            if ( ! preg_match( '/^[a-zA-Z0-9_\-\.=+]+$/', $click_id ) ) {
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
                // Store ga_client_id if provided (for Measurement Protocol)
                if ( ! empty( $ga_client_id ) ) {
                    global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and this is a per-request write.
                    $wpdb->update(
                        $wpdb->prefix . 'cuft_click_tracking',
                        array( 'ga_client_id' => $ga_client_id ),
                        array( 'click_id' => $click_id ),
                        array( '%s' ),
                        array( '%s' )
                    );
                }

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
                CUFT_Logger::log( 'Event recording exception: ' . $e->getMessage(), CUFT_Logger::ERROR );
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