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
     * Event types this public endpoint accepts: only the ones a visitor's own
     * browser produces (cuft-links.js and cuft-dataLayer-utils.js). CRM
     * lifecycle statuses (disqualify_lead, working_lead, close_*_lead,
     * score_updated) reach the click table only through the webhook.
     */
    const VALID_EVENT_TYPES = array(
        'phone_click',
        'email_click',
        'form_submit',
        'generate_lead',
        'qualify_lead',
    );

    /**
     * Longest click ID accepted; matches the click_id column (varchar 255).
     */
    const MAX_CLICK_ID_LENGTH = 255;

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
            // Public by design: visitors' browsers report their own clicks and
            // form submissions here, and visitors are not logged in. What keeps
            // it safe is scope, not identity: the only write is one
            // de-duplicated event entry (or a new click row) for a click ID of
            // at most 255 safe characters, drawn from the five browser event
            // types above; nothing else is read or returned.
            //
            // The nonce is printed into cuftConfig for every visitor
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
            if ( strlen( $click_id ) > self::MAX_CLICK_ID_LENGTH || ! preg_match( '/^[a-zA-Z0-9_\-\.=+]+$/', $click_id ) ) {
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

            // generate_lead is recorded only while the "Generate Lead Events"
            // setting is on, matching the dataLayer push it mirrors.
            if ( 'generate_lead' === $event_type && ! get_option( 'cuft_generate_lead_enabled', false ) ) {
                wp_send_json_success( array(
                    'message' => 'generate_lead events are disabled',
                    'skipped' => true,
                ) );
                return;
            }

            /**
             * Filter whether an event reported by the browser is recorded on the click.
             *
             * Ignored phone numbers and links marked data-cuft-ignore are
             * dropped in the browser before this request is made; the request
             * carries no phone number, so the server cannot tell which link was
             * clicked. Use this filter for decisions the server can make, such
             * as dropping every email_click.
             *
             * @since 3.30.0
             *
             * @param bool   $record     Whether to record the event. Default true.
             * @param string $event_type Validated event type.
             * @param string $click_id   Validated click ID.
             */
            if ( ! apply_filters( 'cuft_record_event', true, $event_type, $click_id ) ) {
                wp_send_json_success( array(
                    'message' => 'Event not recorded',
                    'skipped' => true,
                ) );
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