<?php
/**
 * Unit Tests for Event Constants and Display Helpers
 *
 * Tests valid event type lists, display names, and icons
 * for GA4 lifecycle event types.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.22.0
 */

class Test_Event_Constants extends WP_UnitTestCase {

    public function test_event_recorder_includes_qualify_lead() {
        $valid = CUFT_Event_Recorder::VALID_EVENT_TYPES;
        $this->assertContains( 'qualify_lead', $valid );
    }

    public function test_event_recorder_includes_generate_lead() {
        $valid = CUFT_Event_Recorder::VALID_EVENT_TYPES;
        $this->assertContains( 'generate_lead', $valid );
    }

    public function test_utils_display_name_for_qualify_lead() {
        $name = CUFT_Utils::get_event_display_name( 'qualify_lead' );
        $this->assertEquals( 'Qualify Lead', $name );
    }

    public function test_utils_display_name_for_disqualify_lead() {
        $name = CUFT_Utils::get_event_display_name( 'disqualify_lead' );
        $this->assertEquals( 'Disqualify Lead', $name );
    }

    public function test_utils_display_name_for_working_lead() {
        $name = CUFT_Utils::get_event_display_name( 'working_lead' );
        $this->assertEquals( 'Working Lead', $name );
    }

    public function test_utils_display_name_for_close_convert_lead() {
        $name = CUFT_Utils::get_event_display_name( 'close_convert_lead' );
        $this->assertEquals( 'Close Convert Lead', $name );
    }

    public function test_utils_display_name_for_close_unconvert_lead() {
        $name = CUFT_Utils::get_event_display_name( 'close_unconvert_lead' );
        $this->assertEquals( 'Close Unconvert Lead', $name );
    }

    public function test_utils_icon_for_qualify_lead() {
        $icon = CUFT_Utils::get_event_icon( 'qualify_lead' );
        $this->assertEquals( '⭐', $icon );
    }

    public function test_utils_icon_for_working_lead() {
        $icon = CUFT_Utils::get_event_icon( 'working_lead' );
        $this->assertEquals( '📋', $icon );
    }

    public function test_utils_icon_for_close_convert_lead() {
        $icon = CUFT_Utils::get_event_icon( 'close_convert_lead' );
        $this->assertEquals( '✅', $icon );
    }
}
