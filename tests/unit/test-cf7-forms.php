<?php
/**
 * Tests for CUFT_CF7_Forms (OPS-2652).
 *
 * CF7 core only exposes wpcf7_mail_sent and wpcf7_mail_failed as PHP action
 * hooks; there is no server-side hook at all for validation_failed, spam,
 * acceptance_missing, or aborted submissions. That means wpcf7_mail_failed
 * is the only additional signal CUFT can capture server-side, and today it
 * captures nothing: a failed CF7 send is completely silent, which is what
 * made OPS-2652 hard to diagnose in the first place.
 */

class Test_CF7_Forms extends WP_UnitTestCase {

    public function set_up() {
        parent::set_up();

        if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
            $this->markTestSkipped( 'Contact Form 7 is not loaded in the test environment.' );
        }
    }

    public function tear_down() {
        delete_option( 'cuft_debug_enabled' );
        CUFT_Logger::clear_logs();
        parent::tear_down();
    }

    private function create_contact_form() {
        $contact_form = WPCF7_ContactForm::get_template( array( 'title' => 'OPS-2652 Test Form' ) );
        $contact_form->save();
        return $contact_form;
    }

    public function test_mail_failed_is_logged_as_error() {
        update_option( 'cuft_debug_enabled', true );

        $contact_form = $this->create_contact_form();

        $cf7_forms = new CUFT_CF7_Forms();
        do_action( 'wpcf7_mail_failed', $contact_form );

        $logs = CUFT_Logger::get_logs( 5 );

        $this->assertNotEmpty( $logs, 'wpcf7_mail_failed should produce a log entry' );
        $this->assertSame( CUFT_Logger::ERROR, $logs[0]['level'] );
        $this->assertStringContainsString( 'contact_form_7', $logs[0]['message'] );
        $this->assertSame( $contact_form->id(), $logs[0]['context']['form_id'] );
    }
}
