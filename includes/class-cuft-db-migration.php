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
    const CURRENT_VERSION = '3.15.0';

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

        // Run 3.14.0 migration for indexes
        if ( version_compare( $current_version, '3.14.0', '<' ) ) {
            self::migrate_to_3_14_0();
        }

        // Run 3.15.0 migration for update log table
        if ( version_compare( $current_version, '3.15.0', '<' ) ) {
            self::migrate_to_3_15_0();
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
     * Migration to version 3.14.0
     * Adds performance indexes to click tracking table
     */
    private static function migrate_to_3_14_0() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        // Check if table exists
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
            error_log('CUFT Migration 3.14.0: Table does not exist, skipping index creation');
            return;
        }

        // Array of indexes to add (using actual column names)
        $indexes = array(
            'idx_date_created' => 'date_created',
            'idx_click_id' => 'click_id(50)',
            'idx_qualified' => 'qualified',
            'idx_composite_date_qualified' => 'date_created, qualified',
            'idx_date_updated' => 'date_updated', // Ensure it exists (from 3.12.0)
            'idx_utm_source' => 'utm_source(50)',
            'idx_utm_campaign' => 'utm_campaign(50)'
        );

        $success = true;
        foreach ($indexes as $index_name => $columns) {
            if (!self::index_exists($table_name, $index_name)) {
                $sql = "ALTER TABLE $table_name ADD INDEX $index_name ($columns)";
                $result = $wpdb->query($sql);

                if ($wpdb->last_error) {
                    error_log("CUFT Migration 3.14.0: Failed to add index $index_name: " . $wpdb->last_error);
                    $success = false;
                } else {
                    error_log("CUFT Migration 3.14.0: Successfully added index $index_name");
                }
            }
        }

        // Log migration status
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                $success ? 'info' : 'warning',
                'Migration 3.14.0 completed',
                array(
                    'success' => $success,
                    'indexes' => $indexes
                )
            );
        }

        return $success;
    }

    /**
     * Check if an index exists on a table
     *
     * @param string $table_name Table name
     * @param string $index_name Index name
     * @return bool
     */
    private static function index_exists($table_name, $index_name) {
        global $wpdb;
        $index = $wpdb->get_row(
            $wpdb->prepare(
                "SHOW INDEX FROM $table_name WHERE Key_name = %s",
                $index_name
            )
        );
        return !is_null($index);
    }

    /**
     * Verify all indexes are present
     *
     * @return array Array of index status
     */
    public static function verify_indexes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        $expected_indexes = array(
            'PRIMARY',
            'idx_date_created',
            'idx_click_id',
            'idx_qualified',
            'idx_composite_date_qualified',
            'idx_date_updated',
            'idx_utm_source',
            'idx_utm_campaign'
        );

        $results = array();
        foreach ($expected_indexes as $index_name) {
            $results[$index_name] = self::index_exists($table_name, $index_name);
        }

        return $results;
    }

    /**
     * Get query performance stats
     *
     * @param string $query_type Type of query to analyze
     * @return array Query execution plan
     */
    public static function analyze_query_performance($query_type = 'recent') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_click_tracking';

        $queries = array(
            'recent' => "SELECT * FROM $table_name WHERE date_created > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'qualified' => "SELECT * FROM $table_name WHERE qualified = 1",
            'composite' => "SELECT * FROM $table_name WHERE date_created > DATE_SUB(NOW(), INTERVAL 30 DAY) AND qualified = 1",
            'click_id' => "SELECT * FROM $table_name WHERE click_id = 'test_id'",
            'utm_source' => "SELECT * FROM $table_name WHERE utm_source = 'google'",
            'utm_campaign' => "SELECT * FROM $table_name WHERE utm_campaign = 'summer_sale'"
        );

        if (!isset($queries[$query_type])) {
            return array('error' => 'Invalid query type');
        }

        $explain = $wpdb->get_results("EXPLAIN " . $queries[$query_type], ARRAY_A);
        return $explain;
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

    /**
     * Migration to version 3.15.0
     * Creates update log table
     */
    private static function migrate_to_3_15_0() {
        // Create update log table
        if ( class_exists( 'CUFT_Migration_Create_Update_Log_Table' ) ) {
            CUFT_Migration_Create_Update_Log_Table::up();

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log(
                    'info',
                    'Created update log table',
                    array( 'version' => '3.15.0' )
                );
            }
        }
    }
}