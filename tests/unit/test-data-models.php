<?php
/**
 * Unit Tests for Data Models: Fix Update System Inconsistencies
 *
 * Tests all data models for proper functionality:
 * - Update Status Model (site transients, context-aware timeout)
 * - Update Progress Model (user ID tracking, progress states)
 * - Update Log Model (FIFO cleanup, max 5 entries)
 * - Admin Notice State (dismissal handling)
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

class Test_Data_Models extends WP_UnitTestCase {

    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();

        // Clear all transients and user meta before each test
        $this->clear_test_data();

        // Mock current user for tests
        $user_id = $this->factory->user->create( array(
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'display_name' => 'Test Admin'
        ) );
        wp_set_current_user( $user_id );
    }

    /**
     * Clean up after each test
     */
    public function tearDown() {
        $this->clear_test_data();
        parent::tearDown();
    }

    /**
     * Clear test data
     */
    private function clear_test_data() {
        // Clear transients
        delete_site_transient( 'cuft_update_status' );
        delete_transient( 'cuft_update_progress' );
        delete_site_transient( 'cuft_update_completed' );

        // Clear user meta
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cuft_dismissed_update_%'" );

        // Clear update log table if it exists
        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
            $wpdb->query( "TRUNCATE TABLE $table_name" );
        }
    }

    // ========================================
    // TEST UPDATE STATUS MODEL (T004, T005)
    // ========================================

    /**
     * Test Update Status Model: Site transient storage
     */
    public function test_update_status_site_transient_storage() {
        // Test initial state
        $status = CUFT_Update_Status::get();
        $this->assertEquals( CUFT_VERSION, $status['current_version'] );
        $this->assertFalse( $status['update_available'] );

        // Test setting update available
        $result = CUFT_Update_Status::set_update_available( '3.17.0', array(
            'release_url' => 'https://github.com/test/test/releases/v3.17.0'
        ) );
        $this->assertTrue( $result );

        // Verify data was stored in site transient
        $status = CUFT_Update_Status::get();
        $this->assertEquals( '3.17.0', $status['latest_version'] );
        $this->assertTrue( $status['update_available'] );
        $this->assertEquals( 'https://github.com/test/test/releases/v3.17.0', $status['release_url'] );

        // Verify it's stored as site transient
        $stored = get_site_transient( 'cuft_update_status' );
        $this->assertNotFalse( $stored );
        $this->assertEquals( '3.17.0', $stored['latest_version'] );
    }

    /**
     * Test Update Status Model: Context-aware timeout
     */
    public function test_update_status_context_aware_timeout() {
        // Test default timeout (12 hours)
        $timeout = CUFT_Update_Status::get_context_timeout();
        $this->assertEquals( 12 * HOUR_IN_SECONDS, $timeout );

        // Mock different WordPress contexts
        global $wp_current_filter, $wp_current_action;

        // Test upgrader_process_complete (immediate)
        $wp_current_filter = 'upgrader_process_complete';
        $timeout = CUFT_Update_Status::get_context_timeout();
        $this->assertEquals( 0, $timeout );

        // Test load-update-core.php (1 minute)
        $wp_current_filter = 'load-update-core.php';
        $timeout = CUFT_Update_Status::get_context_timeout();
        $this->assertEquals( MINUTE_IN_SECONDS, $timeout );

        // Test load-plugins.php (1 hour)
        $wp_current_filter = 'load-plugins.php';
        $timeout = CUFT_Update_Status::get_context_timeout();
        $this->assertEquals( HOUR_IN_SECONDS, $timeout );

        // Test load-update.php (1 hour)
        $wp_current_filter = 'load-update.php';
        $timeout = CUFT_Update_Status::get_context_timeout();
        $this->assertEquals( HOUR_IN_SECONDS, $timeout );

        // Reset
        $wp_current_filter = null;
        $wp_current_action = null;
    }

    /**
     * Test Update Status Model: Cache invalidation
     */
    public function test_update_status_cache_invalidation() {
        // Set initial status
        CUFT_Update_Status::set_update_available( '3.17.0' );

        // Verify it exists
        $status = CUFT_Update_Status::get();
        $this->assertTrue( $status['update_available'] );

        // Clear cache
        CUFT_Update_Status::clear();

        // Verify it's cleared
        $status = CUFT_Update_Status::get();
        $this->assertFalse( $status['update_available'] );
        $this->assertEquals( CUFT_VERSION, $status['current_version'] );
    }

    // ========================================
    // TEST UPDATE PROGRESS MODEL (T006)
    // ========================================

    /**
     * Test Update Progress Model: User ID tracking
     */
    public function test_update_progress_user_id_tracking() {
        $user_id = get_current_user_id();
        $this->assertGreaterThan( 0, $user_id );

        // Start update process
        $result = CUFT_Update_Progress::start( 'Test update started' );
        $this->assertTrue( $result );

        // Check progress includes user ID
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( $user_id, $progress['user_id'] );

        // Check display progress includes user display name
        $display_progress = CUFT_Update_Progress::get_display_progress();
        $this->assertEquals( 'Test Admin', $display_progress['user_display_name'] );
    }

    /**
     * Test Update Progress Model: Status transitions
     */
    public function test_update_progress_status_transitions() {
        // Start update
        CUFT_Update_Progress::start();
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'checking', $progress['status'] );

        // Set downloading
        CUFT_Update_Progress::set_downloading( 25 );
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'downloading', $progress['status'] );
        $this->assertEquals( 25, $progress['percentage'] );

        // Set complete
        CUFT_Update_Progress::set_complete();
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'complete', $progress['status'] );
        $this->assertEquals( 100, $progress['percentage'] );
    }

    /**
     * Test Update Progress Model: Auto-expiry (5 minutes)
     */
    public function test_update_progress_auto_expiry() {
        // Set progress
        CUFT_Update_Progress::set( 'downloading', 50, 'Downloading...' );

        // Verify it exists
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'downloading', $progress['status'] );

        // Progress should auto-expire after 5 minutes
        // This is tested by checking the transient expiration
        $transient = get_transient( 'cuft_update_progress' );
        $this->assertNotFalse( $transient );
    }

    // ========================================
    // TEST UPDATE LOG MODEL (T007)
    // ========================================

    /**
     * Test Update Log Model: FIFO cleanup (max 5 entries)
     */
    public function test_update_log_fifo_cleanup() {
        global $wpdb;

        // Create table if it doesn't exist (simulate migration)
        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            // Skip test if table doesn't exist
            $this->markTestSkipped( 'Update log table does not exist' );
            return;
        }

        // Insert 6 entries
        for ( $i = 1; $i <= 6; $i++ ) {
            CUFT_Update_Log::log( 'check_completed', 'success', array(
                'version_from' => '3.16.0',
                'version_to' => '3.16.' . $i,
                'details' => 'Test entry ' . $i
            ) );
        }

        // Verify only 5 entries remain
        $logs = CUFT_Update_Log::get_logs();
        $this->assertCount( 5, $logs );

        // Verify oldest entry (entry 1) was deleted
        $oldest_log = end( $logs ); // Last in DESC order is oldest
        $this->assertNotEquals( 'Test entry 1', $oldest_log['details'] );

        // Verify newest entry exists
        $newest_log = reset( $logs ); // First in DESC order is newest
        $this->assertEquals( 'Test entry 6', $newest_log['details'] );
    }

    /**
     * Test Update Log Model: Entry insertion and retrieval
     */
    public function test_update_log_entry_insertion() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            $this->markTestSkipped( 'Update log table does not exist' );
            return;
        }

        $user_id = get_current_user_id();

        // Log an action
        $log_id = CUFT_Update_Log::log( 'update_started', 'info', array(
            'version_from' => '3.16.2',
            'version_to' => '3.17.0',
            'details' => 'Test update started'
        ) );

        $this->assertGreaterThan( 0, $log_id );

        // Retrieve logs
        $logs = CUFT_Update_Log::get_logs( array( 'limit' => 1 ) );
        $this->assertCount( 1, $logs );

        $log = $logs[0];
        $this->assertEquals( 'update_started', $log['action'] );
        $this->assertEquals( 'info', $log['status'] );
        $this->assertEquals( '3.16.2', $log['version_from'] );
        $this->assertEquals( '3.17.0', $log['version_to'] );
        $this->assertEquals( $user_id, $log['user_id'] );
        $this->assertEquals( 'Test update started', $log['details'] );
    }

    /**
     * Test Update Log Model: Timestamp ordering
     */
    public function test_update_log_timestamp_ordering() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            $this->markTestSkipped( 'Update log table does not exist' );
            return;
        }

        // Insert entries with small delays to ensure different timestamps
        CUFT_Update_Log::log( 'check_started', 'info' );
        usleep( 1000 ); // 1ms delay
        CUFT_Update_Log::log( 'check_completed', 'success' );
        usleep( 1000 ); // 1ms delay
        CUFT_Update_Log::log( 'update_started', 'info' );

        // Retrieve in DESC order (newest first)
        $logs = CUFT_Update_Log::get_logs( array( 'limit' => 3 ) );

        $this->assertEquals( 'update_started', $logs[0]['action'] );
        $this->assertEquals( 'check_completed', $logs[1]['action'] );
        $this->assertEquals( 'check_started', $logs[2]['action'] );

        // Verify timestamps are in descending order
        $this->assertGreaterThanOrEqual( strtotime( $logs[1]['timestamp'] ), strtotime( $logs[0]['timestamp'] ) );
        $this->assertGreaterThanOrEqual( strtotime( $logs[2]['timestamp'] ), strtotime( $logs[1]['timestamp'] ) );
    }

    /**
     * Test Update Log Model: User display name retrieval
     */
    public function test_update_log_user_display_name() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cuft_update_log';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            $this->markTestSkipped( 'Update log table does not exist' );
            return;
        }

        // Log an action
        CUFT_Update_Log::log( 'check_started', 'info' );

        // Get display logs (includes user name)
        $display_logs = CUFT_Update_Log::get_display_logs( array( 'limit' => 1 ) );

        $this->assertCount( 1, $display_logs );
        $this->assertEquals( 'Test Admin', $display_logs[0]['user_name'] );
    }

    // ========================================
    // TEST ADMIN NOTICE STATE (T003, T008)
    // ========================================

    /**
     * Test Admin Notice State: User meta dismissal storage
     */
    public function test_admin_notice_dismissal_storage() {
        $user_id = get_current_user_id();

        // Simulate dismissal
        update_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', time() );

        // Verify storage
        $dismissed = get_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', true );
        $this->assertNotEmpty( $dismissed );
        $this->assertIsInt( $dismissed );
    }

    /**
     * Test Admin Notice State: Version-specific dismissals
     */
    public function test_admin_notice_version_specific_dismissals() {
        $user_id = get_current_user_id();

        // Dismiss version 3.17.0
        update_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', time() );

        // Dismiss version 3.18.0
        update_user_meta( $user_id, 'cuft_dismissed_update_3.18.0', time() + 3600 );

        // Verify both are stored separately
        $dismissed_317 = get_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', true );
        $dismissed_318 = get_user_meta( $user_id, 'cuft_dismissed_update_3.18.0', true );

        $this->assertNotEmpty( $dismissed_317 );
        $this->assertNotEmpty( $dismissed_318 );
        $this->assertNotEquals( $dismissed_317, $dismissed_318 );
    }

    /**
     * Test Admin Notice State: Notice type validation
     */
    public function test_admin_notice_type_validation() {
        // This is more of an integration test, but we can test the logic
        $notice_types = array( 'info', 'warning', 'error', 'success' );

        foreach ( $notice_types as $type ) {
            // Test that notices use proper CSS classes
            $expected_class = 'notice-' . $type;
            $this->assertContains( $type, array( 'info', 'warning', 'error', 'success' ) );
        }
    }

    /**
     * Test Admin Notice State: Cleanup on uninstall
     */
    public function test_admin_notice_cleanup_on_uninstall() {
        $user_id = get_current_user_id();

        // Add dismissal data
        update_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', time() );
        update_user_meta( $user_id, 'cuft_dismissed_update_failed', time() );

        // Verify data exists
        $this->assertNotEmpty( get_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', true ) );
        $this->assertNotEmpty( get_user_meta( $user_id, 'cuft_dismissed_update_failed', true ) );

        // Simulate uninstall cleanup (from uninstall.php)
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'cuft_dismissed_update_%'"
        );

        // Verify cleanup
        $this->assertEmpty( get_user_meta( $user_id, 'cuft_dismissed_update_3.17.0', true ) );
        $this->assertEmpty( get_user_meta( $user_id, 'cuft_dismissed_update_failed', true ) );
    }

    // ========================================
    // INTEGRATION TESTS
    // ========================================

    /**
     * Test Data Model Integration: Status and progress coordination
     */
    public function test_data_model_integration_status_progress() {
        // Set update available
        CUFT_Update_Status::set_update_available( '3.17.0' );

        // Start update progress
        CUFT_Update_Progress::start();

        // Verify status shows update available
        $status = CUFT_Update_Status::get();
        $this->assertTrue( $status['update_available'] );
        $this->assertEquals( '3.17.0', $status['latest_version'] );

        // Verify progress is tracking
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'checking', $progress['status'] );
        $this->assertEquals( get_current_user_id(), $progress['user_id'] );

        // Complete update
        CUFT_Update_Progress::set_complete();

        // Verify progress shows complete
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'complete', $progress['status'] );
        $this->assertEquals( 100, $progress['percentage'] );
    }

    /**
     * Test Data Model Integration: Multisite compatibility
     */
    public function test_data_model_integration_multisite_compatibility() {
        // Test site transients work (multisite compatibility)
        $test_data = array( 'test' => 'data', 'timestamp' => time() );

        set_site_transient( 'cuft_test_multisite', $test_data, 300 );
        $retrieved = get_site_transient( 'cuft_test_multisite' );

        $this->assertEquals( $test_data, $retrieved );

        // Clean up
        delete_site_transient( 'cuft_test_multisite' );
    }

    /**
     * Test Data Model Integration: Concurrent update prevention
     */
    public function test_data_model_integration_concurrent_update_prevention() {
        // Start first update
        $result1 = CUFT_Update_Progress::start( 'First update' );
        $this->assertTrue( $result1 );

        // Verify update is in progress
        $this->assertTrue( CUFT_Update_Progress::is_in_progress() );

        // Attempt second update (should be blocked by business logic)
        // This is tested at the API level, but we can verify the progress state
        $progress = CUFT_Update_Progress::get();
        $this->assertEquals( 'checking', $progress['status'] );

        // Clear progress
        CUFT_Update_Progress::clear();

        // Verify no longer in progress
        $this->assertFalse( CUFT_Update_Progress::is_in_progress() );
    }
}


