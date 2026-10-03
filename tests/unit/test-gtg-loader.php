<?php
class Test_GTG_Loader extends WP_UnitTestCase {
    public function set_up() {
        parent::set_up();
        $GLOBALS['wp_scripts'] = new WP_Scripts();
        update_option( 'cuft_gtm_id', 'GTM-TKVDMKQ6' );
        update_option( 'cuft_gtg_script_path', '/k7q2fx/' );
        update_option( 'cuft_gtg_enabled', true );      // adding the option resets the state to fallback
        update_option( 'cuft_gtg_active', 'gateway' );  // then the tests pick the state
    }

    private function loader() {
        $gtm = new CUFT_GTM();
        $gtm->enqueue_gtm();
        return implode( "\n", (array) wp_scripts()->get_data( CUFT_GTM::HANDLE, 'after' ) );
    }

    private function fake_http( $map ) {
        add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $map ) {
            foreach ( $map as $suffix => $resp ) {
                if ( substr( $url, -strlen( $suffix ) ) === $suffix ) {
                    return array( 'headers' => array(), 'body' => $resp[1], 'response' => array( 'code' => $resp[0], 'message' => '' ), 'cookies' => array(), 'filename' => null );
                }
            }
            return new WP_Error( 'http_request_failed', 'unexpected ' . $url );
        }, 10, 3 );
    }

    public function test_gateway_loader_uses_same_origin_path() {
        $js = $this->loader();
        $this->assertStringContainsString( '"\/k7q2fx\/?id="+i+dl', $js );
    }

    public function test_gateway_loader_has_browser_fallback() {
        $js = $this->loader();
        $this->assertStringContainsString( 'j.onerror=fb', $js );
        $this->assertStringContainsString( 'w.google_tag_manager&&w.google_tag_manager[i]', $js );
        $this->assertSame( 1, substr_count( $js, 'googletagmanager.com' ) );   // only inside fb
    }

    private function attrs() {
        $gtm = new CUFT_GTM();
        $gtm->enqueue_gtm();
        return $gtm->filter_inline_script_attributes( array( 'id' => CUFT_GTM::HANDLE . '-js-after' ) );
    }

    public function test_fallback_state_keeps_the_previous_loader_and_says_why() {
        update_option( 'cuft_gtg_active', 'fallback' );
        update_option( 'cuft_gtg_fallback_reason', 'healthy: 502' );
        $js = $this->loader();
        $this->assertStringContainsString( '"https:\/\/www.googletagmanager.com\/gtm.js?id="+i+dl', $js );
        $this->assertStringNotContainsString( 'j.onerror', $js );
        $attrs = $this->attrs();
        $this->assertSame( 'fallback', $attrs['data-cuft-gtg-state'] );
        $this->assertSame( 'healthy: 502', $attrs['data-cuft-gtg-reason'] );
        $this->assertArrayNotHasKey( 'data-cuft-gtm-source', $attrs );
    }

    public function test_fallback_state_keeps_a_working_tagging_server_loader() {
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_active_server', 'custom' );
        update_option( 'cuft_gtg_active', 'fallback' );
        $this->assertStringContainsString( '"https:\/\/tagging-server.example.com\/gtm.js?id="+i+dl', $this->loader() );
        $attrs = $this->attrs();
        $this->assertSame( 'custom', $attrs['data-cuft-gtm-source'] );
        $this->assertSame( 'fallback', $attrs['data-cuft-gtg-state'] );
    }

    public function test_invalid_path_keeps_existing_behaviour() {
        update_option( 'cuft_gtg_script_path', '/gtm/' );
        $js = $this->loader();
        $this->assertStringNotContainsString( '/gtm/?id=', $js );
        // The existing Google loader must still be emitted, not nothing at all.
        $this->assertStringContainsString( '"https:\/\/www.googletagmanager.com\/gtm.js?id="+i+dl', $js );
        $this->assertStringNotContainsString( 'j.onerror', $js );
        $this->assertArrayNotHasKey( 'data-cuft-gtg-state', $this->attrs() );
    }

    public function test_enabling_starts_in_fallback() {
        update_option( 'cuft_gtg_enabled', false );
        update_option( 'cuft_gtg_active', 'gateway' );
        update_option( 'cuft_gtg_enabled', true );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
        update_option( 'cuft_gtg_active', 'gateway' );
        update_option( 'cuft_gtg_script_path', '/q8w4ez/' );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
    }

    public function test_resaving_the_same_settings_keeps_the_state() {
        update_option( 'cuft_gtg_enabled', '1' );           // same meaning as true
        update_option( 'cuft_gtg_script_path', 'k7q2fx' );  // same path once normalised
        $this->assertSame( 'gateway', get_option( 'cuft_gtg_active' ) );
    }

    public function test_switching_off_from_gateway_purges_and_fires_action() {
        $before = did_action( 'cuft_gtg_state_changed' );
        update_option( 'cuft_gtg_enabled', false );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
        $this->assertSame( $before + 1, did_action( 'cuft_gtg_state_changed' ) );
    }

    public function test_purge_page_caches_flushes_the_object_cache() {
        wp_cache_set( 'cuft_gtg_probe', 'stale', 'cuft_gtg_test' );
        $this->assertSame( 'stale', wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ) );
        CUFT_GTG_Health::purge_page_caches();
        $this->assertFalse( wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ) );
    }

    public function test_a_state_change_flushes_the_object_cache() {
        update_option( 'cuft_gtg_active', 'fallback' );
        wp_cache_set( 'cuft_gtg_probe', 'stale', 'cuft_gtg_test' );
        CUFT_GTG_Health::record( true, '' );
        $this->assertSame( 'stale', wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ), 'one pass is not a state change' );
        CUFT_GTG_Health::record( true, '' );
        $this->assertSame( 'gateway', get_option( 'cuft_gtg_active' ) );
        $this->assertFalse( wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ) );
    }

    public function test_normalize_path() {
        $this->assertSame( '/k7q2fx/', CUFT_GTG_Health::normalize_path( 'k7q2fx' ) );
        $this->assertSame( '/k7q2fx/', CUFT_GTG_Health::normalize_path( '/k7q2fx/' ) );
        $this->assertSame( '', CUFT_GTG_Health::normalize_path( '/' ) );
        $this->assertSame( '', CUFT_GTG_Health::normalize_path( '/gtmx1/' ) );
        $this->assertSame( '', CUFT_GTG_Health::normalize_path( '/a/b/' ) );
    }

    public function test_two_passes_to_gateway_two_failures_to_fallback() {
        update_option( 'cuft_gtg_active', 'fallback' );
        $this->assertSame( 'fallback', CUFT_GTG_Health::record( true, '' ) );
        $this->assertSame( 'gateway', CUFT_GTG_Health::record( true, '' ) );
        $this->assertSame( 'gateway', CUFT_GTG_Health::record( false, 'healthy: 502' ) );
        $this->assertSame( 'fallback', CUFT_GTG_Health::record( false, 'healthy: 502' ) );
        $this->assertSame( 'healthy: 502', get_option( 'cuft_gtg_fallback_reason' ) );
    }

    public function test_one_failure_between_successes_does_not_switch() {
        CUFT_GTG_Health::record( false, 'x' );
        CUFT_GTG_Health::record( true, '' );
        $this->assertSame( 'gateway', CUFT_GTG_Health::record( false, 'x' ) );
    }

    public function test_state_change_fires_action() {
        $before = did_action( 'cuft_gtg_state_changed' );
        CUFT_GTG_Health::record( false, 'a' );
        CUFT_GTG_Health::record( false, 'a' );
        $this->assertSame( $before + 1, did_action( 'cuft_gtg_state_changed' ) );
    }

    public function test_probe_requires_container_id() {
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, 'ok' ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, '<html>a CMS 200 page</html>' ),
        ) );
        list( $ok, $reason ) = CUFT_GTG_Health::probe();
        $this->assertFalse( $ok );
        $this->assertStringContainsString( '?id=GTM-TKVDMKQ6', $reason );
    }

    public function test_probe_passes_with_container_id() {
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, "ok\n" ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, 'var x={"GTM-TKVDMKQ6":1};' ),
        ) );
        $this->assertSame( array( true, '' ), CUFT_GTG_Health::probe() );
    }

    public function test_noscript_stays_on_google_when_gateway_enabled() {
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_active_server', 'custom' );
        $html = get_echo( array( new CUFT_GTM(), 'inject_body_code' ) );
        $this->assertStringContainsString( 'https://www.googletagmanager.com/ns.html?id=GTM-TKVDMKQ6', $html );
    }
}
