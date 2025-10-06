<?php
/**
 * Contract Test: cuft_update_history AJAX Endpoint
 *
 * Tests the cuft_update_history AJAX endpoint for proper nonce validation,
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

class Test_Update_History_AJAX extends WP_UnitTestCase {

    /**
     * Test valid history request with proper nonce
     *
     * Contract: /specs/005-one-click-automated/contracts/ajax-endpoints.md
     *
     * @test
     */
    public function test_valid_history_request() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the history request to return success
            $this->mock_history_success();
            
            // Call the AJAX handler
            do_action( 'wp_ajax_cuft_update_history' );
            
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertArrayHasKey( 'total', $response['data'] );
            $this->assertArrayHasKey( 'limit', $response['data'] );
            $this->assertArrayHasKey( 'offset', $response['data'] );
            $this->assertArrayHasKey( 'entries', $response['data'] );
            $this->assertIsArray( $response['data']['entries'] );

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
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = 'invalid_nonce';
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_history' );
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
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_history' );
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
     * Test default limit and offset parameters
     *
     * @test
     */
    public function test_default_limit_and_offset_parameters() {
        // Set up WordPress AJAX environment without limit/offset
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        unset( $_GET['limit'] );
        unset( $_GET['offset'] );

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the history request
            $this->mock_history_success();
            
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 10, $response['data']['limit'] ); // Default limit
            $this->assertEquals( 0, $response['data']['offset'] ); // Default offset

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test custom limit and offset parameters
     *
     * @test
     */
    public function test_custom_limit_and_offset_parameters() {
        // Set up WordPress AJAX environment with custom limit/offset
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '5';
        $_GET['offset'] = '10';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the history request
            $this->mock_history_success();
            
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 5, $response['data']['limit'] );
            $this->assertEquals( 10, $response['data']['offset'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test history entries format
     *
     * @test
     */
    public function test_history_entries_format() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Mock history with sample entries
        $this->mock_history_with_entries();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertGreaterThan( 0, count( $response['data']['entries'] ) );

            // Check first entry format
            $first_entry = $response['data']['entries'][0];
            $this->assertArrayHasKey( 'id', $first_entry );
            $this->assertArrayHasKey( 'timestamp', $first_entry );
            $this->assertArrayHasKey( 'action', $first_entry );
            $this->assertArrayHasKey( 'status', $first_entry );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test empty history response
     *
     * @test
     */
    public function test_empty_history_response() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Mock empty history
        $this->mock_empty_history();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 0, $response['data']['total'] );
            $this->assertEmpty( $response['data']['entries'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test history request failure handling
     *
     * @test
     */
    public function test_history_request_failure_handling() {
        // Set up WordPress AJAX environment
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '0';

        // Mock history request failure
        $this->mock_history_failure();

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertFalse( $response['success'] );
            $this->assertArrayHasKey( 'message', $response['data'] );
            $this->assertEquals( 'history_failed', $response['data']['code'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test pagination with large offset
     *
     * @test
     */
    public function test_pagination_with_large_offset() {
        // Set up WordPress AJAX environment with large offset
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = '10';
        $_GET['offset'] = '1000';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the history request
            $this->mock_history_success();
            
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 1000, $response['data']['offset'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Test invalid limit parameter handling
     *
     * @test
     */
    public function test_invalid_limit_parameter_handling() {
        // Set up WordPress AJAX environment with invalid limit
        $_GET['action'] = 'cuft_update_history';
        $_GET['nonce'] = wp_create_nonce( 'cuft_updater_nonce' );
        $_GET['limit'] = 'invalid';
        $_GET['offset'] = '0';

        // Simulate AJAX request
        try {
            ob_start();
            
            // Mock the history request
            $this->mock_history_success();
            
            do_action( 'wp_ajax_cuft_update_history' );
            $output = ob_get_clean();
            $response = json_decode( $output, true );

            // Assertions - should default to 10
            $this->assertTrue( $response['success'] );
            $this->assertEquals( 10, $response['data']['limit'] );

        } catch ( Exception $e ) {
            ob_end_clean();
            $this->fail( 'AJAX request failed: ' . $e->getMessage() );
        }
    }

    /**
     * Mock history success
     */
    private function mock_history_success() {
        // This would mock the history request to return success
        // For now, we'll test the actual implementation
    }

    /**
     * Mock history with entries
     */
    private function mock_history_with_entries() {
        // Create sample log entries
        $log_entries = array(
            array(
                'id' => 123,
                'timestamp' => current_time( 'c' ),
                'action' => 'update_completed',
                'status' => 'success',
                'version_from' => '3.13.0',
                'version_to' => '3.14.0',
                'user' => 'admin',
                'details' => 'Update completed in 85 seconds'
            ),
            array(
                'id' => 122,
                'timestamp' => date( 'c', strtotime( '-1 day' ) ),
                'action' => 'check_completed',
                'status' => 'info',
                'message' => 'No updates available'
            )
        );

        // Store in options for testing
        update_option( 'cuft_update_log', $log_entries );
    }

    /**
     * Mock empty history
     */
    private function mock_empty_history() {
        // Clear any existing log entries
        delete_option( 'cuft_update_log' );
    }

    /**
     * Mock history failure
     */
    private function mock_history_failure() {
        // This would mock the history request to return failure
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
        
        // Clear any existing log entries
        delete_option( 'cuft_update_log' );
    }

    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Clean up GET data
        unset( $_GET['action'] );
        unset( $_GET['nonce'] );
        unset( $_GET['limit'] );
        unset( $_GET['offset'] );
        
        // Clear log entries
        delete_option( 'cuft_update_log' );
        
        parent::tearDown();
    }
}
