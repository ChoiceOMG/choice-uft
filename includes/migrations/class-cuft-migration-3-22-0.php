<?php
/**
 * Migration for v3.22.0
 *
 * Adds ga_client_id column to the click tracking table.
 * - ga_client_id: Stores the GA4 client ID from the _ga cookie for Measurement Protocol replay.
 *
 * Replay tracking (replayed_at) is handled entirely within the JSON events blob,
 * so no separate DB column is needed.
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
     * Adds ga_client_id column after ip_hash.
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; schema introspection during migration, not cacheable.
        $columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );

        if ( ! in_array( 'ga_client_id', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin's own table cuft_click_tracking; schema migration for the plugin's own table.
            $result = $wpdb->query( $wpdb->prepare(
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration of the plugin's own table; runs only on activation or upgrade.
                'ALTER TABLE %i ADD COLUMN ga_client_id varchar(255) DEFAULT NULL AFTER ip_hash',
                $table
            ) );

            if ( $result === false ) {
                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'error', 'Migration 3.22.0: Failed to add ga_client_id column: ' . $wpdb->last_error );
                }
                return false;
            }
        }

        update_option( self::OPTION_KEY, true );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Migration 3.22.0: Added ga_client_id column' );
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
