<?php
/**
 * Click tracking database management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Click_Tracker {
    
    /**
     * Database table name
     */
    private static $table_name = 'cuft_click_tracking';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_hooks' ) );
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Register webhook endpoint
        add_action( 'wp_ajax_nopriv_cuft_webhook', array( $this, 'handle_webhook' ) );
        add_action( 'wp_ajax_cuft_webhook', array( $this, 'handle_webhook' ) );
        
        // Add rewrite rule for cleaner webhook URLs
        add_action( 'init', array( $this, 'add_webhook_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'handle_webhook_request' ) );
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            click_id varchar(255) NOT NULL,
            platform varchar(100) DEFAULT NULL,
            campaign varchar(255) DEFAULT NULL,
            utm_source varchar(255) DEFAULT NULL,
            utm_medium varchar(255) DEFAULT NULL,
            utm_campaign varchar(255) DEFAULT NULL,
            utm_term varchar(255) DEFAULT NULL,
            utm_content varchar(255) DEFAULT NULL,
            qualified tinyint(1) DEFAULT 0,
            score int(11) DEFAULT 0,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            additional_data longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY click_id (click_id),
            KEY platform (platform),
            KEY qualified (qualified),
            KEY score (score),
            KEY date_created (date_created)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $result = dbDelta( $sql );
        
        // Log table creation
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'Click tracking table created/updated', 'info', array( 'result' => $result ) );
        }
        
        return $result;
    }
    
    /**
     * Insert or update click tracking record
     */
    public static function track_click( $click_id, $data = array() ) {
        global $wpdb;
        
        if ( empty( $click_id ) ) {
            return false;
        }
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Prepare default data
        $defaults = array(
            'platform' => '',
            'campaign' => '',
            'utm_source' => '',
            'utm_medium' => '',
            'utm_campaign' => '',
            'utm_term' => '',
            'utm_content' => '',
            'qualified' => 0,
            'score' => 0,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
            'additional_data' => ''
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        // Sanitize data
        $data['click_id'] = sanitize_text_field( $click_id );
        $data['platform'] = sanitize_text_field( $data['platform'] );
        $data['campaign'] = sanitize_text_field( $data['campaign'] );
        $data['utm_source'] = sanitize_text_field( $data['utm_source'] );
        $data['utm_medium'] = sanitize_text_field( $data['utm_medium'] );
        $data['utm_campaign'] = sanitize_text_field( $data['utm_campaign'] );
        $data['utm_term'] = sanitize_text_field( $data['utm_term'] );
        $data['utm_content'] = sanitize_text_field( $data['utm_content'] );
        $data['qualified'] = (int) $data['qualified'];
        $data['score'] = max( 0, min( 10, (int) $data['score'] ) ); // Ensure score is 0-10
        $data['ip_address'] = sanitize_text_field( $data['ip_address'] );
        $data['user_agent'] = sanitize_textarea_field( $data['user_agent'] );
        
        if ( is_array( $data['additional_data'] ) ) {
            $data['additional_data'] = json_encode( $data['additional_data'] );
        }
        $data['additional_data'] = sanitize_textarea_field( $data['additional_data'] );
        
        // Check if record exists
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE click_id = %s",
            $data['click_id']
        ) );
        
        if ( $existing ) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                $data,
                array( 'click_id' => $data['click_id'] ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ),
                array( '%s' )
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                $data,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
            );
        }
        
        if ( $result !== false && class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'Click tracked: ' . $click_id, 'info', $data );
        }
        
        return $result;
    }
    
    /**
     * Update qualified status and score
     */
    public static function update_click_status( $click_id, $qualified = null, $score = null ) {
        global $wpdb;
        
        if ( empty( $click_id ) ) {
            return false;
        }
        
        $table_name = $wpdb->prefix . self::$table_name;
        $update_data = array();
        $update_format = array();
        
        if ( $qualified !== null ) {
            $update_data['qualified'] = (int) $qualified;
            $update_format[] = '%d';
        }
        
        if ( $score !== null ) {
            $update_data['score'] = max( 0, min( 10, (int) $score ) );
            $update_format[] = '%d';
        }
        
        if ( empty( $update_data ) ) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'click_id' => sanitize_text_field( $click_id ) ),
            $update_format,
            array( '%s' )
        );
        
        if ( $result !== false && class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'Click status updated: ' . $click_id, 'info', $update_data );
        }
        
        return $result;
    }
    
    /**
     * Get click tracking records
     */
    public static function get_clicks( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'date_created',
            'order' => 'DESC',
            'qualified' => null,
            'platform' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args( $args, $defaults );
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where_clauses = array( '1=1' );
        $where_values = array();
        
        if ( $args['qualified'] !== null ) {
            $where_clauses[] = 'qualified = %d';
            $where_values[] = (int) $args['qualified'];
        }
        
        if ( ! empty( $args['platform'] ) ) {
            $where_clauses[] = 'platform = %s';
            $where_values[] = sanitize_text_field( $args['platform'] );
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field( $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field( $args['date_to'] );
        }
        
        $where_sql = implode( ' AND ', $where_clauses );
        
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'date_created DESC';
        }
        
        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );
        
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY $orderby LIMIT $limit OFFSET $offset";
        
        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }
        
        return $wpdb->get_results( $sql );
    }
    
    /**
     * Get click tracking count
     */
    public static function get_clicks_count( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'qualified' => null,
            'platform' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args( $args, $defaults );
        $table_name = $wpdb->prefix . self::$table_name;
        
        $where_clauses = array( '1=1' );
        $where_values = array();
        
        if ( $args['qualified'] !== null ) {
            $where_clauses[] = 'qualified = %d';
            $where_values[] = (int) $args['qualified'];
        }
        
        if ( ! empty( $args['platform'] ) ) {
            $where_clauses[] = 'platform = %s';
            $where_values[] = sanitize_text_field( $args['platform'] );
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field( $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field( $args['date_to'] );
        }
        
        $where_sql = implode( ' AND ', $where_clauses );
        $sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        
        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare( $sql, $where_values );
        }
        
        return (int) $wpdb->get_var( $sql );
    }
    
    /**
     * Add webhook rewrite rules
     */
    public function add_webhook_rewrite_rules() {
        add_rewrite_rule( '^cuft-webhook/?$', 'index.php?cuft_webhook=1', 'top' );
        add_rewrite_tag( '%cuft_webhook%', '([^&]+)' );
    }
    
    /**
     * Handle webhook request via template redirect
     */
    public function handle_webhook_request() {
        if ( get_query_var( 'cuft_webhook' ) ) {
            $this->handle_webhook();
            exit;
        }
    }
    
    /**
     * Handle webhook requests
     */
    public function handle_webhook() {
        // Verify webhook key
        $webhook_key = get_option( 'cuft_webhook_key', '' );
        $provided_key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
        
        if ( empty( $webhook_key ) || $provided_key !== $webhook_key ) {
            wp_send_json_error( array( 'message' => 'Invalid webhook key' ), 403 );
        }
        
        // Get required parameters
        $click_id = isset( $_GET['click_id'] ) ? sanitize_text_field( $_GET['click_id'] ) : '';
        
        if ( empty( $click_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing click_id parameter' ), 400 );
        }
        
        // Get optional parameters
        $qualified = isset( $_GET['qualified'] ) ? (int) $_GET['qualified'] : null;
        $score = isset( $_GET['score'] ) ? (int) $_GET['score'] : null;
        
        // Validate score range
        if ( $score !== null && ( $score < 0 || $score > 10 ) ) {
            wp_send_json_error( array( 'message' => 'Score must be between 0 and 10' ), 400 );
        }
        
        // Update the record
        $result = self::update_click_status( $click_id, $qualified, $score );
        
        if ( $result !== false ) {
            wp_send_json_success( array( 
                'message' => 'Click status updated successfully',
                'click_id' => $click_id,
                'qualified' => $qualified,
                'score' => $score
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update click status' ), 500 );
        }
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( $_SERVER[ $key ] );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Generate CSV export
     */
    public static function export_csv( $args = array() ) {
        $clicks = self::get_clicks( array_merge( $args, array( 'limit' => 10000 ) ) );
        
        if ( empty( $clicks ) ) {
            return false;
        }
        
        $filename = 'cuft-click-tracking-' . date( 'Y-m-d-H-i-s' ) . '.csv';
        
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        
        $output = fopen( 'php://output', 'w' );
        
        // CSV headers
        fputcsv( $output, array(
            'ID',
            'Click ID',
            'Platform',
            'Campaign',
            'UTM Source',
            'UTM Medium', 
            'UTM Campaign',
            'UTM Term',
            'UTM Content',
            'Qualified',
            'Score',
            'Date Created (UTC)',
            'Date Updated (UTC)',
            'IP Address',
            'User Agent'
        ) );
        
        // CSV data
        foreach ( $clicks as $click ) {
            fputcsv( $output, array(
                $click->id,
                $click->click_id,
                $click->platform,
                $click->campaign,
                $click->utm_source,
                $click->utm_medium,
                $click->utm_campaign,
                $click->utm_term,
                $click->utm_content,
                $click->qualified ? 'Yes' : 'No',
                $click->score,
                $click->date_created,
                $click->date_updated,
                $click->ip_address,
                $click->user_agent
            ) );
        }
        
        fclose( $output );
        exit;
    }
    
    /**
     * Delete old records
     */
    public static function cleanup_old_records( $days = 365 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE date_created < %s",
            $cutoff_date
        ) );
        
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( "Cleaned up {$result} old click tracking records", 'info' );
        }

        return $result;
    }

    /**
     * Add event to click tracking record
     *
     * @param string $click_id Unique click identifier
     * @param string $event_type Event type (phone_click, email_click, form_submit, generate_lead, status_update)
     * @return bool Success status
     */
    public static function add_event( $click_id, $event_type ) {
        global $wpdb;

        if ( empty( $click_id ) || empty( $event_type ) ) {
            return false;
        }

        // Check feature flag
        if ( class_exists( 'CUFT_Utils' ) && ! CUFT_Utils::is_feature_enabled( 'click_event_tracking' ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;

        // Validate event type
        $valid_events = array( 'phone_click', 'email_click', 'form_submit', 'generate_lead', 'status_update' );
        if ( ! in_array( $event_type, $valid_events ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Invalid event type: ' . $event_type );
            }
            return false;
        }

        try {
            // Get current events or initialize empty array
            $current_record = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, events FROM $table_name WHERE click_id = %s",
                sanitize_text_field( $click_id )
            ) );

            if ( ! $current_record ) {
                // Create new record if it doesn't exist
                $result = self::track_click( $click_id, array() );
                if ( ! $result ) {
                    return false;
                }

                // Get the newly created record
                $current_record = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id, events FROM $table_name WHERE click_id = %s",
                    sanitize_text_field( $click_id )
                ) );
            }

            // Parse existing events
            $events = array();
            if ( ! empty( $current_record->events ) ) {
                $events = json_decode( $current_record->events, true );
                if ( ! is_array( $events ) ) {
                    $events = array();
                }
            }

            // Create new event
            $new_event = array(
                'event' => $event_type,
                'timestamp' => gmdate( 'c' ) // ISO 8601 UTC format
            );

            // Add to events array
            $events[] = $new_event;

            // Limit to 100 events (keep most recent)
            if ( count( $events ) > 100 ) {
                $events = array_slice( $events, -100 );
            }

            // Sort by timestamp (newest last)
            usort( $events, function( $a, $b ) {
                return strcmp( $a['timestamp'], $b['timestamp'] );
            } );

            // Update record with new events
            $result = $wpdb->update(
                $table_name,
                array(
                    'events' => json_encode( $events ),
                    'date_updated' => current_time( 'mysql', true )
                ),
                array( 'id' => $current_record->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( $result !== false && class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'info', "Event added: {$event_type} for click_id: {$click_id}" );
            }

            return $result !== false;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'error', 'Failed to add event: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Get events for specific click
     *
     * @param string $click_id Unique click identifier
     * @return array Event array with timestamps
     */
    public static function get_events( $click_id ) {
        global $wpdb;

        if ( empty( $click_id ) ) {
            return array();
        }

        $table_name = $wpdb->prefix . self::$table_name;

        $events_json = $wpdb->get_var( $wpdb->prepare(
            "SELECT events FROM $table_name WHERE click_id = %s",
            sanitize_text_field( $click_id )
        ) );

        if ( empty( $events_json ) ) {
            return array();
        }

        $events = json_decode( $events_json, true );
        return is_array( $events ) ? $events : array();
    }

    /**
     * Get latest event timestamp
     *
     * @param string $click_id Unique click identifier
     * @return string|null ISO timestamp or null
     */
    public static function get_latest_event_time( $click_id ) {
        $events = self::get_events( $click_id );

        if ( empty( $events ) ) {
            return null;
        }

        // Events are sorted chronologically, so get the last one
        $latest_event = end( $events );
        return isset( $latest_event['timestamp'] ) ? $latest_event['timestamp'] : null;
    }

    /**
     * Cleanup old events (keep latest 100)
     *
     * @param string $click_id Unique click identifier
     * @return bool Success status
     */
    public static function cleanup_events( $click_id ) {
        $events = self::get_events( $click_id );

        if ( count( $events ) <= 100 ) {
            return true; // No cleanup needed
        }

        // Keep only the latest 100 events
        $events = array_slice( $events, -100 );

        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;

        $result = $wpdb->update(
            $table_name,
            array( 'events' => json_encode( $events ) ),
            array( 'click_id' => sanitize_text_field( $click_id ) ),
            array( '%s' ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Get clicks with event filtering
     *
     * @param array $args Query arguments including event_type filter
     * @return array Click records with events
     */
    public static function get_clicks_by_event( $event_type, $args = array() ) {
        global $wpdb;

        if ( empty( $event_type ) ) {
            return array();
        }

        $defaults = array(
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'date_updated',
            'order' => 'DESC'
        );

        $args = wp_parse_args( $args, $defaults );
        $table_name = $wpdb->prefix . self::$table_name;

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'date_updated DESC';
        }

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        // Use JSON_CONTAINS for MySQL 5.7+ compatibility
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE events IS NOT NULL
             AND JSON_CONTAINS(events, %s)
             ORDER BY $orderby
             LIMIT $limit OFFSET $offset",
            json_encode( array( 'event' => $event_type ) )
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Enhanced track_click method with events initialization
     * Overrides original method to include events column handling
     */
    public static function track_click( $click_id, $data = array() ) {
        global $wpdb;

        if ( empty( $click_id ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;

        // Prepare default data (updated for new schema)
        $defaults = array(
            'platform' => '',
            'campaign' => '',
            'utm_source' => '',
            'utm_medium' => '',
            'utm_campaign' => '',
            'utm_term' => '',
            'utm_content' => '',
            'events' => null, // Initialize empty events
            'qualified' => 0,
            'score' => 0,
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
            'additional_data' => ''
        );

        $data = wp_parse_args( $data, $defaults );

        // Sanitize data
        $data['click_id'] = sanitize_text_field( $click_id );
        $data['platform'] = sanitize_text_field( $data['platform'] );
        $data['campaign'] = sanitize_text_field( $data['campaign'] );
        $data['utm_source'] = sanitize_text_field( $data['utm_source'] );
        $data['utm_medium'] = sanitize_text_field( $data['utm_medium'] );
        $data['utm_campaign'] = sanitize_text_field( $data['utm_campaign'] );
        $data['utm_term'] = sanitize_text_field( $data['utm_term'] );
        $data['utm_content'] = sanitize_text_field( $data['utm_content'] );
        $data['qualified'] = (int) $data['qualified'];
        $data['score'] = max( 0, min( 10, (int) $data['score'] ) );
        $data['ip_address'] = sanitize_text_field( $data['ip_address'] );
        $data['user_agent'] = sanitize_textarea_field( $data['user_agent'] );

        if ( is_array( $data['additional_data'] ) ) {
            $data['additional_data'] = json_encode( $data['additional_data'] );
        }
        $data['additional_data'] = sanitize_textarea_field( $data['additional_data'] );

        // Initialize empty events array if events column exists
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE 'events'" );
        if ( ! empty( $columns ) && $data['events'] === null ) {
            $data['events'] = json_encode( array() );
        }

        // Check if record exists
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE click_id = %s",
            $data['click_id']
        ) );

        if ( $existing ) {
            // Update existing record (preserve events)
            unset( $data['events'] ); // Don't overwrite existing events
            unset( $data['click_id'] ); // Don't update click_id

            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' );
            if ( ! empty( $columns ) ) {
                // Remove events from format array since we're not updating it
                $format = array_slice( $format, 0, -1 );
            }

            $result = $wpdb->update(
                $table_name,
                $data,
                array( 'click_id' => $click_id ),
                $format,
                array( '%s' )
            );
        } else {
            // Insert new record
            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' );
            if ( ! empty( $columns ) ) {
                $format[] = '%s'; // Add format for events column
            }

            $result = $wpdb->insert(
                $table_name,
                $data,
                $format
            );
        }

        if ( $result !== false && class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( 'info', 'Click tracked: ' . $click_id, $data );
        }

        return $result;
    }
}
