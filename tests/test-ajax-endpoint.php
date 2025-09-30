<?php
/**
 * Contract Test: AJAX Endpoint Valid Event Recording
 *
 * Tests the cuft_record_event AJAX endpoint for valid event recording.
 * This test MUST FAIL until T013 (AJAX handler) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
    // For standalone testing, load WordPress
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_Endpoint extends WP_UnitTestCase {

    /**
     * Test valid event recording
     *
     * Contract: /specs/migrations/click-tracking-events/contracts/ajax-endpoint.md
     *
     * @test
     */
    public function test_valid_event_recording() {
        // Setup: Create click_id in database
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_click_' . time();

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Set up WordPress AJAX environment
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        // Simulate AJAX request
        try {
            ob_start();
            do_action( 'wp_ajax_cuft_record_event' );
            do_action( 'wp_ajax_nopriv_cuft_record_event' );
            $response = ob_get_clean();

            // Parse JSON response
            $data = json_decode( $response, true );

            // Assertions per contract
            $this->assertIsArray( $data, 'Response should be JSON array' );
            $this->assertTrue( $data['success'], 'Response should indicate success' );
            $this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
            $this->assertArrayHasKey( 'message', $data['data'], 'Response should have message' );
            $this->assertArrayHasKey( 'event_count', $data['data'], 'Response should have event_count' );
            $this->assertEquals( 1, $data['data']['event_count'], 'Event count should be 1' );

            // Verify event recorded in database
            $record = $wpdb->get_row( $wpdb->prepare(
                "SELECT events FROM {$table} WHERE click_id = %s",
                $click_id
            ) );

            $this->assertNotNull( $record, 'Record should exist in database' );
            $this->assertNotNull( $record->events, 'Events should not be null' );

            $events = json_decode( $record->events, true );
            $this->assertIsArray( $events, 'Events should be array' );
            $this->assertCount( 1, $events, 'Should have 1 event' );
            $this->assertEquals( 'phone_click', $events[0]['event'], 'Event type should match' );
            $this->assertArrayHasKey( 'timestamp', $events[0], 'Event should have timestamp' );

        } catch ( Exception $e ) {
            $this->fail( 'AJAX endpoint threw exception: ' . $e->getMessage() );
        }

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test event recording returns event_count
     *
     * @test
     */
    public function test_event_count_returned() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_click_count_' . time();

        // Create record with 2 existing events
        $existing_events = json_encode( array(
            array( 'event' => 'phone_click', 'timestamp' => gmdate( 'c' ) ),
            array( 'event' => 'email_click', 'timestamp' => gmdate( 'c' ) ),
        ) );

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => $existing_events,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Record new event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        // Should return count of 3
        $this->assertEquals( 3, $data['data']['event_count'], 'Event count should be 3' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test event recording creates new record if click_id doesn't exist
     *
     * @test
     */
    public function test_creates_record_if_not_exists() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_new_' . time();

        // Ensure record doesn't exist
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );

        // Record event for non-existent click_id
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        // Should create record and return success
        $this->assertTrue( $data['success'], 'Should succeed even if record did not exist' );

        // Verify record was created
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertNotNull( $record, 'Record should have been created' );
        $events = json_decode( $record->events, true );
        $this->assertCount( 1, $events, 'Should have 1 event' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running AJAX Endpoint Contract Tests...\n";
    echo "These tests MUST FAIL until T013 (AJAX handler) is implemented.\n\n";

    $test = new Test_AJAX_Endpoint();

    try {
        $test->test_valid_event_recording();
        echo "❌ UNEXPECTED: test_valid_event_recording PASSED (should fail before implementation)\n";
    } catch ( Exception $e ) {
        echo "✅ EXPECTED: test_valid_event_recording FAILED: " . $e->getMessage() . "\n";
    }

    try {
        $test->test_event_count_returned();
        echo "❌ UNEXPECTED: test_event_count_returned PASSED (should fail before implementation)\n";
    } catch ( Exception $e ) {
        echo "✅ EXPECTED: test_event_count_returned FAILED: " . $e->getMessage() . "\n";
    }

    try {
        $test->test_creates_record_if_not_exists();
        echo "❌ UNEXPECTED: test_creates_record_if_not_exists PASSED (should fail before implementation)\n";
    } catch ( Exception $e ) {
        echo "✅ EXPECTED: test_creates_record_if_not_exists FAILED: " . $e->getMessage() . "\n";
    }
}