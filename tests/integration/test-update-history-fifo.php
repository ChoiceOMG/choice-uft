<?php
/**
 * Integration Test: Update History FIFO
 *
 * Tests update history FIFO (First-In-First-Out) cleanup
 * Implements T029 from specs/007-fix-update-system/tasks.md
 * Validates Scenario 6 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Update_History_FIFO extends WP_UnitTestCase {

    /**
     * Admin user ID for testing
     * @var int
     */
    private $admin_id;

    /**
     * Update log model instance
     * @var CUFT_Update_Log
     */
    private $update_log;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create admin user
        $this->admin_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        wp_set_current_user($this->admin_id);

        // Initialize update log
        if (!class_exists('CUFT_Update_Log')) {
            require_once CUFT_PLUGIN_DIR . 'includes/models/class-cuft-update-log.php';
        }
        $this->update_log = new CUFT_Update_Log();

        // Clear any existing logs
        $this->clear_all_logs();
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();
        $this->clear_all_logs();
        wp_set_current_user(0);
    }

    /**
     * Helper: Clear all update logs
     */
    private function clear_all_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Check if table exists before truncating
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if ($table_exists) {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    /**
     * Helper: Create a log entry
     */
    private function create_log_entry($action = 'check', $status = 'success', $version_from = '3.16.0', $version_to = '3.16.1') {
        return $this->update_log->add_entry(array(
            'action' => $action,
            'status' => $status,
            'version_from' => $version_from,
            'version_to' => $version_to,
            'user_id' => $this->admin_id,
            'message' => "Test {$action} from {$version_from} to {$version_to}",
            'duration' => rand(1, 60)
        ));
    }

    /**
     * Test generating 6 update log entries
     *
     * Validates that we can create 6 entries
     */
    public function test_generate_six_log_entries() {
        // Generate 6 entries
        $entries = array();
        for ($i = 1; $i <= 6; $i++) {
            $entry_id = $this->create_log_entry(
                'update',
                'success',
                "3.16.{$i}",
                "3.16." . ($i + 1)
            );
            $entries[] = $entry_id;

            // Small delay to ensure different timestamps
            usleep(100000); // 0.1 second
        }

        // Count entries
        $all_entries = $this->update_log->get_entries(10);
        $this->assertGreaterThanOrEqual(6, count($all_entries), 'Should have created at least 6 entries');
    }

    /**
     * Test only 5 entries retained after FIFO cleanup
     *
     * Validates maximum 5 entries enforcement
     */
    public function test_only_five_entries_retained() {
        // Generate 6 entries
        $entries = array();
        for ($i = 1; $i <= 6; $i++) {
            $entry_id = $this->create_log_entry(
                'update',
                'success',
                "3.16.{$i}",
                "3.16." . ($i + 1)
            );
            $entries[] = $entry_id;

            // Small delay to ensure different timestamps
            usleep(100000); // 0.1 second
        }

        // Get all entries
        $all_entries = $this->update_log->get_entries(10);

        // Should only have 5 entries due to FIFO cleanup
        $this->assertCount(5, $all_entries, 'Should only retain 5 entries after FIFO cleanup');
    }

    /**
     * Test oldest entry is deleted in FIFO manner
     *
     * Validates First-In-First-Out behavior
     */
    public function test_oldest_entry_deleted_fifo() {
        // Create 5 entries with known data
        $first_five = array();
        for ($i = 1; $i <= 5; $i++) {
            $entry_id = $this->create_log_entry(
                'update',
                'success',
                "3.16.{$i}",
                "3.16." . ($i + 1)
            );
            $first_five[$i] = $entry_id;

            // Delay to ensure different timestamps
            sleep(1);
        }

        // Get current entries
        $entries_before = $this->update_log->get_entries(10);
        $this->assertCount(5, $entries_before, 'Should have 5 entries before adding 6th');

        // Add 6th entry - should trigger FIFO cleanup
        $sixth_entry = $this->create_log_entry(
            'update',
            'success',
            '3.16.6',
            '3.16.7'
        );

        // Get entries after adding 6th
        $entries_after = $this->update_log->get_entries(10);
        $this->assertCount(5, $entries_after, 'Should still have only 5 entries');

        // Check that first entry (oldest) was deleted
        $remaining_ids = array_column($entries_after, 'id');
        $this->assertNotContains($first_five[1], $remaining_ids, 'First (oldest) entry should be deleted');

        // Check that entries 2-5 and the new 6th entry remain
        for ($i = 2; $i <= 5; $i++) {
            $this->assertContains($first_five[$i], $remaining_ids, "Entry {$i} should still exist");
        }
        $this->assertContains($sixth_entry, $remaining_ids, 'New 6th entry should exist');
    }

    /**
     * Test FIFO order validation
     *
     * Validates entries are in correct chronological order
     */
    public function test_fifo_order_validation() {
        // Create entries with specific timestamps
        $entries = array();
        for ($i = 1; $i <= 7; $i++) {
            $entry_id = $this->create_log_entry(
                'update',
                'success',
                "3.16.{$i}",
                "3.16." . ($i + 1)
            );
            $entries[$i] = array(
                'id' => $entry_id,
                'version' => "3.16." . ($i + 1),
                'timestamp' => time() + $i
            );

            sleep(1); // Ensure different timestamps
        }

        // Get final 5 entries
        $final_entries = $this->update_log->get_entries(10);
        $this->assertCount(5, $final_entries, 'Should have exactly 5 entries');

        // Verify they are the 5 most recent (entries 3-7)
        $versions = array_column($final_entries, 'version_to');
        $expected_versions = array('3.16.7', '3.16.6', '3.16.5', '3.16.4', '3.16.3');

        // Note: Results are typically returned in DESC order (newest first)
        $this->assertEquals($expected_versions, $versions, 'Should retain the 5 most recent entries in DESC order');
    }

    /**
     * Test cleanup happens automatically on insert
     *
     * Validates cleanup is triggered automatically
     */
    public function test_automatic_cleanup_on_insert() {
        // Fill to maximum (5 entries)
        for ($i = 1; $i <= 5; $i++) {
            $this->create_log_entry('check', 'success', "3.16.{$i}", "3.16.{$i}");
        }

        $count_before = count($this->update_log->get_entries(10));
        $this->assertEquals(5, $count_before, 'Should have 5 entries');

        // Add one more - should trigger cleanup automatically
        $this->create_log_entry('update', 'success', '3.16.6', '3.16.7');

        $count_after = count($this->update_log->get_entries(10));
        $this->assertEquals(5, $count_after, 'Should still have 5 entries after automatic cleanup');
    }

    /**
     * Test different action types in FIFO
     *
     * Validates FIFO works with mixed action types
     */
    public function test_mixed_action_types_fifo() {
        // Create entries with different actions
        $actions = array('check', 'download', 'install', 'rollback', 'check', 'update', 'check');

        foreach ($actions as $i => $action) {
            $this->create_log_entry(
                $action,
                ($action === 'rollback') ? 'failure' : 'success',
                "3.16.{$i}",
                "3.16." . ($i + 1)
            );
            sleep(1);
        }

        // Should have only 5 most recent
        $entries = $this->update_log->get_entries(10);
        $this->assertCount(5, $entries, 'Should have 5 entries regardless of action types');

        // Check variety of actions retained
        $retained_actions = array_column($entries, 'action');
        $unique_actions = array_unique($retained_actions);
        $this->assertGreaterThan(1, count($unique_actions), 'Should retain different action types');
    }

    /**
     * Test user display names in history
     *
     * Validates user information is retained
     */
    public function test_user_display_names_in_history() {
        // Create entries from different users
        $user2_id = $this->factory->user->create(array(
            'role' => 'administrator',
            'display_name' => 'Test Admin 2'
        ));

        // Create entries alternating users
        for ($i = 1; $i <= 6; $i++) {
            wp_set_current_user(($i % 2 === 0) ? $user2_id : $this->admin_id);
            $this->create_log_entry('update', 'success', "3.16.{$i}", "3.16." . ($i + 1));
        }

        // Get entries with user info
        $entries = $this->update_log->get_entries_with_users(5);

        // Check user info is included
        foreach ($entries as $entry) {
            $this->assertArrayHasKey('user_display_name', $entry, 'Entry should have user display name');
            $this->assertNotEmpty($entry['user_display_name'], 'User display name should not be empty');
        }
    }

    /**
     * Test database index performance
     *
     * Validates timestamp index for efficient cleanup
     */
    public function test_database_index_performance() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_update_log';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            // Check for timestamp index
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
            $has_timestamp_index = false;

            foreach ($indexes as $index) {
                if ($index->Column_name === 'timestamp') {
                    $has_timestamp_index = true;
                    break;
                }
            }

            $this->assertTrue($has_timestamp_index, 'Should have index on timestamp column for FIFO performance');
        } else {
            // If table doesn't exist, mark test as incomplete
            $this->markTestIncomplete('Update log table does not exist');
        }
    }

    /**
     * Test Scenario 6: Update History
     *
     * From quickstart.md Scenario 6
     */
    public function test_quickstart_scenario_6() {
        // Given: Update history with 5 entries
        for ($i = 1; $i <= 5; $i++) {
            $this->create_log_entry(
                'update',
                'success',
                "3.15.{$i}",
                "3.15." . ($i + 1)
            );
            sleep(1); // Ensure different timestamps
        }

        $entries_before = $this->update_log->get_entries(10);
        $this->assertCount(5, $entries_before, 'Should have 5 entries initially');

        // When: 6th update completes
        $sixth_id = $this->create_log_entry(
            'update',
            'success',
            '3.15.6',
            '3.16.0'
        );

        // Then: Only 5 most recent entries retained (oldest deleted)
        $entries_after = $this->update_log->get_entries(10);
        $this->assertCount(5, $entries_after, 'Should have exactly 5 entries after 6th update');

        // Verify oldest was removed
        $remaining_versions = array_column($entries_after, 'version_from');
        $this->assertNotContains('3.15.1', $remaining_versions, 'Oldest entry (3.15.1) should be deleted');

        // Verify newest is present
        $this->assertContains('3.15.6', $remaining_versions, 'Newest entry (3.15.6) should be present');

        // Verify FIFO order maintained
        $first_entry = $entries_after[0]; // Should be newest (DESC order)
        $this->assertEquals('3.16.0', $first_entry['version_to'], 'Newest entry should be first in DESC order');
    }

    /**
     * Test pagination with FIFO limit
     *
     * Validates pagination respects FIFO limit
     */
    public function test_pagination_with_fifo_limit() {
        // Create exactly 5 entries
        for ($i = 1; $i <= 5; $i++) {
            $this->create_log_entry('update', 'success', "3.16.{$i}", "3.16." . ($i + 1));
        }

        // Test pagination
        $page1 = $this->update_log->get_entries(3, 0); // First 3
        $page2 = $this->update_log->get_entries(3, 3); // Next 2 (only 5 total)

        $this->assertCount(3, $page1, 'First page should have 3 entries');
        $this->assertCount(2, $page2, 'Second page should have 2 entries');

        // Verify no duplicates
        $page1_ids = array_column($page1, 'id');
        $page2_ids = array_column($page2, 'id');
        $intersection = array_intersect($page1_ids, $page2_ids);
        $this->assertEmpty($intersection, 'Should have no duplicate entries between pages');
    }
}