<?php
// tests/unit/test-webhook-mp-integration.php

class Test_Webhook_MP_Integration extends WP_UnitTestCase {

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
            'click_id'     => 'test-mp-click',
            'platform'     => 'google',
            'qualified'    => 0,
            'score'        => 0,
            'ga_client_id' => '111111.222222',
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-mp-click' ) );
        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );
        parent::tear_down();
    }

    public function test_webhook_status_records_event_when_mp_configured() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        update_option( 'cuft_measurement_api_secret', 'secret' );

        CUFT_Click_Tracker::update_click_status( 'test-mp-click', null, null, 'qualify_lead' );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-mp-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'qualify_lead', $last_event['event'] );
    }

    public function test_webhook_records_event_when_mp_not_configured() {
        // No measurement options set — event should still be recorded
        CUFT_Click_Tracker::update_click_status( 'test-mp-click', null, null, 'working_lead' );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-mp-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'working_lead', $last_event['event'] );
    }
}
