<?php
/**
 * Contract Test: cuft_update_status AJAX Endpoint
 *
 * Tests the cuft_update_status AJAX endpoint for proper nonce validation,
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

class Test_Update_Status_AJAX extends WP_UnitTestCase {

    /**
     * Test valid status check with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_status_check() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the status check to return success
            $this->mock_status_check_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_update_status' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey( 'status', $response['data'] );
            $this->assertArrayHasKey( 'percentage', $response['data'] );
            $this->assertArrayHasKey( 'message', $response['data'] );

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
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = 'invalid_nonce';
        $_GET['update_id'] = 'update_1234567890';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
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
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
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
     * Test in-progress status response format
     *
     * @test
     */
    public function test_in_progress_status_response_format() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Mock in-progress status
        $this->mock_in_progress_status();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for in-progress status
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'downloading', $response['data']['status'] );
            $this->assertEquals( 45, $response['data']['percentage'] );
            $this->assertEquals( 'Downloading update package...', $response['data']['message'] );
            $this->assertArrayHasKey( 'started_at', $response['data'] );
            $this->assertArrayHasKey( 'elapsed_seconds', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test complete status response format
     *
     * @test
     */
    public function test_complete_status_response_format() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Mock complete status
        $this->mock_complete_status();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for complete status
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'complete', $response['data']['status'] );
            $this->assertEquals( 100, $response['data']['percentage'] );
            $this->assertEquals( 'Update completed successfully', $response['data']['message'] );
            $this->assertArrayHasKey( 'old_version', $response['data'] );
            $this->assertArrayHasKey( 'new_version', $response['data'] );
            $this->assertArrayHasKey( 'completed_at', $response['data'] );
            $this->assertArrayHasKey( 'total_time_seconds', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test failed status response format
     *
     * @test
     */
    public function test_failed_status_response_format() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Mock failed status
        $this->mock_failed_status();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions for failed status
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'failed', $response['data']['status'] );
            $this->assertEquals( 'Update failed and was rolled back', $response['data']['message'] );
            $this->assertArrayHasKey( 'error', $response['data'] );
            $this->assertArrayHasKey( 'rollback_status', $response['data'] );
            $this->assertArrayHasKey( 'current_version', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test missing update ID parameter
     *
     * @test
     */
    public function test_missing_update_id_parameter() {
        // Set up WordPress AJAX environment without update_id
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        unset( $_GET['update_id'] );

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions - should still work without update_id
            $this->assertTrue( $response['success'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test status check failure handling
     *
     * @test
     */
    public function test_status_check_failure_handling() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        // Mock status check failure
        $this->mock_status_check_failure();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertArrayHasKey( 'message', $response['data'] );
            $this->assertArrayHasKey( 'code', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test different status values
     *
     * @test
     */
    public function test_different_status_values() {
        $statuses = array( 'idle', 'checking', 'downloading', 'backing_up', 'installing', 'verifying', 'complete', 'failed', 'rolling_back' );

        foreach ( $statuses as $status ) {
            // Set up WordPress AJAX environment
            $_GET['action'] = 'cuft_update_status';
            $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
            $_GET['update_id'] = 'update_1234567890';

            // Mock specific status
            $this->mock_specific_status( $status );

            // Simulate AJAX request
            try {
                ob_start();
                do_action( 'wp_ajax_cuft_update_status' );
                $output = ob_get_clean();
                $response = json_decode( $output, true );

                // Assertions
                $this->assertTrue( $response['success'] );
                $this->assertEquals( $status, $response['data']['status'] );

            } catch ( Exception $e ) {
                ob_end_clean();
                $this->fail( 'AJAX request failed for status ' . $status . ': ' . $e->getMessage() );
            }
        }
    }

    /**
     * Mock status check success
     */
    private function mock_status_check_success() {
        // This would mock the status check to return success
        // For now, we'll test the actual implementation
    }

    /**
     * Mock in-progress status
     */
    private function mock_in_progress_status() {
        set_transient( 'cuft_update_progress', array(
            'status' => 'downloading',
            'percentage' => 45,
            'message' => 'Downloading update package...',
            'started_at' => current_time( 'c' ),
            'elapsed_seconds' => 15
        ), 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Mock complete status
     */
    private function mock_complete_status() {
        set_transient( 'cuft_update_progress', array(
            'status' => 'complete',
            'percentage' => 100,
            'message' => 'Update completed successfully',
            'old_version' => '3.14.0',
            'new_version' => '3.15.0',
            'completed_at' => current_time( 'c' ),
            'total_time_seconds' => 90
        ), 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Mock failed status
     */
    private function mock_failed_status() {
        set_transient( 'cuft_update_progress', array(
            'status' => 'failed',
            'message' => 'Update failed and was rolled back',
            'error' => 'File extraction failed',
            'rollback_status' => 'complete',
            'current_version' => '3.14.0'
        ), 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Mock status check failure
     */
    private function mock_status_check_failure() {
        // This would mock the status check to return failure
        // For now, we'll test the actual implementation
    }

    /**
     * Mock specific status
     */
    private function mock_specific_status( $status ) {
        set_transient( 'cuft_update_progress', array(
            'status' => $status,
            'percentage' => 50,
            'message' => 'Test message for ' . $status
        ), 5 * MINUTE_IN_SECONDS );
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
        delete_transient( 'cuft_update_progress' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up GET data
        unset( $_GET['action'] );
        unset( $_GET['nonce'] );
        unset( $_GET['update_id'] );
        
        // Clear transients
        delete_transient( 'cuft_update_progress' );
        
        parent::tearDown();
    }
}
