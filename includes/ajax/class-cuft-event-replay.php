<?php
/**
 * AJAX handler for client-side event replay.
 *
 * Webhook-originated lifecycle events (qualify_lead, etc.) are stored with
 * source=webhook and replayed_at=null. On the next pageview the browser JS
 * calls this endpoint, receives the pending events, pushes them into the
 * dataLayer, and the server marks them as replayed so they fire only once.
 *
 * @package Choice_Universal_Form_Tracker
 * @since   3.22.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Event_Replay {

    /**
     * Register AJAX actions.
     */
    public function __construct() {
        add_action( 'wp_ajax_cuft_get_pending_events', array( $this, 'ajax_get_pending' ) );
        add_action( 'wp_ajax_nopriv_cuft_get_pending_events', array( $this, 'ajax_get_pending' ) );
    }

    /**
     * Return unreplayed webhook events for a given click_id.
     *
     * @param string $click_id Click identifier.
     * @return array Pending event objects.
     */
    public static function get_pending_events( string $click_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s",
            sanitize_text_field( $click_id )
        ) );

        if ( ! $row || empty( $row->events ) ) {
            return array();
        }

        $events = json_decode( $row->events, true );
        if ( ! is_array( $events ) ) {
            return array();
        }

        $pending = array();
        foreach ( $events as $event ) {
            if (
                ! empty( $event['source'] ) &&
                'webhook' === $event['source'] &&
                ( ! isset( $event['replayed_at'] ) || null === $event['replayed_at'] )
            ) {
                $pending[] = $event;
            }
        }

        return $pending;
    }

    /**
     * Mark all unreplayed webhook events as replayed for a given click_id.
     *
     * @param string $click_id Click identifier.
     */
    public static function mark_events_replayed( string $click_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s",
            sanitize_text_field( $click_id )
        ) );

        if ( ! $row || empty( $row->events ) ) {
            return;
        }

        $events  = json_decode( $row->events, true );
        $updated = false;

        foreach ( $events as &$event ) {
            if (
                ! empty( $event['source'] ) &&
                'webhook' === $event['source'] &&
                ( ! isset( $event['replayed_at'] ) || null === $event['replayed_at'] )
            ) {
                $event['replayed_at'] = current_time( 'mysql' );
                $updated              = true;
            }
        }
        unset( $event );

        if ( $updated ) {
            $wpdb->update(
                $table,
                array( 'events' => wp_json_encode( $events ) ),
                array( 'click_id' => sanitize_text_field( $click_id ) ),
                array( '%s' ),
                array( '%s' )
            );
        }
    }

    /**
     * Atomically fetch and mark pending webhook events for a given click_id.
     *
     * Uses a database transaction with SELECT ... FOR UPDATE to prevent race
     * conditions where concurrent requests could replay the same events twice.
     *
     * @param string $click_id Click identifier.
     * @return array Pending event objects (already marked as replayed in DB).
     */
    public static function get_and_mark_pending_events( string $click_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $wpdb->query( 'START TRANSACTION' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s FOR UPDATE",
            sanitize_text_field( $click_id )
        ) );

        if ( ! $row || empty( $row->events ) ) {
            $wpdb->query( 'COMMIT' );
            return array();
        }

        $events = json_decode( $row->events, true );
        if ( ! is_array( $events ) ) {
            $wpdb->query( 'COMMIT' );
            return array();
        }

        $pending = array();
        $updated = false;

        foreach ( $events as &$event ) {
            if ( ! empty( $event['source'] ) && 'webhook' === $event['source'] && ( ! isset( $event['replayed_at'] ) || null === $event['replayed_at'] ) ) {
                $pending[]             = $event;
                $event['replayed_at']  = current_time( 'mysql' );
                $updated               = true;
            }
        }
        unset( $event );

        if ( $updated ) {
            $wpdb->update(
                $table,
                array( 'events' => wp_json_encode( $events ) ),
                array( 'click_id' => sanitize_text_field( $click_id ) ),
                array( '%s' ),
                array( '%s' )
            );
        }

        $wpdb->query( 'COMMIT' );

        return $pending;
    }

    /**
     * AJAX handler: return pending events and mark them replayed atomically.
     */
    public function ajax_get_pending(): void {
        // Verify nonce.
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'cuft-event-recorder' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
            return;
        }

        $click_id = isset( $_GET['click_id'] ) ? sanitize_text_field( wp_unslash( $_GET['click_id'] ) ) : '';

        if ( empty( $click_id ) ) {
            wp_send_json_success( array( 'events' => array() ) );
            return;
        }

        // Atomically get and mark pending events (prevents race condition).
        $pending = self::get_and_mark_pending_events( $click_id );

        wp_send_json_success( array( 'events' => $pending ) );
    }
}
