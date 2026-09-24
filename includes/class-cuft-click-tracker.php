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
     * Option holding the most recent click-write failure.
     *
     * A small, independent record of the last error, separate from the
     * debug log: it survives with debug logging off, it never grows
     * unbounded, and it carries no PII (no IP, no user agent, and only a
     * short prefix of the click id).
     */
    const OPTION_LAST_WRITE_ERROR = 'cuft_last_click_write_error';

    /**
     * Option/transient family used by the schema self-heal check.
     */
    const OPTION_SCHEMA_VERIFIED_VERSION = 'cuft_click_schema_verified_version';
    const TRANSIENT_SCHEMA_CHECK_PREFIX  = 'cuft_click_schema_ok_';

    /**
     * Columns the current code depends on, beyond what create_table() ships
     * with, added by later migrations. Keyed by column name, valued by the
     * SQL fragment used to add it (without the leading ADD COLUMN).
     *
     * @var array<string,string>
     */
    private static function get_expected_added_columns() {
        return array(
            'events'       => 'events LONGTEXT DEFAULT NULL AFTER utm_content',
            'ga_client_id' => 'ga_client_id varchar(255) DEFAULT NULL AFTER ip_hash',
        );
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init_hooks' ) );

        // Schema self-heal: admin screens only, never on a front-end
        // request. self_heal_schema() itself is cheap after the first check
        // per version (cached in a transient), so this is safe on every
        // wp-admin page load.
        add_action( 'admin_init', array( __CLASS__, 'self_heal_schema' ) );
    }

    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Register webhook endpoint
        add_action( 'wp_ajax_nopriv_cuft_webhook', array( $this, 'handle_webhook' ) );
        add_action( 'wp_ajax_cuft_webhook', array( $this, 'handle_webhook' ) );

        // Add rewrite rule for cleaner webhook URLs
        $this->add_webhook_rewrite_rules();
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
            ip_hash varchar(64) DEFAULT NULL,
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
        // Read the error before any other query runs: every $wpdb query,
        // including the existence check below, resets last_error.
        $db_error = $wpdb->last_error;

        // dbDelta() reports what it attempted, not what actually landed: it
        // logs "Created table" before running the query and does not
        // surface a failed CREATE. Confirm the table is really there.
        $exists = self::table_exists( $table_name );

        if ( ! $exists ) {
            // Run the CREATE directly once so MySQL's own error text is
            // captured; dbDelta() can skip or reshape the statement.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin's own table cuft_click_tracking; $sql is the hardcoded schema above with only $wpdb->prefix and the charset interpolated.
            $wpdb->query( $sql );
            $direct_error = $wpdb->last_error;
            $exists       = self::table_exists( $table_name );

            if ( ! $exists ) {
                $detail = $direct_error ? $direct_error : $db_error;
                self::record_click_write_error(
                    'create_table',
                    $detail ? $detail : self::describe_missing_table( $table_name ),
                    ''
                );
            }
        }

        // Log table creation
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                'Click tracking table created/updated',
                $exists ? CUFT_Logger::INFO : CUFT_Logger::ERROR,
                array(
                    'result'        => $result,
                    'table_exists'  => $exists,
                    'wpdb_error'    => $exists ? '' : $db_error,
                )
            );
        }

        return $exists ? $result : false;
    }

    /**
     * Whether a table exists, verified with a real SHOW TABLES query.
     *
     * @param string $table_name Fully prefixed table name.
     * @return bool
     */
    private static function describe_missing_table( $table_name ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic read of the current schema name.
        $schema = $wpdb->get_var( 'SELECT DATABASE()' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic: can the table be read even though SHOW TABLES does not list it.
        $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
        $select_error = $wpdb->last_error;
        return sprintf(
            'No MySQL error returned. schema=%s; SELECT on %s: %s',
            $schema ? $schema : 'unknown',
            $table_name,
            $select_error ? $select_error : 'succeeded (table readable but not listed by SHOW TABLES)'
        );
    }

    private static function table_exists( $table_name ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; existence check, not cacheable.
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;
    }

    /**
     * Record a click-write failure so it is visible even with debug logging off.
     *
     * Writes an ERROR-level entry to CUFT_Logger (always recorded regardless
     * of the debug logging setting since 3.28.2) and a small, capped, PII-free
     * summary to its own option for the admin notice on the Click Tracking
     * page. No IP, no user agent; the click id is truncated to a short
     * prefix, since a raw click id can itself be sensitive-adjacent data
     * pulled off a URL.
     *
     * @param string $context   Short label for where the failure happened (e.g. 'insert', 'update', 'create_table').
     * @param string $db_error  $wpdb->last_error at the point of failure.
     * @param string $click_id  The click id involved, if any.
     */
    private static function record_click_write_error( $context, $db_error, $click_id = '' ) {
        $click_id_prefix = '' !== $click_id ? substr( sanitize_text_field( $click_id ), 0, 12 ) . '...' : '';

        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                'Click tracking write failed: ' . $context,
                CUFT_Logger::ERROR,
                array(
                    'context'  => $context,
                    'db_error' => $db_error,
                    'click_id' => $click_id_prefix,
                )
            );
        }

        $message = $db_error ? substr( (string) $db_error, 0, 300 ) : 'Unknown database error';

        update_option(
            self::OPTION_LAST_WRITE_ERROR,
            array(
                'timestamp'  => current_time( 'mysql', true ),
                'context'    => $context,
                'message'    => $message,
                'click_id'   => $click_id_prefix,
            ),
            false
        );
    }

    /**
     * Get the most recently recorded click-write failure, if any.
     *
     * @return array|null
     */
    public static function get_last_write_error() {
        $error = get_option( self::OPTION_LAST_WRITE_ERROR, null );
        return is_array( $error ) ? $error : null;
    }

    /**
     * Clear the recorded click-write failure (e.g. after an admin dismisses it).
     */
    public static function clear_last_write_error() {
        delete_option( self::OPTION_LAST_WRITE_ERROR );
    }

    /**
     * Verify the click tracking table exists and carries every column the
     * current code depends on, creating or repairing it if not.
     *
     * Cheap on the common path: once a given plugin version has been
     * verified on this site, the result is cached in a transient and every
     * later call returns immediately with no query. Call this from
     * admin_init (not on every front-end request) and after a version
     * change.
     *
     * @param bool $force Skip the cache and check for real.
     * @return bool True if the schema is (now) healthy.
     */
    public static function self_heal_schema( $force = false ) {
        global $wpdb;

        $version_key      = defined( 'CUFT_VERSION' ) ? CUFT_VERSION : 'unknown';
        $transient_key     = self::TRANSIENT_SCHEMA_CHECK_PREFIX . md5( $version_key );

        if ( ! $force && false !== get_transient( $transient_key ) ) {
            return true;
        }

        // After a failed repair, wait an hour before trying again, so a site
        // whose database refuses the schema change does not retry the DDL on
        // every admin page load.
        $failure_key = $transient_key . '_failed';
        if ( ! $force && false !== get_transient( $failure_key ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;
        $healthy    = true;

        if ( ! self::table_exists( $table_name ) ) {
            self::create_table();
            if ( ! self::table_exists( $table_name ) ) {
                // create_table() already recorded the failure.
                set_transient( $failure_key, true, HOUR_IN_SECONDS );
                return false;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; schema introspection, not cacheable, and gated by the transient above.
        $existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ) );

        foreach ( self::get_expected_added_columns() as $column => $add_fragment ) {
            if ( in_array( $column, $existing_columns, true ) ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin's own table cuft_click_tracking; $add_fragment comes from the hardcoded map above, not user input, and %i cannot express a full ADD COLUMN definition.
            $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN ' . $add_fragment, $table_name ) );
            $alter_error = $wpdb->last_error;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; verifying the ALTER above landed.
            $now_has_column = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ) );

            if ( ! in_array( $column, $now_has_column, true ) ) {
                $healthy = false;
                self::record_click_write_error( 'self_heal_add_column_' . $column, $alter_error, '' );
            } elseif ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log(
                    'Click tracking self-heal: added missing column ' . $column,
                    CUFT_Logger::INFO,
                    array( 'column' => $column )
                );
            }
        }

        if ( ! $healthy ) {
            set_transient( $failure_key, true, HOUR_IN_SECONDS );
        }

        if ( $healthy ) {
            // Cache success for a day; a version bump invalidates the key
            // automatically since it is part of the transient name.
            set_transient( $transient_key, true, DAY_IN_SECONDS );
            update_option( self::OPTION_SCHEMA_VERIFIED_VERSION, $version_key, false );
        }

        return $healthy;
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
            'ip_hash' => self::hash_ip( self::get_client_ip() ),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
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
        $data['ip_hash'] = sanitize_text_field( $data['ip_hash'] );
        $data['user_agent'] = sanitize_textarea_field( $data['user_agent'] );
        
        if ( is_array( $data['additional_data'] ) ) {
            $data['additional_data'] = wp_json_encode( $data['additional_data'] );
        }
        $data['additional_data'] = sanitize_textarea_field( $data['additional_data'] );

        // Initialize empty events array if events column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        $columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, 'events' ) );
        if ( ! empty( $columns ) && ! isset( $data['events'] ) ) {
            $data['events'] = wp_json_encode( array() );
        }

        // Check if record exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        $existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM %i WHERE click_id = %s', $table_name, $data['click_id'] ) );

        if ( $existing ) {
            // Update existing record (preserve events if they exist)
            if ( ! empty( $columns ) ) {
                // Don't overwrite existing events on update
                unset( $data['events'] );
            }
            unset( $data['click_id'] ); // Don't update click_id

            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it.
            $result = $wpdb->update(
                $table_name,
                $data,
                array( 'click_id' => $click_id ),
                $format,
                array( '%s' )
            );
        } else {
            // Insert new record
            // Format: platform, campaign, utm_source, utm_medium, utm_campaign, utm_term, utm_content,
            //         qualified(%d), score(%d), ip_hash, user_agent, additional_data, click_id, [events]
            $format = ! empty( $columns )
                ? array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
                : array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin's own table cuft_click_tracking; no WP API covers it.
            $result = $wpdb->insert(
                $table_name,
                $data,
                $format
            );
        }
        
        if ( $result !== false ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Click tracked: ' . $click_id, CUFT_Logger::INFO, $data );
            }
        } else {
            self::record_click_write_error(
                $existing ? 'update' : 'insert',
                $wpdb->last_error,
                $click_id
            );
        }

        return $result;
    }
    
    /**
     * Get valid webhook status values for lead lifecycle events
     *
     * @return array List of valid status strings.
     */
    /**
     * Option that switches the webhook key check on.
     */
    const OPTION_REQUIRE_KEY = 'cuft_webhook_require_key';

    /**
     * Option holding the shared webhook key.
     */
    const OPTION_WEBHOOK_KEY = 'cuft_webhook_key';

    /**
     * Whether webhook requests must carry the site's key.
     *
     * On for new installs since 3.28.0; installs upgrading from an earlier
     * version keep it off until an administrator turns it on, so existing
     * integrations keep working (see CUFT_DB_Migration::migrate_to_3_28_0()).
     *
     * @return bool
     */
    public static function webhook_key_required() {
        return (bool) get_option( self::OPTION_REQUIRE_KEY, false );
    }

    /**
     * Return the webhook key, generating one first if none exists.
     *
     * @return string
     */
    public static function get_webhook_key() {
        $key = (string) get_option( self::OPTION_WEBHOOK_KEY, '' );
        if ( '' === $key ) {
            $key = wp_generate_password( 32, false );
            update_option( self::OPTION_WEBHOOK_KEY, $key, false );
        }
        return $key;
    }

    public static function get_valid_webhook_statuses() {
        return array(
            'qualify_lead',
            'disqualify_lead',
            'working_lead',
            'close_convert_lead',
            'close_unconvert_lead',
        );
    }

    /**
     * Fire Measurement Protocol for webhook-driven lifecycle events
     *
     * @param string      $click_id       Unique click identifier.
     * @param string|null $status         Lifecycle status value.
     * @param object|null $current_record Current DB record for the click.
     */
    private static function fire_measurement_protocol( $click_id, $status, $current_record ) {
        if ( ! $status || ! in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
            return;
        }

        $ga_client_id = '';
        if ( $current_record && ! empty( $current_record->ga_client_id ) ) {
            $ga_client_id = $current_record->ga_client_id;
        }

        $platform = 'unknown';
        if ( $current_record && ! empty( $current_record->platform ) ) {
            $platform = $current_record->platform;
        }

        $mp = new CUFT_Measurement_Protocol();
        $mp->send( $ga_client_id, $status, array(
            'click_id'    => $click_id,
            'lead_source' => $platform,
        ) );
    }

    /**
     * Update qualified status and score
     */
    public static function update_click_status( $click_id, $qualified = null, $score = null, $status = null ) {
        global $wpdb;

        if ( empty( $click_id ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;

        // Get current record to check for score increase and MP firing
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        $current_record = $wpdb->get_row( $wpdb->prepare( 'SELECT qualified, score, platform, ga_client_id FROM %i WHERE click_id = %s', $table_name, sanitize_text_field( $click_id ) ) );

        $old_score = $current_record ? (int) $current_record->score : 0;

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

        // If only status provided (no qualified/score changes), still record the event
        if ( empty( $update_data ) && $status ) {
            try {
                if ( in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
                    self::add_event( $click_id, $status, 'webhook' );
                    self::fire_measurement_protocol( $click_id, $status, $current_record );
                }
            } catch ( Exception $e ) {
                if ( class_exists( 'CUFT_Logger' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    CUFT_Logger::log( 'Failed to record webhook event: ' . $e->getMessage(), CUFT_Logger::ERROR );
                }
            }
            return true;
        }

        if ( empty( $update_data ) && ! $status ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it.
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'click_id' => sanitize_text_field( $click_id ) ),
            $update_format,
            array( '%s' )
        );

        if ( $result !== false ) {
            try {
                // Record lifecycle status event
                if ( $status && in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
                    self::add_event( $click_id, $status, 'webhook' );
                    self::fire_measurement_protocol( $click_id, $status, $current_record );
                } elseif ( $qualified === 1 || $qualified === '1' ) {
                    // Backward compatibility: qualified=1 maps to qualify_lead
                    self::add_event( $click_id, 'qualify_lead', 'webhook' );
                }

                // Record score_updated event if score increased
                if ( $score !== null && $score > $old_score ) {
                    self::add_event( $click_id, 'score_updated', 'webhook' );
                }
            } catch ( Exception $e ) {
                if ( class_exists( 'CUFT_Logger' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    CUFT_Logger::log( 'Failed to record webhook event: ' . $e->getMessage(), CUFT_Logger::ERROR );
                }
            }

            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Click status updated: ' . $click_id, 'info', $update_data );
            }
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
            'event_type' => '',
            'has_events' => '',
            'date_from' => '',
            'date_to' => '',
            'ip_hash' => '',
            'sort_by' => 'date_created'
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

        // Event type filter (v3.12.0+)
        if ( ! empty( $args['event_type'] ) ) {
            if ( $args['event_type'] === 'no_event' ) {
                $where_clauses[] = '(events IS NULL OR JSON_LENGTH(events) = 0)';
            } else {
                $where_clauses[] = 'JSON_CONTAINS(events, %s)';
                $where_values[] = wp_json_encode( array( 'event' => sanitize_text_field( $args['event_type'] ) ) );
            }
        }

        // Has events filter (v3.21.7+)
        if ( $args['has_events'] === '0' ) {
            $where_clauses[] = '(events IS NULL OR JSON_LENGTH(events) = 0)';
        } elseif ( $args['has_events'] === '1' ) {
            $where_clauses[] = '(events IS NOT NULL AND JSON_LENGTH(events) > 0)';
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field( $args['date_from'] );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field( $args['date_to'] );
        }

        // IP hash filter (v3.21.0+)
        if ( ! empty( $args['ip_hash'] ) ) {
            $where_clauses[] = 'ip_hash = %s';
            $where_values[] = sanitize_text_field( $args['ip_hash'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Handle sort_by parameter (v3.12.0+). Column and direction are whitelisted.
        $orderby_column = ( $args['sort_by'] === 'date_updated' ) ? 'date_updated' : 'date_created';
        $order          = ( 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        // $where_sql holds only the fixed fragments above with %d/%s placeholders; every value is bound by prepare().
        $sql        = "SELECT * FROM %i WHERE {$where_sql} ORDER BY %i {$order} LIMIT %d OFFSET %d";
        $query_args = array_merge( array( $table_name ), $where_values, array( $orderby_column, $limit, $offset ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is built from fixed WHERE fragments and whitelisted ORDER BY direction; table, column and all values are bound via %i/%s/%d here. Plugin's own table; admin list data read fresh.
        return $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
    }

    /**
     * Get a single click record by click_id
     *
     * @param string $click_id The click ID to look up
     * @return object|null The click record or null if not found
     */
    public static function get_click_by_id( $click_id ) {
        global $wpdb;

        if ( empty( $click_id ) ) {
            return null;
        }

        $table_name = $wpdb->prefix . self::$table_name;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE click_id = %s', $table_name, sanitize_text_field( $click_id ) ) );
    }

    /**
     * Get click tracking count
     */
    public static function get_clicks_count( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'qualified' => null,
            'platform' => '',
            'event_type' => '',
            'has_events' => '',
            'date_from' => '',
            'date_to' => '',
            'ip_hash' => ''
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

        // Event type filter (v3.12.0+)
        if ( ! empty( $args['event_type'] ) ) {
            if ( $args['event_type'] === 'no_event' ) {
                $where_clauses[] = '(events IS NULL OR JSON_LENGTH(events) = 0)';
            } else {
                $where_clauses[] = 'JSON_CONTAINS(events, %s)';
                $where_values[] = wp_json_encode( array( 'event' => sanitize_text_field( $args['event_type'] ) ) );
            }
        }

        // Has events filter (v3.21.7+)
        if ( $args['has_events'] === '0' ) {
            $where_clauses[] = '(events IS NULL OR JSON_LENGTH(events) = 0)';
        } elseif ( $args['has_events'] === '1' ) {
            $where_clauses[] = '(events IS NOT NULL AND JSON_LENGTH(events) > 0)';
        }

        if ( ! empty( $args['date_from'] ) ) {
            $where_clauses[] = 'date_created >= %s';
            $where_values[] = sanitize_text_field( $args['date_from'] );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $where_clauses[] = 'date_created <= %s';
            $where_values[] = sanitize_text_field( $args['date_to'] );
        }

        // IP hash filter (v3.21.0+)
        if ( ! empty( $args['ip_hash'] ) ) {
            $where_clauses[] = 'ip_hash = %s';
            $where_values[] = sanitize_text_field( $args['ip_hash'] );
        }

        $where_sql = implode( ' AND ', $where_clauses );
        // $where_sql holds only the fixed fragments above with %d/%s placeholders; every value is bound by prepare().
        $sql        = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
        $query_args = array_merge( array( $table_name ), $where_values );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is built from fixed WHERE fragments; the table and all values are bound via %i/%s/%d here. Plugin's own table; admin list data read fresh.
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $query_args ) );
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
     *
     * Public endpoint for updating click status. Security through obscurity:
     * - No authentication required (for use in email messages)
     * - click_id acts as the obscure identifier
     * - Only allows updates to existing records
     *
     * @since 3.13.0 Changed from key-based auth to public obscure endpoint
     * @since 3.28.0 Optional shared key (cuft_webhook_require_key), on by default for new installs
     */
    public function handle_webhook() {
        // Shared-key check. External callers (email links, CRM) cannot hold a
        // nonce, so when the setting is on every request must carry the site's
        // key, compared in constant time.
        if ( self::webhook_key_required() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External webhook authenticated by the shared key compared below; a nonce cannot be issued to email links or CRM systems.
            $provided_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
            if ( '' === $provided_key || ! hash_equals( self::get_webhook_key(), $provided_key ) ) {
                wp_send_json_error( array( 'message' => 'Invalid or missing webhook key' ), 403 );
            }
        }

        // Get required parameters.
        // No nonce by design: this endpoint is called from emails and CRM integrations
        // that cannot hold a WordPress nonce. It only updates rows whose click_id already exists.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public webhook for external systems (email links, CRM); a nonce cannot be issued to them. Access is keyed by an existing click_id.
        $click_id = isset( $_GET['click_id'] ) ? sanitize_text_field( wp_unslash( $_GET['click_id'] ) ) : '';

        if ( empty( $click_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing click_id parameter' ), 400 );
        }

        // Verify the click_id exists (security: only allow updates to existing records)
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE click_id = %s', $table_name, $click_id ) );

        if ( ! $exists ) {
            // Don't reveal whether record exists (return generic error)
            wp_send_json_error( array( 'message' => 'Invalid request' ), 400 );
        }

        // Get optional parameters (same public webhook as above; no nonce by design).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same public webhook as above; external callers cannot hold a nonce.
        $qualified = isset( $_GET['qualified'] ) ? intval( $_GET['qualified'] ) : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same public webhook as above; external callers cannot hold a nonce.
        $score     = isset( $_GET['score'] ) ? intval( $_GET['score'] ) : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same public webhook as above; external callers cannot hold a nonce.
        $status    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;

        // Validate status if provided
        if ( $status && ! in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
            wp_send_json_error( array(
                'message' => 'Invalid status value. Valid values: ' . implode( ', ', self::get_valid_webhook_statuses() ),
            ), 400 );
        }

        // Status takes precedence over qualified parameter
        if ( $status ) {
            if ( 'qualify_lead' === $status ) {
                // qualify_lead implies qualified=1
                $qualified = 1;
            } else {
                // Other statuses: don't update qualified field
                $qualified = null;
            }
        }

        // Validate score range
        if ( $score !== null && ( $score < 0 || $score > 10 ) ) {
            wp_send_json_error( array( 'message' => 'Score must be between 0 and 10' ), 400 );
        }

        // Update the record
        $result = self::update_click_status( $click_id, $qualified, $score, $status );

        if ( $result !== false ) {
            wp_send_json_success( array(
                'message' => 'Click status updated successfully',
                'click_id' => $click_id,
                'qualified' => $qualified,
                'score' => $score,
                'status' => $status,
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to update click status' ), 500 );
        }
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                // Validate IP address (allow all valid IPs including private ranges)
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Hash IP address using SHA256 for privacy
     *
     * @param string $ip The IP address to hash
     * @return string The SHA256 hash of the IP (64 characters) or empty string
     */
    private static function hash_ip( $ip ) {
        if ( empty( $ip ) ) {
            return '';
        }
        return hash( 'sha256', $ip );
    }
    
    /**
     * Generate CSV export
     */
    public static function export_csv( $args = array() ) {
        $clicks = self::get_clicks( array_merge( $args, array( 'limit' => 10000 ) ) );

        if ( empty( $clicks ) ) {
            return false;
        }

        // Clean any output buffers to prevent corruption
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        $filename = 'cuft-click-tracking-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://output is the HTTP response stream, not a file.
        $output = fopen( 'php://output', 'w' );

        // Add UTF-8 BOM for Excel compatibility
        fprintf( $output, "\xEF\xBB\xBF" );

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
            'IP Hash',
            'User Agent'
        ) );

        // CSV data
        foreach ( $clicks as $click ) {
            fputcsv( $output, array(
                $click->id,
                $click->click_id,
                self::csv_safe_cell( $click->platform ),
                self::csv_safe_cell( $click->campaign ),
                self::csv_safe_cell( $click->utm_source ),
                self::csv_safe_cell( $click->utm_medium ),
                self::csv_safe_cell( $click->utm_campaign ),
                self::csv_safe_cell( $click->utm_term ),
                self::csv_safe_cell( $click->utm_content ),
                $click->qualified ? 'Yes' : 'No',
                $click->score,
                $click->date_created,
                $click->date_updated,
                $click->ip_hash,
                self::csv_safe_cell( $click->user_agent )
            ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the php://output stream opened above.
        fclose( $output );
        exit;
    }

    /**
     * Neutralise a visitor-supplied value so a spreadsheet does not run it as a formula.
     *
     * UTM values and user agents come from the visitor's URL and browser, and the
     * export is opened in Excel or Sheets. A leading =, +, -, @, tab or carriage
     * return is prefixed with a single quote (OWASP CSV injection guidance).
     *
     * @param mixed $value Cell value.
     * @return string Safe cell value.
     */
    private static function csv_safe_cell( $value ) {
        $value = (string) $value;
        if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
            $value = "'" . $value;
        }
        return $value;
    }

    /**
     * Generate Google Ads OCI (Offline Conversion Import) CSV export
     *
     * Exports click tracking data in Google Ads OCI format for importing
     * offline conversions. Only includes GCLID records.
     *
     * @param array $args Query arguments for filtering clicks
     * @return void
     */
    public static function export_google_ads_oci_csv( $args = array() ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        // Get settings
        $lead_value = get_option( 'cuft_lead_value', 100 );
        $currency = get_option( 'cuft_lead_currency', 'CAD' );

        // Build query to get only GCLID records
        $where_clauses = array( '1=1' );
        $where_values = array();

        // Filter for GCLID only (common patterns: Cj0K, EAIaIQ)
        $where_clauses[] = '(click_id LIKE %s OR click_id LIKE %s)';
        $where_values[]  = $wpdb->esc_like( 'Cj0K' ) . '%';
        $where_values[]  = $wpdb->esc_like( 'EAIaIQ' ) . '%';

        // Apply additional filters from $args
        if ( isset( $args['qualified'] ) && $args['qualified'] !== null ) {
            $where_clauses[] = 'qualified = %d';
            $where_values[] = (int) $args['qualified'];
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

        // Limit to 10000 records. $where_sql holds only the fixed fragments above with placeholders.
        $sql        = "SELECT * FROM %i WHERE {$where_sql} ORDER BY date_created DESC LIMIT 10000";
        $query_args = array_merge( array( $table_name ), $where_values );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is built from fixed WHERE fragments; the table and all values are bound via %i/%s/%d here. Plugin's own table; one-off admin export.
        $clicks = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );

        if ( empty( $clicks ) ) {
            return false;
        }

        // Clean any output buffers to prevent corruption
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        // Set headers for CSV download
        $filename = 'google-ads-oci-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://output is the HTTP response stream, not a file.
        $output = fopen( 'php://output', 'w' );

        // Add UTF-8 BOM for Excel compatibility
        fprintf( $output, "\xEF\xBB\xBF" );

        // Google Ads OCI format requires Parameters row first
        fputcsv( $output, array( 'Parameters', 'Time Zone=UTC' ) );

        // CSV headers
        fputcsv( $output, array(
            'Google Click ID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Conversion Currency'
        ) );

        // Process each click record
        foreach ( $clicks as $click ) {
            $events = self::get_events( $click->click_id );

            if ( ! empty( $events ) ) {
                // Export one row per event
                foreach ( $events as $event ) {
                    $conversion_name = self::get_conversion_name( $event['event'] );
                    $conversion_time = $event['timestamp']; // Already in ISO 8601 UTC
                    $conversion_value = self::calculate_conversion_value(
                        $event['event'],
                        $click->score,
                        $lead_value
                    );

                    fputcsv( $output, array(
                        $click->click_id,
                        $conversion_name,
                        $conversion_time,
                        $conversion_value,
                        $currency
                    ) );
                }
            } else {
                // No events - export single row for the ad click
                $conversion_time = self::convert_to_iso8601_utc( $click->date_created );

                fputcsv( $output, array(
                    $click->click_id,
                    'Ad Click',
                    $conversion_time,
                    0,
                    $currency
                ) );
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the php://output stream opened above.
        fclose( $output );
        exit;
    }

    /**
     * Get human-readable conversion name for event type
     *
     * @param string $event_type Event type from events JSON
     * @return string Conversion name for Google Ads
     */
    private static function get_conversion_name( $event_type ) {
        $conversion_names = array(
            'phone_click' => 'Phone Click',
            'email_click' => 'Email Click',
            'form_submit' => 'Form Submit',
            'generate_lead' => 'Lead Generated',
            'status_qualified' => 'Status Qualified', // Retained for display of legacy events
            'qualify_lead' => 'Qualified Lead',
            'disqualify_lead' => 'Disqualify Lead',
            'working_lead' => 'Working Lead',
            'close_convert_lead' => 'Close Convert Lead',
            'close_unconvert_lead' => 'Close Unconvert Lead',
            'score_updated' => 'Score Updated'
        );

        return isset( $conversion_names[ $event_type ] )
            ? $conversion_names[ $event_type ]
            : ucwords( str_replace( '_', ' ', $event_type ) );
    }

    /**
     * Calculate conversion value based on event type and score
     *
     * @param string $event_type Event type from events JSON
     * @param int $score Lead quality score (0-10)
     * @param float $lead_value Base lead value from settings
     * @return float Calculated conversion value
     */
    private static function calculate_conversion_value( $event_type, $score, $lead_value ) {
        // Only qualified leads get a value
        if ( $event_type === 'qualify_lead' ) {
            return round( ( $lead_value * $score ) / 10, 2 );
        }

        // All other events have 0 value
        return 0;
    }

    /**
     * Convert MySQL datetime to ISO 8601 UTC format
     *
     * @param string $mysql_datetime MySQL datetime string (e.g., "2025-01-15 14:30:00")
     * @return string ISO 8601 UTC format (e.g., "2025-01-15T14:30:00Z")
     */
    private static function convert_to_iso8601_utc( $mysql_datetime ) {
        if ( empty( $mysql_datetime ) ) {
            return gmdate( 'c' ); // Current UTC time
        }

        try {
            $dt = new DateTime( $mysql_datetime, new DateTimeZone( 'UTC' ) );
            return $dt->format( 'Y-m-d\TH:i:s\Z' );
        } catch ( Exception $e ) {
            return gmdate( 'c' );
        }
    }
    
    /**
     * Delete old records
     */
    public static function cleanup_old_records( $days = 365 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $days        = absint( $days );
        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it.
        $result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE date_created < %s', $table_name, $cutoff_date ) );
        
        if ( class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log( "Cleaned up {$result} old click tracking records", 'info' );
        }

        return $result;
    }

    /**
     * Add event to click tracking record
     *
     * @param string      $click_id   Unique click identifier.
     * @param string      $event_type Event type (phone_click, email_click, form_submit, generate_lead, etc.).
     * @param string|null $source     Optional origin tag (e.g. 'webhook'). When set, the event also
     *                                gets a `replayed_at` field (initially null) for client-side replay.
     * @return bool Success status
     */
    public static function add_event( $click_id, $event_type, $source = null ) {
        global $wpdb;

        if ( empty( $click_id ) || empty( $event_type ) ) {
            return false;
        }

        $table_name = $wpdb->prefix . self::$table_name;

        // Validate event type
        $valid_events = array(
            'phone_click',
            'email_click',
            'form_submit',
            'generate_lead',
            'qualify_lead',
            'disqualify_lead',
            'working_lead',
            'close_convert_lead',
            'close_unconvert_lead',
            'score_updated',
            'status_qualified', // Legacy: retained for backward compatibility with existing data
        );
        if ( ! in_array( $event_type, $valid_events ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Invalid event type: ' . $event_type, CUFT_Logger::ERROR );
            }
            return false;
        }

        try {
            // Get current events or initialize empty array
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
            $current_record = $wpdb->get_row( $wpdb->prepare( 'SELECT id, events FROM %i WHERE click_id = %s', $table_name, sanitize_text_field( $click_id ) ) );

            if ( ! $current_record ) {
                // Create new record if it doesn't exist
                $result = self::track_click( $click_id, array() );
                if ( ! $result ) {
                    return false;
                }

                // Get the newly created record
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
                $current_record = $wpdb->get_row( $wpdb->prepare( 'SELECT id, events FROM %i WHERE click_id = %s', $table_name, sanitize_text_field( $click_id ) ) );
            }

            // Parse existing events
            $events = array();
            if ( ! empty( $current_record->events ) ) {
                $events = json_decode( $current_record->events, true );
                if ( ! is_array( $events ) ) {
                    $events = array();
                }
            }

            // Create new event timestamp
            $new_timestamp = gmdate( 'c' ); // ISO 8601 UTC format

            // Check for duplicate event type (deduplication)
            $event_exists = false;
            foreach ( $events as &$existing_event ) {
                if ( $existing_event['event'] === $event_type ) {
                    // Update timestamp for duplicate event type
                    $existing_event['timestamp'] = $new_timestamp;
                    $event_exists = true;
                    break;
                }
            }
            unset( $existing_event ); // Break reference

            // If event type doesn't exist, append it
            if ( ! $event_exists ) {
                $new_event = array(
                    'event'     => $event_type,
                    'timestamp' => $new_timestamp,
                );
                if ( $source ) {
                    $new_event['source']      = $source;
                    $new_event['replayed_at'] = null;
                }
                $events[] = $new_event;
            }

            // FIFO cleanup: Limit to 100 events (remove oldest if exceeded)
            if ( count( $events ) > 100 ) {
                // Sort by timestamp to identify oldest
                usort( $events, function( $a, $b ) {
                    return strcmp( $a['timestamp'], $b['timestamp'] );
                } );

                // Keep only newest 100
                $events = array_slice( $events, -100 );
            }

            // Sort by timestamp (newest last)
            usort( $events, function( $a, $b ) {
                return strcmp( $a['timestamp'], $b['timestamp'] );
            } );

            // Update record with new events
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it.
            $result = $wpdb->update(
                $table_name,
                array(
                    'events' => wp_json_encode( $events ),
                    'date_updated' => current_time( 'mysql', true )
                ),
                array( 'id' => $current_record->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( $result !== false && class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( "Event added: {$event_type} for click_id: {$click_id}", CUFT_Logger::INFO );
            }

            return $result !== false;

        } catch ( Exception $e ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Failed to add event: ' . $e->getMessage(), CUFT_Logger::ERROR );
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        $events_json = $wpdb->get_var( $wpdb->prepare( 'SELECT events FROM %i WHERE click_id = %s', $table_name, sanitize_text_field( $click_id ) ) );

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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it.
        $result = $wpdb->update(
            $table_name,
            array( 'events' => wp_json_encode( $events ) ),
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

        // Whitelist the ORDER BY column and direction.
        $allowed_orderby = array( 'id', 'click_id', 'platform', 'qualified', 'score', 'date_created', 'date_updated' );
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'date_updated';
        $order           = ( 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        // Use JSON_CONTAINS for MySQL 5.7+ compatibility. ORDER BY direction is one of two literals.
        if ( 'ASC' === $order ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
            return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE events IS NOT NULL AND JSON_CONTAINS(events, %s) ORDER BY %i ASC LIMIT %d OFFSET %d', $table_name, wp_json_encode( array( 'event' => $event_type ) ), $orderby, $limit, $offset ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and click rows must be read fresh per request.
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE events IS NOT NULL AND JSON_CONTAINS(events, %s) ORDER BY %i DESC LIMIT %d OFFSET %d', $table_name, wp_json_encode( array( 'event' => $event_type ) ), $orderby, $limit, $offset ) );
    }

}
