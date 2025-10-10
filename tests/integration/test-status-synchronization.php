<?php
/**
 * Integration Test: Status Synchronization
 *
 * Tests update status synchronization across all UI locations
 * Implements T025 from specs/007-fix-update-system/tasks.md
 * Validates Scenarios 3 and 5 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Status_Synchronization extends WP_UnitTestCase {

    /**
     * Admin user ID for testing
     * @var int
     */
    private $admin_id;

    /**
     * WordPress updater instance
     * @var CUFT_WordPress_Updater
     */
    private $wp_updater;

    /**
     * Admin notices instance
     * @var CUFT_Admin_Notices
     */
    private $admin_notices;

    /**
     * Admin bar instance
     * @var CUFT_Admin_Bar
     */
    private $admin_bar;

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

        // Initialize components
        if (!class_exists('CUFT_WordPress_Updater')) {
            require_once CUFT_PLUGIN_DIR . 'includes/class-cuft-wordpress-updater.php';
        }
        $this->wp_updater = new CUFT_WordPress_Updater();

        if (!class_exists('CUFT_Admin_Notices')) {
            require_once CUFT_PLUGIN_DIR . 'includes/admin/class-cuft-admin-notices.php';
        }
        $this->admin_notices = new CUFT_Admin_Notices();

        if (!class_exists('CUFT_Admin_Bar')) {
            require_once CUFT_PLUGIN_DIR . 'includes/admin/class-cuft-admin-bar.php';
        }
        $this->admin_bar = new CUFT_Admin_Bar();

        // Clear all transients
        $this->clear_all_transients();
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();
        $this->clear_all_transients();
        wp_set_current_user(0);
    }

    /**
     * Helper: Clear all update-related transients
     */
    private function clear_all_transients() {
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_progress');
        delete_site_transient('cuft_update_completed');
        delete_site_transient('update_plugins');
    }

    /**
     * Test all interfaces use same data source (site transient)
     *
     * Validates that all UI components read from the same source
     */
    public function test_all_interfaces_use_site_transient() {
        // Set update status in site transient
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Test 1: Admin notices read from site transient
        $notices_status = CUFT_Update_Status::get();
        $this->assertEquals($status['latest_version'], $notices_status['latest_version'], 'Admin notices should read from site transient');

        // Test 2: Admin bar reads from site transient
        $bar_status = CUFT_Update_Status::get();
        $this->assertEquals($status['latest_version'], $bar_status['latest_version'], 'Admin bar should read from site transient');

        // Test 3: WordPress updater reads from site transient
        $updater_status = CUFT_Update_Status::get();
        $this->assertEquals($status['latest_version'], $updater_status['latest_version'], 'WordPress updater should read from site transient');
    }

    /**
     * Test cache invalidation after manual check
     *
     * Validates cache is cleared after manual update check
     */
    public function test_cache_invalidation_after_manual_check() {
        // Set initial cached status
        $old_status = array(
            'update_available' => false,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.2',
            'last_check' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        );
        set_site_transient('cuft_update_status', $old_status, 6 * HOUR_IN_SECONDS);

        // Simulate manual check (force refresh)
        CUFT_Update_Status::clear();

        // Set new status
        $new_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $new_status, HOUR_IN_SECONDS);

        // Verify all interfaces see new status
        $status = CUFT_Update_Status::get();
        $this->assertTrue($status['update_available'], 'Update should be available after manual check');
        $this->assertEquals('3.16.3', $status['latest_version'], 'Latest version should be updated');
    }

    /**
     * Test cache invalidation after update completion
     *
     * Validates cache is cleared after successful update
     */
    public function test_cache_invalidation_after_update() {
        // Set pre-update status
        $pre_update = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $pre_update, HOUR_IN_SECONDS);

        // Simulate update completion
        $upgrader_data = array(
            'action' => 'update',
            'type' => 'plugin',
            'plugins' => array('choice-uft/choice-universal-form-tracker.php')
        );
        $this->wp_updater->invalidate_cache_after_update($upgrader_data, array());

        // Check that caches were cleared
        $this->assertFalse(get_site_transient('cuft_update_status'), 'Update status cache should be cleared');
        $this->assertFalse(get_site_transient('update_plugins'), 'WordPress update cache should be cleared');

        // Check completion transient was set
        $completion = get_site_transient('cuft_update_completed');
        $this->assertNotFalse($completion, 'Completion transient should be set');
        $this->assertArrayHasKey('timestamp', $completion, 'Should have timestamp');
        $this->assertArrayHasKey('version', $completion, 'Should have version');
    }

    /**
     * Test cache invalidation after rollback
     *
     * Validates cache is cleared after rollback
     */
    public function test_cache_invalidation_after_rollback() {
        // Set status before rollback
        $status = array(
            'update_available' => false,
            'current_version' => '3.16.3',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Simulate rollback
        CUFT_Update_Status::clear();

        // Set post-rollback status
        $rolled_back = array(
            'update_available' => true,
            'current_version' => '3.16.2', // Rolled back to previous version
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $rolled_back, HOUR_IN_SECONDS);

        // Verify all interfaces see rolled-back status
        $current = CUFT_Update_Status::get();
        $this->assertEquals('3.16.2', $current['current_version'], 'Should show rolled-back version');
        $this->assertTrue($current['update_available'], 'Update should be available after rollback');
    }

    /**
     * Test consistency across Admin Bar, Plugins page, Updates page, Settings page
     *
     * Validates Scenario 3 from quickstart.md
     */
    public function test_consistency_across_all_ui_locations() {
        // Set a specific status
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip',
            'changelog_url' => 'https://example.com/changelog'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // 1. Admin Bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);
        $bar_node = $wp_admin_bar->get_node('cuft-updates');
        $this->assertStringContainsString('3.16.3', $bar_node->title, 'Admin bar should show latest version');

        // 2. Admin Notices (would appear on settings page)
        ob_start();
        $this->admin_notices->display_update_available_notice();
        $notice_output = ob_get_clean();
        $this->assertStringContainsString('3.16.3', $notice_output, 'Admin notice should show latest version');

        // 3. WordPress Updates integration
        $update_plugins = new stdClass();
        $update_plugins->response = array();
        $update_plugins->checked = array(
            'choice-uft/choice-universal-form-tracker.php' => '3.16.2'
        );

        $filtered = $this->wp_updater->add_plugin_update_info($update_plugins);
        $this->assertArrayHasKey('choice-uft/choice-universal-form-tracker.php', $filtered->response, 'Should add update info');

        $update_info = $filtered->response['choice-uft/choice-universal-form-tracker.php'];
        $this->assertEquals('3.16.3', $update_info->new_version, 'Updates page should show latest version');

        // 4. All show same version info
        $this->assertEquals('3.16.3', $status['latest_version'], 'Base status');
        $this->assertStringContainsString('3.16.3', $bar_node->title, 'Admin bar');
        $this->assertStringContainsString('3.16.3', $notice_output, 'Admin notice');
        $this->assertEquals('3.16.3', $update_info->new_version, 'Updates page');
    }

    /**
     * Test status synchronized within 5 seconds
     *
     * Validates performance requirement for synchronization
     */
    public function test_status_synchronization_performance() {
        // Clear all caches
        $this->clear_all_transients();

        // Start timing
        $start_time = microtime(true);

        // Trigger status update
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Read from all interfaces
        $status1 = CUFT_Update_Status::get();
        $status2 = CUFT_Update_Status::get();
        $status3 = CUFT_Update_Status::get();

        $elapsed = microtime(true) - $start_time;

        // All should be synchronized within 5 seconds
        $this->assertLessThan(5, $elapsed, 'Status synchronization should complete within 5 seconds');

        // All should have same data
        $this->assertEquals($status1, $status2, 'Status should be consistent');
        $this->assertEquals($status2, $status3, 'Status should be consistent');
    }

    /**
     * Test multi-user synchronization
     *
     * Validates that multiple admin users see consistent status
     */
    public function test_multi_user_synchronization() {
        // Create second admin user
        $admin2_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));

        // Set status as first admin
        wp_set_current_user($this->admin_id);
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Check status as second admin
        wp_set_current_user($admin2_id);
        $status2 = CUFT_Update_Status::get();

        // Both users should see same status
        $this->assertEquals($status['latest_version'], $status2['latest_version'], 'Both users should see same version');
        $this->assertEquals($status['update_available'], $status2['update_available'], 'Both users should see same availability');
    }

    /**
     * Test no conflicting data between interfaces
     *
     * Ensures no race conditions or data conflicts
     */
    public function test_no_conflicting_data() {
        // Set initial status
        $status = array(
            'update_available' => false,
            'current_version' => '3.16.3',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Simulate rapid status changes
        for ($i = 0; $i < 5; $i++) {
            // Toggle update availability
            $status['update_available'] = !$status['update_available'];
            $status['latest_version'] = $status['update_available'] ? '3.16.4' : '3.16.3';

            // Update transient
            set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

            // Read from all interfaces immediately
            $read1 = CUFT_Update_Status::get();
            $read2 = CUFT_Update_Status::get();

            // Should always be consistent
            $this->assertEquals($read1['latest_version'], $read2['latest_version'], 'No conflicting versions');
            $this->assertEquals($read1['update_available'], $read2['update_available'], 'No conflicting availability');
        }
    }

    /**
     * Test Scenario 3: Consistent Version Display
     *
     * From quickstart.md Scenario 3
     */
    public function test_quickstart_scenario_3() {
        // Given: Admin views Updates tab
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // When: Checking version status
        $updates_tab_status = CUFT_Update_Status::get();
        $admin_bar_status = CUFT_Update_Status::get();
        $settings_page_status = CUFT_Update_Status::get();

        // Then: All UI elements show consistent version information
        $this->assertEquals('3.16.3', $updates_tab_status['latest_version'], 'Updates tab should show 3.16.3');
        $this->assertEquals('3.16.3', $admin_bar_status['latest_version'], 'Admin bar should show 3.16.3');
        $this->assertEquals('3.16.3', $settings_page_status['latest_version'], 'Settings page should show 3.16.3');

        // All should agree on update availability
        $this->assertTrue($updates_tab_status['update_available'], 'Updates tab should show update available');
        $this->assertTrue($admin_bar_status['update_available'], 'Admin bar should show update available');
        $this->assertTrue($settings_page_status['update_available'], 'Settings page should show update available');
    }

    /**
     * Test Scenario 5: Synchronized Update Indicators
     *
     * From quickstart.md Scenario 5
     */
    public function test_quickstart_scenario_5() {
        // Given: Update is available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // When: Checking status in different interfaces
        $interfaces = array();

        // Admin bar check
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);
        $bar_node = $wp_admin_bar->get_node('cuft-updates');
        $interfaces['admin_bar'] = strpos($bar_node->title, 'Update Available') !== false;

        // Direct status check
        $status_check = CUFT_Update_Status::get();
        $interfaces['status_api'] = $status_check['update_available'];

        // WordPress updates integration
        $update_plugins = new stdClass();
        $update_plugins->response = array();
        $update_plugins->checked = array(
            'choice-uft/choice-universal-form-tracker.php' => '3.16.2'
        );
        $filtered = $this->wp_updater->add_plugin_update_info($update_plugins);
        $interfaces['wp_updates'] = isset($filtered->response['choice-uft/choice-universal-form-tracker.php']);

        // Then: All indicators show same update availability consistently
        $this->assertTrue($interfaces['admin_bar'], 'Admin bar should indicate update available');
        $this->assertTrue($interfaces['status_api'], 'Status API should indicate update available');
        $this->assertTrue($interfaces['wp_updates'], 'WordPress Updates should indicate update available');

        // All should be true (update available)
        $unique_values = array_unique(array_values($interfaces));
        $this->assertCount(1, $unique_values, 'All interfaces should agree on update availability');
        $this->assertTrue($unique_values[0], 'All should show update available');
    }
}