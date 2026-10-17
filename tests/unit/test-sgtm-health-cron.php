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

    public function test_dead_tagging_server_falls_back_on_a_cron_run() {
        $this->assertFalse( is_admin() );
        add_filter( 'pre_http_request', function () {
            return new WP_Error( 'http_request_failed', 'Connection refused' );
        } );

        CUFT_Plugin::get_instance()->init();
        do_action( self::HOOK );

        $this->assertSame( 'fallback', get_option( 'cuft_sgtm_active_server' ) );
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
