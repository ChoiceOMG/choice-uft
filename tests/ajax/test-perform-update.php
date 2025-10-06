<?php
/**
 * Contract Test: cuft_perform_update AJAX Endpoint
 *
 * Tests the cuft_perform_update AJAX endpoint for proper nonce validation,
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

class Test_Perform_Update_AJAX extends WP_UnitTestCase {

    /**
     * Test valid update performance with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_perform_update() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update process to return success
            $this->mock_update_process_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_perform_update' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 'started', $response['data']['status'] );
            $this->assertArrayHasKey( 'update_id', $response['data'] );
            $this->assertEquals( 'Update process started', $response['data']['message'] );

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
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
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
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
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
     * Test update already in progress rejection
     *
     * @test
     */
    public function test_update_already_in_progress_rejection() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Mock update already in progress
        $this->mock_update_in_progress();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertEquals( 'Update already in progress', $response['data']['message'] );
            $this->assertEquals( 'update_in_progress', $response['data']['code'] );
            $this->assertArrayHasKey( 'current_status', $response['data'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test version parameter handling
     *
     * @test
     */
    public function test_version_parameter_handling() {
        // Test with specific version
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = '3.15.0';
        $_POST['backup'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update process
            $this->mock_update_process_success();
            
            do_action( 'wp_ajax_cuft_perform_update' );
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
     * Test backup parameter handling
     *
     * @test
     */
    public function test_backup_parameter_handling() {
        // Test with backup disabled
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'false';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update process
            $this->mock_update_process_success();
            
            do_action( 'wp_ajax_cuft_perform_update' );
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
     * Test missing version parameter defaults to latest
     *
     * @test
     */
    public function test_missing_version_defaults_to_latest() {
        // Set up WordPress AJAX environment without version
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['backup'] = 'true';
        unset( $_POST['version'] );

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update process
            $this->mock_update_process_success();
            
            do_action( 'wp_ajax_cuft_perform_update' );
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
     * Test update process failure handling
     *
     * @test
     */
    public function test_update_process_failure_handling() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Mock update process failure
        $this->mock_update_process_failure();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_perform_update' );
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
     * Test update ID generation
     *
     * @test
     */
    public function test_update_id_generation() {
        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_perform_update';
        $_POST['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_POST['version'] = 'latest';
        $_POST['backup'] = 'true';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the update process
            $this->mock_update_process_success();
            
            do_action( 'wp_ajax_cuft_perform_update' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertStringStartsWith( 'update_', $response['data']['update_id'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock update process success
     */
    private function mock_update_process_success() {
        // This would mock the update process to return success
        // For now, we'll test the actual implementation
    }

    /**
     * Mock update in progress
     */
    private function mock_update_in_progress() {
        // Set transient to simulate update in progress
        set_transient( 'cuft_update_in_progress', array(
            'status' => 'downloading',
            'percentage' => 30,
            'message' => 'Downloading update...'
        ), 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Mock update process failure
     */
    private function mock_update_process_failure() {
        // This would mock the update process to return failure
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
        delete_transient( 'cuft_update_in_progress' );
        delete_transient( 'cuft_update_progress' );
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
        
        // Clear transients
        delete_transient( 'cuft_update_in_progress' );
        delete_transient( 'cuft_update_progress' );
        
        parent::tearDown();
    }
}
