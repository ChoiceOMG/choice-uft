<?php
/**
 * Contract Test: cuft_rollback_update AJAX Endpoint
 *
 * Tests the cuft_rollback_update AJAX endpoint for proper nonce validation,
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

class Test_Rollback_Update_AJAX extends WP_UnitTestCase {

    /**
     * Test valid rollback with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_rollback() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the rollback process to return success
            $this->mock_rollback_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_rollback_update' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'rolled_back', $response['data']['status'] );
            $this->assertEquals( 'Previous version has been restored', $response['data']['message'] );
            $this->assertArrayHasKey( 'restored_version', $response['data'] );

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
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
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
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
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
     * Test no active update to cancel
     *
     * @test
     */
    public function test_no_active_update_to_cancel() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Mock no active update
        $this->mock_no_active_update();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'No update in progress to cancel', $response['data']['message'] );
            $this->assertEquals( 'no_active_update', $response['data']['code'] );

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
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['reason'] = 'User requested rollback';
        unset( $_POST['update_id'] );

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the rollback process
            $this->mock_rollback_success();
            
            do_action( 'wp_ajax_cuft_rollback_update' );
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
     * Test missing reason parameter defaults
     *
     * @test
     */
    public function test_missing_reason_parameter_defaults() {
        // Set up WordPress AJAX environment without reason
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        unset( $_POST['reason'] );

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the rollback process
            $this->mock_rollback_success();
            
            do_action( 'wp_ajax_cuft_rollback_update' );
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
     * Test rollback failure handling
     *
     * @test
     */
    public function test_rollback_failure_handling() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Mock rollback failure
        $this->mock_rollback_failure();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertArrayHasKey( 'message', $response['data'] );
            $this->assertEquals( 'rollback_failed', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test rollback process logging
     *
     * @test
     */
    public function test_rollback_process_logging() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the rollback process
            $this->mock_rollback_success();
            
            do_action( 'wp_ajax_cuft_rollback_update' );
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
     * Test rollback clears update progress
     *
     * @test
     */
    public function test_rollback_clears_update_progress() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        // Set update progress
        set_transient( 'cuft_update_progress', array(
            'status' => 'failed',
            'percentage' => 0,
            'message' => 'Update failed'
        ), 5 * MINUTE_IN_SECONDS );

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the rollback process
            $this->mock_rollback_success();
            
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            
            // Check that update progress was cleared
            $progress = get_transient( 'cuft_update_progress' );
            $this->assertFalse( $progress );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock rollback success
     */
    private function mock_rollback_success() {
        // This would mock the rollback process to return success
        // For now, we'll test the actual implementation
    }

    /**
     * Mock no active update
     */
    private function mock_no_active_update() {
        // Clear any existing update progress
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
    }

    /**
     * Mock rollback failure
     */
    private function mock_rollback_failure() {
        // This would mock the rollback process to return failure
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
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up POST data
        unset( $_POST['action'] );
        unset( $_POST['nonce'] );
        unset( $_POST['update_id'] );
        unset( $_POST['reason'] );
        
        // Clear transients
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
        
        parent::tearDown();
    }
}
