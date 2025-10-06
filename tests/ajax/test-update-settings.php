<?php
/**
 * Contract Test: cuft_update_settings AJAX Endpoint
 *
 * Tests the cuft_update_settings AJAX endpoint for proper nonce validation,
 * capability checks, and response formats.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
    // For standalone testing, load WordPress
    require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Update_Settings_AJAX extends WP_UnitTestCase {

    /**
     * Test valid settings update with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_settings_update() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['enabled'] = 'true';
        $_POST['check_frequency'] = 'twicedaily';
        $_POST['include_prereleases'] = 'false';
        $_POST['backup_before_update'] = 'true';
        $_POST['notification_email'] = 'admin@example.com';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update to return success
            $this->mock_settings_update_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_update_settings' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'Settings updated successfully', $response['data']['message'] );
            $this->assertArrayHasKey( 'settings', $response['data'] );
            $this->assertTrue( $response['data']['settings']['enabled'] );
            $this->assertEquals( 'twicedaily', $response['data']['settings']['check_frequency'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test invalid nonce rejection
     *
     * @test
     */
    public function test_invalid_nonce_rejection() {
        // Set up WordPress AJAX environment with invalid nonce
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['enabled'] = 'true';
        $_POST['check_frequency'] = 'twicedaily';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'Security check failed', $response['data']['message'] );
            $this->assertEquals( 'invalid_nonce', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test insufficient permissions rejection
     *
     * @test
     */
    public function test_insufficient_permissions_rejection() {
        // Create user without manage_options capability
        $user = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user );

        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['enabled'] = 'true';
        $_POST['check_frequency'] = 'twicedaily';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'Insufficient permissions', $response['data']['message'] );
            $this->assertEquals( 'insufficient_permissions', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test enabled parameter handling
     *
     * @test
     */
    public function test_enabled_parameter_handling() {
        // Test with enabled = true
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['enabled'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test check frequency parameter handling
     *
     * @test
     */
    public function test_check_frequency_parameter_handling() {
        $frequencies = array( 'manual', 'hourly', 'twicedaily', 'daily', 'weekly' );

        foreach ( $frequencies as $frequency ) {
            // Set up WordPress AJAX environment
            $_POST['action'] = 'cuft_update_settings';
            $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
            $_POST['check_frequency'] = $frequency;

            // Simulate AJAX request
            try {
                ob_start();
                
                // Mock the settings update
                $this->mock_settings_update_success();
                
                do_action( 'wp_ajax_cuft_update_settings' );
                $output = ob_get_clean();
                $response = json_decode( $output, true );

                // Assertions
                $this->assertTrue( $response['success'] );

            } catch ( Exception $e ) {
                ob_end_clean();
                $this->fail( 'AJAX request failed for frequency ' . $frequency . ': ' . $e->getMessage() );
            }
        }
    }

    /**
     * Test include prereleases parameter handling
     *
     * @test
     */
    public function test_include_prereleases_parameter_handling() {
        // Test with include_prereleases = true
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['include_prereleases'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test backup before update parameter handling
     *
     * @test
     */
    public function test_backup_before_update_parameter_handling() {
        // Test with backup_before_update = false
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['backup_before_update'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test notification email parameter handling
     *
     * @test
     */
    public function test_notification_email_parameter_handling() {
        // Test with valid email
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['notification_email'] = 'admin@example.com';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test invalid email parameter handling
     *
     * @test
     */
    public function test_invalid_email_parameter_handling() {
        // Test with invalid email
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['notification_email'] = 'invalid-email';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions - should still succeed but sanitize email
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test missing parameters handling
     *
     * @test
     */
    public function test_missing_parameters_handling() {
        // Set up WordPress AJAX environment with minimal parameters
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        // No other parameters

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions - should still succeed
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test settings update failure handling
     *
     * @test
     */
    public function test_settings_update_failure_handling() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['enabled'] = 'true';

        // Mock settings update failure
        $this->mock_settings_update_failure();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'Failed to save settings', $response['data']['message'] );
            $this->assertEquals( 'save_failed', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test settings response format
     *
     * @test
     */
    public function test_settings_response_format() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_update_settings';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['enabled'] = 'true';
        $_POST['check_frequency'] = 'twicedaily';
        $_POST['include_prereleases'] = 'false';
        $_POST['backup_before_update'] = 'true';
        $_POST['notification_email'] = 'admin@example.com';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the settings update
            $this->mock_settings_update_success();
            
            do_action( 'wp_ajax_cuft_update_settings' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for response format
            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey( 'settings', $response['data'] );
            
            $settings = $response['data']['settings'];
            $this->assertArrayHasKey( 'enabled', $settings );
            $this->assertArrayHasKey( 'check_frequency', $settings );
            $this->assertArrayHasKey( 'include_prereleases', $settings );
            $this->assertArrayHasKey( 'backup_before_update', $settings );
            $this->assertArrayHasKey( 'notification_email', $settings );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock settings update success
     */
    private function mock_settings_update_success() {
        // This would mock the settings update to return success
        // For now, we'll test the actual implementation
    }

    /**
     * Mock settings update failure
     */
    private function mock_settings_update_failure() {
        // This would mock the settings update to return failure
        // For now, we'll test the actual implementation
    }

    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Ensure we have an admin user
        $admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_user );
        
        // Clear any existing settings
        delete_option( 'cuft_update_config' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up POST data
        unset( $_POST['action'] );
        unset( $_POST['nonce'] );
        unset( $_POST['enabled'] );
        unset( $_POST['check_frequency'] );
        unset( $_POST['include_prereleases'] );
        unset( $_POST['backup_before_update'] );
        unset( $_POST['notification_email'] );
        
        // Clear settings
        delete_option( 'cuft_update_config' );
        
        parent::tearDown();
    }
}
