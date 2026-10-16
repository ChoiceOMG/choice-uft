<?php
/**
 * The cuft_record_event filter in cuft_record_event (3.30.0).
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 * @group ajax
 */

class Test_Event_Recorder_Filter extends WP_Ajax_UnitTestCase {

    private $click_id = 'test_record_filter_click';

    public function setUp(): void {
        parent::setUp();
        CUFT_Click_Tracker::create_table();
        CUFT_Click_Tracker::track_click( $this->click_id, array( 'platform' => 'google' ) );
        $_POST = array(
            'nonce'      => wp_create_nonce( 'cuft-event-recorder' ),
            'click_id'   => $this->click_id,
            'event_type' => 'phone_click',
        );
    }

    public function tearDown(): void {
        $_POST = array();
        remove_all_filters( 'cuft_record_event' );
        parent::tearDown();
    }

    private function call_recorder() {
        $level = ob_get_level();
        try {
            $this->_handleAjax( 'cuft_record_event' );
        } catch ( WPAjaxDieContinueException $e ) {
            unset( $e );
        }
        // The second response's die handler closes one buffer more than the
        // harness opened; reopen it so PHPUnit's own buffer survives the test.
        while ( ob_get_level() < $level ) {
            ob_start();
        }
        // record_event() catches Exception, which includes the harness's die
        // exception, and then sends a second "Internal error" response. In
        // production wp_send_json_*() exits first, so the first response is
        // the real one.
        $responses = preg_split( '/(?<=\})(?=\{"success")/', $this->_last_response );
        return json_decode( $responses[0], true );
    }

    private function recorded_types() {
        $events = CUFT_Click_Tracker::get_events( $this->click_id );
        return wp_list_pluck( is_array( $events ) ? $events : array(), 'event' );
    }

    public function test_event_is_recorded_by_default() {
        $response = $this->call_recorder();
        $this->assertTrue( $response['success'] );
        $this->assertContains( 'phone_click', $this->recorded_types() );
    }

    public function test_filter_can_drop_an_event() {
        $seen = array();
        add_filter( 'cuft_record_event', function ( $record, $event_type, $click_id ) use ( &$seen ) {
            $seen = array( $event_type, $click_id );
            return 'phone_click' !== $event_type;
        }, 10, 3 );

        $response = $this->call_recorder();

        $this->assertTrue( $response['success'] );
        $this->assertTrue( $response['data']['skipped'] );
        $this->assertSame( array( 'phone_click', $this->click_id ), $seen );
        $this->assertNotContains( 'phone_click', $this->recorded_types() );
    }
}
