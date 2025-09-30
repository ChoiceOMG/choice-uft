<?php
/**
 * Integration Test: Phone Click Event Recording
 *
 * Tests end-to-end phone link click event recording.
 * This test MUST FAIL until T016 (cuft-links.js integration) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_Phone_Click_Integration extends WP_UnitTestCase {

    /**
     * Test phone click records phone_click event
     *
     * Scenario: quickstart.md Step 3
     *
     * @test
     */
    public function test_phone_click_records_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'phone_integration_' . time();

        // Simulate JavaScript fetch to AJAX endpoint (what cuft-links.js will do)
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' ); // nopriv for frontend
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        // Assertions
        $this->assertIsArray( $data, 'Response should be array' );
        $this->assertTrue( $data['success'], 'Phone click recording should succeed' );

        // Verify in database
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertNotNull( $record, 'Record should exist' );
        $this->assertNotNull( $record->events, 'Events should not be null' );

        $events = json_decode( $record->events, true );
        $event_types = array_column( $events, 'event' );

        $this->assertContains( 'phone_click', $event_types, 'Should contain phone_click event' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test email click records email_click event
     *
     * @test
     */
    public function test_email_click_records_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'email_integration_' . time();

        // Simulate email link click
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'email_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Email click recording should succeed' );

        // Verify in database
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $events = json_decode( $record->events, true );
        $event_types = array_column( $events, 'event' );

        $this->assertContains( 'email_click', $event_types, 'Should contain email_click event' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test error isolation - AJAX failure doesn't break functionality
     *
     * @test
     */
    public function test_error_isolation() {
        // Simulate invalid request (should fail gracefully)
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = ''; // Invalid empty click_id
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        // Should return error, but not throw exception
        $this->assertIsArray( $data, 'Should return JSON even on error' );
        $this->assertFalse( $data['success'], 'Should indicate failure' );

        // This test verifies that errors are handled gracefully
        $this->assertTrue( true, 'Error handled without breaking' );
    }

    /**
     * Test with existing click_id
     *
     * @test
     */
    public function test_with_existing_click_id() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'existing_click_' . time();

        // Pre-create record
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Record phone click for existing record
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Should work with existing record' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test without existing click_id (creates new record)
     *
     * @test
     */
    public function test_without_existing_click_id() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'new_click_' . time();

        // Ensure no existing record
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );

        // Record phone click for non-existent record
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Should create new record' );

        // Verify record was created
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertNotNull( $record, 'New record should be created' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running Phone Click Integration Tests...\n";
    echo "These tests MUST FAIL until T016 (cuft-links.js) is implemented.\n\n";

    $test = new Test_Phone_Click_Integration();

    $tests = array(
        'test_phone_click_records_event',
        'test_email_click_records_event',
        'test_error_isolation',
        'test_with_existing_click_id',
        'test_without_existing_click_id',
    );

    foreach ( $tests as $test_name ) {
        try {
            $test->$test_name();
            echo "❌ UNEXPECTED: {$test_name} PASSED (should fail before implementation)\n";
        } catch ( Exception $e ) {
            echo "✅ EXPECTED: {$test_name} FAILED: " . $e->getMessage() . "\n";
        }
    }
}