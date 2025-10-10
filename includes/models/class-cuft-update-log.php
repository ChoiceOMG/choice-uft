<?php
/**
 * UpdateLog Model
 *
 * Manages audit trail of all update-related activities using database storage.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT UpdateLog Model
 *
 * Handles logging of update activities to the database.
 */
class CUFT_Update_Log {

    /**
     * Valid action types
     */
    const VALID_ACTIONS = array(
        'check_started',
        'check_completed',
        'update_started',
        'download_started',
        'download_completed',
        'backup_created',
        'install_started',
        'install_completed',
        'rollback_started',
        'rollback_completed',
        'error',
        'migration_completed'
    );

    /**
     * Valid status types
     */
    const VALID_STATUSES = array(
        'success',
        'failure',
        'warning',
        'info'
    );

    /**
     * Get table name
     *
     * @return string Table name with prefix
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'cuft_update_log';
    }

    /**
     * Log an action
     *
     * @param string $action Action type
     * @param string $status Status type
     * @param array $data Additional data
     * @return int|false Insert ID on success, false on failure
     */
    public static function log( $action, $status = 'info', $data = array() ) {
        global $wpdb;

        // Check if table exists (in case migration hasn't run yet)
        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            return false;
        }

        // Validate action and status
        if ( ! in_array( $action, self::VALID_ACTIONS ) ) {
            return false;
        }

        if ( ! in_array( $status, self::VALID_STATUSES ) ) {
            $status = 'info';
        }

        // Prepare data
        $insert_data = array(
            'timestamp' => current_time( 'mysql' ),
            'action' => $action,
            'status' => $status,
            'version_from' => isset( $data['version_from'] ) ? substr( $data['version_from'], 0, 20 ) : null,
            'version_to' => isset( $data['version_to'] ) ? substr( $data['version_to'], 0, 20 ) : null,
            'details' => isset( $data['details'] ) ? substr( $data['details'], 0, 1000 ) : null,
            'user_id' => isset( $data['user_id'] ) ? intval( $data['user_id'] ) : get_current_user_id()
        );

        // Insert into database
        $result = $wpdb->insert(
            self::get_table_name(),
            $insert_data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );

        if ( $result ) {
            // Enforce FIFO limit (keep only last 5 entries)
            self::cleanup_old_entries();
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Log check started
     *
     * @param string $details Additional details
     * @return int|false Insert ID or false
     */
    public static function log_check_started( $details = '' ) {
        return self::log( 'check_started', 'info', array(
            'details' => $details ?: 'Update check initiated',
            'version_from' => CUFT_VERSION
        ) );
    }

    /**
     * Log check completed
     *
     * @param string $latest_version Latest version found
     * @param bool $update_available Whether update is available
     * @return int|false Insert ID or false
     */
    public static function log_check_completed( $latest_version, $update_available ) {
        $details = $update_available ?
            sprintf( 'Update available: %s → %s', CUFT_VERSION, $latest_version ) :
            'Already on latest version';

        return self::log( 'check_completed', 'success', array(
            'details' => $details,
            'version_from' => CUFT_VERSION,
            'version_to' => $latest_version
        ) );
    }

    /**
     * Log update started
     *
     * @param string $target_version Target version
     * @return int|false Insert ID or false
     */
    public static function log_update_started( $target_version ) {
        return self::log( 'update_started', 'info', array(
            'details' => sprintf( 'Starting update from %s to %s', CUFT_VERSION, $target_version ),
            'version_from' => CUFT_VERSION,
            'version_to' => $target_version
        ) );
    }

    /**
     * Log error
     *
     * @param string $error_message Error message
     * @param array $context Additional context
     * @return int|false Insert ID or false
     */
    public static function log_error( $error_message, $context = array() ) {
        $data = array(
            'details' => substr( $error_message, 0, 1000 )
        );

        if ( isset( $context['version_from'] ) ) {
            $data['version_from'] = $context['version_from'];
        }
        if ( isset( $context['version_to'] ) ) {
            $data['version_to'] = $context['version_to'];
        }

        return self::log( 'error', 'failure', $data );
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public static function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC',
            'orderby' => 'timestamp',
            'action' => null,
            'status' => null,
            'user_id' => null,
            'since' => null
        );

        $args = wp_parse_args( $args, $defaults );

        // Build query
        $table_name = self::get_table_name();
        $where = array( '1=1' );
        $where_values = array();

        // Add filters
        if ( $args['action'] ) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( $args['user_id'] ) {
            $where[] = 'user_id = %d';
            $where_values[] = intval( $args['user_id'] );
        }

        if ( $args['since'] ) {
            $where[] = 'timestamp >= %s';
            $where_values[] = $args['since'];
        }

        // Validate orderby
        $allowed_orderby = array( 'id', 'timestamp', 'action', 'status' );
        if ( ! in_array( $args['orderby'], $allowed_orderby ) ) {
            $args['orderby'] = 'timestamp';
        }

        // Validate order
        $args['order'] = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Build final query
        $sql = "SELECT * FROM $table_name WHERE " . implode( ' AND ', $where );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }

        $sql .= sprintf(
            ' ORDER BY %s %s LIMIT %d OFFSET %d',
            esc_sql( $args['orderby'] ),
            esc_sql( $args['order'] ),
            intval( $args['limit'] ),
            intval( $args['offset'] )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Get log count
     *
     * @param array $args Query arguments
     * @return int Log count
     */
    public static function get_count( $args = array() ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $where = array( '1=1' );
        $where_values = array();

        // Add filters
        if ( isset( $args['action'] ) ) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if ( isset( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( isset( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $where_values[] = intval( $args['user_id'] );
        }

        if ( isset( $args['since'] ) ) {
            $where[] = 'timestamp >= %s';
            $where_values[] = $args['since'];
        }

        // Build query
        $sql = "SELECT COUNT(*) FROM $table_name WHERE " . implode( ' AND ', $where );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get latest logs
     *
     * @param int $count Number of logs to retrieve
     * @return array Array of log entries
     */
    public static function get_latest( $count = 10 ) {
        return self::get_logs( array(
            'limit' => $count,
            'order' => 'DESC',
            'orderby' => 'timestamp'
        ) );
    }

    /**
     * Get logs for display
     *
     * @param array $args Query arguments
     * @return array Formatted logs for display
     */
    public static function get_display_logs( $args = array() ) {
        $logs = self::get_logs( $args );

        foreach ( $logs as &$log ) {
            // Add human-readable timestamp
            $log['timestamp_human'] = human_time_diff( strtotime( $log['timestamp'] ) ) . ' ago';

            // Add formatted date
            $log['timestamp_formatted'] = date_i18n(
                get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                strtotime( $log['timestamp'] )
            );

            // Add user display name
            if ( ! empty( $log['user_id'] ) ) {
                $user = get_userdata( $log['user_id'] );
                $log['user_name'] = $user ? $user->display_name : 'Unknown';
            } else {
                $log['user_name'] = 'System';
            }

            // Add action label
            $log['action_label'] = self::get_action_label( $log['action'] );

            // Add status class
            $log['status_class'] = 'cuft-status-' . $log['status'];
        }

        return $logs;
    }

    /**
     * Get action label
     *
     * @param string $action Action type
     * @return string Human-readable label
     */
    private static function get_action_label( $action ) {
        $labels = array(
            'check_started' => 'Check Started',
            'check_completed' => 'Check Completed',
            'update_started' => 'Update Started',
            'download_started' => 'Download Started',
            'download_completed' => 'Download Completed',
            'backup_created' => 'Backup Created',
            'install_started' => 'Installation Started',
            'install_completed' => 'Installation Completed',
            'rollback_started' => 'Rollback Started',
            'rollback_completed' => 'Rollback Completed',
            'error' => 'Error',
            'migration_completed' => 'Migration Completed'
        );

        return isset( $labels[ $action ] ) ? $labels[ $action ] : ucfirst( str_replace( '_', ' ', $action ) );
    }

    /**
     * Clear old logs
     *
     * @param int $days Days to keep
     * @return int Number of rows deleted
     */
    public static function clear_old_logs( $days = 30 ) {
        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %s WHERE timestamp < %s",
                self::get_table_name(),
                $cutoff_date
            )
        );

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Clear all logs
     *
     * @return bool True on success
     */
    public static function clear_all_logs() {
        global $wpdb;
        return $wpdb->query( "TRUNCATE TABLE " . self::get_table_name() ) !== false;
    }

    /**
     * Get summary statistics
     *
     * @param string $period Period for stats (day, week, month)
     * @return array Statistics
     */
    public static function get_stats( $period = 'week' ) {
        global $wpdb;

        $since = date( 'Y-m-d H:i:s', strtotime( "-1 {$period}" ) );
        $table_name = self::get_table_name();

        // Get counts by action
        $actions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, COUNT(*) as count
                 FROM $table_name
                 WHERE timestamp >= %s
                 GROUP BY action",
                $since
            ),
            ARRAY_A
        );

        // Get counts by status
        $statuses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                 FROM $table_name
                 WHERE timestamp >= %s
                 GROUP BY status",
                $since
            ),
            ARRAY_A
        );

        // Get last successful update
        $last_update = $wpdb->get_row(
            "SELECT * FROM $table_name
             WHERE action = 'install_completed' AND status = 'success'
             ORDER BY timestamp DESC
             LIMIT 1",
            ARRAY_A
        );

        // Get error count
        $error_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                 WHERE status = 'failure' AND timestamp >= %s",
                $since
            )
        );

        return array(
            'period' => $period,
            'since' => $since,
            'actions' => $actions,
            'statuses' => $statuses,
            'last_update' => $last_update,
            'error_count' => intval( $error_count ),
            'total_count' => self::get_count( array( 'since' => $since ) )
        );
    }

    /**
     * Clean up old entries to maintain FIFO limit (max 5 entries)
     *
     * @return int Number of entries deleted
     */
    private static function cleanup_old_entries() {
        global $wpdb;

        $table_name = self::get_table_name();
        $max_entries = 5;

        // Get ID of 6th most recent entry
        $threshold_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name
                 ORDER BY timestamp DESC
                 LIMIT 1 OFFSET %d",
                $max_entries
            )
        );

        // Delete all older entries
        if ( $threshold_id ) {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table_name WHERE id < %d",
                    $threshold_id
                )
            );

            return $deleted !== false ? $deleted : 0;
        }

        return 0;
    }
}