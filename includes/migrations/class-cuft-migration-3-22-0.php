<?php
/**
 * Migration for v3.22.0
 *
 * Adds ga_client_id and replayed_at columns to the click tracking table.
 * - ga_client_id: Stores the GA4 client ID from the _ga cookie for Measurement Protocol replay.
 * - replayed_at: Marks when webhook-driven events have been replayed to the client-side dataLayer.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.22.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Migration_3_22_0 {

    const OPTION_KEY = 'cuft_migration_3_22_0_completed';

    /**
     * Execute schema migration (up)
     *
     * Adds ga_client_id column after ip_hash and replayed_at column after events.
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';

        // Check if migration already completed
        if ( get_option( self::OPTION_KEY ) ) {
            return true;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );

        if ( ! in_array( 'ga_client_id', $columns, true ) ) {
            $result = $wpdb->query( "ALTER TABLE $table ADD COLUMN ga_client_id varchar(255) DEFAULT NULL AFTER ip_hash" );

            if ( $result === false ) {
                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'error', 'Migration 3.22.0: Failed to add ga_client_id column: ' . $wpdb->last_error );
                }
                return false;
            }
        }

        if ( ! in_array( 'replayed_at', $columns, true ) ) {
            $result = $wpdb->query( "ALTER TABLE $table ADD COLUMN replayed_at datetime DEFAULT NULL AFTER events" );

            if ( $result === false ) {
                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'error', 'Migration 3.22.0: Failed to add replayed_at column: ' . $wpdb->last_error );
                }
                return false;
            }
        }

        update_option( self::OPTION_KEY, true );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Migration 3.22.0: Added ga_client_id and replayed_at columns' );
        }

        return true;
    }

    /**
     * Check if migration needs to run
     *
     * @return bool True if migration is needed
     */
    public static function needs_migration() {
        return ! get_option( self::OPTION_KEY, false );
    }
}
