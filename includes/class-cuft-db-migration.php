<?php
/**
 * Database Migration Handler
 * Manages database schema updates and migrations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_DB_Migration {

    /**
     * Current database schema version
     */
    const CURRENT_VERSION = '1.0.0';

    /**
     * Option name for storing database version
     */
    const VERSION_OPTION = 'cuft_db_version';

    /**
     * Run all pending migrations
     */
    public static function run_migrations() {
        $current_version = get_option( self::VERSION_OPTION, '0.0.0' );

        // If we're already up to date, skip migrations
        if ( version_compare( $current_version, self::CURRENT_VERSION, '>=' ) ) {
            return;
        }

        // Run migrations in order
        if ( version_compare( $current_version, '1.0.0', '<' ) ) {
            self::migrate_to_1_0_0();
        }

        // Update version
        update_option( self::VERSION_OPTION, self::CURRENT_VERSION );

        // Log successful migration
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                'info',
                'Database migrated successfully',
                array(
                    'from_version' => $current_version,
                    'to_version' => self::CURRENT_VERSION
                )
            );
        }
    }

    /**
     * Migration to version 1.0.0
     * Adds events column to click tracking table
     */
    private static function migrate_to_1_0_0() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

        if ( ! $table_exists ) {
            // Table doesn't exist yet, create it with events column
            CUFT_Click_Tracker::create_table();
            self::add_events_column( $table_name );
            return;
        }

        // Check if events column already exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
                'events'
            )
        );

        if ( empty( $column_exists ) ) {
            // Add events column
            self::add_events_column( $table_name );
        }
    }

    /**
     * Add events column to click tracking table
     */
    private static function add_events_column( $table_name ) {
        global $wpdb;

        // Add events column after utm_content
        $sql = "ALTER TABLE `{$table_name}`
                ADD COLUMN `events` LONGTEXT DEFAULT NULL
                AFTER `utm_content`";

        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            // Log error
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log(
                    'error',
                    'Failed to add events column',
                    array(
                        'table' => $table_name,
                        'error' => $wpdb->last_error
                    )
                );
            }
            return false;
        }

        // Log success
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                'info',
                'Events column added successfully',
                array( 'table' => $table_name )
            );
        }

        return true;
    }

    /**
     * Get current database version
     */
    public static function get_current_version() {
        return get_option( self::VERSION_OPTION, '0.0.0' );
    }

    /**
     * Get target database version
     */
    public static function get_target_version() {
        return self::CURRENT_VERSION;
    }

    /**
     * Check if migration is needed
     */
    public static function needs_migration() {
        $current_version = self::get_current_version();
        return version_compare( $current_version, self::CURRENT_VERSION, '<' );
    }

    /**
     * Force run all migrations (for debugging)
     */
    public static function force_migrate() {
        // Reset version to force migration
        update_option( self::VERSION_OPTION, '0.0.0' );

        // Run migrations
        self::run_migrations();
    }

    /**
     * Rollback migrations (for development/testing)
     */
    public static function rollback() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        // Check if events column exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
                'events'
            )
        );

        if ( ! empty( $column_exists ) ) {
            // Remove events column
            $sql = "ALTER TABLE `{$table_name}` DROP COLUMN `events`";
            $wpdb->query( $sql );

            // Log rollback
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log(
                    'info',
                    'Events column removed (rollback)',
                    array( 'table' => $table_name )
                );
            }
        }

        // Reset version
        update_option( self::VERSION_OPTION, '0.0.0' );
    }
}