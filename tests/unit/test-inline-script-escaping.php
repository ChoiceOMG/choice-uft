<?php
/**
 * Every value the plugin prints inside a <script> element must stay inside
 * it: a UTM value of </script><script>alert(1)</script> must not close the
 * element or start a new one.
 */

class Test_Inline_Script_Escaping extends WP_UnitTestCase {

    const PAYLOAD = '</script><script>alert(1)</script>';

    private $saved_cookie;

    public function set_up() {
        parent::set_up();
        $this->saved_cookie = $_COOKIE;
    }

    public function tear_down() {
        $_COOKIE = $this->saved_cookie;
        delete_option( 'cuft_gtm_id' );
        delete_option( 'cuft_collector_host' );
        parent::tear_down();
    }

    public function test_js_encode_hex_escapes_markup() {
        $json = CUFT_Utils::js_encode( array( 'utm_campaign' => self::PAYLOAD, 'q' => "it's \"x\" & y" ) );

        $this->assertStringNotContainsString( '<', $json );
        $this->assertStringNotContainsString( '>', $json );
        $this->assertStringNotContainsString( '&', $json );
        $this->assertStringNotContainsString( "'", $json );
        $this->assertSame( array( 'utm_campaign' => self::PAYLOAD, 'q' => "it's \"x\" & y" ), json_decode( $json, true ), 'round-trips to the same value' );
    }

    public function test_localize_matches_core_value_handling_and_escapes() {
        wp_register_script( 'cuft-test-localize', 'https://example.com/x.js', array(), '1', true );
        CUFT_Utils::localize( 'cuft-test-localize', 'cuftTest', array(
            'flag'    => false,
            'on'      => true,
            'num'     => 100,
            'payload' => self::PAYLOAD,
            'nested'  => array( 'a' => 1 ),
        ) );

        $before = trim( implode( "\n", array_filter( (array) wp_scripts()->get_data( 'cuft-test-localize', 'before' ) ) ) );
        $this->assertStringStartsWith( 'var cuftTest = ', $before );
        $this->assertStringNotContainsString( '</script', $before );
        $this->assertStringNotContainsString( '<script', $before );

        $decoded = json_decode( substr( rtrim( $before, ';' ), strlen( 'var cuftTest = ' ) ), true );
        $this->assertSame( '', $decoded['flag'] );
        $this->assertSame( '1', $decoded['on'] );
        $this->assertSame( '100', $decoded['num'] );
        $this->assertSame( self::PAYLOAD, $decoded['payload'] );
        $this->assertSame( array( 'a' => 1 ), $decoded['nested'] );

        wp_deregister_script( 'cuft-test-localize' );
    }

    public function test_front_end_scripts_do_not_break_out_with_hostile_utm_and_click_cookies() {
        update_option( 'cuft_gtm_id', 'GTM-ABC1234' );

        $_COOKIE['cuft_utm_data']    = wp_json_encode( array(
            'utm'       => array( 'utm_campaign' => self::PAYLOAD, 'utm_source' => self::PAYLOAD ),
            'timestamp' => current_time( 'timestamp' ),
        ) );
        $_COOKIE['cuft_first_touch'] = wp_json_encode( array( 'utm' => array( 'utm_campaign' => self::PAYLOAD ) ) );
        $_COOKIE['cuft_click_id']    = self::PAYLOAD;

        do_action( 'wp_enqueue_scripts' );

        ob_start();
        wp_print_head_scripts();
        wp_print_footer_scripts();
        $html = ob_get_clean();

        $this->assertNotEmpty( $html );
        $this->assertStringNotContainsString( '<script>alert(1)', $html );
        $this->assertStringNotContainsString( 'alert(1)</script>', $html );
        $this->assertSame(
            substr_count( $html, '<script' ),
            substr_count( $html, '</script>' ),
            'every <script> opened by the plugin is closed by its own </script>'
        );
    }
}
