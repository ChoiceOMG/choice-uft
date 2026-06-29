<?php
/**
 * Tests for CUFT_Form_Attribution server-side attribution assembler (OPS-2209).
 */

class Test_Form_Attribution extends WP_UnitTestCase {

    public function tear_down() {
        unset( $_COOKIE['cuft_utm_data'], $_COOKIE['cuft_first_touch'], $_COOKIE['cuft_click_id'] );
        delete_option( 'cuft_service_interest_map' );
        remove_all_filters( 'cuft_form_attribution_payload' );
        remove_all_filters( 'cuft_service_interest' );
        parent::tear_down();
    }

    private function set_last_touch( array $utm ) {
        $_COOKIE['cuft_utm_data'] = wp_json_encode( array(
            'utm'       => $utm,
            'timestamp' => current_time( 'timestamp' ),
        ) );
    }

    private function set_first_touch( array $utm, $landing = 'https://example.com/landing/', $ts = '2026-01-01T00:00:00+00:00' ) {
        $_COOKIE['cuft_first_touch'] = wp_json_encode( array(
            'utm'          => $utm,
            'landing_page' => $landing,
            'timestamp'    => $ts,
        ) );
    }

    public function test_last_touch_utm_and_click_ids_included() {
        $this->set_last_touch( array(
            'utm_source'   => 'google',
            'utm_medium'   => 'cpc',
            'utm_campaign' => 'spring',
            'gclid'        => 'abc123',
        ) );

        $payload = CUFT_Form_Attribution::get_payload( array( 'page_url' => 'https://example.com/contact/' ) );

        $this->assertSame( 'google', $payload['utm_source'] );
        $this->assertSame( 'cpc', $payload['utm_medium'] );
        $this->assertSame( 'spring', $payload['utm_campaign'] );
        $this->assertSame( 'abc123', $payload['gclid'] );
        // The assembler does not promote gclid to click_id; the client-side
        // tracker does that before storage, so an isolated cookie with only
        // gclid yields no generic click_id.
        $this->assertArrayNotHasKey( 'click_id', $payload );
    }

    public function test_generic_click_id_from_stored_cookie() {
        $this->set_last_touch( array(
            'utm_source' => 'google',
            'click_id'   => 'gen-987',
            'gclid'      => 'gen-987',
        ) );

        $payload = CUFT_Form_Attribution::get_payload( array( 'page_url' => 'https://example.com/contact/' ) );

        $this->assertSame( 'gen-987', $payload['click_id'] );
        $this->assertSame( 'gen-987', $payload['gclid'] );
    }

    public function test_first_touch_prefixed_and_present() {
        $this->set_first_touch( array( 'utm_source' => 'facebook', 'utm_medium' => 'social' ) );

        $payload = CUFT_Form_Attribution::get_payload( array( 'page_url' => 'https://example.com/contact/' ) );

        $this->assertSame( 'facebook', $payload['first_utm_source'] );
        $this->assertSame( 'social', $payload['first_utm_medium'] );
        $this->assertSame( 'https://example.com/landing/', $payload['landing_page'] );
        $this->assertSame( '2026-01-01T00:00:00+00:00', $payload['first_touch_at'] );
    }

    public function test_submitted_at_always_present() {
        $payload = CUFT_Form_Attribution::get_payload();
        $this->assertArrayHasKey( 'submitted_at', $payload );
        $this->assertNotEmpty( $payload['submitted_at'] );
    }

    public function test_service_interest_humanized_from_page_path() {
        $payload = CUFT_Form_Attribution::get_payload( array(
            'page_url' => 'https://example.com/care-for-your-body/',
        ) );
        $this->assertSame( 'Care For Your Body', $payload['service_interest'] );
    }

    public function test_service_interest_map_override() {
        update_option( 'cuft_service_interest_map', array( 'care-for-your-body' => 'Body Sculpting' ) );

        $payload = CUFT_Form_Attribution::get_payload( array(
            'page_url' => 'https://example.com/care-for-your-body/',
        ) );
        $this->assertSame( 'Body Sculpting', $payload['service_interest'] );
    }

    public function test_service_interest_filter_has_final_say() {
        add_filter( 'cuft_service_interest', function () {
            return 'Filtered Service';
        } );

        $payload = CUFT_Form_Attribution::get_payload( array(
            'page_url' => 'https://example.com/anything/',
        ) );
        $this->assertSame( 'Filtered Service', $payload['service_interest'] );
    }

    public function test_payload_filter_applies() {
        add_filter( 'cuft_form_attribution_payload', function ( $payload ) {
            $payload['custom_key'] = 'custom_value';
            return $payload;
        } );

        $payload = CUFT_Form_Attribution::get_payload();
        $this->assertSame( 'custom_value', $payload['custom_key'] );
    }

    public function test_form_context_included() {
        $payload = CUFT_Form_Attribution::get_payload( array(
            'form_id'   => 'abc123',
            'form_name' => 'Care for Your Body Form',
            'page_url'  => 'https://example.com/contact/',
        ) );
        $this->assertSame( 'abc123', $payload['form_id'] );
        $this->assertSame( 'Care for Your Body Form', $payload['form_name'] );
        $this->assertSame( 'https://example.com/contact/', $payload['page_url'] );
    }
}
