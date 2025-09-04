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
}
