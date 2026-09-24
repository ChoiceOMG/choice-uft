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
    const CURRENT_VERSION = '3.28.0';

    /**
     * Option name for storing database version
     */
    const VERSION_OPTION = 'cuft_db_version';

    /**
     * Whether the site already carried plugin data when the activation hook
     * ran. Null outside an activation request. Set by
     * CUFT_Plugin::activate() before it writes default options, because
     * those defaults would otherwise make a brand-new install look old.
     *
     * @var bool|null
     */
    private static $activation_found_existing = null;

    /**
     * Record, at the start of activation, whether plugin data already existed.
     *
     * @param bool $existing True when cuft_db_version or cuft_gtm_id was present.
     */
    public static function note_activation( $existing ) {
        self::$activation_found_existing = (bool) $existing;
    }

    /**
     * Whether this site ran the plugin before the current migration pass.
     *
     * 1. A stored cuft_db_version means an earlier version ran its
     *    migrations: every release since the migration runner was added
     *    writes it on activation and on the first request after an upgrade.
     * 2. Inside an activation request, use what activate() saw before writing
     *    its defaults (covers a deactivate/reactivate of a pre-migration
     *    version).
     * 3. Otherwise (an upgrade from a version older than the runner, with no
     *    activation), cuft_gtm_id, which activation has written since 3.0,
     *    marks an existing install.
     *
     * @param string|false $stored_version Raw cuft_db_version option value.
     * @return bool
     */
    private static function is_existing_install( $stored_version ) {
        if ( false !== $stored_version && '' !== $stored_version ) {
            return true;
        }
        if ( null !== self::$activation_found_existing ) {
            return self::$activation_found_existing;
        }
        return false !== get_option( 'cuft_gtm_id' );
    }

    /**
     * Run all pending migrations
     */
    public static function run_migrations() {
        $stored_version  = get_option( self::VERSION_OPTION, false );
        $current_version = ( false === $stored_version || '' === $stored_version ) ? '0.0.0' : $stored_version;

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

        // Run 3.21.0 migration for IP hash
        if ( version_compare( $current_version, '3.21.0', '<' ) ) {
            self::migrate_to_3_21_0();
        }

        // Run 3.22.0 migration for ga_client_id and replayed_at columns
        if ( version_compare( $current_version, '3.22.0', '<' ) ) {
            if ( class_exists( 'CUFT_Migration_3_22_0' ) ) {
                if ( CUFT_Migration_3_22_0::needs_migration() ) {
                    CUFT_Migration_3_22_0::up();
                }
            }
        }

        // 3.28.0: settings defaults that differ between new and existing installs,
        // and removal of the retired Test Form Builder's pages and rewrite rule.
        if ( version_compare( $current_version, '3.28.0', '<' ) ) {
            self::migrate_to_3_28_0( self::is_existing_install( $stored_version ) );
        }

        // Verify the click tracking table itself (not just the migration
        // bookkeeping above) actually carries every column the code
        // depends on. A version bump is exactly the moment a previous
        // dbDelta()/ALTER TABLE could have silently failed on a host that
        // restricts DDL, so force a real check here rather than trusting
        // the cached self-heal result from before the bump.
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::self_heal_schema( true );
        }

        // Update version
        update_option( self::VERSION_OPTION, self::CURRENT_VERSION );

        // Log successful migration
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                'Database migrated successfully',
                CUFT_Logger::INFO,
                array(
                    'from_version' => $current_version,
                    'to_version' => self::CURRENT_VERSION
                )
            );
        }
    }

    /**
     * Migration to version 3.28.0
     *
     * New installs get the stricter defaults: the webhook requires its key and
     * generate_lead stays off until an administrator turns it on. Existing
     * installs keep behaving exactly as before the upgrade: the webhook key
     * stays optional, and generate_lead is switched on, because before 3.28.0
     * the client script pushed generate_lead whatever the setting said.
     *
     * @param bool $existing_install Whether the site ran an earlier version.
     */
    private static function migrate_to_3_28_0( $existing_install ) {
        if ( $existing_install ) {
            if ( false === get_option( 'cuft_webhook_require_key' ) ) {
                add_option( 'cuft_webhook_require_key', 0 );
            }
            if ( ! get_option( 'cuft_generate_lead_enabled', false ) ) {
                update_option( 'cuft_generate_lead_enabled', 1 );
            }
        } else {
            if ( false === get_option( 'cuft_webhook_require_key' ) ) {
                add_option( 'cuft_webhook_require_key', 1 );
            }
            if ( false === get_option( 'cuft_generate_lead_enabled' ) ) {
                add_option( 'cuft_generate_lead_enabled', 0 );
            }
        }

        if ( get_option( 'cuft_webhook_require_key', false ) && class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::get_webhook_key();
        }

        if ( $existing_install ) {
            if ( class_exists( 'CUFT_Legacy_Test_Forms' ) ) {
                CUFT_Legacy_Test_Forms::remove_all();
            }
            // The Test Form Builder registered a /cuft-test-form/ rewrite rule.
            // Deleting the stored rules makes WordPress rebuild them on the next
            // request, without it.
            delete_option( 'rewrite_rules' );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; existence check during migration, not cacheable.
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;

        if ( ! $table_exists ) {
            // Table doesn't exist yet, create it with events column
            CUFT_Click_Tracker::create_table();
            self::add_events_column( $table_name );
            return;
        }

        // Check if events column already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; schema introspection during migration, not cacheable.
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                'SHOW COLUMNS FROM %i LIKE %s',
                $table_name,
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin's own table cuft_click_tracking; schema migration for the plugin's own table.
        $result = $wpdb->query( $wpdb->prepare(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration of the plugin's own table; runs only on activation or upgrade.
            'ALTER TABLE %i ADD COLUMN events LONGTEXT DEFAULT NULL AFTER utm_content',
            $table_name
        ) );

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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; existence check during migration, not cacheable.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) != $table_name ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::debug_log( 'CUFT Migration 3.14.0: Table does not exist, skipping index creation' );
            }
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
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin's own table cuft_click_tracking, schema migration; $index_name/$columns come from the hardcoded $indexes map above, not user input, and %i cannot express a column-length index definition like "click_id(50)".
                $result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX ' . $index_name . ' (' . $columns . ')', $table_name ) );

                if ($wpdb->last_error) {
                    if ( class_exists( 'CUFT_Logger' ) ) {
                        CUFT_Logger::debug_log( "CUFT Migration 3.14.0: Failed to add index $index_name: " . $wpdb->last_error );
                    }
                    $success = false;
                } else {
                    if ( class_exists( 'CUFT_Logger' ) ) {
                        CUFT_Logger::debug_log( "CUFT Migration 3.14.0: Successfully added index $index_name" );
                    }
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; schema introspection, not cacheable.
        $index = $wpdb->get_row(
            $wpdb->prepare(
                'SHOW INDEX FROM %i WHERE Key_name = %s',
                $table_name,
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query text is selected by key from the hardcoded $queries map above (fixed WHERE clauses on the plugin's own table), not user input; admin diagnostic tool.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; schema introspection during rollback, not cacheable.
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                'SHOW COLUMNS FROM %i LIKE %s',
                $table_name,
                'events'
            )
        );

        if ( ! empty( $column_exists ) ) {
            // Remove events column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin's own table cuft_click_tracking; schema migration for the plugin's own table (dev/testing rollback).
            $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN events', $table_name ) );

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

    /**
     * Migration to version 3.21.0
     * Converts ip_address column to ip_hash with SHA256 hashing
     */
    private static function migrate_to_3_21_0() {
        if ( class_exists( 'CUFT_Migration_3_21_0' ) ) {
            $result = CUFT_Migration_3_21_0::up();

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log(
                    $result ? 'info' : 'error',
                    'Migration 3.21.0: IP hash conversion ' . ( $result ? 'completed' : 'failed' ),
                    array( 'version' => '3.21.0' )
                );
            }
        }
    }
}