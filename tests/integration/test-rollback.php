<?php
/**
 * Integration Test: Automatic Rollback
 *
 * Tests the automatic rollback functionality when updates fail.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
    // For standalone testing, load WordPress
    require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Rollback_Integration extends WP_UnitTestCase {

    /**
     * Test Scenario 5: Automatic Rollback on Failure
     *
     * Contract: /specs/005-one-click-automated/quickstart.md Scenario 5
     *
     * @test
     */
    public function test_automatic_rollback_on_failure() {
        // Start update
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );
            $update_id = $response['data']['update_id'];

            // Mock update failure
            $this->mock_update_failure();

            // Check status - should show failed
            $_GET['action'] = 'cuft_update_status';
            $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
            $_GET['update_id'] = $update_id;

            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $status_response = json_decode( $output, true );

            $this->assertTrue( $status_response['success'] );
            $this->assertEquals( 'failed', $status_response['data']['status'] );
            $this->assertArrayHasKey( 'error', $status_response['data'] );
            $this->assertArrayHasKey( 'rollback_status', $status_response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test manual rollback
     *
     * @test
     */
    public function test_manual_rollback() {
        // Start update
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );
            $update_id = $response['data']['update_id'];

            // Perform manual rollback
            $_POST['action'] = 'cuft_rollback_update';
            $_POST['update_id'] = $update_id;
            $_POST['reason'] = 'User requested rollback';

            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $rollback_response = json_decode( $output, true );

            $this->assertTrue( $rollback_response['success'] );
            $this->assertEquals( 'rolled_back', $rollback_response['data']['status'] );
            $this->assertEquals( 'Previous version has been restored', $rollback_response['data']['message'] );
            $this->assertArrayHasKey( 'restored_version', $rollback_response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test rollback when no update in progress
     *
     * @test
     */
    public function test_rollback_when_no_update_in_progress() {
        // Try to rollback when no update is in progress
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Should fail with appropriate message
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'no_active_update', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test rollback failure handling
     *
     * @test
     */
    public function test_rollback_failure_handling() {
        // Start update
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );
            $update_id = $response['data']['update_id'];

            // Mock rollback failure
            $this->mock_rollback_failure();

            // Try to rollback
            $_POST['action'] = 'cuft_rollback_update';
            $_POST['update_id'] = $update_id;
            $_POST['reason'] = 'User requested rollback';

            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $rollback_response = json_decode( $output, true );

            $this->assertFalse( $rollback_response['success'] );
            $this->assertEquals( 'rollback_failed', $rollback_response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test rollback clears update progress
     *
     * @test
     */
    public function test_rollback_clears_update_progress() {
        // Set update progress
        set_transient( 'cuft_update_progress', array(
            'status' => 'failed',
            'percentage' => 0,
            'message' => 'Update failed'
        ), 5 * MINUTE_IN_SECONDS );

        // Perform rollback
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );

            // Check that update progress was cleared
            $progress = get_transient( 'cuft_update_progress' );
            $this->assertFalse( $progress );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test rollback logging
     *
     * @test
     */
    public function test_rollback_logging() {
        // Perform rollback
        $_POST['action'] = 'cuft_rollback_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['update_id'] = 'update_1234567890';
        $_POST['reason'] = 'User requested rollback';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_rollback_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );

            // Check that rollback was logged
            $log = get_option( 'cuft_update_log', array() );
            $this->assertNotEmpty( $log );

            // Find rollback entry
            $rollback_entry = null;
            foreach ( $log as $entry ) {
                if ( $entry['action'] === 'rollback_started' ) {
                    $rollback_entry = $entry;
                    break;
                }
            }

            $this->assertNotNull( $rollback_entry );
            $this->assertEquals( 'info', $rollback_entry['status'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock update failure
     */
    private function mock_update_failure() {
        set_transient( 'cuft_update_progress', array(
            'status' => 'failed',
            'message' => 'Update failed and was rolled back',
            'error' => 'File extraction failed',
            'rollback_status' => 'complete',
            'current_version' => '3.14.0'
        ), 5 * MINUTE_IN_SECONDS );
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
        
        // Clear any existing transients and logs
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
        delete_option( 'cuft_update_log' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up POST data
        unset( $_POST['action'] );
        unset( $_POST['nonce'] );
        unset( $_POST['version'] );
        unset( $_POST['backup'] );
        unset( $_POST['update_id'] );
        unset( $_POST['reason'] );
        
        // Clear transients and logs
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
        delete_option( 'cuft_update_log' );
        
        parent::tearDown();
    }
}
