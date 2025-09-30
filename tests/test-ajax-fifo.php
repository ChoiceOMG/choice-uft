<?php
/**
 * Contract Test: AJAX FIFO Cleanup (100-event limit)
 *
 * Tests that event array is limited to 100 events with FIFO (First In First Out) cleanup.
 * This test MUST FAIL until T009 (FIFO cleanup) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_FIFO extends WP_UnitTestCase {

    /**
     * Test 100-event limit enforced
     *
     * Contract: /specs/migrations/click-tracking-events/contracts/ajax-endpoint.md
     *
     * @test
     */
    public function test_100_event_limit_enforced() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_fifo_' . time();

        // Create record with 100 events
        $events = array();
        for ( $i = 1; $i <= 100; $i++ ) {
            $events[] = array(
                'event' => 'form_submit',
                'timestamp' => gmdate( 'c', strtotime( "-{$i} minutes" ) ),
            );
        }

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => json_encode( $events ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Add one more event (should trigger FIFO cleanup)
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'generate_lead';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertEquals( 100, $data['data']['event_count'], 'Event count should be capped at 100' );

        // Verify in database
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $stored_events = json_decode( $record->events, true );
        $this->assertCount( 100, $stored_events, 'Should have exactly 100 events' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test oldest events removed (FIFO)
     *
     * @test
     */
    public function test_oldest_events_removed() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_fifo_oldest_' . time();

        // Create record with 100 events (oldest first)
        $events = array();
        for ( $i = 100; $i >= 1; $i-- ) {
            $events[] = array(
                'event' => 'phone_click',
                'timestamp' => gmdate( 'c', strtotime( "-{$i} hours" ) ),
            );
        }

        $oldest_timestamp = $events[0]['timestamp'];
        $second_oldest_timestamp = $events[1]['timestamp'];

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => json_encode( $events ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Add new event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'email_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        ob_get_clean();

        // Verify oldest event removed
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $stored_events = json_decode( $record->events, true );
        $timestamps = array_column( $stored_events, 'timestamp' );

        $this->assertNotContains( $oldest_timestamp, $timestamps, 'Oldest event should be removed' );
        $this->assertContains( $second_oldest_timestamp, $timestamps, 'Second oldest should remain' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test newest event added after FIFO cleanup
     *
     * @test
     */
    public function test_newest_event_added_after_cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_fifo_newest_' . time();

        // Create record with 100 events
        $events = array();
        for ( $i = 1; $i <= 100; $i++ ) {
            $events[] = array(
                'event' => 'form_submit',
                'timestamp' => gmdate( 'c', strtotime( "-{$i} minutes" ) ),
            );
        }

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => json_encode( $events ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Add generate_lead event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'generate_lead';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        ob_get_clean();

        // Verify generate_lead event exists
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $stored_events = json_decode( $record->events, true );
        $event_types = array_column( $stored_events, 'event' );

        $this->assertContains( 'generate_lead', $event_types, 'New event should be added' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test date_updated reflects newest event after cleanup
     *
     * @test
     */
    public function test_date_updated_reflects_newest_after_cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_fifo_date_' . time();

        // Create record with 100 events
        $events = array();
        for ( $i = 1; $i <= 100; $i++ ) {
            $events[] = array(
                'event' => 'phone_click',
                'timestamp' => gmdate( 'c', strtotime( "-{$i} hours" ) ),
            );
        }

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => json_encode( $events ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        $old_date_updated = $wpdb->get_var( $wpdb->prepare(
            "SELECT date_updated FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        sleep( 2 );

        // Add new event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        ob_get_clean();

        // Verify date_updated is newer
        $new_date_updated = $wpdb->get_var( $wpdb->prepare(
            "SELECT date_updated FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertGreaterThan(
            strtotime( $old_date_updated ),
            strtotime( $new_date_updated ),
            'date_updated should reflect newest event'
        );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test multiple events over limit trigger multiple removals
     *
     * @test
     */
    public function test_multiple_events_over_limit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'test_fifo_multi_' . time();

        // Create record with 98 events
        $events = array();
        for ( $i = 1; $i <= 98; $i++ ) {
            $events[] = array(
                'event' => 'email_click',
                'timestamp' => gmdate( 'c', strtotime( "-{$i} minutes" ) ),
            );
        }

        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'events' => json_encode( $events ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Add 5 more events (should stay at 100)
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        for ( $i = 0; $i < 5; $i++ ) {
            $_POST['event_type'] = ( $i % 2 === 0 ) ? 'phone_click' : 'form_submit';
            ob_start();
            do_action( 'wp_ajax_cuft_record_event' );
            ob_get_clean();
        }

        // Verify count is 100
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $stored_events = json_decode( $record->events, true );
        $this->assertCount( 100, $stored_events, 'Should maintain 100 event limit' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running AJAX FIFO Cleanup Contract Tests...\n";
    echo "These tests MUST FAIL until T009 (FIFO cleanup) is implemented.\n\n";

    $test = new Test_AJAX_FIFO();

    $tests = array(
        'test_100_event_limit_enforced',
        'test_oldest_events_removed',
        'test_newest_event_added_after_cleanup',
        'test_date_updated_reflects_newest_after_cleanup',
        'test_multiple_events_over_limit',
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