<?php
/**
 * Unit Tests for CUFT_Event_Replay
 *
 * Verifies that unreplayed webhook events are returned correctly
 * and that marking them as replayed prevents duplicate replay.
 *
 * @package Choice_Universal_Form_Tracker
 * @since   3.22.0
 */

class Test_Event_Replay extends WP_UnitTestCase {

    private static $table_name;

    public static function set_up_before_class() {
        parent::set_up_before_class();
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
    }

    public function set_up() {
        parent::set_up();
        global $wpdb;
        $wpdb->insert( self::$table_name, array(
            'click_id'     => 'test-replay-click',
            'platform'     => 'google',
            'qualified'    => 1,
            'score'        => 0,
            'ga_client_id' => '111.222',
            'events'       => wp_json_encode( array(
                array(
                    'event'     => 'form_submit',
                    'timestamp' => '2026-04-01T10:00:00+00:00',
                ),
                array(
                    'event'       => 'qualify_lead',
                    'timestamp'   => '2026-04-02T14:00:00+00:00',
                    'source'      => 'webhook',
                    'replayed_at' => null,
                ),
            ) ),
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-replay-click' ) );
        parent::tear_down();
    }

    public function test_get_pending_events_returns_unreplayed_webhook_events() {
        $pending = CUFT_Event_Replay::get_pending_events( 'test-replay-click' );
        $this->assertCount( 1, $pending );
        $this->assertEquals( 'qualify_lead', $pending[0]['event'] );
        $this->assertEquals( 'webhook', $pending[0]['source'] );
    }

    public function test_get_pending_events_excludes_non_webhook_events() {
        $pending = CUFT_Event_Replay::get_pending_events( 'test-replay-click' );
        foreach ( $pending as $event ) {
            $this->assertEquals( 'webhook', $event['source'] );
        }
    }

    public function test_get_pending_events_returns_empty_for_unknown_click_id() {
        $pending = CUFT_Event_Replay::get_pending_events( 'nonexistent-click-id' );
        $this->assertCount( 0, $pending );
    }

    public function test_mark_events_replayed_sets_timestamp() {
        CUFT_Event_Replay::mark_events_replayed( 'test-replay-click' );
        $pending = CUFT_Event_Replay::get_pending_events( 'test-replay-click' );
        $this->assertCount( 0, $pending );
    }

    public function test_mark_events_replayed_preserves_non_webhook_events() {
        CUFT_Event_Replay::mark_events_replayed( 'test-replay-click' );

        global $wpdb;
        $table = self::$table_name;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s",
            'test-replay-click'
        ) );

        $events = json_decode( $row->events, true );
        $this->assertCount( 2, $events );
        // form_submit should be untouched (no source, no replayed_at)
        $this->assertEquals( 'form_submit', $events[0]['event'] );
        $this->assertArrayNotHasKey( 'source', $events[0] );
    }

    public function test_add_event_with_webhook_source_includes_replay_fields() {
        CUFT_Click_Tracker::add_event( 'test-replay-click', 'working_lead', 'webhook' );

        $events = CUFT_Click_Tracker::get_events( 'test-replay-click' );
        $working = null;
        foreach ( $events as $event ) {
            if ( 'working_lead' === $event['event'] ) {
                $working = $event;
                break;
            }
        }

        $this->assertNotNull( $working, 'working_lead event should exist' );
        $this->assertEquals( 'webhook', $working['source'] );
        $this->assertArrayHasKey( 'replayed_at', $working );
        $this->assertNull( $working['replayed_at'] );
    }

    public function test_add_event_without_source_has_no_replay_fields() {
        CUFT_Click_Tracker::add_event( 'test-replay-click', 'phone_click' );

        $events = CUFT_Click_Tracker::get_events( 'test-replay-click' );
        $phone = null;
        foreach ( $events as $event ) {
            if ( 'phone_click' === $event['event'] ) {
                $phone = $event;
                break;
            }
        }

        $this->assertNotNull( $phone, 'phone_click event should exist' );
        $this->assertArrayNotHasKey( 'source', $phone );
        $this->assertArrayNotHasKey( 'replayed_at', $phone );
    }
}
