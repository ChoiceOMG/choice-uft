<?php
/**
 * Admin notices render only on the plugin's own screens and never announce
 * that the plugin is merely active.
 */

class Test_Admin_Notices_Scope extends WP_UnitTestCase {

    private $admin;

    public function set_up() {
        parent::set_up();
        $user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );
        $this->admin = new CUFT_Admin();
    }

    public function tear_down() {
        delete_option( 'cuft_gtm_id' );
        delete_option( 'cuft_sgtm_server_failed' );
        set_current_screen( 'front' );
        parent::tear_down();
    }

    private function render_on( $screen_id ) {
        set_current_screen( $screen_id );
        ob_start();
        $this->admin->admin_notices();
        return ob_get_clean();
    }

    public function test_no_notice_on_dashboard_or_posts_even_when_unconfigured() {
        delete_option( 'cuft_gtm_id' );
        add_option( 'cuft_sgtm_server_failed', time() );

        $this->assertSame( '', $this->render_on( 'dashboard' ) );
        $this->assertSame( '', $this->render_on( 'edit-post' ) );
        $this->assertSame( '', $this->render_on( 'plugins' ) );
    }

    public function test_no_active_notice_when_configured() {
        update_option( 'cuft_gtm_id', 'GTM-ABC1234' );

        $html = $this->render_on( 'toplevel_page_cuft-click-tracking' );
        $this->assertStringNotContainsString( 'is active', $html );
        $this->assertStringNotContainsString( 'framework(s) detected', $html );
    }

    public function test_missing_gtm_notice_is_dismissible_and_scoped() {
        delete_option( 'cuft_gtm_id' );

        $html = $this->render_on( 'toplevel_page_cuft-click-tracking' );
        $this->assertStringContainsString( 'GTM container ID is missing or invalid', $html );
        $this->assertStringContainsString( 'is-dismissible', $html );

        // The settings screen carries the form itself.
        $this->assertSame( '', $this->render_on( 'settings_page_choice-universal-form-tracker' ) );
    }

    public function test_dismiss_notice_ajax_handler_is_gone() {
        $this->assertFalse( has_action( 'wp_ajax_cuft_dismiss_notice' ) );
    }
}
