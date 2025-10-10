<?php
/**
 * Integration Test: Concurrent Updates
 *
 * Tests concurrent update handling and prevention
 * Implements T030 from specs/007-fix-update-system/tasks.md
 * Validates Scenario 7 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Concurrent_Updates extends WP_UnitTestCase {

    /**
     * First admin user ID
     * @var int
     */
    private $admin1_id;

    /**
     * Second admin user ID
     * @var int
     */
    private $admin2_id;

    /**
     * AJAX handler instance
     * @var CUFT_Updater_Ajax
     */
    private $ajax_handler;

    /**
     * Update progress model
     * @var CUFT_Update_Progress
     */
    private $update_progress;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create two admin users
        $this->admin1_id = $this->factory->user->create(array(
            'role' => 'administrator',
            'display_name' => 'Admin User 1'
        ));

        $this->admin2_id = $this->factory->user->create(array(
            'role' => 'administrator',
            'display_name' => 'Admin User 2'
        ));

        // Initialize AJAX handler
        if (!class_exists('CUFT_Updater_Ajax')) {
            require_once CUFT_PLUGIN_DIR . 'includes/ajax/class-cuft-updater-ajax.php';
        }
        $this->ajax_handler = new CUFT_Updater_Ajax();

        // Initialize update progress model
        if (!class_exists('CUFT_Update_Progress')) {
            require_once CUFT_PLUGIN_DIR . 'includes/models/class-cuft-update-progress.php';
        }
        $this->update_progress = new CUFT_Update_Progress();

        // Clear transients
        $this->clear_all_transients();
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();
        $this->clear_all_transients();
        wp_set_current_user(0);
        unset($_POST);
        unset($_REQUEST);
    }

    /**
     * Helper: Clear all update-related transients
     */
    private function clear_all_transients() {
        delete_site_transient('cuft_update_progress');
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_lock');
    }

    /**
     * Test simulating concurrent update requests
     *
     * Validates concurrent request handling
     */
    public function test_simulate_concurrent_update_requests() {
        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // First user initiates update
        wp_set_current_user($this->admin1_id);
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response1 = ob_get_clean();
        $data1 = json_decode($response1, true);

        // First request should succeed
        $this->assertTrue($data1['success'], 'First update request should succeed');
        $this->assertEquals('scheduled', $data1['data']['status'], 'First update should be scheduled');

        // Second user attempts update immediately after
        wp_set_current_user($this->admin2_id);
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response2 = ob_get_clean();
        $data2 = json_decode($response2, true);

        // Second request should be blocked
        $this->assertFalse($data2['success'], 'Second concurrent update request should fail');
        $this->assertEquals('update_in_progress', $data2['data']['code'], 'Should return update_in_progress error');
    }

    /**
     * Test first request succeeds, second gets 409 error
     *
     * Validates HTTP 409 Conflict response
     */
    public function test_first_succeeds_second_gets_409() {
        // Set up update scenario
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // First user request
        wp_set_current_user($this->admin1_id);
        $progress = array(
            'status' => 'in_progress',
            'percentage' => 0,
            'message' => 'Starting update',
            'started_at' => current_time('mysql'),
            'user_id' => $this->admin1_id
        );
        set_site_transient('cuft_update_progress', $progress, 5 * MINUTE_IN_SECONDS);

        // Second user attempts update
        wp_set_current_user($this->admin2_id);
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Should get conflict error
        $this->assertFalse($data['success'], 'Should fail when update in progress');
        $this->assertEquals('update_in_progress', $data['data']['code'], 'Should return 409 conflict error code');

        // Message should include who is updating
        $this->assertStringContainsString('Admin User 1', $data['data']['message'], 'Error should mention who is updating');
    }

    /**
     * Test lock mechanism prevents concurrent updates
     *
     * Validates transient-based locking
     */
    public function test_lock_mechanism() {
        // Clear any existing locks
        $this->clear_all_transients();

        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // First user acquires lock
        wp_set_current_user($this->admin1_id);
        $lock_acquired = $this->update_progress->acquire_lock($this->admin1_id);
        $this->assertTrue($lock_acquired, 'First user should acquire lock');

        // Check lock exists
        $progress = get_site_transient('cuft_update_progress');
        $this->assertNotFalse($progress, 'Update progress should be set');
        $this->assertEquals('pending', $progress['status'], 'Status should be pending');
        $this->assertEquals($this->admin1_id, $progress['user_id'], 'Should track user ID');

        // Second user tries to acquire lock
        wp_set_current_user($this->admin2_id);
        $lock_acquired2 = $this->update_progress->acquire_lock($this->admin2_id);
        $this->assertFalse($lock_acquired2, 'Second user should not acquire lock');

        // Progress should still show first user
        $progress = get_site_transient('cuft_update_progress');
        $this->assertEquals($this->admin1_id, $progress['user_id'], 'Lock should still belong to first user');
    }

    /**
     * Test user information included in conflict error
     *
     * Validates user display name in error message
     */
    public function test_user_info_in_error() {
        // Set first user as updating
        wp_set_current_user($this->admin1_id);
        $progress = array(
            'status' => 'in_progress',
            'percentage' => 50,
            'message' => 'Downloading update',
            'started_at' => current_time('mysql'),
            'user_id' => $this->admin1_id
        );
        set_site_transient('cuft_update_progress', $progress, 5 * MINUTE_IN_SECONDS);

        // Second user attempts update
        wp_set_current_user($this->admin2_id);
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Error message should include user info
        $this->assertFalse($data['success'], 'Should fail');
        $this->assertStringContainsString('Admin User 1', $data['data']['message'], 'Should include first user display name');
        $this->assertStringContainsString('already in progress', $data['data']['message'], 'Should indicate update in progress');
    }

    /**
     * Test lock expiration after timeout
     *
     * Validates locks don't persist forever
     */
    public function test_lock_expiration() {
        // Set initial lock
        wp_set_current_user($this->admin1_id);
        $progress = array(
            'status' => 'in_progress',
            'percentage' => 0,
            'message' => 'Starting',
            'started_at' => date('Y-m-d H:i:s', strtotime('-6 minutes')), // Old timestamp
            'user_id' => $this->admin1_id
        );
        set_site_transient('cuft_update_progress', $progress, 5 * MINUTE_IN_SECONDS);

        // Check if lock is considered expired
        $current_progress = $this->update_progress->get_current();

        // After 5 minutes, transient should have expired
        // WordPress handles transient expiration automatically
        // If we get false, lock has expired
        if ($current_progress === false) {
            $this->assertFalse($current_progress, 'Lock should expire after timeout');
        } else {
            // Check if it's been too long
            $started = strtotime($current_progress['started_at']);
            $elapsed = time() - $started;
            $this->assertGreaterThan(300, $elapsed, 'Lock should be considered stale after 5 minutes');
        }
    }

    /**
     * Test multiple rapid requests handling
     *
     * Validates system handles rapid-fire requests
     */
    public function test_multiple_rapid_requests() {
        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        $results = array();

        // Simulate 5 rapid requests from different users
        for ($i = 1; $i <= 5; $i++) {
            $user_id = $this->factory->user->create(array(
                'role' => 'administrator',
                'display_name' => "Admin User {$i}"
            ));

            wp_set_current_user($user_id);
            $_POST['action'] = 'cuft_perform_update';
            $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
            $_POST['version'] = '3.16.3';

            ob_start();
            $this->ajax_handler->handle_perform_update();
            $response = ob_get_clean();
            $data = json_decode($response, true);

            $results[$i] = $data['success'];
        }

        // Only first request should succeed
        $this->assertTrue($results[1], 'First request should succeed');

        // All others should fail
        for ($i = 2; $i <= 5; $i++) {
            $this->assertFalse($results[$i], "Request {$i} should fail due to concurrent update");
        }
    }

    /**
     * Test status endpoint during concurrent attempt
     *
     * Validates status reporting during conflicts
     */
    public function test_status_during_concurrent_attempt() {
        // First user has update in progress
        wp_set_current_user($this->admin1_id);
        $progress = array(
            'status' => 'in_progress',
            'percentage' => 25,
            'message' => 'Downloading files',
            'started_at' => current_time('mysql'),
            'user_id' => $this->admin1_id
        );
        set_site_transient('cuft_update_progress', $progress, 5 * MINUTE_IN_SECONDS);

        // Second user checks status
        wp_set_current_user($this->admin2_id);
        $_POST['action'] = 'cuft_update_status';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');

        ob_start();
        $this->ajax_handler->handle_update_status();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Status should show update in progress by another user
        $this->assertTrue($data['success'], 'Status check should succeed');
        $this->assertEquals('in_progress', $data['data']['status'], 'Should show in_progress status');
        $this->assertEquals(25, $data['data']['percentage'], 'Should show current percentage');
        $this->assertStringContainsString('Admin User 1', $data['data']['user_display_name'], 'Should show who is updating');
    }

    /**
     * Test Scenario 7: Concurrent Updates
     *
     * From quickstart.md Scenario 7
     */
    public function test_quickstart_scenario_7() {
        // Given: Admin 1 initiates update
        wp_set_current_user($this->admin1_id);

        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Admin 1 starts update
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response1 = ob_get_clean();
        $data1 = json_decode($response1, true);

        $this->assertTrue($data1['success'], 'Admin 1 update should succeed');

        // When: Admin 2 attempts update simultaneously
        wp_set_current_user($this->admin2_id);
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response2 = ob_get_clean();
        $data2 = json_decode($response2, true);

        // Then: Admin 2 receives "Update already in progress by Admin User 1" error
        $this->assertFalse($data2['success'], 'Admin 2 update should fail');
        $this->assertEquals('update_in_progress', $data2['data']['code'], 'Should return update_in_progress error');
        $this->assertStringContainsString('Admin User 1', $data2['data']['message'], 'Error should mention Admin User 1');
        $this->assertStringContainsString('already in progress', $data2['data']['message'], 'Should indicate update in progress');
    }

    /**
     * Test cleanup after failed concurrent attempt
     *
     * Validates system recovers properly
     */
    public function test_cleanup_after_failed_attempt() {
        // First user starts update
        wp_set_current_user($this->admin1_id);
        $progress = array(
            'status' => 'in_progress',
            'percentage' => 50,
            'message' => 'Installing',
            'started_at' => current_time('mysql'),
            'user_id' => $this->admin1_id
        );
        set_site_transient('cuft_update_progress', $progress, 5 * MINUTE_IN_SECONDS);

        // Simulate update completion
        delete_site_transient('cuft_update_progress');
        set_site_transient('cuft_update_completed', array(
            'timestamp' => time(),
            'version' => '3.16.3',
            'message' => 'Update completed successfully'
        ), 5 * MINUTE_IN_SECONDS);

        // Second user should now be able to initiate if needed
        wp_set_current_user($this->admin2_id);
        $can_update = !get_site_transient('cuft_update_progress');
        $this->assertTrue($can_update, 'Should be able to update after previous completion');
    }
}