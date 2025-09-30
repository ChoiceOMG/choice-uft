<?php
/**
 * Click Tracking Events Migration for v3.12.0
 *
 * Migrates click tracking from static column-based storage to event-based JSON array tracking.
 * Implements hybrid rollback strategy that preserves business-critical data.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Migration_3_12_0 {

    /**
     * Migration version identifier
     */
    const VERSION = '3.12.0';
    const MIGRATION_KEY = 'cuft_migration_3_12_0';

    /**
     * Execute schema migration (up)
     *
     * Adds events JSON column and idx_date_updated index.
     * Creates backup before making changes.
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Step 1: Create full backup for rollback
            if ( ! self::create_backup() ) {
                throw new Exception( 'Failed to create backup table' );
            }

            // Step 2: Check if events column already exists
            $column_exists = $wpdb->get_results( $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'events'
            ) );

            if ( empty( $column_exists ) ) {
                // Add events column as JSON type
                $result = $wpdb->query(
                    "ALTER TABLE {$table}
                     ADD COLUMN events JSON DEFAULT NULL AFTER utm_content"
                );

                if ( $result === false ) {
                    throw new Exception( 'Failed to add events column: ' . $wpdb->last_error );
                }

                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'info', 'Added events JSON column to ' . $table );
                }
            } else {
                // Column exists - check if it's correct type (JSON not LONGTEXT)
                $column_info = $wpdb->get_row( $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table} LIKE %s",
                    'events'
                ) );

                // If column is LONGTEXT, convert to JSON
                if ( $column_info && stripos( $column_info->Type, 'json' ) === false ) {
                    $wpdb->query(
                        "ALTER TABLE {$table}
                         MODIFY COLUMN events JSON DEFAULT NULL"
                    );

                    if ( class_exists( 'CUFT_Logger' ) ) {
                        CUFT_Logger::log( 'info', 'Converted events column from ' . $column_info->Type . ' to JSON' );
                    }
                }
            }

            // Step 3: Add date_updated index if it doesn't exist
            $index_exists = $wpdb->get_results(
                "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_date_updated'"
            );

            if ( empty( $index_exists ) ) {
                $result = $wpdb->query(
                    "ALTER TABLE {$table}
                     ADD INDEX idx_date_updated (date_updated)"
                );

                if ( $result === false ) {
                    throw new Exception( 'Failed to add date_updated index: ' . $wpdb->last_error );
                }

                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'info', 'Added idx_date_updated index to ' . $table );
                }
            }

            // Step 4: Update migration status
            update_option( self::MIGRATION_KEY . '_up_completed', true );
            update_option( self::MIGRATION_KEY . '_timestamp', current_time( 'mysql', true ) );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Migration 3.12.0 up() completed successfully' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Migration 3.12.0 up() failed: ' . $e->getMessage() );
            }

            // Attempt to restore from backup
            self::restore_from_backup();

            return false;
        }
    }

    /**
     * Rollback migration (down) - Hybrid strategy
     *
     * Restores original schema (removes events column and index).
     * PRESERVES qualified/score updates (business-critical).
     * DISCARDS all event data.
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Hybrid rollback strategy:
            // 1. Restore original schema (remove events column and indexes)
            // 2. Preserve qualified/score updates (do NOT restore from backup)
            // 3. Discard all event data

            // Remove events column
            $wpdb->query(
                "ALTER TABLE {$table}
                 DROP COLUMN IF EXISTS events"
            );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Dropped events column from ' . $table );
            }

            // Remove idx_date_updated index
            $wpdb->query(
                "ALTER TABLE {$table}
                 DROP INDEX IF EXISTS idx_date_updated"
            );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Dropped idx_date_updated index from ' . $table );
            }

            // Note: We do NOT restore qualified/score from backup
            // This preserves business-critical webhook updates made during migration period

            // Clean up migration status
            delete_option( self::MIGRATION_KEY . '_up_completed' );
            delete_option( self::MIGRATION_KEY . '_timestamp' );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Migration 3.12.0 down() completed successfully (hybrid rollback)' );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Migration 3.12.0 down() failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Create backup table before migration
     *
     * Creates a timestamped backup table with full copy of data.
     * Stores backup table name in wp_options for rollback reference.
     *
     * @return bool True on success, false on failure
     */
    public static function create_backup() {
        global $wpdb;

        $table = $wpdb->prefix . 'cuft_click_tracking';
        $backup_table = $table . '_backup_' . date( 'Ymd_His' );

        try {
            // Create backup table structure
            $result = $wpdb->query(
                "CREATE TABLE {$backup_table} LIKE {$table}"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to create backup table structure: ' . $wpdb->last_error );
            }

            // Copy all data to backup table
            $result = $wpdb->query(
                "INSERT INTO {$backup_table} SELECT * FROM {$table}"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to copy data to backup table: ' . $wpdb->last_error );
            }

            // Store backup table name in options
            update_option( self::MIGRATION_KEY . '_backup_table', $backup_table );
            update_option( self::MIGRATION_KEY . '_backup_timestamp', current_time( 'mysql', true ) );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Created backup table: ' . $backup_table );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Backup creation failed: ' . $e->getMessage() );
            }

            // Clean up partially created backup table
            $wpdb->query( "DROP TABLE IF EXISTS {$backup_table}" );

            return false;
        }
    }

    /**
     * Restore from backup table (full restore, not hybrid)
     *
     * Only used for catastrophic failures during migration.
     * For normal rollback, use down() method (hybrid strategy).
     *
     * @return bool True on success, false on failure
     */
    public static function restore_from_backup() {
        global $wpdb;

        $backup_table = get_option( self::MIGRATION_KEY . '_backup_table' );

        if ( empty( $backup_table ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'No backup table found for restoration' );
            }
            return false;
        }

        $table = $wpdb->prefix . 'cuft_click_tracking';

        try {
            // Drop current table
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );

            // Recreate from backup
            $result = $wpdb->query(
                "CREATE TABLE {$table} LIKE {$backup_table}"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to recreate table structure: ' . $wpdb->last_error );
            }

            // Copy data from backup
            $result = $wpdb->query(
                "INSERT INTO {$table} SELECT * FROM {$backup_table}"
            );

            if ( $result === false ) {
                throw new Exception( 'Failed to restore data from backup: ' . $wpdb->last_error );
            }

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Restored table from backup: ' . $backup_table );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Backup restoration failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Clean up backup table
     *
     * Removes backup table after successful migration validation period.
     *
     * @return bool True on success, false on failure
     */
    public static function cleanup_backup() {
        global $wpdb;

        $backup_table = get_option( self::MIGRATION_KEY . '_backup_table' );

        if ( empty( $backup_table ) ) {
            return true; // No backup to clean up
        }

        try {
            $wpdb->query( "DROP TABLE IF EXISTS {$backup_table}" );

            delete_option( self::MIGRATION_KEY . '_backup_table' );
            delete_option( self::MIGRATION_KEY . '_backup_timestamp' );

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', 'Cleaned up backup table: ' . $backup_table );
            }

            return true;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Backup cleanup failed: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Check if migration has been run
     *
     * @return bool True if migration completed, false otherwise
     */
    public static function is_migrated() {
        return (bool) get_option( self::MIGRATION_KEY . '_up_completed', false );
    }

    /**
     * Get migration status for admin display
     *
     * @return array Migration status information
     */
    public static function get_status() {
        $backup_table = get_option( self::MIGRATION_KEY . '_backup_table' );
        $backup_exists = false;

        if ( $backup_table ) {
            global $wpdb;
            $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $backup_table ) );
            $backup_exists = ! empty( $result );
        }

        return array(
            'migrated' => self::is_migrated(),
            'timestamp' => get_option( self::MIGRATION_KEY . '_timestamp' ),
            'backup_table' => $backup_table,
            'backup_exists' => $backup_exists,
            'backup_timestamp' => get_option( self::MIGRATION_KEY . '_backup_timestamp' ),
        );
    }
}