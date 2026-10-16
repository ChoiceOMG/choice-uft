<?php
/**
 * Tests for CUFT_SGTM: the server-side GTM host is a validated bare hostname
 * and every URL is built by the plugin from it.
 */

class Test_SGTM_Host extends WP_UnitTestCase {

    public function tear_down() {
        foreach ( array( 'cuft_sgtm_host', 'cuft_sgtm_url', 'cuft_sgtm_enabled', 'cuft_sgtm_active_server', 'cuft_sgtm_validated', 'cuft_gtm_id' ) as $option ) {
            delete_option( $option );
        }
        parent::tear_down();
    }

    public function valid_hosts() {
        return array(
            array( 'gtm.example.com', 'gtm.example.com' ),
            array( 'tagging-server.drlampee.com', 'tagging-server.drlampee.com' ),
            array( 'https://tagging-server.example.com', 'tagging-server.example.com' ),
            array( 'https://tagging-server.example.com/', 'tagging-server.example.com' ),
            array( '  GTM.Example.COM ', 'gtm.example.com' ),
        );
    }

    /**
     * @dataProvider valid_hosts
     */
    public function test_valid_hosts_are_accepted( $input, $expected ) {
        $this->assertSame( $expected, CUFT_SGTM::sanitize_host( $input ) );
    }

    public function invalid_hosts() {
        return array(
            array( 'javascript:alert(1)' ),
            array( 'https://evil.com/x.js?' ),
            array( 'evil.com/path' ),
            array( 'evil.com/gtm.js' ),
            array( '127.0.0.1' ),
            array( 'localhost' ),
            array( 'localhost:8080' ),
            array( 'evil.com:8443' ),
            array( 'user:pass@evil.com' ),
            array( 'http://evil.com' ),
            array( '//evil.com' ),
            array( 'evil.com?x=1' ),
            array( 'evil.com#frag' ),
            array( 'tagging-server.localnet' ),
            array( 'foo.localhost' ),
            array( 'singlelabel' ),
            array( 'evil..com' ),
            array( '-evil.com' ),
            array( 'evil .com' ),
            array( '"><script>alert(1)</script>' ),
            array( '' ),
        );
    }

    /**
     * @dataProvider invalid_hosts
     */
    public function test_invalid_hosts_are_rejected( $input ) {
        $this->assertFalse( CUFT_SGTM::sanitize_host( $input ) );
    }

    public function test_script_url_is_built_from_host_and_id_only() {
        $this->assertSame(
            'https://tagging-server.example.com/gtm.js?id=GTM-ABC1234',
            CUFT_SGTM::script_url( 'tagging-server.example.com', 'GTM-ABC1234' )
        );
        $this->assertSame(
            'https://tagging-server.example.com/ns.html?id=GTM-ABC1234',
            CUFT_SGTM::noscript_url( 'tagging-server.example.com', 'GTM-ABC1234' )
        );
        $this->assertSame( '', CUFT_SGTM::script_url( 'evil.com/x', 'GTM-ABC1234' ) );
        $this->assertSame( '', CUFT_SGTM::script_url( 'gtm.example.com', 'GTM-ABC1234"+alert(1)+"' ) );
    }

    public function test_legacy_full_url_migrates_to_bare_host() {
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_active_server', 'custom' );

        $this->assertSame( 'tagging-server.example.com', CUFT_SGTM::get_host() );
        $this->assertFalse( get_option( 'cuft_sgtm_url', false ), 'legacy option is removed after migration' );
        $this->assertSame( 'tagging-server.example.com', get_option( 'cuft_sgtm_host' ) );
        $this->assertSame( 'custom', get_option( 'cuft_sgtm_active_server' ), 'an exact migration keeps the server active' );
        $this->assertTrue( CUFT_SGTM::custom_host_active() );
    }

    public function test_legacy_url_with_path_falls_back_until_retested() {
        update_option( 'cuft_sgtm_url', 'https://example.com/gtm' );
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_active_server', 'custom' );

        $this->assertSame( 'example.com', CUFT_SGTM::get_host() );
        $this->assertSame( 'fallback', get_option( 'cuft_sgtm_active_server' ) );
        $this->assertFalse( CUFT_SGTM::custom_host_active() );
    }

    public function test_invalid_legacy_url_is_dropped() {
        update_option( 'cuft_sgtm_url', 'https://127.0.0.1:8443' );
        $this->assertSame( '', CUFT_SGTM::get_host() );
        $this->assertFalse( get_option( 'cuft_sgtm_url', false ) );
    }

    public function test_tampered_stored_host_is_not_used() {
        update_option( 'cuft_sgtm_host', 'evil.com/x.js?' );
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_active_server', 'custom' );

        $this->assertSame( '', CUFT_SGTM::get_host() );
        $this->assertSame( CUFT_SGTM::DEFAULT_HOST, CUFT_SGTM::get_active_host() );
    }

    public function test_loader_src_uses_validated_host() {
        update_option( 'cuft_gtm_id', 'GTM-ABC1234' );
        update_option( 'cuft_sgtm_url', 'https://tagging-server.example.com' );
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_active_server', 'custom' );

        $gtm = new CUFT_GTM();
        $gtm->enqueue_gtm();
        $after = wp_scripts()->get_data( CUFT_GTM::HANDLE, 'after' );
        $this->assertIsArray( $after );
        $this->assertStringContainsString( 'j.src="https:\/\/tagging-server.example.com\/gtm.js?id=GTM-ABC1234"', implode( "\n", $after ) );
        wp_dequeue_script( CUFT_GTM::HANDLE );
        wp_deregister_script( CUFT_GTM::HANDLE );
    }
}
