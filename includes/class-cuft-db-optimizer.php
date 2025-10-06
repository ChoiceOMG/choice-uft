<?php
/**
 * Database Query Optimizer
 *
 * Optimizes database queries for the update feature with proper indexing
 * and query performance improvements.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CUFT_DB_Optimizer
 *
 * Provides database optimization utilities for update-related queries.
 */
class CUFT_DB_Optimizer {

    /**
     * Initialize optimizer
     *
     * @since 3.15.0
     */
    public static function init() {
        // Hook into WordPress admin init for optimization checks
        add_action('admin_init', array(__CLASS__, 'maybe_optimize_tables'));
    }

    /**
     * Check and add missing indexes to update log table
     *
     * Adds performance indexes for common query patterns:
     * - timestamp DESC (recent logs)
     * - status (filter by status)
     * - action (filter by action type)
     *
     * @since 3.15.0
     * @return bool True if indexes were added or already exist
     */
    public static function add_update_log_indexes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';

        try {
            // Check if table exists
            $table_exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            );

            if (!$table_exists) {
                return false;
            }

            // Get existing indexes
            $existing_indexes = $wpdb->get_results(
                "SHOW INDEX FROM {$table_name}",
                ARRAY_A
            );

            $index_names = array();
            foreach ($existing_indexes as $index) {
                $index_names[] = $index['Key_name'];
            }

            // Add timestamp index if missing (for recent log queries)
            if (!in_array('idx_timestamp', $index_names)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} ADD INDEX idx_timestamp (timestamp DESC)"
                );
            }

            // Add status index if missing (for filtering by status)
            if (!in_array('idx_status', $index_names)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} ADD INDEX idx_status (status)"
                );
            }

            // Add action index if missing (for filtering by action)
            if (!in_array('idx_action', $index_names)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} ADD INDEX idx_action (action)"
                );
            }

            // Add composite index for common queries (status + timestamp)
            if (!in_array('idx_status_timestamp', $index_names)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} ADD INDEX idx_status_timestamp (status, timestamp DESC)"
                );
            }

            return true;

        } catch (Exception $e) {
            error_log('CUFT DB Optimizer: Failed to add indexes - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Optimize update log queries with prepared statements
     *
     * Returns optimized query for fetching recent logs with filters.
     *
     * @since 3.15.0
     * @param array $args Query arguments (limit, offset, status, action)
     * @return array Query results
     */
    public static function get_optimized_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit' => 10,
            'offset' => 0,
            'status' => null,
            'action' => null,
            'order_by' => 'timestamp',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Build WHERE clause with proper escaping
        $where_clauses = array();
        $where_values = array();

        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['action'])) {
            $where_clauses[] = "action = %s";
            $where_values[] = $args['action'];
        }

        $where_sql = !empty($where_clauses)
            ? 'WHERE ' . implode(' AND ', $where_clauses)
            : '';

        // Build ORDER BY clause (validated)
        $allowed_order_by = array('id', 'timestamp', 'status', 'action');
        $order_by = in_array($args['order_by'], $allowed_order_by)
            ? $args['order_by']
            : 'timestamp';

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Build complete query
        $query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";

        // Add limit and offset to values
        $where_values[] = absint($args['limit']);
        $where_values[] = absint($args['offset']);

        // Prepare and execute
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $wpdb->prepare($query, $args['limit'], $args['offset']);
        }

        return $wpdb->get_results($prepared_query, ARRAY_A);
    }

    /**
     * Get optimized count query
     *
     * Returns total count of logs matching filters without fetching all rows.
     *
     * @since 3.15.0
     * @param array $args Filter arguments (status, action)
     * @return int Total count
     */
    public static function get_logs_count($args = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Build WHERE clause
        $where_clauses = array();
        $where_values = array();

        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['action'])) {
            $where_clauses[] = "action = %s";
            $where_values[] = $args['action'];
        }

        $where_sql = !empty($where_clauses)
            ? 'WHERE ' . implode(' AND ', $where_clauses)
            : '';

        $query = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";

        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }

        return (int) $wpdb->get_var($prepared_query);
    }

    /**
     * Optimize tables periodically
     *
     * Runs OPTIMIZE TABLE command on update log table if needed.
     * Only runs once per day to avoid overhead.
     *
     * @since 3.15.0
     * @return bool True if optimization ran
     */
    public static function maybe_optimize_tables() {
        // Check if optimization needed (once per day)
        $last_optimized = get_option('cuft_last_db_optimize', 0);
        $one_day = 24 * HOUR_IN_SECONDS;

        if (time() - $last_optimized < $one_day) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Check if table exists first
        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        if (!$table_exists) {
            return false;
        }

        try {
            // Only optimize if table has substantial data
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            if ($row_count > 100) {
                $wpdb->query("OPTIMIZE TABLE {$table_name}");
                update_option('cuft_last_db_optimize', time());
                return true;
            }

        } catch (Exception $e) {
            error_log('CUFT DB Optimizer: Table optimization failed - ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Clean up old update logs
     *
     * Removes logs older than specified retention period.
     * Default: 90 days
     *
     * @since 3.15.0
     * @param int $retention_days Number of days to retain logs
     * @return int Number of rows deleted
     */
    public static function cleanup_old_logs($retention_days = 90) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < %s",
                $cutoff_date
            )
        );

        return (int) $deleted;
    }

    /**
     * Analyze slow queries and provide recommendations
     *
     * @since 3.15.0
     * @return array Performance analysis
     */
    public static function analyze_performance() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';

        $analysis = array(
            'table_size' => 0,
            'row_count' => 0,
            'index_count' => 0,
            'recommendations' => array()
        );

        try {
            // Get table size
            $table_status = $wpdb->get_row(
                $wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table_name),
                ARRAY_A
            );

            if ($table_status) {
                $analysis['table_size'] = size_format($table_status['Data_length'] + $table_status['Index_length']);
                $analysis['row_count'] = $table_status['Rows'];
            }

            // Count indexes
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}", ARRAY_A);
            $analysis['index_count'] = count(array_unique(array_column($indexes, 'Key_name')));

            // Provide recommendations
            if ($analysis['row_count'] > 1000) {
                $analysis['recommendations'][] = 'Consider enabling automatic log cleanup for old entries';
            }

            if ($analysis['index_count'] < 3) {
                $analysis['recommendations'][] = 'Run add_update_log_indexes() to improve query performance';
            }

        } catch (Exception $e) {
            $analysis['error'] = $e->getMessage();
        }

        return $analysis;
    }
}

// Initialize optimizer
CUFT_DB_Optimizer::init();
