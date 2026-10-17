<?php
/**
 * The plugin's own screens credit Choice OMG and invite a WordPress.org review
 * in the admin footer, and the Plugins screen row links to the documentation
 * and the review form. Nothing appears on any other admin screen.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.1
 */

class Test_Admin_Branding extends WP_UnitTestCase {

    private $admin;

    public function set_up() {
        parent::set_up();
        $this->admin = new CUFT_Admin();
    }

    public function tear_down() {
        set_current_screen( 'front' );
        parent::tear_down();
    }

    private function footer_on( $screen_id ) {
        set_current_screen( $screen_id );
        return $this->admin->admin_footer_text( 'Thank you for creating with WordPress.' );
    }

    public function test_footer_credits_choice_omg_on_each_plugin_screen() {
        foreach ( array( 'settings_page_choice-universal-form-tracker', 'toplevel_page_cuft-click-tracking', 'settings_page_cuft-testing-dashboard' ) as $screen ) {
            $footer = $this->footer_on( $screen );

            $this->assertStringContainsString( '>Choice OMG</a>', $footer, $screen );
            $this->assertStringContainsString( 'https://choice.marketing/?utm_source=wp-plugin', $footer, $screen );
            $this->assertStringContainsString( 'https://wordpress.org/support/plugin/choice-universal-form-tracker/reviews/#new-post', $footer, $screen );
        }
    }

    public function test_footer_is_untouched_elsewhere() {
        $this->assertSame( 'Thank you for creating with WordPress.', $this->footer_on( 'dashboard' ) );
        $this->assertSame( 'Thank you for creating with WordPress.', $this->footer_on( 'plugins' ) );
    }

    public function test_footer_filter_is_registered() {
        $this->assertNotFalse( has_filter( 'admin_footer_text', array( $this->admin, 'admin_footer_text' ) ) );
    }

    public function test_settings_header_shows_the_mark_and_credits_choice_omg() {
        $m = new ReflectionMethod( $this->admin, 'render_admin_header' );
        $m->setAccessible( true );
        ob_start();
        $m->invoke( $this->admin );
        $header = ob_get_clean();

        $this->assertStringContainsString( '<h1>Choice Universal Form Tracker</h1>', $header );
        $this->assertStringContainsString( 'assets/images/cuft-icon-128.png', $header );
        $this->assertStringContainsString( '>Choice OMG</a>', $header );
        $this->assertStringContainsString( 'utm_content=header', $header );
        $this->assertStringNotContainsString( '📊', $header );
        $this->assertFileExists( CUFT_PATH . 'assets/images/cuft-icon-128.png' );
    }

    public function test_plugin_row_links_documentation_and_reviews() {
        $meta = implode( ' ', apply_filters( 'plugin_row_meta', array(), CUFT_BASENAME, array(), 'all' ) );

        $this->assertStringContainsString( 'href="https://choice.marketing/tools/choice-uft/?utm_source=wp-plugin', $meta );
        $this->assertStringContainsString( 'href="https://wordpress.org/support/plugin/choice-universal-form-tracker/reviews/#new-post"', $meta );
    }
}
