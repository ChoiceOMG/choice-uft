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
        remove_all_filters( 'cuft_lead_id' );
        remove_all_filters( 'cuft_lead_id_phone_country' );
        parent::tear_down();
    }

    /**
     * Known SHA-256 vector for "test@example.com" (lowercase normalized).
     */
    const EMAIL_HASH = '973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b';

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

    // ── lead_id (OPS-2210) ───────────────────────────────────────────────────

    public function test_lead_id_from_email_known_vector() {
        $this->assertSame( self::EMAIL_HASH, CUFT_Form_Attribution::lead_id_from_email( 'test@example.com' ) );
    }

    public function test_lead_id_email_normalized_trim_and_lowercase() {
        // Same person, different casing/whitespace -> identical id.
        $this->assertSame( self::EMAIL_HASH, CUFT_Form_Attribution::lead_id_from_email( '  Test@Example.COM  ' ) );
    }

    public function test_lead_id_from_email_empty_returns_empty() {
        $this->assertSame( '', CUFT_Form_Attribution::lead_id_from_email( '' ) );
        $this->assertSame( '', CUFT_Form_Attribution::lead_id_from_email( '   ' ) );
    }

    public function test_lead_id_in_payload_prefers_email() {
        $payload = CUFT_Form_Attribution::get_payload( array(
            'email' => 'test@example.com',
            'phone' => '(780) 555-1234',
        ) );
        $this->assertSame( self::EMAIL_HASH, $payload['lead_id'] );
        $this->assertSame( 'email', $payload['lead_id_source'] );
    }

    public function test_lead_id_falls_back_to_phone_when_no_email() {
        $expected = hash( 'sha256', '+17805551234' );
        $payload  = CUFT_Form_Attribution::get_payload( array(
            'phone' => '(780) 555-1234',
        ) );
        $this->assertSame( $expected, $payload['lead_id'] );
        $this->assertSame( 'phone', $payload['lead_id_source'] );
    }

    public function test_lead_id_phone_normalization_is_format_independent() {
        // 10-digit, 11-digit-with-1, and formatted variants all canonicalize equally.
        $a = CUFT_Form_Attribution::lead_id_from_phone( '7805551234' );
        $b = CUFT_Form_Attribution::lead_id_from_phone( '1-780-555-1234' );
        $c = CUFT_Form_Attribution::lead_id_from_phone( '+1 (780) 555-1234' );
        $this->assertSame( hash( 'sha256', '+17805551234' ), $a );
        $this->assertSame( $a, $b );
        $this->assertSame( $a, $c );
    }

    public function test_lead_id_phone_empty_returns_empty() {
        $this->assertSame( '', CUFT_Form_Attribution::lead_id_from_phone( '' ) );
        $this->assertSame( '', CUFT_Form_Attribution::lead_id_from_phone( 'no-digits-here' ) );
    }

    public function test_lead_id_omitted_when_no_email_or_phone() {
        $payload = CUFT_Form_Attribution::get_payload( array(
            'page_url' => 'https://example.com/contact/',
        ) );
        $this->assertArrayNotHasKey( 'lead_id', $payload );
        $this->assertArrayNotHasKey( 'lead_id_source', $payload );
    }

    public function test_lead_id_phone_country_filter() {
        add_filter( 'cuft_lead_id_phone_country', function () {
            return '44';
        } );
        // Bare 10-digit number now takes the +44 prefix.
        $this->assertSame( hash( 'sha256', '+447805551234' ), CUFT_Form_Attribution::lead_id_from_phone( '7805551234' ) );
    }

    public function test_lead_id_filter_override() {
        add_filter( 'cuft_lead_id', function () {
            return 'OVERRIDDEN';
        } );
        $this->assertSame( 'OVERRIDDEN', CUFT_Form_Attribution::lead_id_from_email( 'test@example.com' ) );
    }
}
