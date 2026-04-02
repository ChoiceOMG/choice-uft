<?php
/**
 * Tests for admin API credentials settings and Token Manager DB fallback.
 */

class Test_Admin_Secrets extends WP_UnitTestCase {

    public function test_register_secret_falls_back_to_option() {
        update_option( 'cuft_register_secret', 'test-secret-from-db' );
        $secret = CUFT_Token_Manager::get_register_secret_value();
        $this->assertEquals( 'test-secret-from-db', $secret );
        delete_option( 'cuft_register_secret' );
    }

    public function test_measurement_id_option() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        $this->assertEquals( 'G-TEST123', get_option( 'cuft_measurement_id', '' ) );
        delete_option( 'cuft_measurement_id' );
    }

    public function test_measurement_api_secret_option() {
        update_option( 'cuft_measurement_api_secret', 'secret123' );
        $this->assertEquals( 'secret123', get_option( 'cuft_measurement_api_secret', '' ) );
        delete_option( 'cuft_measurement_api_secret' );
    }
}
