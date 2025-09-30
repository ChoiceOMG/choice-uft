<?php
/**
 * Contract Test: AJAX Event Deduplication
 *
 * Tests event deduplication - duplicate event types should update timestamp, not append.
 * This test MUST FAIL until T009 (deduplication logic) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_Deduplication extends WP_UnitTestCase {

    /**
     * Test duplicate event type updates timestamp
     *
     * Contract: /specs/migrations/click-tracking-events/contracts/ajax-endpoint.md
     *
     * @test
     */
    public function test_duplicate_event_updates_timestamp() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_dedup_' . time();

        // Create record with initial phone_click event
        $first_timestamp = gmdate( 'c', strtotime( '-5 minutes' ) );
        $initial_events = json_encode( array(
            array( 'event' => 'phone_click', 'timestamp' => $first_timestamp ),
        ) );

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => $initial_events,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Record duplicate phone_click event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        sleep( 1 ); // Ensure different timestamp

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Duplicate event recording should succeed' );

        // Verify only ONE phone_click event exists with UPDATED timestamp
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $events = json_decode( $record->events, true );
        $this->assertCount( 1, $events, 'Should still have only 1 event (no duplicate appended)' );
        $this->assertEquals( 'phone_click', $events[0]['event'], 'Event type should be phone_click' );
        $this->assertNotEquals( $first_timestamp, $events[0]['timestamp'], 'Timestamp should be updated (not original)' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test different event types are appended
     *
     * @test
     */
    public function test_different_events_are_appended() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_append_' . time();

        // Create record with phone_click
        $initial_events = json_encode( array(
            array( 'event' => 'phone_click', 'timestamp' => gmdate( 'c' ) ),
        ) );

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => $initial_events,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Record different event type (email_click)
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'email_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        ob_get_clean();

        // Verify TWO events exist
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $events = json_decode( $record->events, true );
        $this->assertCount( 2, $events, 'Should have 2 events (different types appended)' );

        $event_types = array_column( $events, 'event' );
        $this->assertContains( 'phone_click', $event_types, 'Should contain phone_click' );
        $this->assertContains( 'email_click', $event_types, 'Should contain email_click' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test date_updated reflects most recent event
     *
     * @test
     */
    public function test_date_updated_reflects_latest_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_date_update_' . time();

        // Create record
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        $original_date = $wpdb->get_var( $wpdb->prepare(
            "SELECT date_updated FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        sleep( 2 ); // Ensure different timestamp

        // Record event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        ob_get_clean();

        // Verify date_updated changed
        $new_date = $wpdb->get_var( $wpdb->prepare(
            "SELECT date_updated FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertNotEquals( $original_date, $new_date, 'date_updated should be updated' );
        $this->assertGreaterThan( strtotime( $original_date ), strtotime( $new_date ), 'New date should be later' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test multiple duplicate events only keep latest timestamp
     *
     * @test
     */
    public function test_multiple_duplicates_keep_latest() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_multi_dedup_' . time();

        // Create record
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        // Record same event 3 times
        for ( $i = 0; $i < 3; $i++ ) {
            sleep( 1 );
            ob_start();
            do_action( 'wp_ajax_cuft_record_event' );
            ob_get_clean();
        }

        // Verify only ONE event exists
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $events = json_decode( $record->events, true );
        $this->assertCount( 1, $events, 'Should have only 1 event despite 3 recordings' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running AJAX Deduplication Contract Tests...\n";
    echo "These tests MUST FAIL until T009 (deduplication logic) is implemented.\n\n";

    $test = new Test_AJAX_Deduplication();

    $tests = array(
        'test_duplicate_event_updates_timestamp',
        'test_different_events_are_appended',
        'test_date_updated_reflects_latest_event',
        'test_multiple_duplicates_keep_latest',
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