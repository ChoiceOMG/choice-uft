<?php
/**
 * Unit tests for CUFT_Click_Tracker schema self-heal and failure logging.
 *
 * Covers: missing table, missing column, healthy no-op, and that a write
 * failure is recorded both via CUFT_Logger (error level, regardless of the
 * debug logging setting) and in the small cuft_last_click_write_error
 * option surfaced on the Click Tracking admin page.
 *
 * The WP core test harness shadows every CREATE TABLE/DROP TABLE with a
 * per-test TEMPORARY table (see abstract-testcase.php
 * _create_temporary_tables()/_drop_temporary_tables()) so schema changes
 * roll back between tests. That shadow is invisible to SHOW TABLES, so a
 * test that actually needs to observe a real DROP/CREATE (this file) turns
 * the two filters off for its own duration and restores its own state by
 * hand instead of relying on the per-test rollback.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.28.2
 */

class Test_Click_Tracker_Self_Heal extends WP_UnitTestCase {

    private $table_name;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cuft_click_tracking';

        delete_option( 'cuft_debug_enabled' );
        delete_option( 'cuft_debug_logs' );
        CUFT_Click_Tracker::clear_last_write_error();
        $this->clear_schema_transients();

        // Work against the real table, not a per-test temporary shadow of
        // it, since these tests need SHOW TABLES/SHOW COLUMNS to reflect
        // what a real site would see.
        remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
        remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

        // Start every test from a known-healthy table.
        CUFT_Click_Tracker::create_table();
    }

    public function tearDown(): void {
        // Leave the real table fully healthy (base schema plus every
        // migration-added column) and empty for whatever runs next in the
        // suite; this class works against the real table, not a per-test
        // temporary shadow of it, so nothing else restores it.
        global $wpdb;
        $this->clear_schema_transients();
        CUFT_Click_Tracker::self_heal_schema( true );
        if ( $this->table_name ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test cleanup: this class works against the real table (temp-table filters removed above), so restore it to empty by hand.
            $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $this->table_name ) );
        }
        $this->clear_schema_transients();
        parent::tearDown();
    }

    private function clear_schema_transients() {
        delete_option( CUFT_Click_Tracker::OPTION_SCHEMA_VERIFIED_VERSION );
        if ( defined( 'CUFT_VERSION' ) ) {
            delete_transient( CUFT_Click_Tracker::TRANSIENT_SCHEMA_CHECK_PREFIX . md5( CUFT_VERSION ) );
        }
    }

    /**
     * Missing table: self_heal_schema() recreates it.
     */
    public function test_self_heal_recreates_missing_table() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test setup: drop the plugin's own table to simulate the missing-table bug.
        $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $this->table_name ) );

        $this->assertNotEquals(
            $this->table_name,
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Verifying the drop above landed.
            $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name ) ) )
        );

        $result = CUFT_Click_Tracker::self_heal_schema( true );

        $this->assertTrue( $result );
        $this->assertEquals(
            $this->table_name,
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Verifying self-heal recreated the table.
            $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name ) ) )
        );

        // The recreated table accepts a real write.
        $insert = CUFT_Click_Tracker::track_click( 'SELFHEAL-TABLE-TEST' );
        $this->assertNotFalse( $insert );
    }

    /**
     * Missing column (events): self_heal_schema() adds it back.
     */
    public function test_self_heal_adds_missing_events_column() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test setup: drop a column to simulate a partially-migrated table.
        $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN events', $this->table_name ) );

        $columns_before = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $this->table_name ) );
        $this->assertNotContains( 'events', $columns_before );

        $result = CUFT_Click_Tracker::self_heal_schema( true );

        $this->assertTrue( $result );
        $columns_after = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $this->table_name ) );
        $this->assertContains( 'events', $columns_after );
    }

    /**
     * Missing column (ga_client_id): self_heal_schema() adds it back.
     */
    public function test_self_heal_adds_missing_ga_client_id_column() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test setup: simulate a table that predates the 3.22.0 migration.
        $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN ga_client_id', $this->table_name ) );

        $columns_before = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $this->table_name ) );
        $this->assertNotContains( 'ga_client_id', $columns_before );

        $result = CUFT_Click_Tracker::self_heal_schema( true );

        $this->assertTrue( $result );
        $columns_after = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $this->table_name ) );
        $this->assertContains( 'ga_client_id', $columns_after );
    }

    /**
     * Healthy table: self_heal_schema() is a no-op and reports healthy on a
     * cached, repeat call too.
     */
    public function test_self_heal_is_noop_on_healthy_table() {
        $result = CUFT_Click_Tracker::self_heal_schema( true );
        $this->assertTrue( $result );

        $result_cached = CUFT_Click_Tracker::self_heal_schema();
        $this->assertTrue( $result_cached );
    }

    /**
     * Once verified for a version, a later call is cached and does not
     * re-run the schema check (no DB traffic, cheap on admin_init).
     */
    public function test_self_heal_caches_healthy_result_for_the_version() {
        global $wpdb;

        CUFT_Click_Tracker::self_heal_schema( true );

        $queries_before = $wpdb->num_queries;
        CUFT_Click_Tracker::self_heal_schema();
        $queries_after = $wpdb->num_queries;

        $this->assertSame( $queries_before, $queries_after, 'A cached self-heal check should not issue any queries.' );
    }

    /**
     * A track_click() failure (simulated by dropping the table) is recorded
     * via CUFT_Logger at error level even though debug logging is off, and
     * in the small cuft_last_click_write_error option with no PII.
     */
    public function test_track_click_failure_is_logged_even_with_debug_off() {
        global $wpdb;

        update_option( 'cuft_debug_enabled', false );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test setup: drop the table so the insert inside track_click() fails.
        $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $this->table_name ) );

        $result = CUFT_Click_Tracker::track_click( 'SELFHEAL-FAIL-TEST', array( 'platform' => 'google' ) );

        $this->assertFalse( $result );

        // Recorded in CUFT_Logger despite debug logging being off.
        $logs = CUFT_Logger::get_logs();
        $found_error = false;
        foreach ( $logs as $entry ) {
            if ( CUFT_Logger::ERROR === $entry['level'] && false !== strpos( $entry['message'], 'Click tracking write failed' ) ) {
                $found_error = true;
                break;
            }
        }
        $this->assertTrue( $found_error, 'An error-level log entry should be recorded even with debug logging off.' );

        // Recorded in the small last-write-error option, with no PII.
        $last_error = CUFT_Click_Tracker::get_last_write_error();
        $this->assertNotNull( $last_error );
        $this->assertArrayHasKey( 'message', $last_error );
        $this->assertArrayHasKey( 'timestamp', $last_error );
        $this->assertArrayNotHasKey( 'ip_hash', $last_error );
        $this->assertArrayNotHasKey( 'user_agent', $last_error );
        // Only a short prefix of the click id is kept, never the full value.
        $this->assertLessThan( strlen( 'SELFHEAL-FAIL-TEST' ), strlen( $last_error['click_id'] ) );
    }

    /**
     * CUFT_Logger::log() normalises the historical call shape used across
     * this codebase, CUFT_Logger::log( 'error', 'message' ) (level and
     * message swapped), so those entries are stored under the level the
     * caller meant.
     */
    public function test_logger_normalises_swapped_level_and_message() {
        update_option( 'cuft_debug_enabled', true );
        CUFT_Logger::clear_logs();

        CUFT_Logger::log( 'error', 'Something failed swapped' );

        $logs = CUFT_Logger::get_logs();
        $this->assertNotEmpty( $logs );
        $this->assertSame( CUFT_Logger::ERROR, $logs[0]['level'] );
        $this->assertSame( 'Something failed swapped', $logs[0]['message'] );
    }

    /**
     * A non-error log entry is still gated on debug logging being on.
     */
    public function test_logger_info_level_still_requires_debug_enabled() {
        update_option( 'cuft_debug_enabled', false );
        CUFT_Logger::clear_logs();

        CUFT_Logger::log( 'Just informational', CUFT_Logger::INFO );

        $logs = CUFT_Logger::get_logs();
        $this->assertEmpty( $logs );
    }
}
