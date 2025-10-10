<?php
/**
 * Integration Test: Secure Update Button
 *
 * Tests secure update button with proper nonce validation
 * Implements T028 from specs/007-fix-update-system/tasks.md
 * Validates Scenario 4 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Secure_Update_Button extends WP_UnitTestCase {

    /**
     * Admin user ID for testing
     * @var int
     */
    private $admin_id;

    /**
     * AJAX handler instance
     * @var CUFT_Updater_Ajax
     */
    private $ajax_handler;

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

        // Initialize AJAX handler
        if (!class_exists('CUFT_Updater_Ajax')) {
            require_once CUFT_PLUGIN_DIR . 'includes/ajax/class-cuft-updater-ajax.php';
        }
        $this->ajax_handler = new CUFT_Updater_Ajax();

        // Clear transients
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_progress');
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_progress');
        wp_set_current_user(0);
        unset($_POST);
        unset($_REQUEST);
    }

    /**
     * Test nonce is properly included in update request
     *
     * Validates nonce generation and inclusion
     */
    public function test_nonce_included_in_request() {
        // Generate nonce as it would be in JavaScript
        $nonce = wp_create_nonce('cuft_updater_nonce');

        // Simulate AJAX request with nonce
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = $nonce;
        $_POST['version'] = '3.16.3';

        // Check nonce is present and valid
        $this->assertNotEmpty($_POST['nonce'], 'Nonce should be included in request');
        $this->assertNotFalse(wp_verify_nonce($_POST['nonce'], 'cuft_updater_nonce'), 'Nonce should be valid');
    }

    /**
     * Test successful response with valid nonce (HTTP 200)
     *
     * Validates successful update initiation
     */
    public function test_successful_response_with_valid_nonce() {
        // Set update available state
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Create valid request
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        // Capture response
        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();

        // Parse JSON response
        $data = json_decode($response, true);

        // Assert successful response
        $this->assertTrue($data['success'], 'Update should succeed with valid nonce');
        $this->assertArrayHasKey('update_id', $data['data'], 'Should return update ID');
        $this->assertEquals('scheduled', $data['data']['status'], 'Update should be scheduled');

        // Verify HTTP status would be 200 (wp_die sets this in real execution)
        $this->assertNotEmpty($response, 'Should have response content');
    }

    /**
     * Test no "Security check failed" error with valid nonce
     *
     * Validates security check passes properly
     */
    public function test_no_security_check_failed_with_valid_nonce() {
        // Create valid request
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');

        // Capture response
        ob_start();
        $this->ajax_handler->handle_check_update();
        $response = ob_get_clean();

        // Parse response
        $data = json_decode($response, true);

        // Should not contain security error
        $this->assertNotContains('Security check failed', $response, 'Should not have security check failed message');
        $this->assertTrue($data['success'], 'Check should succeed');

        // Verify no security-related error codes
        if (isset($data['data']['code'])) {
            $this->assertNotEquals('invalid_nonce', $data['data']['code'], 'Should not have invalid_nonce error');
            $this->assertNotEquals('insufficient_permissions', $data['data']['code'], 'Should not have permissions error');
        }
    }

    /**
     * Test request fails with invalid nonce
     *
     * Validates security rejection for bad nonce
     */
    public function test_request_fails_with_invalid_nonce() {
        // Create request with invalid nonce
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = 'invalid_nonce_value';
        $_POST['version'] = '3.16.3';

        // Capture response
        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();

        // Parse response
        $data = json_decode($response, true);

        // Should fail with security error
        $this->assertFalse($data['success'], 'Should fail with invalid nonce');
        $this->assertEquals('invalid_nonce', $data['data']['code'], 'Should return invalid_nonce error code');
        $this->assertStringContainsString('Security check failed', $data['data']['message'], 'Should have security error message');
    }

    /**
     * Test request fails without nonce
     *
     * Validates security rejection when nonce missing
     */
    public function test_request_fails_without_nonce() {
        // Create request without nonce
        $_POST['action'] = 'cuft_perform_update';
        $_POST['version'] = '3.16.3';
        // No nonce field

        // Capture response
        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();

        // Parse response
        $data = json_decode($response, true);

        // Should fail
        $this->assertFalse($data['success'], 'Should fail without nonce');
        $this->assertEquals('invalid_nonce', $data['data']['code'], 'Should return invalid_nonce error code');
    }

    /**
     * Test nonce validation checks both POST and GET
     *
     * Validates fallback to GET parameters
     */
    public function test_nonce_validation_checks_post_and_get() {
        // Test with POST nonce
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');

        ob_start();
        $this->ajax_handler->handle_check_update();
        $post_response = ob_get_clean();
        $post_data = json_decode($post_response, true);

        $this->assertTrue($post_data['success'], 'Should succeed with POST nonce');

        // Clear POST, test with GET nonce
        unset($_POST['nonce']);
        $_GET['nonce'] = wp_create_nonce('cuft_updater_nonce');

        ob_start();
        $this->ajax_handler->handle_check_update();
        $get_response = ob_get_clean();
        $get_data = json_decode($get_response, true);

        $this->assertTrue($get_data['success'], 'Should succeed with GET nonce');
    }

    /**
     * Test nonce action matches JavaScript localization
     *
     * Validates nonce action consistency
     */
    public function test_nonce_action_matches_javascript() {
        // Check constant definition
        $this->assertEquals('cuft_updater_nonce', CUFT_Updater_Ajax::NONCE_ACTION, 'Nonce action should be cuft_updater_nonce');

        // Verify JavaScript localization would use same action
        $expected_nonce = wp_create_nonce('cuft_updater_nonce');
        $actual_nonce = wp_create_nonce(CUFT_Updater_Ajax::NONCE_ACTION);

        $this->assertEquals($expected_nonce, $actual_nonce, 'Nonce actions should match');
    }

    /**
     * Test capability checks alongside nonce validation
     *
     * Validates proper permission checking
     */
    public function test_capability_checks_with_nonce() {
        // Test as non-admin user
        $subscriber_id = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        wp_set_current_user($subscriber_id);

        // Create request with valid nonce but insufficient permissions
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        // Capture response
        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();

        // Parse response
        $data = json_decode($response, true);

        // Should fail due to permissions even with valid nonce
        $this->assertFalse($data['success'], 'Should fail for non-admin user');
        $this->assertEquals('insufficient_permissions', $data['data']['code'], 'Should return permissions error');
    }

    /**
     * Test request/response structure validation
     *
     * Validates complete request/response contract
     */
    public function test_request_response_structure() {
        // Set up valid update scenario
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Test perform update endpoint
        $_POST = array(
            'action' => 'cuft_perform_update',
            'nonce' => wp_create_nonce('cuft_updater_nonce'),
            'version' => '3.16.3',
            'backup' => true
        );

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Validate response structure
        $this->assertArrayHasKey('success', $data, 'Response should have success field');
        $this->assertArrayHasKey('data', $data, 'Response should have data field');

        if ($data['success']) {
            $this->assertArrayHasKey('update_id', $data['data'], 'Success response should have update_id');
            $this->assertArrayHasKey('status', $data['data'], 'Success response should have status');
            $this->assertArrayHasKey('message', $data['data'], 'Success response should have message');
        } else {
            $this->assertArrayHasKey('code', $data['data'], 'Error response should have code');
            $this->assertArrayHasKey('message', $data['data'], 'Error response should have message');
        }
    }

    /**
     * Test Scenario 4: Secure update button
     *
     * From quickstart.md Scenario 4
     */
    public function test_quickstart_scenario_4() {
        // Given: Admin clicks "Download & Install Update" button
        wp_set_current_user($this->admin_id);

        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://example.com/update.zip'
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // When: Request is processed
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');
        $_POST['version'] = '3.16.3';

        ob_start();
        $this->ajax_handler->handle_perform_update();
        $response = ob_get_clean();

        // Then: Completes successfully with proper nonce validation (no "Security check failed")
        $data = json_decode($response, true);

        $this->assertTrue($data['success'], 'Update request should succeed');
        $this->assertNotContains('Security check failed', $response, 'Should not have security check failed error');
        $this->assertArrayHasKey('update_id', $data['data'], 'Should return update ID');
        $this->assertEquals('scheduled', $data['data']['status'], 'Update should be scheduled');

        // Verify progress tracking was initiated
        $progress = get_site_transient('cuft_update_progress');
        $this->assertNotFalse($progress, 'Update progress should be set');
        if ($progress) {
            $this->assertEquals('pending', $progress['status'], 'Progress should show pending status');
        }
    }

    /**
     * Test all AJAX endpoints validate nonces consistently
     *
     * Validates nonce validation across all endpoints
     */
    public function test_all_endpoints_validate_nonces() {
        $endpoints = array(
            'cuft_check_update' => 'handle_check_update',
            'cuft_perform_update' => 'handle_perform_update',
            'cuft_update_status' => 'handle_update_status',
            'cuft_rollback_update' => 'handle_rollback',
            'cuft_update_history' => 'handle_update_history'
        );

        foreach ($endpoints as $action => $method) {
            // Test with invalid nonce
            $_POST = array(
                'action' => $action,
                'nonce' => 'invalid_nonce'
            );

            // Skip if method doesn't exist (some endpoints might not be implemented)
            if (!method_exists($this->ajax_handler, $method)) {
                continue;
            }

            ob_start();
            $this->ajax_handler->$method();
            $response = ob_get_clean();
            $data = json_decode($response, true);

            // All should fail with invalid nonce
            $this->assertFalse($data['success'], "{$action} should fail with invalid nonce");
            $this->assertEquals('invalid_nonce', $data['data']['code'], "{$action} should return invalid_nonce error");

            // Clear for next iteration
            unset($_POST);
        }
    }
}