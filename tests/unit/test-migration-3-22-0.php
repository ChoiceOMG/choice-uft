<?php
/**
 * Unit Tests for Migration 3.22.0
 *
 * Tests that the migration correctly adds ga_client_id and replayed_at
 * columns to the cuft_click_tracking table.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.22.0
 */

class Test_Migration_3_22_0 extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Reset migration state before each test
        delete_option( CUFT_Migration_3_22_0::OPTION_KEY );
    }

    public function test_migration_adds_ga_client_id_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        CUFT_Migration_3_22_0::up();

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );
        $this->assertContains( 'ga_client_id', $columns );
    }

    public function test_migration_adds_replayed_at_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        CUFT_Migration_3_22_0::up();

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );
        $this->assertContains( 'replayed_at', $columns );
    }

    public function test_needs_migration_returns_true_before_running() {
        delete_option( CUFT_Migration_3_22_0::OPTION_KEY );
        $this->assertTrue( CUFT_Migration_3_22_0::needs_migration() );
    }

    public function test_needs_migration_returns_false_after_running() {
        CUFT_Migration_3_22_0::up();
        $this->assertFalse( CUFT_Migration_3_22_0::needs_migration() );
    }

    public function test_migration_is_idempotent() {
        // Running up() twice should not error
        $first = CUFT_Migration_3_22_0::up();
        $second = CUFT_Migration_3_22_0::up();

        $this->assertTrue( $first );
        $this->assertTrue( $second );
    }
}
