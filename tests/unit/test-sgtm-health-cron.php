<?php
/**
 * The six-hour server-side GTM health check runs from WP-Cron, which is not
 * an admin request. Before 3.30.0 its callback and its six_hours schedule were
 * registered only when CUFT_Admin was built (admin requests only), so the
 * check never ran and a dead tagging server never fell back.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

class Test_SGTM_Health_Cron extends WP_UnitTestCase {

    const HOOK = 'cuft_scheduled_health_check';

    public function setUp(): void {
        parent::setUp();
        update_option( 'cuft_gtm_id', 'GTM-ABC1234' );
        update_option( 'cuft_sgtm_host', 'tagging-server.example.com' );
        update_option( 'cuft_sgtm_enabled', true );
        update_option( 'cuft_sgtm_active_server', 'custom' );
        wp_clear_scheduled_hook( self::HOOK );
        remove_all_actions( self::HOOK );
    }

    public function tearDown(): void {
        foreach ( array( 'cuft_gtm_id', 'cuft_sgtm_host', 'cuft_sgtm_enabled', 'cuft_sgtm_active_server', 'cuft_sgtm_server_failed' ) as $option ) {
            delete_option( $option );
        }
        foreach ( array( 'last_check', 'last_result', 'last_message', 'response_time', 'consecutive_success', 'consecutive_failure' ) as $suffix ) {
            delete_option( 'cuft_sgtm_health_' . $suffix );
        }
        wp_clear_scheduled_hook( self::HOOK );
        parent::tearDown();
    }

    private function server_is_down() {
        add_filter( 'pre_http_request', function () {
            return new WP_Error( 'http_request_failed', 'Connection refused' );
        } );
    }

    private function server_is_up() {
        add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
            $body = false !== strpos( $url, 'ns.html' ) ? '<html></html>' : '/* google_tag_manager gtm.js */';
            return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
        }, 10, 3 );
    }

    private function recheck_time() {
        return wp_next_scheduled( self::HOOK, array( 'recheck' ) );
    }

    public function test_dead_tagging_server_falls_back_after_two_failed_checks() {
        $this->assertFalse( is_admin() );
        $this->server_is_down();

        CUFT_Plugin::get_instance()->init();
        do_action( self::HOOK );
        do_action( self::HOOK );

        $this->assertSame( 'fallback', get_option( 'cuft_sgtm_active_server' ) );
    }

    /**
     * One timeout from a busy host must not take a site off its tagging
     * server; a recheck 15 minutes later confirms or clears it.
     */
    public function test_one_failed_check_keeps_the_server_and_rechecks_soon() {
        $this->server_is_down();

        CUFT_Plugin::get_instance()->init();
        do_action( self::HOOK );

        $this->assertSame( 'custom', get_option( 'cuft_sgtm_active_server' ) );
        $this->assertIsInt( $this->recheck_time() );
        $this->assertLessThanOrEqual( time() + 20 * MINUTE_IN_SECONDS, $this->recheck_time() );
    }

    /**
     * Three passes in a row bring the tagging server back. Six hours apart
     * that took 18 hours; rechecks 15 minutes apart take under an hour.
     */
    public function test_recovery_is_rechecked_soon_and_restores_the_server() {
        update_option( 'cuft_sgtm_active_server', 'fallback' );
        $this->server_is_up();

        CUFT_Plugin::get_instance()->init();
        do_action( self::HOOK );
        $this->assertSame( 'fallback', get_option( 'cuft_sgtm_active_server' ) );
        $this->assertIsInt( $this->recheck_time() );

        do_action( self::HOOK, 'recheck' );
        do_action( self::HOOK, 'recheck' );
        $this->assertSame( 'custom', get_option( 'cuft_sgtm_active_server' ) );
    }

    public function test_healthy_server_needs_no_recheck() {
        $this->server_is_up();

        CUFT_Plugin::get_instance()->init();
        do_action( self::HOOK );

        $this->assertSame( 'custom', get_option( 'cuft_sgtm_active_server' ) );
        $this->assertFalse( $this->recheck_time() );
    }

    public function test_check_is_scheduled_every_six_hours_when_sgtm_is_on() {
        CUFT_Plugin::get_instance()->init();

        $this->assertIsInt( wp_next_scheduled( self::HOOK ) );
        $this->assertSame( 'six_hours', wp_get_schedule( self::HOOK ) );
    }

    public function test_check_is_not_scheduled_when_sgtm_is_off() {
        update_option( 'cuft_sgtm_enabled', false );

        CUFT_Plugin::get_instance()->init();

        $this->assertFalse( wp_next_scheduled( self::HOOK ) );
    }
}
