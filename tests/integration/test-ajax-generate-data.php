<?php
/**
 * Test AJAX Generate Test Data Endpoint
 *
 * Tests for the test data generation AJAX endpoint.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

class Test_Ajax_Generate_Data extends WP_Ajax_UnitTestCase {

    /**
     * Admin user ID
     *
     * @var int
     */
    private $admin_user_id;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create admin user
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));

        // Initialize the generator
        new CUFT_Test_Data_Generator();
    }

    /**
     * Test successful test data generation
     *
     * @test
     */
    public function test_generate_test_data_success() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Set up nonce
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        // Start timing
        $start_time = microtime(true);

        // Make AJAX call
        try {
            $this->_handleAjax('cuft_generate_test_data');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        // Check execution time
        $execution_time = (microtime(true) - $start_time) * 1000;
        $this->assertLessThan(500, $execution_time, 'Response time should be under 500ms');

        // Get response
        $response = json_decode($this->_last_response, true);

        // Assert success
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);

        // Check response structure
        $data = $response['data'];
        $this->assertArrayHasKey('session_id', $data);
        $this->assertArrayHasKey('click_ids', $data);
        $this->assertArrayHasKey('utm_params', $data);
        $this->assertArrayHasKey('contact', $data);
        $this->assertArrayHasKey('execution_time_ms', $data);

        // Validate session ID format
        $this->assertStringStartsWith('test_', $data['session_id']);

        // Validate click IDs
        $this->assertArrayHasKey('click_id', $data['click_ids']);
        $this->assertStringStartsWith('test_', $data['click_ids']['click_id']);

        // Should have at least one platform-specific click ID
        $has_platform_id = isset($data['click_ids']['gclid']) ||
                           isset($data['click_ids']['fbclid']) ||
                           isset($data['click_ids']['msclkid']);
        $this->assertTrue($has_platform_id, 'Should have at least one platform-specific click ID');

        // Validate UTM params (simplified version should only have campaign)
        $this->assertArrayHasKey('utm_campaign', $data['utm_params']);
        $this->assertContains(date('Y'), $data['utm_params']['utm_campaign']);

        // Validate contact info
        $this->assertArrayHasKey('email', $data['contact']);
        $this->assertArrayHasKey('phone', $data['contact']);
        $this->assertArrayHasKey('name', $data['contact']);

        // Validate email format
        $this->assertRegExp('/^test\+[a-f0-9]+@example\.com$/', $data['contact']['email']);

        // Validate phone format
        $this->assertRegExp('/^555-01\d{2}-\d{4}$/', $data['contact']['phone']);
    }

    /**
     * Test that non-admin users cannot generate test data
     *
     * @test
     */
    public function test_non_admin_cannot_generate_data() {
        // Create editor user
        $editor_id = $this->factory->user->create(array(
            'role' => 'editor'
        ));

        // Set current user as editor
        wp_set_current_user($editor_id);

        // Set up nonce
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        // Make AJAX call
        try {
            $this->_handleAjax('cuft_generate_test_data');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        // Get response
        $response = json_decode($this->_last_response, true);

        // Assert failure
        $this->assertFalse($response['success']);
        $this->assertEquals(403, wp_remote_retrieve_response_code($response));
        $this->assertContains('Insufficient permissions', $response['data']['message']);
    }

    /**
     * Test invalid nonce rejection
     *
     * @test
     */
    public function test_invalid_nonce_rejected() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Set invalid nonce
        $_REQUEST['nonce'] = 'invalid_nonce';

        // Make AJAX call
        try {
            $this->_handleAjax('cuft_generate_test_data');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        // Get response
        $response = json_decode($this->_last_response, true);

        // Assert failure
        $this->assertFalse($response['success']);
        $this->assertEquals(403, wp_remote_retrieve_response_code($response));
        $this->assertContains('Security check failed', $response['data']['message']);
    }

    /**
     * Test click ID format validation
     *
     * @test
     */
    public function test_click_id_formats() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        // Generate multiple times to test randomization
        $click_ids_collected = array();

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->_handleAjax('cuft_generate_test_data');
            } catch (WPAjaxDieContinueException $e) {
                // Expected
            }

            $response = json_decode($this->_last_response, true);
            $click_ids = $response['data']['click_ids'];

            // Always has click_id
            $this->assertArrayHasKey('click_id', $click_ids);

            // Collect which platform IDs are present
            if (isset($click_ids['gclid'])) {
                $click_ids_collected['gclid'] = true;
                // Validate Google Ads format
                $this->assertStringStartsWith('Cj0KCQiA', $click_ids['gclid']);
            }
            if (isset($click_ids['fbclid'])) {
                $click_ids_collected['fbclid'] = true;
                // Validate Facebook format
                $this->assertStringStartsWith('IwAR', $click_ids['fbclid']);
            }
            if (isset($click_ids['msclkid'])) {
                $click_ids_collected['msclkid'] = true;
                // Validate Microsoft format (32 char hex)
                $this->assertEquals(32, strlen($click_ids['msclkid']));
            }
        }

        // Should have seen variety over multiple runs
        $this->assertGreaterThan(1, count($click_ids_collected),
            'Should generate different platform IDs across multiple runs');
    }

    /**
     * Test performance requirement
     *
     * @test
     */
    public function test_performance_under_500ms() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        // Run multiple times to get average
        $times = array();

        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);

            try {
                $this->_handleAjax('cuft_generate_test_data');
            } catch (WPAjaxDieContinueException $e) {
                // Expected
            }

            $time = (microtime(true) - $start) * 1000;
            $times[] = $time;

            // Each request should be under 500ms
            $this->assertLessThan(500, $time,
                "Request $i took {$time}ms, should be under 500ms");
        }

        // Average should also be well under 500ms
        $average = array_sum($times) / count($times);
        $this->assertLessThan(400, $average,
            "Average response time {$average}ms should be well under 500ms");
    }
}