<?php
// tests/unit/test-measurement-protocol.php

class Test_Measurement_Protocol extends WP_UnitTestCase {

    public function test_build_payload_structure() {
        $mp = new CUFT_Measurement_Protocol();
        $payload = $mp->build_payload( '123456.789012', 'qualify_lead', array(
            'click_id'    => 'abc123',
            'lead_source' => 'gravity_forms',
        ) );

        $this->assertEquals( '123456.789012', $payload['client_id'] );
        $this->assertCount( 1, $payload['events'] );
        $this->assertEquals( 'qualify_lead', $payload['events'][0]['name'] );
        $this->assertEquals( 'abc123', $payload['events'][0]['params']['click_id'] );
        $this->assertEquals( 'gravity_forms', $payload['events'][0]['params']['lead_source'] );
        $this->assertEquals( 1, $payload['events'][0]['params']['engagement_time_msec'] );
    }

    public function test_build_payload_includes_lead_value() {
        update_option( 'cuft_lead_value', 250 );
        update_option( 'cuft_lead_currency', 'USD' );

        $mp = new CUFT_Measurement_Protocol();
        $payload = $mp->build_payload( '123.456', 'close_convert_lead', array() );

        $this->assertEquals( 250, $payload['events'][0]['params']['value'] );
        $this->assertEquals( 'USD', $payload['events'][0]['params']['currency'] );

        delete_option( 'cuft_lead_value' );
        delete_option( 'cuft_lead_currency' );
    }

    public function test_is_configured_returns_false_when_missing() {
        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );

        $mp = new CUFT_Measurement_Protocol();
        $this->assertFalse( $mp->is_configured() );
    }

    public function test_is_configured_returns_true_when_set() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        update_option( 'cuft_measurement_api_secret', 'secret' );

        $mp = new CUFT_Measurement_Protocol();
        $this->assertTrue( $mp->is_configured() );

        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );
    }
}
