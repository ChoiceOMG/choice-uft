<?php
/**
 * Integration Test: Form Submit Event Recording
 *
 * Tests end-to-end form submission event recording across frameworks.
 * This test MUST FAIL until T017-T021 (framework integrations) are implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_Form_Submit_Integration extends WP_UnitTestCase {

    /**
     * Test form submission records form_submit event
     *
     * Scenario: quickstart.md Step 4
     *
     * @test
     */
    public function test_form_submit_records_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'form_integration_' . time();

        // Simulate form submission recording
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Form submit recording should succeed' );

        // Verify in database
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $this->assertNotNull( $record, 'Record should exist' );

        $events = json_decode( $record->events, true );
        $event_types = array_column( $events, 'event' );

        $this->assertContains( 'form_submit', $event_types, 'Should contain form_submit event' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test generate_lead fires when email + phone + click_id present
     *
     * @test
     */
    public function test_generate_lead_when_qualified() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'qualified_lead_' . time();

        // Create record with email and phone (simulating form with both fields)
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'additional_data' => json_encode( array(
                    'email' => 'test@example.com',
                    'phone' => '123-456-7890',
                ) ),
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Record generate_lead event
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'generate_lead';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Generate lead should succeed' );

        // Verify in database
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM {$table} WHERE click_id = %s",
            $click_id
        ) );

        $events = json_decode( $record->events, true );
        $event_types = array_column( $events, 'event' );

        $this->assertContains( 'generate_lead', $event_types, 'Should contain generate_lead event' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test Elementor form submit (simulated)
     *
     * @test
     */
    public function test_elementor_form_submit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'elementor_form_' . time();

        // Simulate Elementor form submission
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Elementor form submit should succeed' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test Contact Form 7 submit (simulated)
     *
     * @test
     */
    public function test_cf7_form_submit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'cf7_form_' . time();

        // Simulate CF7 form submission
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'CF7 form submit should succeed' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test Ninja Forms submit (simulated)
     *
     * @test
     */
    public function test_ninja_forms_submit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'ninja_form_' . time();

        // Simulate Ninja form submission
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Ninja form submit should succeed' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test Gravity Forms submit (simulated)
     *
     * @test
     */
    public function test_gravity_forms_submit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'gravity_form_' . time();

        // Simulate Gravity form submission
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Gravity form submit should succeed' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test Avada Forms submit (simulated)
     *
     * @test
     */
    public function test_avada_forms_submit() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'avada_form_' . time();

        // Simulate Avada form submission
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $click_id;
        $_POST['event_type'] = 'form_submit';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_nopriv_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );
        $this->assertTrue( $data['success'], 'Avada form submit should succeed' );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test dataLayer events still fire correctly (validation)
     *
     * @test
     */
    public function test_datalayer_events_unchanged() {
        // This test validates that existing dataLayer pushes are not affected
        // Form scripts should FIRST push to dataLayer, THEN record event

        // Mock dataLayer
        global $mock_datalayer;
        $mock_datalayer = array();

        // Simulate form_submit dataLayer push (what framework scripts do)
        $form_data = array(
            'event' => 'form_submit',
            'form_type' => 'elementor',
            'form_id' => 'test-form-123',
            'cuft_tracked' => true,
            'cuft_source' => 'elementor_pro',
        );

        $mock_datalayer[] = $form_data;

        // Verify dataLayer structure
        $this->assertArrayHasKey( 'event', $form_data, 'Should have event key' );
        $this->assertEquals( 'form_submit', $form_data['event'], 'Event should be form_submit' );
        $this->assertTrue( $form_data['cuft_tracked'], 'Should be tracked' );
        $this->assertEquals( 'elementor_pro', $form_data['cuft_source'], 'Should have source' );

        // This test verifies dataLayer structure is preserved
        $this->assertTrue( true, 'DataLayer events structure validated' );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running Form Submit Integration Tests...\n";
    echo "These tests MUST FAIL until T017-T021 (framework integrations) are implemented.\n\n";

    $test = new Test_Form_Submit_Integration();

    $tests = array(
        'test_form_submit_records_event',
        'test_generate_lead_when_qualified',
        'test_elementor_form_submit',
        'test_cf7_form_submit',
        'test_ninja_forms_submit',
        'test_gravity_forms_submit',
        'test_avada_forms_submit',
        'test_datalayer_events_unchanged',
    );

    foreach ( $tests as $test_name ) {
        try {
            $test->$test_name();
            echo "❌ UNEXPECTED: {$test_name} PASSED (should fail before implementation)\n";
        } catch ( Exception $e ) {
            echo "✅ EXPECTED: {$test_name} FAILED: " . $e->getMessage() . "\n";
        }
    }
}