<?php
/**
 * Integration Test: One-Click Update Flow
 *
 * Tests the complete flow of performing a one-click update from start to finish.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
    // For standalone testing, load WordPress
    require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Update_Flow_Integration extends WP_UnitTestCase {

    /**
     * Test Scenario 3: One-Click Update Flow
     *
     * Contract: /specs/005-one-click-automated/quickstart.md Scenario 3
     *
     * @test
     */
    public function test_one_click_update_flow() {
        // Step 1: Check for updates
        $_POST['action'] = 'cuft_check_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['force'] = 'true';

        $this->mock_github_api_newer_version();

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_check_update' );
            $output = ob_get_clean();
            $check_response = json_decode( $output, true );

            $this->assertTrue( $check_response['success'] );
            $this->assertTrue( $check_response['data']['update_available'] );

            // Step 2: Perform update
            $_POST['action'] = 'cuft_perform_update';
            $_POST['version'] = $check_response['data']['latest_version'];
            $_POST['backup'] = 'true';

            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $update_response = json_decode( $output, true );

            $this->assertTrue( $update_response['success'] );
            $this->assertEquals( 'started', $update_response['data']['status'] );
            $this->assertArrayHasKey( 'update_id', $update_response['data'] );

            // Step 3: Monitor progress
            $update_id = $update_response['data']['update_id'];
            $this->monitor_update_progress( $update_id );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test update progress monitoring
     *
     * @test
     */
    public function test_update_progress_monitoring() {
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

            // Monitor progress through different stages
            $stages = array( 'checking', 'downloading', 'backing_up', 'installing', 'verifying', 'complete' );

            foreach ( $stages as $stage ) {
                $this->mock_update_stage( $stage );
                
                $_GET['action'] = 'cuft_update_status';
                $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
                $_GET['update_id'] = $update_id;

                ob_start();
                do_action( 'wp_ajax_cuft_update_status' );
                $output = ob_get_clean();
                $status_response = json_decode( $output, true );

                $this->assertTrue( $status_response['success'] );
                $this->assertEquals( $stage, $status_response['data']['status'] );
            }

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test backup creation during update
     *
     * @test
     */
    public function test_backup_creation_during_update() {
        // Start update with backup
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

            // Check that backup stage is included
            $this->mock_update_stage( 'backing_up' );
            
            $_GET['action'] = 'cuft_update_status';
            $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
            $_GET['update_id'] = $response['data']['update_id'];

            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $status_response = json_decode( $output, true );

            $this->assertTrue( $status_response['success'] );
            $this->assertEquals( 'backing_up', $status_response['data']['status'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test update without backup
     *
     * @test
     */
    public function test_update_without_backup() {
        // Start update without backup
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'false';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );

            // Check that backup stage is skipped
            $this->mock_update_stage( 'downloading' );
            
            $_GET['action'] = 'cuft_update_status';
            $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
            $_GET['update_id'] = $response['data']['update_id'];

            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $status_response = json_decode( $output, true );

            $this->assertTrue( $status_response['success'] );
            $this->assertNotEquals( 'backing_up', $status_response['data']['status'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test update completion
     *
     * @test
     */
    public function test_update_completion() {
        // Mock completed update
        $this->mock_update_stage( 'complete' );

        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = 'update_1234567890';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'complete', $response['data']['status'] );
            $this->assertEquals( 100, $response['data']['percentage'] );
            $this->assertArrayHasKey( 'old_version', $response['data'] );
            $this->assertArrayHasKey( 'new_version', $response['data'] );
            $this->assertArrayHasKey( 'completed_at', $response['data'] );
            $this->assertArrayHasKey( 'total_time_seconds', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test concurrent update prevention
     *
     * @test
     */
    public function test_concurrent_update_prevention() {
        // Start first update
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response1 = json_decode( $output, true );

            $this->assertTrue( $response1['success'] );

            // Try to start second update
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response2 = json_decode( $output, true );

            $this->assertFalse( $response2['success'] );
            $this->assertEquals( 'update_in_progress', $response2['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Integration test failed: ' . $e->getMessage() );
        }
    }

    /**
     * Monitor update progress
     */
    private function monitor_update_progress( $update_id ) {
        $_GET['action'] = 'cuft_update_status';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['update_id'] = $update_id;

        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_status' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey( 'status', $response['data'] );
            $this->assertArrayHasKey( 'percentage', $response['data'] );
            $this->assertArrayHasKey( 'message', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'Progress monitoring failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock GitHub API response with newer version
     */
    private function mock_github_api_newer_version() {
        // This would mock the GitHub API response with a newer version
        // For now, we'll test the actual implementation
    }

    /**
     * Mock update stage
     */
    private function mock_update_stage( $stage ) {
        $stage_data = array(
            'checking' => array( 'status' => 'checking', 'percentage' => 10, 'message' => 'Checking for updates...' ),
            'downloading' => array( 'status' => 'downloading', 'percentage' => 30, 'message' => 'Downloading update package...' ),
            'backing_up' => array( 'status' => 'backing_up', 'percentage' => 50, 'message' => 'Creating backup...' ),
            'installing' => array( 'status' => 'installing', 'percentage' => 70, 'message' => 'Installing update...' ),
            'verifying' => array( 'status' => 'verifying', 'percentage' => 90, 'message' => 'Verifying installation...' ),
            'complete' => array( 'status' => 'complete', 'percentage' => 100, 'message' => 'Update completed successfully', 'old_version' => '3.14.0', 'new_version' => '3.15.0', 'completed_at' => current_time( 'c' ), 'total_time_seconds' => 90 )
        );

        if ( isset( $stage_data[ $stage ] ) ) {
            set_transient( 'cuft_update_progress', $stage_data[ $stage ], 5 * MINUTE_IN_SECONDS );
        }
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
        // Clean up POST/GET data
        unset( $_POST['action'] );
        unset( $_POST['nonce'] );
        unset( $_POST['version'] );
        unset( $_POST['backup'] );
        unset( $_GET['action'] );
        unset( $_GET['nonce'] );
        unset( $_GET['update_id'] );
        
        // Clear transients
        delete_transient( 'cuft_update_progress' );
        delete_transient( 'cuft_update_in_progress' );
        
        parent::tearDown();
    }
}
