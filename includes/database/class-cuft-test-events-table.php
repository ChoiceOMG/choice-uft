<?php
/**
 * Test Events Database Table
 *
 * Manages a separate database table for storing test event data,
 * isolated from production click tracking data.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Test Events Table Class
 *
 * Handles creation, updates, and CRUD operations for the test events table.
 */
class CUFT_Test_Events_Table {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private $table_name;

    /**
     * Version tracking option key
     *
     * @var string
     */
    private $version_key = 'cuft_test_events_db_version';

    /**
     * Current table version
     *
     * @var string
     */
    private $current_version = '1.0';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuft_test_events';
    }

    /**
     * Create or update table schema
     *
     * Uses dbDelta for safe schema updates.
     * CRITICAL: dbDelta is very strict about formatting:
     * - Two spaces after PRIMARY KEY
     * - No spaces around default values
     * - Use KEY not INDEX
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta strict formatting requirements
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(50) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            test_mode tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Update version
        update_option($this->version_key, $this->current_version);
    }

    /**
     * Check if table needs update and create/update if needed
     *
     * Safe to call multiple times - only updates if version changed.
     */
    public function maybe_update() {
        $installed_version = get_option($this->version_key);

        if ($installed_version !== $this->current_version) {
            $this->create_table();
        }
    }

    /**
     * Insert test event
     *
     * @param string $session_id Test session identifier
     * @param string $event_type Event type (phone_click, email_click, form_submit, generate_lead)
     * @param array  $event_data Complete dataLayer event object
     * @return int|false Insert ID on success, false on failure
     */
    public function insert_event($session_id, $event_type, $event_data) {
        global $wpdb;

        try {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'session_id' => sanitize_text_field($session_id),
                    'event_type' => sanitize_text_field($event_type),
                    'event_data' => wp_json_encode($event_data),
                    'test_mode' => 1,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );

            return $result ? $wpdb->insert_id : false;
        } catch (Exception $e) {
            error_log('CUFT: Failed to insert test event - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get events by session ID
     *
     * @param string $session_id Test session identifier
     * @return array Array of event objects
     */
    public function get_events_by_session($session_id) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE session_id = %s ORDER BY created_at ASC",
                sanitize_text_field($session_id)
            )
        );

        // Decode JSON data
        if ($results) {
            foreach ($results as &$event) {
                $event->event_data = json_decode($event->event_data, true);
            }
        }

        return $results ? $results : array();
    }

    /**
     * Get events with filters
     *
     * @param array $filters Associative array of filters (session_id, event_type, limit, offset)
     * @return array Array of event objects
     */
    public function get_events($filters = array()) {
        global $wpdb;

        $where = array('1=1');
        $values = array();

        // Session ID filter
        if (!empty($filters['session_id'])) {
            $where[] = 'session_id = %s';
            $values[] = sanitize_text_field($filters['session_id']);
        }

        // Event type filter
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $values[] = sanitize_text_field($filters['event_type']);
        }

        $where_clause = implode(' AND ', $where);

        // Build query
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC";

        // Add pagination
        if (isset($filters['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', absint($filters['limit']));

            if (isset($filters['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', absint($filters['offset']));
            }
        }

        // Execute query
        if (!empty($values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, $values));
        } else {
            $results = $wpdb->get_results($query);
        }

        // Decode JSON data
        if ($results) {
            foreach ($results as &$event) {
                $event->event_data = json_decode($event->event_data, true);
            }
        }

        return $results ? $results : array();
    }

    /**
     * Get total count of events (for pagination)
     *
     * @param array $filters Associative array of filters (session_id, event_type)
     * @return int Total event count
     */
    public function get_events_count($filters = array()) {
        global $wpdb;

        $where = array('1=1');
        $values = array();

        if (!empty($filters['session_id'])) {
            $where[] = 'session_id = %s';
            $values[] = sanitize_text_field($filters['session_id']);
        }

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $values[] = sanitize_text_field($filters['event_type']);
        }

        $where_clause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        if (!empty($values)) {
            return (int) $wpdb->get_var($wpdb->prepare($query, $values));
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Delete events by IDs
     *
     * @param array $ids Array of event IDs to delete
     * @return int Number of rows deleted
     */
    public function delete_by_id($ids) {
        global $wpdb;

        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
                $ids
            )
        );
    }

    /**
     * Delete all events from a session
     *
     * @param string $session_id Test session identifier
     * @return int Number of rows deleted
     */
    public function delete_by_session($session_id) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE session_id = %s",
                sanitize_text_field($session_id)
            )
        );
    }

    /**
     * Delete all test events
     *
     * @return int Number of rows deleted
     */
    public function delete_all() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Delete events older than specified days
     *
     * Automatic cleanup for old test data.
     *
     * @param int $days Number of days to retain (default 30)
     * @return int Number of rows deleted
     */
    public function cleanup_old_events($days = 30) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                absint($days)
            )
        );
    }

    /**
     * Drop table (for uninstall)
     *
     * WARNING: This permanently deletes all test event data.
     */
    public function drop_table() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        delete_option($this->version_key);
    }

    /**
     * Get table name (with prefix)
     *
     * @return string Full table name
     */
    public function get_table_name() {
        return $this->table_name;
    }
}
