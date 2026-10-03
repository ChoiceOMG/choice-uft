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

    /** @var array Every request the probe made: array( url, args ). */
    private $http_calls = array();

    /**
     * Answer the probe's requests. $map is suffix => array( code, body ), or suffix => WP_Error;
     * the suffix '*' matches any URL.
     */
    private function fake_http( $map ) {
        $this->http_calls = array();
        add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $map ) {
            $this->http_calls[] = array( $url, $args );
            foreach ( $map as $suffix => $resp ) {
                if ( '*' === $suffix || substr( $url, -strlen( $suffix ) ) === $suffix ) {
                    if ( $resp instanceof WP_Error ) {
                        return $resp;
                    }
                    return array( 'headers' => array(), 'body' => $resp[1], 'response' => array( 'code' => $resp[0], 'message' => '' ), 'cookies' => array(), 'filename' => null );
                }
            }
            return new WP_Error( 'http_request_failed', 'unexpected ' . $url );
        }, 10, 3 );
    }

    private function probe_urls() {
        return wp_list_pluck( $this->http_calls, 0 );
    }

    private function container_body() {
        return 'var data={"GTM-TKVDMKQ6":1};window.google_tag_manager=window.google_tag_manager||{};';
    }

    private function html_404_page() {
        return '<!DOCTYPE html><html lang="en-US"><head><meta charset="UTF-8" /><title>Page not found</title>'
            . '<script>alert(1)</script><style>p{color:red}</style></head><body><h1>Not Found</h1><p>Nothing here.</p></body></html>';
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
        wp_cache_set( 'cuft_gtg_probe', 'stale', 'cuft_gtg_test' );
        update_option( 'cuft_gtg_enabled', false );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
        $this->assertSame( $before + 1, did_action( 'cuft_gtg_state_changed' ) );
        $this->assertFalse( wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ), 'switching off must purge the caches' );
    }

    public function test_string_false_turns_the_gateway_off() {
        // `wp option update cuft_gtg_enabled false` stores the string "false".
        $before = did_action( 'cuft_gtg_state_changed' );
        wp_cache_set( 'cuft_gtg_probe', 'stale', 'cuft_gtg_test' );
        update_option( 'cuft_gtg_enabled', 'false' );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
        $this->assertSame( $before + 1, did_action( 'cuft_gtg_state_changed' ) );
        $this->assertFalse( wp_cache_get( 'cuft_gtg_probe', 'cuft_gtg_test' ) );
        $js = $this->loader();
        $this->assertStringNotContainsString( 'k7q2fx', $js );
        $this->assertStringContainsString( '"https:\/\/www.googletagmanager.com\/gtm.js?id="+i+dl', $js );
        $attrs = $this->attrs();
        $this->assertArrayNotHasKey( 'data-cuft-gtg-state', $attrs );
        $this->assertArrayNotHasKey( 'data-cuft-gtm-source', $attrs );
    }

    public function test_string_false_on_a_stored_gateway_state_still_loads_google() {
        // The option reads "false" while the state still says gateway (the reset hook did not run).
        remove_action( 'update_option_cuft_gtg_enabled', array( 'CUFT_GTG_Health', 'on_enabled_change' ), 10 );
        update_option( 'cuft_gtg_enabled', 'false' );
        $this->assertSame( 'gateway', get_option( 'cuft_gtg_active' ) );
        $this->assertStringNotContainsString( 'k7q2fx', $this->loader() );
    }

    public function test_string_false_keeps_the_sGTM_noscript() {
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_active_server', 'custom' );
        update_option( 'cuft_gtg_enabled', 'false' );
        $html = get_echo( array( new CUFT_GTM(), 'inject_body_code' ) );
        $this->assertStringContainsString( 'https://tagging-server.example.com/ns.html?id=GTM-TKVDMKQ6', $html );
    }

    public function test_string_false_stops_the_probe_and_the_schedule() {
        wp_schedule_event( time() + 60, 'hourly', CUFT_GTG_Health::HOOK );
        update_option( 'cuft_gtg_enabled', 'false' );
        $this->fake_http( array( '*' => array( 200, 'ok' ) ) );
        $this->assertSame( '', CUFT_GTG_Health::run() );
        $this->assertSame( array(), $this->http_calls, 'a disabled gateway makes no requests' );
        CUFT_GTG_Health::init();
        $this->assertFalse( wp_next_scheduled( CUFT_GTG_Health::HOOK ) );
        update_option( 'cuft_gtg_enabled', '1' );
        CUFT_GTG_Health::init();
        $this->assertNotFalse( wp_next_scheduled( CUFT_GTG_Health::HOOK ) );
    }

    public function test_false_and_the_string_false_mean_the_same() {
        update_option( 'cuft_gtg_enabled', false );
        update_option( 'cuft_gtg_active', 'gateway' );   // a marker: a reset would turn it back into fallback
        update_option( 'cuft_gtg_enabled', 'false' );
        $this->assertSame( 'gateway', get_option( 'cuft_gtg_active' ) );
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
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, $this->container_body() ),
        ) );
        $this->assertSame( array( true, '' ), CUFT_GTG_Health::probe() );
    }

    public function test_probe_requires_google_tag_manager_in_the_container_body() {
        // A site page that merely echoes the id (a search page, a 200 error page) must not pass.
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, 'ok' ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, '<html><body>No results for GTM-TKVDMKQ6</body></html>' ),
        ) );
        list( $ok, $reason ) = CUFT_GTG_Health::probe();
        $this->assertFalse( $ok );
        $this->assertStringStartsWith( '?id=GTM-TKVDMKQ6: 200', $reason );
    }

    public function test_probe_does_not_follow_redirects() {
        $this->fake_http( array( '*' => array( 301, '' ) ) );
        list( $ok, $reason ) = CUFT_GTG_Health::probe();
        $this->assertFalse( $ok );
        $this->assertSame( 'healthy: 301', $reason );
        $this->assertCount( 1, $this->http_calls, 'the first failing check ends the probe' );
        $this->assertSame( 0, $this->http_calls[0][1]['redirection'] );
        $this->assertSame( 5, $this->http_calls[0][1]['timeout'] );
    }

    public function test_probe_sends_no_redirects_on_any_check() {
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, 'ok' ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, $this->container_body() ),
        ) );
        CUFT_GTG_Health::probe();
        $this->assertCount( 3, $this->http_calls );
        foreach ( $this->http_calls as $call ) {
            $this->assertSame( 0, $call[1]['redirection'], $call[0] );
        }
    }

    public function test_probe_asks_the_origin_root_on_a_subdirectory_install() {
        update_option( 'home', 'https://example.org/blog/' );
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, 'ok' ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, $this->container_body() ),
        ) );
        $this->assertSame( array( true, '' ), CUFT_GTG_Health::probe() );
        $this->assertSame( array(
            'https://example.org/k7q2fx/healthy',
            'https://example.org/k7q2fx/?validate_geo=healthy',
            'https://example.org/k7q2fx/?id=GTM-TKVDMKQ6',
        ), $this->probe_urls() );
    }

    public function test_probe_keeps_the_port_and_drops_the_path_and_credentials() {
        update_option( 'home', 'http://user:pw@localhost:8080/some/dir' );
        $this->fake_http( array( '*' => array( 404, '' ) ) );
        CUFT_GTG_Health::probe();
        $this->assertSame( array( 'http://localhost:8080/k7q2fx/healthy' ), $this->probe_urls() );
    }

    public function test_probe_timeout_counts_as_a_failure_with_a_clean_reason() {
        $this->fake_http( array( '*' => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received' ) ) );
        list( $ok, $reason ) = CUFT_GTG_Health::probe();
        $this->assertFalse( $ok );
        $this->assertStringStartsWith( 'healthy: 0 cURL error 28', $reason );
        $this->assertStringContainsString( 'timed out', $reason );
        $this->assertDoesNotMatchRegularExpression( '/[<>]/', $reason );
        // Two failing probes in a row reach the fallback with that reason stored.
        update_option( 'cuft_gtg_active', 'gateway' );
        update_option( 'cuft_gtg_fail_streak', 0 );
        $this->assertSame( 'gateway', CUFT_GTG_Health::record( false, $reason ) );
        $this->assertSame( 'fallback', CUFT_GTG_Health::record( false, $reason ) );
        $this->assertSame( $reason, get_option( 'cuft_gtg_fallback_reason' ) );
    }

    public function test_two_timeouts_in_a_row_reach_fallback() {
        $this->fake_http( array( '*' => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ) );
        $this->assertSame( 'gateway', CUFT_GTG_Health::run() );
        $this->assertSame( 'fallback', CUFT_GTG_Health::run() );
        $this->assertSame( 'healthy: 0 cURL error 28: Operation timed out', get_option( 'cuft_gtg_fallback_reason' ) );
    }

    public function test_reason_from_an_html_body_holds_no_markup() {
        $this->fake_http( array( '*' => array( 404, $this->html_404_page() ) ) );
        list( $ok, $reason ) = CUFT_GTG_Health::probe();
        $this->assertFalse( $ok );
        $this->assertStringStartsWith( 'healthy: 404 ', $reason );
        $this->assertDoesNotMatchRegularExpression( '/[<>]/', $reason );
        $this->assertStringNotContainsString( 'DOCTYPE', $reason );
        $this->assertStringNotContainsString( 'alert(1)', $reason, 'script bodies are dropped, not quoted' );
        $this->assertStringContainsString( 'Page not found', $reason );
        $this->assertLessThanOrEqual( strlen( 'healthy: 404 ' ) + 60, strlen( $reason ) );
    }

    public function test_reason_from_a_truncated_tag_holds_no_markup() {
        $this->fake_http( array( '*' => array( 502, "<!DOCTYPE html>\n<html\n lang=\"en-US\"><head><meta charset=\"UT" ) ) );
        list( , $reason ) = CUFT_GTG_Health::probe();
        $this->assertStringStartsWith( 'healthy: 502', $reason );
        $this->assertDoesNotMatchRegularExpression( '/[<>]/', $reason );
        $this->fake_http( array( '*' => array( 500, 'a < b and c > d <b onclick="x' ) ) );
        list( , $reason ) = CUFT_GTG_Health::probe();
        $this->assertDoesNotMatchRegularExpression( '/[<>]/', $reason );
    }

    public function test_record_cleans_a_reason_it_is_handed() {
        update_option( 'cuft_gtg_active', 'fallback' );
        CUFT_GTG_Health::record( false, '<b>bold</b>"><script>alert(1)</script>' . "\n" . 'tail' );
        CUFT_GTG_Health::record( false, '<b>bold</b>"><script>alert(1)</script>' . "\n" . 'tail' );
        $stored = get_option( 'cuft_gtg_fallback_reason' );
        $this->assertDoesNotMatchRegularExpression( '/[<>]/', $stored );
        $this->assertStringNotContainsString( "\n", $stored );
        $this->assertSame( 'bold" tail', $stored );
    }

    public function test_the_tag_attribute_escapes_the_reason() {
        update_option( 'cuft_gtg_active', 'fallback' );
        update_option( 'cuft_gtg_fallback_reason', '"><script>alert(1)</script>' );
        $GLOBALS['wp_scripts'] = new WP_Scripts();
        $gtm = new CUFT_GTM();
        $gtm->enqueue_gtm();
        $html = get_echo( 'wp_print_scripts' );
        $this->assertStringNotContainsString( '<script>alert(1)', $html );
        $this->assertStringContainsString( 'data-cuft-gtg-state="fallback"', $html );
    }

    public function test_noscript_stays_on_google_when_gateway_enabled() {
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_active_server', 'custom' );
        $html = get_echo( array( new CUFT_GTM(), 'inject_body_code' ) );
        $this->assertStringContainsString( 'https://www.googletagmanager.com/ns.html?id=GTM-TKVDMKQ6', $html );
    }

    /**
     * The id is compared and requested in upper case on both sides: is_valid_gtm_id() accepts
     * any case, so a lowercase stored id must neither stay in fallback forever nor have the
     * page ask the gateway for a different id than the probe checked.
     */
    public function test_probe_accepts_a_lowercase_stored_id_and_asks_for_the_uppercase_one() {
        update_option( 'cuft_gtm_id', 'gtm-tkvdmkq6' );
        $this->fake_http( array(
            '/k7q2fx/healthy'               => array( 200, 'ok' ),
            '/k7q2fx/?validate_geo=healthy' => array( 200, 'ok' ),
            '/k7q2fx/?id=GTM-TKVDMKQ6'      => array( 200, $this->container_body() ),
        ) );
        $this->assertSame( array( true, '' ), CUFT_GTG_Health::probe() );
        $this->assertStringEndsWith( '/k7q2fx/?id=GTM-TKVDMKQ6', $this->probe_urls()[2] );
    }

    public function test_gateway_loader_names_the_uppercase_id_the_probe_checked() {
        update_option( 'cuft_gtm_id', 'gtm-tkvdmkq6' );
        $js = $this->loader();
        $this->assertStringContainsString( '"\/k7q2fx\/?id="+i+dl', $js );
        $this->assertStringContainsString( "'dataLayer',\"GTM-TKVDMKQ6\");", $js );
        $this->assertStringNotContainsString( 'gtm-tkvdmkq6', $js );
    }

    public function test_probe_refuses_an_id_the_loader_would_not_print() {
        update_option( 'cuft_gtm_id', 'GTM-AB1' );          // under the four characters is_valid_gtm_id() needs
        $this->fake_http( array( '*' => array( 200, 'ok' ) ) );
        $this->assertSame( array( false, 'no valid gateway path or GTM id' ), CUFT_GTG_Health::probe() );
        $this->assertSame( array(), $this->http_calls );
    }
}
