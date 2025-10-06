<?php
/**
 * Database migration: Create update log table
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create UpdateLog table migration
 *
 * Creates the wp_cuft_update_log table for tracking all update-related activities.
 */
class CUFT_Migration_Create_Update_Log_Table {

    /**
     * Run the migration
     *
     * @return bool True on success, false on failure
     */
    public static function up() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table already exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
            return true;
        }

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            version_from VARCHAR(20),
            version_to VARCHAR(20),
            details TEXT,
            user_id BIGINT UNSIGNED,
            INDEX idx_timestamp (timestamp),
            INDEX idx_action (action)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Verify table was created
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
            error_log( 'CUFT: Failed to create update log table' );
            return false;
        }

        // Add initial log entry
        $wpdb->insert(
            $table_name,
            array(
                'timestamp' => current_time( 'mysql' ),
                'action' => 'migration_completed',
                'status' => 'success',
                'version_from' => null,
                'version_to' => CUFT_VERSION,
                'details' => 'Update log table created successfully',
                'user_id' => get_current_user_id()
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );

        return true;
    }

    /**
     * Rollback the migration
     *
     * @return bool True on success, false on failure
     */
    public static function down() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Drop the table
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

        // Verify table was dropped
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
            error_log( 'CUFT: Failed to drop update log table' );
            return false;
        }

        return true;
    }

    /**
     * Clean old log entries (older than 30 days)
     *
     * @return int Number of rows deleted
     */
    public static function cleanup_old_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            )
        );

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Get migration version
     *
     * @return string Migration version
     */
    public static function get_version() {
        return '3.16.0';
    }

    /**
     * Check if migration should run
     *
     * @return bool True if migration should run
     */
    public static function should_run() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        return $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name;
    }
}

// Run migration on plugin activation
register_activation_hook( CUFT_BASENAME, array( 'CUFT_Migration_Create_Update_Log_Table', 'up' ) );

// Schedule cleanup cron job
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'cuft_cleanup_update_logs' ) ) {
        wp_schedule_event( time(), 'daily', 'cuft_cleanup_update_logs' );
    }
});

// Hook cleanup to cron event
add_action( 'cuft_cleanup_update_logs', array( 'CUFT_Migration_Create_Update_Log_Table', 'cleanup_old_logs' ) );