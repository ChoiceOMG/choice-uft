<?php
class Test_Loader_Golden extends WP_UnitTestCase {
    const DIR = __DIR__ . '/fixtures/loader-golden/';

    public function set_up() {
        parent::set_up();
        $GLOBALS['wp_scripts'] = new WP_Scripts();
        foreach ( array( 'cuft_sgtm_enabled', 'cuft_sgtm_url', 'cuft_sgtm_active_server', 'cuft_gtg_enabled', 'cuft_gtg_script_path', 'cuft_gtg_active' ) as $o ) {
            delete_option( $o );
        }
        update_option( 'cuft_gtm_id', 'GTM-TKVDMKQ6' );
    }

    public function modes() {
        $sgtm = array( 'cuft_sgtm_enabled' => true, 'cuft_sgtm_url' => 'https://tagging-server.example.com' );
        return array(
            'plain'                 => array( 'plain', array() ),
            'sgtm-custom'           => array( 'sgtm-custom', $sgtm + array( 'cuft_sgtm_active_server' => 'custom' ) ),
            'sgtm-fallback'         => array( 'sgtm-fallback', $sgtm + array( 'cuft_sgtm_active_server' => 'fallback' ) ),
            // A path saved but the gateway switched off must change nothing.
            'gtg-off-with-path'     => array( 'plain', array( 'cuft_gtg_enabled' => false, 'cuft_gtg_script_path' => '/k7q2fx/' ) ),
        );
    }

    /** @dataProvider modes */
    public function test_loader_and_noscript_match_golden( $fixture, $opts ) {
        foreach ( $opts as $k => $v ) {
            update_option( $k, $v );
        }
        $gtm = new CUFT_GTM();
        $gtm->enqueue_gtm();
        $actual = implode( "\n", (array) wp_scripts()->get_data( CUFT_GTM::HANDLE, 'after' ) ) . "\n---\n"
            . wp_json_encode( $gtm->filter_inline_script_attributes( array( 'id' => CUFT_GTM::HANDLE . '-js-after' ) ) ) . "\n---\n"
            . get_echo( array( $gtm, 'inject_body_code' ) );
        $file = self::DIR . $fixture . '.txt';
        if ( getenv( 'CUFT_RECORD_GOLDEN' ) ) {
            if ( ! is_dir( self::DIR ) ) {
                mkdir( self::DIR, 0755, true );
            }
            file_put_contents( $file, $actual );
            $this->markTestIncomplete( 'recorded ' . $fixture );
        }
        $this->assertSame( file_get_contents( $file ), $actual );
    }
}
