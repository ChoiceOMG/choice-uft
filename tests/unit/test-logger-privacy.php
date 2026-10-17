<?php
/**
 * The plugin's log lives in the cuft_debug_logs option. Error entries are
 * written even with debug logging off, and nothing erases them on request,
 * so a visitor's contact details and form answers must never reach it.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

class Test_Logger_Privacy extends WP_UnitTestCase {

    public function tearDown(): void {
        CUFT_Logger::clear_logs();
        delete_option( 'cuft_debug_enabled' );
        parent::tearDown();
    }

    private function stored_logs() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'cuft_debug_logs'" );
    }

    /**
     * The context CUFT_CF7_Forms::track_mail_failed() logs at error level.
     */
    public function test_failed_mail_error_keeps_no_contact_details() {
        CUFT_Logger::log_error( 'CF7 mail failed to send: contact_form_7', array(
            'form_id'    => 42,
            'form_name'  => 'Contact',
            'user_email' => 'visitor@example.com',
            'user_phone' => '+17805550142',
        ) );

        $stored = $this->stored_logs();
        $this->assertStringNotContainsString( 'visitor@example.com', $stored );
        $this->assertStringNotContainsString( '7805550142', $stored );

        $entry = CUFT_Logger::get_logs( 1 )[0];
        $this->assertSame( 42, $entry['context']['form_id'] );
        $this->assertTrue( $entry['context']['user_email'] );
        $this->assertTrue( $entry['context']['user_phone'] );
    }

    /**
     * The shape CUFT_Elementor_Forms logs when debug logging is on: every
     * field with its value.
     */
    public function test_form_field_values_are_not_logged() {
        update_option( 'cuft_debug_enabled', true );
        CUFT_Logger::log_form_submission( 'elementor', array(
            'form_id'    => 'abc123',
            'user_email' => '',
            'field_data' => array(
                'name'    => array( 'type' => 'text', 'value' => 'Jane Visitor', 'raw_value' => 'Jane Visitor' ),
                'message' => array( 'type' => 'textarea', 'value' => 'Call me at 780-555-0142', 'raw_value' => 'Call me at 780-555-0142' ),
                'contact' => array( 'type' => 'text', 'value' => 'visitor@example.com', 'raw_value' => 'visitor@example.com' ),
            ),
        ) );

        $stored = $this->stored_logs();
        $this->assertStringNotContainsString( 'Jane Visitor', $stored );
        $this->assertStringNotContainsString( '555-0142', $stored );
        $this->assertStringNotContainsString( 'visitor@example.com', $stored );

        $entry = CUFT_Logger::get_logs( 1 )[0];
        $this->assertSame( 'textarea', $entry['context']['field_data']['message']['type'] );
        $this->assertFalse( $entry['context']['user_email'] );
    }

    public function test_log_option_is_not_autoloaded() {
        CUFT_Logger::log_error( 'Something failed' );

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $autoload = $wpdb->get_var( "SELECT autoload FROM {$wpdb->options} WHERE option_name = 'cuft_debug_logs'" );
        $this->assertContains( $autoload, array( 'no', 'off' ) );
    }
}
