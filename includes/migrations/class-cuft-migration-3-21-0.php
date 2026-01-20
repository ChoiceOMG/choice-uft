<?php
/**
 * IP Hash Migration for v3.21.0
 *
 * Migrates click tracking from storing raw IP addresses to SHA256 hashes.
 * This improves privacy while still allowing correlation of events by IP.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Migration_3_21_0 {

    /**
     * Migration version identifier
     */
    const VERSION = '3.21.0';
    const MIGRATION_KEY = 'cuft_migration_3_21_0';

    /**
     * Execute schema migration (up)
     *
     * Renames ip_address column to ip_hash and converts existing IPs to hashes.
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';

        // Check if migration already completed
        if ( get_option( self::MIGRATION_KEY . '_completed' ) ) {
            return true;
        }

        try {
            // Step 1: Check current column state
            $ip_address_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$table} LIKE 'ip_address'"
            );

            $ip_hash_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$table} LIKE 'ip_hash'"
            );

            // If ip_hash already exists, migration is complete
            if ( ! empty( $ip_hash_exists ) ) {
                update_option( self::MIGRATION_KEY . '_completed', true );
                return true;
            }

            // If ip_address doesn't exist either, something is wrong
            if ( empty( $ip_address_exists ) ) {
                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'error', 'Migration 3.21.0: Neither ip_address nor ip_hash column exists' );
                }
                return false;
            }

            // Step 2: Hash existing IP addresses before renaming column
            // Get all records with non-empty IP addresses
            $records = $wpdb->get_results(
                "SELECT id, ip_address FROM {$table} WHERE ip_address IS NOT NULL AND ip_address != '' AND ip_address != '0'"
            );

            if ( ! empty( $records ) ) {
                foreach ( $records as $record ) {
                    $hashed_ip = hash( 'sha256', $record->ip_address );
                    $wpdb->update(
                        $table,
                        array( 'ip_address' => $hashed_ip ),
                        array( 'id' => $record->id ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }

                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'info', 'Migration 3.21.0: Hashed ' . count( $records ) . ' IP addresses' );
                }
            }

            // Step 3: Rename column and change type
            $result = $wpdb->query(
                "ALTER TABLE {$table} CHANGE COLUMN ip_address ip_hash VARCHAR(64) DEFAULT NULL"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to rename ip_address to ip_hash: ' . $wpdb->last_error );
            }

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Migration 3.21.0: Renamed ip_address to ip_hash' );
            }

            // Mark migration as complete
            update_option( self::MIGRATION_KEY . '_completed', true );

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Migration 3.21.0 failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Rollback migration (down)
     *
     * Renames ip_hash column back to ip_address.
     * Note: Original IP addresses cannot be recovered from hashes.
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Check if ip_hash column exists
            $ip_hash_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$table} LIKE 'ip_hash'"
            );

            if ( empty( $ip_hash_exists ) ) {
                // Column doesn't exist, nothing to rollback
                return true;
            }

            // Rename back to ip_address (data will remain as hashes)
            $result = $wpdb->query(
                "ALTER TABLE {$table} CHANGE COLUMN ip_hash ip_address VARCHAR(45) DEFAULT NULL"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to rename ip_hash to ip_address: ' . $wpdb->last_error );
            }

            // Remove migration flag
            delete_option( self::MIGRATION_KEY . '_completed' );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Migration 3.21.0 rolled back: Renamed ip_hash to ip_address' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Migration 3.21.0 rollback failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Check if migration needs to run
     *
     * @return bool True if migration is needed
     */
    public static function needs_migration() {
        global $wpdb;

        // Already completed
        if ( get_option( self::MIGRATION_KEY . '_completed' ) ) {
            return false;
        }

        $table = $wpdb->prefix . 'cuft_click_tracking';

        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table )
        );

        if ( ! $table_exists ) {
            return false;
        }

        // Check if ip_address column exists (needs migration)
        $ip_address_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table} LIKE 'ip_address'"
        );

        return ! empty( $ip_address_exists );
    }
}
