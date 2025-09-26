<?php
/**
 * Click Tracking Events Migration Utility
 *
 * Handles progressive migration from current table structure to event-based tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Migration_Events {

    /**
     * Migration version identifier
     */
    const MIGRATION_VERSION = '3.10.0';
    const MIGRATION_KEY = 'cuft_click_events_migration';

    /**
     * Batch size for processing records
     */
    const BATCH_SIZE = 1000;

    /**
     * Check if migration is needed
     */
    public static function needs_migration() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        // Check if events column exists
        $column_exists = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'events'
        ) );

        if ( empty( $column_exists ) ) {
            return true; // Need to add events column
        }

        // Check migration status
        $migration_status = get_option( self::MIGRATION_KEY, array() );
        return empty( $migration_status['completed'] );
    }

    /**
     * Get migration status
     */
    public static function get_migration_status() {
        return get_option( self::MIGRATION_KEY, array(
            'started' => false,
            'completed' => false,
            'progress' => 0,
            'total_records' => 0,
            'processed_records' => 0,
            'batch_number' => 0,
            'errors' => array(),
            'start_time' => null,
            'completion_time' => null
        ) );
    }

    /**
     * Update migration status
     */
    private static function update_migration_status( $updates ) {
        $status = self::get_migration_status();
        $status = array_merge( $status, $updates );
        update_option( self::MIGRATION_KEY, $status );

        // Log status update
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Migration status updated', $updates );
        }

        return $status;
    }

    /**
     * Execute database schema migration
     */
    public static function migrate_schema() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Add events column if it doesn't exist
            $column_exists = $wpdb->get_results( $wpdb->prepare(
                "SHOW COLUMNS FROM {$table_name} LIKE %s",
                'events'
            ) );

            if ( empty( $column_exists ) ) {
                $result = $wpdb->query(
                    "ALTER TABLE {$table_name}
                     ADD COLUMN events JSON DEFAULT NULL AFTER utm_content"
                );

                if ( $result === false ) {
                    throw new Exception( 'Failed to add events column: ' . $wpdb->last_error );
                }
            }

            // Add date_updated index if it doesn't exist
            $index_exists = $wpdb->get_results(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = 'date_updated'"
            );

            if ( empty( $index_exists ) ) {
                $result = $wpdb->query(
                    "ALTER TABLE {$table_name}
                     ADD INDEX idx_date_updated (date_updated)"
                );

                if ( $result === false ) {
                    throw new Exception( 'Failed to add date_updated index: ' . $wpdb->last_error );
                }
            }

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Schema migration completed successfully' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Schema migration failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Start data migration process
     */
    public static function start_migration() {
        global $wpdb;

        // Ensure schema is migrated first
        if ( ! self::migrate_schema() ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        // Count total records to migrate
        $total_records = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE events IS NULL"
        );

        // Initialize migration status
        self::update_migration_status( array(
            'started' => true,
            'completed' => false,
            'progress' => 0,
            'total_records' => (int) $total_records,
            'processed_records' => 0,
            'batch_number' => 0,
            'errors' => array(),
            'start_time' => current_time( 'mysql', true )
        ) );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', "Migration started for {$total_records} records" );
        }

        return true;
    }

    /**
     * Process next batch of records
     */
    public static function process_batch() {
        global $wpdb;

        $status = self::get_migration_status();

        if ( ! $status['started'] || $status['completed'] ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'cuft_click_tracking';
        $batch_number = $status['batch_number'] + 1;
        $offset = ( $batch_number - 1 ) * self::BATCH_SIZE;

        // Get batch of records without events
        $records = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, click_id, utm_source, platform, qualified, date_created, date_updated
             FROM {$table_name}
             WHERE events IS NULL
             ORDER BY id ASC
             LIMIT %d OFFSET %d",
            self::BATCH_SIZE,
            $offset
        ) );

        if ( empty( $records ) ) {
            // Migration complete
            self::complete_migration();
            return true;
        }

        $processed_count = 0;
        $errors = $status['errors'];

        foreach ( $records as $record ) {
            try {
                $events = self::reconstruct_events( $record );

                // Update record with reconstructed events
                $result = $wpdb->update(
                    $table_name,
                    array( 'events' => json_encode( $events ) ),
                    array( 'id' => $record->id ),
                    array( '%s' ),
                    array( '%d' )
                );

                if ( $result === false ) {
                    throw new Exception( "Failed to update record {$record->id}: " . $wpdb->last_error );
                }

                $processed_count++;

            } catch ( Exception $e ) {
                $errors[] = array(
                    'record_id' => $record->id,
                    'click_id' => $record->click_id,
                    'error' => $e->getMessage(),
                    'timestamp' => current_time( 'mysql', true )
                );

                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'error', "Migration error for record {$record->id}: " . $e->getMessage() );
                }
            }
        }

        // Update progress
        $total_processed = $status['processed_records'] + $processed_count;
        $progress = $status['total_records'] > 0
            ? round( ( $total_processed / $status['total_records'] ) * 100, 2 )
            : 100;

        self::update_migration_status( array(
            'processed_records' => $total_processed,
            'batch_number' => $batch_number,
            'progress' => $progress,
            'errors' => $errors
        ) );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', "Processed batch {$batch_number}: {$processed_count} records, {$progress}% complete" );
        }

        return count( $records ) === self::BATCH_SIZE; // Return true if more batches remain
    }

    /**
     * Reconstruct events array from existing data
     */
    private static function reconstruct_events( $record ) {
        $events = array();

        // If we have utm_source, this was likely a form submission
        if ( ! empty( $record->utm_source ) ) {
            $events[] = array(
                'event' => 'form_submit',
                'timestamp' => self::format_timestamp( $record->date_created )
            );
        }

        // If we have platform data, this might indicate initial click activity
        if ( ! empty( $record->platform ) && empty( $record->utm_source ) ) {
            $events[] = array(
                'event' => 'phone_click', // Most common for platform tracking
                'timestamp' => self::format_timestamp( $record->date_created )
            );
        }

        // If record is qualified, add generate_lead event
        if ( $record->qualified ) {
            $events[] = array(
                'event' => 'generate_lead',
                'timestamp' => self::format_timestamp( $record->date_updated )
            );
        }

        // Sort events chronologically
        usort( $events, function( $a, $b ) {
            return strcmp( $a['timestamp'], $b['timestamp'] );
        } );

        return $events;
    }

    /**
     * Format timestamp to ISO 8601 UTC
     */
    private static function format_timestamp( $mysql_timestamp ) {
        if ( empty( $mysql_timestamp ) ) {
            return gmdate( 'c' ); // Current time as fallback
        }

        try {
            $dt = new DateTime( $mysql_timestamp, new DateTimeZone( 'UTC' ) );
            return $dt->format( 'c' ); // ISO 8601 format
        } catch ( Exception $e ) {
            return gmdate( 'c' ); // Current time as fallback
        }
    }

    /**
     * Complete migration process
     */
    private static function complete_migration() {
        self::update_migration_status( array(
            'completed' => true,
            'progress' => 100,
            'completion_time' => current_time( 'mysql', true )
        ) );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Migration completed successfully' );
        }
    }

    /**
     * Reset migration status (for re-running)
     */
    public static function reset_migration() {
        delete_option( self::MIGRATION_KEY );

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Migration status reset' );
        }
    }

    /**
     * Rollback migration (remove events column)
     */
    public static function rollback_migration() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Remove events column
            $result = $wpdb->query(
                "ALTER TABLE {$table_name} DROP COLUMN events"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to drop events column: ' . $wpdb->last_error );
            }

            // Remove date_updated index if it was added by migration
            $wpdb->query(
                "ALTER TABLE {$table_name} DROP INDEX IF EXISTS idx_date_updated"
            );

            // Reset migration status
            self::reset_migration();

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Migration rolled back successfully' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Migration rollback failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Clean up deprecated columns (utm_source, platform)
     * This should only be run after successful migration and validation period
     */
    public static function cleanup_deprecated_columns() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';
        $status = self::get_migration_status();

        if ( ! $status['completed'] ) {
            return false; // Migration must be completed first
        }

        try {
            // Drop utm_source column
            $wpdb->query(
                "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS utm_source"
            );

            // Drop platform column and its index
            $wpdb->query(
                "ALTER TABLE {$table_name} DROP INDEX IF EXISTS platform"
            );
            $wpdb->query(
                "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS platform"
            );

            // Update migration status
            self::update_migration_status( array(
                'columns_cleaned' => true,
                'cleanup_time' => current_time( 'mysql', true )
            ) );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Deprecated columns cleaned up successfully' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Column cleanup failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Validate migration data integrity
     */
    public static function validate_migration() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';
        $errors = array();

        // Check for records with NULL events that should have data
        $null_events_with_data = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE events IS NULL AND (utm_source IS NOT NULL OR platform IS NOT NULL OR qualified = 1)"
        );

        if ( $null_events_with_data > 0 ) {
            $errors[] = "{$null_events_with_data} records have NULL events but contain migratable data";
        }

        // Check for invalid JSON in events column
        $invalid_json = $wpdb->get_results(
            "SELECT id, click_id, events FROM {$table_name}
             WHERE events IS NOT NULL AND NOT JSON_VALID(events)"
        );

        if ( ! empty( $invalid_json ) ) {
            $errors[] = count( $invalid_json ) . " records have invalid JSON in events column";
        }

        // Check for events with invalid structure
        $invalid_events = $wpdb->get_results(
            "SELECT id, click_id FROM {$table_name}
             WHERE events IS NOT NULL
             AND NOT JSON_VALID(events)
             OR JSON_TYPE(events) != 'ARRAY'"
        );

        if ( ! empty( $invalid_events ) ) {
            $errors[] = count( $invalid_events ) . " records have events that are not valid arrays";
        }

        $validation_result = array(
            'valid' => empty( $errors ),
            'errors' => $errors,
            'timestamp' => current_time( 'mysql', true )
        );

        if ( class_exists( 'CUFT_Logger' ) ) {
            if ( $validation_result['valid'] ) {
                CUFT_Logger::log( 'info', 'Migration validation passed' );
            } else {
                CUFT_Logger::log( 'error', 'Migration validation failed', $validation_result );
            }
        }

        return $validation_result;
    }

    /**
     * Get migration progress for admin display
     */
    public static function get_migration_progress() {
        $status = self::get_migration_status();

        $progress = array(
            'needs_migration' => self::needs_migration(),
            'in_progress' => $status['started'] && ! $status['completed'],
            'completed' => $status['completed'],
            'progress_percentage' => $status['progress'],
            'processed_records' => $status['processed_records'],
            'total_records' => $status['total_records'],
            'batch_number' => $status['batch_number'],
            'errors_count' => count( $status['errors'] ),
            'start_time' => $status['start_time'],
            'completion_time' => $status['completion_time']
        );

        return $progress;
    }
}