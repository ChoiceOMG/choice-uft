<?php
// tests/unit/test-webhook-status.php

class Test_Webhook_Status extends WP_UnitTestCase {

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
            'click_id'     => 'test-webhook-click',
            'platform'     => 'google',
            'qualified'    => 0,
            'score'        => 0,
            'ga_client_id' => '123456.789012',
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-webhook-click' ) );
        parent::tear_down();
    }

    public function test_valid_status_values() {
        $valid = CUFT_Click_Tracker::get_valid_webhook_statuses();
        $this->assertContains( 'qualify_lead', $valid );
        $this->assertContains( 'disqualify_lead', $valid );
        $this->assertContains( 'working_lead', $valid );
        $this->assertContains( 'close_convert_lead', $valid );
        $this->assertContains( 'close_unconvert_lead', $valid );
    }

    public function test_status_parameter_records_event() {
        global $wpdb;
        CUFT_Click_Tracker::update_click_status( 'test-webhook-click', null, null, 'working_lead' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-webhook-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'working_lead', $last_event['event'] );
    }

    public function test_qualified_param_maps_to_qualify_lead() {
        global $wpdb;
        CUFT_Click_Tracker::update_click_status( 'test-webhook-click', 1 );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-webhook-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'qualify_lead', $last_event['event'] );
    }
}
