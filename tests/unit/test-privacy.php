<?php
/**
 * Privacy controls (3.30.0): ignored phone numbers, click retention and
 * keyed IP hashes.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

class Test_CUFT_Privacy extends WP_UnitTestCase {

    private $table;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( "DELETE FROM {$this->table}" );
        delete_option( CUFT_Privacy::OPTION_IGNORE_PHONES );
        delete_option( CUFT_Privacy::OPTION_RETENTION_DAYS );
        delete_option( CUFT_Privacy::OPTION_LEGACY_IP_HASH_BEFORE );
        wp_unschedule_hook( CUFT_Privacy::RETENTION_HOOK );
    }

    public function tearDown(): void {
        remove_all_filters( 'cuft_ignore_phone_numbers' );
        remove_all_filters( 'cuft_click_retention_days' );
        unset( $_SERVER['REMOTE_ADDR'] );
        wp_unschedule_hook( CUFT_Privacy::RETENTION_HOOK );
        parent::tearDown();
    }

    /**
     * Insert a click row with a chosen age, measured on the database clock.
     */
    private function insert_click( $click_id, $days_old, $ip_hash = null ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$this->table} (click_id, events, ip_hash, date_created) VALUES (%s, '[]', %s, NOW() - INTERVAL %d DAY)",
            $click_id,
            $ip_hash,
            $days_old
        ) );
    }

    private function count_rows() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }

    private function ip_hash_of( $click_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_var( $wpdb->prepare( "SELECT ip_hash FROM {$this->table} WHERE click_id = %s", $click_id ) );
    }

    // Phone normalization

    public function test_formats_of_one_number_share_a_match_key() {
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( '+1 (780) 555-0173' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( '780.555.0173' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( 'tel:+17805550173' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( 'TEL:%2B1-780-555-0173' ) );
    }

    public function test_extensions_are_not_part_of_the_match_key() {
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( 'tel:+1-780-555-0173;ext=2' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( 'tel:7805550173,22' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( '780-555-0173 ext 4' ) );
        $this->assertSame( '7805550173', CUFT_Privacy::normalize_phone( '780-555-0173x4' ) );
    }

    public function test_too_few_digits_gives_no_match_key() {
        $this->assertSame( '', CUFT_Privacy::normalize_phone( '911' ) );
        $this->assertSame( '', CUFT_Privacy::normalize_phone( 'tel:' ) );
        $this->assertSame( '', CUFT_Privacy::normalize_phone( array( '7805550173' ) ) );
        $this->assertSame( '5550173', CUFT_Privacy::normalize_phone( '555-0173' ) );
    }

    public function test_phone_list_is_split_normalized_and_deduplicated() {
        $rejected = array();
        $keys     = CUFT_Privacy::parse_phone_list( "+1 (780) 555-0173\n\n780.555.0173, 403-555-0199\r\n12", $rejected );
        $this->assertSame( array( '7805550173', '4035550199' ), $keys );
        $this->assertSame( array( '12' ), $rejected );
    }

    public function test_ignore_list_combines_setting_and_filter() {
        update_option( CUFT_Privacy::OPTION_IGNORE_PHONES, array( '7805550173' ) );
        add_filter( 'cuft_ignore_phone_numbers', function ( $numbers ) {
            $numbers[] = '+1 403 555 0199';
            return $numbers;
        } );
        $this->assertSame( array( '7805550173', '4035550199' ), CUFT_Privacy::get_ignored_phones() );
        $this->assertTrue( CUFT_Privacy::is_phone_ignored( 'tel:+14035550199' ) );
        $this->assertTrue( CUFT_Privacy::is_phone_ignored( 'tel:780-555-0173' ) );
        $this->assertFalse( CUFT_Privacy::is_phone_ignored( 'tel:780-555-0100' ) );
    }

    public function test_ignore_list_survives_a_corrupt_option_or_filter() {
        update_option( CUFT_Privacy::OPTION_IGNORE_PHONES, 'not-an-array' );
        $this->assertSame( array(), CUFT_Privacy::get_ignored_phones() );
        add_filter( 'cuft_ignore_phone_numbers', '__return_false' );
        $this->assertSame( array(), CUFT_Privacy::get_ignored_phones() );
    }

    // Front-end delivery of the ignore list

    public function test_link_script_carries_the_ignore_list() {
        update_option( CUFT_Privacy::OPTION_IGNORE_PHONES, array( '7805550173' ) );
        $tracking = new CUFT_Link_Tracking();
        $tracking->enqueue_scripts();
        $before = implode( "\n", (array) wp_scripts()->get_data( 'cuft-link-tracking', 'before' ) );
        $this->assertStringContainsString( 'window.cuftIgnorePhones = ["7805550173"];', $before );
    }

    public function test_cuft_config_carries_the_ignore_list() {
        update_option( CUFT_Privacy::OPTION_IGNORE_PHONES, array( '7805550173' ) );
        CUFT_Plugin::get_instance()->enqueue_cuft_config();
        $after = implode( "\n", (array) wp_scripts()->get_data( 'cuft-config', 'after' ) );
        $this->assertStringContainsString( '"ignorePhones":["7805550173"]', $after );
    }

    // Retention

    public function test_retention_days_are_clamped() {
        $this->assertSame( 0, CUFT_Privacy::sanitize_retention_days( '0' ) );
        $this->assertSame( 30, CUFT_Privacy::sanitize_retention_days( '3' ) );
        $this->assertSame( 365, CUFT_Privacy::sanitize_retention_days( 365 ) );
        $this->assertSame( 3650, CUFT_Privacy::sanitize_retention_days( '99999' ) );
        $this->assertSame( 365, CUFT_Privacy::sanitize_retention_days( 'abc' ) );
        $this->assertSame( 365, CUFT_Privacy::sanitize_retention_days( '' ) );
    }

    public function test_default_retention_is_365_days() {
        $this->assertSame( 365, CUFT_Privacy::get_retention_days() );
    }

    public function test_retention_deletes_only_rows_past_the_limit() {
        $this->insert_click( 'old_a', 400 );
        $this->insert_click( 'old_b', 366 );
        $this->insert_click( 'recent', 364 );
        $this->insert_click( 'today', 0 );

        $result = CUFT_Privacy::run_retention();

        $this->assertSame( 2, $result['deleted'] );
        $this->assertTrue( $result['complete'] );
        $this->assertSame( 2, $this->count_rows() );
        $this->assertNotNull( CUFT_Click_Tracker::get_click_by_id( 'recent' ) );
        $this->assertNull( CUFT_Click_Tracker::get_click_by_id( 'old_a' ) );
    }

    public function test_retention_setting_and_filter_change_the_limit() {
        $this->insert_click( 'sixty', 60 );
        $this->insert_click( 'ten', 10 );

        update_option( CUFT_Privacy::OPTION_RETENTION_DAYS, 90 );
        CUFT_Privacy::run_retention();
        $this->assertSame( 2, $this->count_rows() );

        add_filter( 'cuft_click_retention_days', function () {
            return 30;
        } );
        CUFT_Privacy::run_retention();
        $this->assertSame( 1, $this->count_rows() );
    }

    public function test_zero_retention_keeps_everything() {
        update_option( CUFT_Privacy::OPTION_RETENTION_DAYS, 0 );
        $this->insert_click( 'ancient', 2000 );
        $result = CUFT_Privacy::run_retention();
        $this->assertSame( 0, $result['deleted'] );
        $this->assertSame( 1, $this->count_rows() );
    }

    public function test_retention_deletes_in_batches_until_done() {
        global $wpdb;
        $values = array();
        for ( $i = 0; $i < 2500; $i++ ) {
            $values[] = $wpdb->prepare( "(%s, '[]', NOW() - INTERVAL 500 DAY)", 'bulk_' . $i );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "INSERT INTO {$this->table} (click_id, events, date_created) VALUES " . implode( ',', $values ) );
        $this->insert_click( 'keep', 1 );

        $result = CUFT_Privacy::run_retention();

        $this->assertSame( 2500, $result['deleted'] );
        $this->assertTrue( $result['complete'] );
        $this->assertSame( 1, $this->count_rows() );
        $this->assertFalse( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK, array( true ) ) );
    }

    public function test_cleanup_old_records_deletes_in_batches() {
        global $wpdb;
        $values = array();
        for ( $i = 0; $i < 1500; $i++ ) {
            $values[] = $wpdb->prepare( "(%s, '[]', NOW() - INTERVAL 500 DAY)", 'direct_' . $i );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "INSERT INTO {$this->table} (click_id, events, date_created) VALUES " . implode( ',', $values ) );
        $this->assertSame( 1500, CUFT_Click_Tracker::cleanup_old_records( 365 ) );
        $this->assertSame( 0, $this->count_rows() );
    }

    public function test_daily_event_is_scheduled_and_unscheduled() {
        $this->assertFalse( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK ) );
        CUFT_Privacy::maybe_schedule();
        $this->assertIsInt( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK ) );
        $this->assertSame( 'daily', wp_get_schedule( CUFT_Privacy::RETENTION_HOOK ) );

        wp_schedule_single_event( time() + 300, CUFT_Privacy::RETENTION_HOOK, array( true ) );
        CUFT_Privacy::unschedule();
        $this->assertFalse( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK ) );
        $this->assertFalse( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK, array( true ) ) );
    }

    public function test_deactivation_clears_the_retention_job() {
        CUFT_Privacy::maybe_schedule();
        CUFT_Plugin::get_instance()->deactivate();
        $this->assertFalse( wp_next_scheduled( CUFT_Privacy::RETENTION_HOOK ) );
    }

    public function test_cron_callback_is_registered() {
        $this->assertNotFalse( has_action( CUFT_Privacy::RETENTION_HOOK, array( 'CUFT_Privacy', 'run_retention' ) ) );
    }

    /**
     * cuft_enabled switches tracking off, for example from a consent check,
     * and is false on every wp-cron.php request when it reads a visitor
     * cookie. Retention is cleanup, not tracking, so it must still run.
     */
    public function test_retention_runs_when_tracking_is_disabled() {
        $this->insert_click( 'expired', 400 );
        remove_all_actions( CUFT_Privacy::RETENTION_HOOK );

        add_filter( 'cuft_enabled', '__return_false' );
        CUFT_Plugin::get_instance()->init();
        do_action( CUFT_Privacy::RETENTION_HOOK );

        $this->assertSame( 0, $this->count_rows() );
    }

    // Keyed IP hashes

    public function test_ip_hash_is_keyed() {
        $ip   = '203.0.113.7';
        $hash = CUFT_Click_Tracker::hash_ip( $ip );
        $this->assertSame( 64, strlen( $hash ) );
        $this->assertNotSame( hash( 'sha256', $ip ), $hash );
        $this->assertSame( $hash, CUFT_Click_Tracker::hash_ip( $ip ) );
        $this->assertSame( '', CUFT_Click_Tracker::hash_ip( '' ) );
    }

    /**
     * The key must come from the wp-config.php auth secrets. A custom
     * wp_salt() scheme falls back to the secret_key option in the database
     * on any wp-config.php that does not define SECRET_KEY (the sample file
     * does not), and a database copy alone would then reverse every hash.
     */
    public function test_ip_hash_key_derives_from_the_auth_secrets() {
        $pin_auth = function ( $salt, $scheme ) {
            return 'auth' === $scheme ? 'auth-secret-from-wp-config' : $salt;
        };
        add_filter( 'salt', $pin_auth, 10, 2 );
        $hash = CUFT_Click_Tracker::hash_ip( '203.0.113.7' );
        remove_filter( 'salt', $pin_auth, 10 );

        $key = hash_hmac( 'sha256', 'cuft_ip_hash', 'auth-secret-from-wp-config' );
        $this->assertSame( hash_hmac( 'sha256', '203.0.113.7', $key ), $hash );
    }

    public function test_new_clicks_store_the_keyed_hash() {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        CUFT_Click_Tracker::track_click( 'keyed_click', array( 'platform' => 'google' ) );
        $this->assertSame( CUFT_Click_Tracker::hash_ip( '203.0.113.7' ), $this->ip_hash_of( 'keyed_click' ) );
        $this->assertNotSame( hash( 'sha256', '203.0.113.7' ), $this->ip_hash_of( 'keyed_click' ) );
    }

    public function test_upgrade_marks_rows_with_unkeyed_hashes() {
        update_option( 'cuft_db_version', '3.29.0' );
        CUFT_DB_Migration::run_migrations();
        $this->assertNotEmpty( get_option( CUFT_Privacy::OPTION_LEGACY_IP_HASH_BEFORE ) );
        $this->assertSame( CUFT_DB_Migration::CURRENT_VERSION, get_option( 'cuft_db_version' ) );
    }

    public function test_retention_clears_unkeyed_hashes_written_before_the_upgrade() {
        global $wpdb;
        $this->insert_click( 'before_a', 20, hash( 'sha256', '203.0.113.7' ) );
        $this->insert_click( 'before_b', 3, hash( 'sha256', '198.51.100.9' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        add_option( CUFT_Privacy::OPTION_LEGACY_IP_HASH_BEFORE, $wpdb->get_var( 'SELECT NOW() - INTERVAL 1 DAY' ), '', false );
        $this->insert_click( 'after', 0, CUFT_Click_Tracker::hash_ip( '203.0.113.7' ) );

        $result = CUFT_Privacy::run_retention();

        $this->assertSame( 2, $result['ip_hashes_cleared'] );
        $this->assertNull( $this->ip_hash_of( 'before_a' ) );
        $this->assertNull( $this->ip_hash_of( 'before_b' ) );
        $this->assertSame( CUFT_Click_Tracker::hash_ip( '203.0.113.7' ), $this->ip_hash_of( 'after' ) );
        $this->assertSame( 3, $this->count_rows() );
        $this->assertFalse( get_option( CUFT_Privacy::OPTION_LEGACY_IP_HASH_BEFORE ) );
    }

    public function test_unkeyed_hashes_are_cleared_even_when_retention_is_off() {
        global $wpdb;
        update_option( CUFT_Privacy::OPTION_RETENTION_DAYS, 0 );
        $this->insert_click( 'legacy', 900, hash( 'sha256', '203.0.113.7' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        add_option( CUFT_Privacy::OPTION_LEGACY_IP_HASH_BEFORE, $wpdb->get_var( 'SELECT NOW()' ), '', false );

        CUFT_Privacy::run_retention();

        $this->assertSame( 1, $this->count_rows() );
        $this->assertNull( $this->ip_hash_of( 'legacy' ) );
    }
}
