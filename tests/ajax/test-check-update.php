<?php
/**
 * Contract Test: cuft_check_update AJAX Endpoint
 *
 * Tests the cuft_check_update AJAX endpoint for proper nonce validation,
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

class Test_Check_Update_AJAX extends WP_UnitTestCase {

    /**
     * Test valid update check with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_update_check() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update checker to return success
            $this->mock_update_checker_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_check_update' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey( 'current_version', $response['data'] );
            $this->assertArrayHasKey( 'latest_version', $response['data'] );
            $this->assertArrayHasKey( 'update_available', $response['data'] );
            $this->assertArrayHasKey( 'last_check', $response['data'] );

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
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_check_update' );
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
     * Test missing nonce rejection
     *
     * @test
     */
    public function test_missing_nonce_rejection() {
        // Set up WordPress AJAX environment without nonce
        $_POST['action'] = 'cuft_check_update';
        $_POST['force'] = 'false';
        unset( $_POST['nonce'] );

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_check_update' );
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
        // Create user without update_plugins capability
        $user = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $user );

        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_check_update' );
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
     * Test force check parameter
     *
     * @test
     */
    public function test_force_check_parameter() {
        // Set up WordPress AJAX environment with force check
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update checker to verify force parameter
            $this->mock_update_checker_force_check();
            
            do_action( 'wp_ajax_cuft_check_update' );
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
     * Test update available response format
     *
     * @test
     */
    public function test_update_available_response_format() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock update available scenario
            $this->mock_update_checker_update_available();
            
            do_action( 'wp_ajax_cuft_check_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for update available
            $this->assertTrue( $response['success'] );
            $this->assertTrue( $response['data']['update_available'] );
            $this->assertArrayHasKey( 'changelog', $response['data'] );
            $this->assertArrayHasKey( 'download_size', $response['data'] );
            $this->assertArrayHasKey( 'published_date', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test no update available response format
     *
     * @test
     */
    public function test_no_update_available_response_format() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock no update available scenario
            $this->mock_update_checker_no_update();
            
            do_action( 'wp_ajax_cuft_check_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for no update available
            $this->assertTrue( $response['success'] );
            $this->assertFalse( $response['data']['update_available'] );
            $this->assertEquals( 'You have the latest version', $response['data']['message'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test GitHub API error handling
     *
     * @test
     */
    public function test_github_api_error_handling() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock GitHub API error
            $this->mock_update_checker_api_error();
            
            do_action( 'wp_ajax_cuft_check_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for API error
            $this->assertFalse( $response['success'] );
            $this->assertArrayHasKey( 'message', $response['data'] );
            $this->assertArrayHasKey( 'code', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock update checker success response
     */
    private function mock_update_checker_success() {
        // This would mock the CUFT_Update_Checker::check() method
        // For now, we'll test the actual implementation
    }

    /**
     * Mock update checker force check
     */
    private function mock_update_checker_force_check() {
        // This would mock the CUFT_Update_Checker::check() method with force=true
        // For now, we'll test the actual implementation
    }

    /**
     * Mock update available scenario
     */
    private function mock_update_checker_update_available() {
        // This would mock the CUFT_Update_Checker::check() method to return update available
        // For now, we'll test the actual implementation
    }

    /**
     * Mock no update available scenario
     */
    private function mock_update_checker_no_update() {
        // This would mock the CUFT_Update_Checker::check() method to return no update
        // For now, we'll test the actual implementation
    }

    /**
     * Mock GitHub API error
     */
    private function mock_update_checker_api_error() {
        // This would mock the CUFT_Update_Checker::check() method to return API error
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
        
        // Clear any existing transients
        delete_transient( 'cuft_update_status' );
        delete_transient( 'cuft_github_version' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up POST data
        unset( $_POST['action'] );
        unset( $_POST['nonce'] );
        unset( $_POST['force'] );
        
        // Clear transients
        delete_transient( 'cuft_update_status' );
        delete_transient( 'cuft_github_version' );
        
        parent::tearDown();
    }
}
