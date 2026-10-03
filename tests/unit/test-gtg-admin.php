<?php
/**
 * The Google tag gateway rows of the settings screen: the form text and the save notice.
 */
class Test_GTG_Admin extends WP_UnitTestCase {
    public function set_up() {
        parent::set_up();
        // Settings notices are a global the save adds to; each test reads only its own.
        $GLOBALS['wp_settings_errors'] = array();
        update_option( 'cuft_gtm_id', 'GTM-TKVDMKQ6' );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    public function tear_down() {
        $_POST    = array();
        $_REQUEST = array();
        $GLOBALS['wp_settings_errors'] = array();
        parent::tear_down();
    }

    private function call_private( $method, array $args = array() ) {
        $admin  = new CUFT_Admin();
        $m      = new ReflectionMethod( $admin, $method );
        $m->setAccessible( true );
        ob_start();
        $m->invokeArgs( $admin, $args );
        return ob_get_clean();
    }

    private function form() {
        return $this->call_private( 'render_settings_form', array( 'GTM-TKVDMKQ6', false, false, 'CAD', 100, 'no' ) );
    }

    /** Post the settings form with the gateway on and every probe answering $code and $body. */
    private function save_with_probe_answering( $code, $body ) {
        add_filter( 'pre_http_request', function () use ( $code, $body ) {
            return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
        } );
        $nonce    = wp_create_nonce( 'cuft_settings' );
        $_POST    = array(
            'cuft_nonce'      => $nonce,
            'gtm_id'          => 'GTM-TKVDMKQ6',
            'gtg_enabled'     => '1',
            'gtg_script_path' => 'k7q2fx',
        );
        $_REQUEST = $_POST;
        return $this->call_private( 'save_settings' );
    }

    public function test_help_text_is_neutral() {
        $html = $this->form();
        $this->assertStringContainsString( 'Google tag gateway', $html );
        $this->assertStringContainsString( "The script path your edge serves for Google's tag gateway, for example /k7q2fx/.", $html );
        $this->assertStringNotContainsString( 'register.yaml', $html );
        $this->assertStringContainsString( 'State: <strong>fallback</strong>', $html );
    }

    public function test_checkbox_follows_the_switch_as_a_boolean() {
        update_option( 'cuft_gtg_script_path', '/k7q2fx/' );
        update_option( 'cuft_gtg_enabled', true );
        $this->assertMatchesRegularExpression( '/name="gtg_enabled"[^>]*checked=/', $this->form() );
        update_option( 'cuft_gtg_enabled', 'false' );   // what `wp option update cuft_gtg_enabled false` stores
        $this->assertDoesNotMatchRegularExpression( '/name="gtg_enabled"[^>]*checked=/', $this->form() );
        update_option( 'cuft_gtg_enabled', 'true' );
        $this->assertMatchesRegularExpression( '/name="gtg_enabled"[^>]*checked=/', $this->form() );
    }

    public function test_notice_after_a_failed_probe_shows_no_page_markup() {
        $page = '<!DOCTYPE html><html lang="en-US"><head><meta charset="UTF-8" /><title>Page not found</title></head><body><h1>Not Found</h1></body></html>';
        $out  = $this->save_with_probe_answering( 404, $page );
        $this->assertStringContainsString( 'Google tag gateway not live yet', $out );
        $this->assertStringContainsString( 'healthy: 404', $out );
        $this->assertStringNotContainsString( '<!DOCTYPE', $out );
        $this->assertStringNotContainsString( '<html', $out );
        $this->assertStringNotContainsString( '<meta', $out );
        $this->assertStringNotContainsString( '&lt;', $out, 'the stored reason holds no markup to escape' );
        $this->assertSame( 'fallback', get_option( 'cuft_gtg_active' ) );
    }

    public function test_notice_escapes_a_reason_that_holds_markup() {
        // A reason written by an older build or another writer must still be escaped on output.
        add_filter( 'pre_option_cuft_gtg_fallback_reason', function () {
            return '<b onmouseover="x">bad</b><script>alert(1)</script>';
        } );
        $out = $this->save_with_probe_answering( 500, 'x' );
        $this->assertStringContainsString( 'Google tag gateway not live yet', $out );
        $this->assertStringNotContainsString( '<script>alert(1)', $out );
        $this->assertStringNotContainsString( '<b onmouseover', $out );
        $this->assertStringContainsString( '&lt;b onmouseover=', $out );
    }

    public function test_an_invalid_path_is_reported_and_not_enabled() {
        $nonce    = wp_create_nonce( 'cuft_settings' );
        $_POST    = array( 'cuft_nonce' => $nonce, 'gtm_id' => 'GTM-TKVDMKQ6', 'gtg_enabled' => '1', 'gtg_script_path' => '/gtm/' );
        $_REQUEST = $_POST;
        $out      = $this->call_private( 'save_settings' );
        $this->assertStringContainsString( 'Gateway not enabled', $out );
        $this->assertFalse( (bool) get_option( 'cuft_gtg_enabled', false ) );
    }
}
