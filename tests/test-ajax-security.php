<?php
/**
 * Contract Test: AJAX Endpoint Security Validation
 *
 * Tests security validation for the cuft_record_event AJAX endpoint.
 * This test MUST FAIL until T013 (AJAX handler) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_Security extends WP_UnitTestCase {

    /**
     * Test invalid nonce returns 403
     *
     * Contract: /specs/migrations/click-tracking-events/contracts/ajax-endpoint.md
     *
     * @test
     */
    public function test_invalid_nonce_returns_403() {
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = 'test_click_123';
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = 'invalid_nonce_12345';

        ob_start();
        try {
            do_action( 'wp_ajax_cuft_record_event' );
            $response = ob_get_clean();

            $data = json_decode( $response, true );

            $this->assertFalse( $data['success'], 'Response should indicate failure' );
            $this->assertStringContainsString( 'Security check failed', $data['data']['message'], 'Should return security error message' );

        } catch ( WPDieException $e ) {
            // WordPress may die on nonce failure - this is acceptable
            ob_end_clean();
            $this->assertStringContainsString( 'nonce', strtolower( $e->getMessage() ), 'Error should mention nonce' );
        }
    }

    /**
     * Test missing nonce returns error
     *
     * @test
     */
    public function test_missing_nonce_returns_error() {
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = 'test_click_123';
        $_POST['event_type'] = 'phone_click';
        // No nonce provided

        ob_start();
        try {
            do_action( 'wp_ajax_cuft_record_event' );
            $response = ob_get_clean();

            $data = json_decode( $response, true );

            $this->assertFalse( $data['success'], 'Response should indicate failure' );

        } catch ( WPDieException $e ) {
            ob_end_clean();
            $this->assertStringContainsString( 'nonce', strtolower( $e->getMessage() ), 'Error should mention nonce' );
        }
    }

    /**
     * Test invalid event_type returns 400
     *
     * @test
     */
    public function test_invalid_event_type_returns_400() {
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = 'test_click_123';
        $_POST['event_type'] = 'invalid_event_type';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        $this->assertFalse( $data['success'], 'Response should indicate failure' );
        $this->assertStringContainsString( 'Invalid event type', $data['data']['message'], 'Should return invalid event type error' );
        $this->assertArrayHasKey( 'allowed_types', $data['data'], 'Should include allowed types' );

        $allowed = $data['data']['allowed_types'];
        $this->assertContains( 'phone_click', $allowed, 'Should list phone_click as allowed' );
        $this->assertContains( 'email_click', $allowed, 'Should list email_click as allowed' );
        $this->assertContains( 'form_submit', $allowed, 'Should list form_submit as allowed' );
        $this->assertContains( 'generate_lead', $allowed, 'Should list generate_lead as allowed' );
    }

    /**
     * Test missing click_id returns 400
     *
     * @test
     */
    public function test_missing_click_id_returns_400() {
        $_POST['action'] = 'cuft_record_event';
        // No click_id provided
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        $this->assertFalse( $data['success'], 'Response should indicate failure' );
        $this->assertStringContainsString( 'Missing required parameter: click_id', $data['data']['message'], 'Should return missing click_id error' );
    }

    /**
     * Test empty click_id returns 400
     *
     * @test
     */
    public function test_empty_click_id_returns_400() {
        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = '';
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        $data = json_decode( $response, true );

        $this->assertFalse( $data['success'], 'Response should indicate failure' );
    }

    /**
     * Test event type whitelist enforcement
     *
     * @test
     */
    public function test_event_type_whitelist_enforced() {
        $valid_types = array( 'phone_click', 'email_click', 'form_submit', 'generate_lead' );
        $invalid_types = array( 'sql_injection', 'xss_attack', 'random_event', '../../etc/passwd' );

        foreach ( $invalid_types as $invalid_type ) {
            $_POST['action'] = 'cuft_record_event';
            $_POST['click_id'] = 'test_click_123';
            $_POST['event_type'] = $invalid_type;
            $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

            ob_start();
            do_action( 'wp_ajax_cuft_record_event' );
            $response = ob_get_clean();

            $data = json_decode( $response, true );

            $this->assertFalse( $data['success'], "Invalid event type '{$invalid_type}' should be rejected" );
        }
    }

    /**
     * Test click_id sanitization
     *
     * @test
     */
    public function test_click_id_sanitization() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        // Test with potentially malicious click_id
        $malicious_click_id = '<script>alert("xss")</script>';

        $_POST['action'] = 'cuft_record_event';
        $_POST['click_id'] = $malicious_click_id;
        $_POST['event_type'] = 'phone_click';
        $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

        ob_start();
        do_action( 'wp_ajax_cuft_record_event' );
        $response = ob_get_clean();

        // Check that click_id was sanitized (script tags removed)
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT click_id FROM {$table} WHERE click_id LIKE %s",
            '%alert%'
        ) );

        if ( $record ) {
            $this->assertStringNotContainsString( '<script>', $record->click_id, 'Script tags should be sanitized' );
            $this->assertStringNotContainsString( '</script>', $record->click_id, 'Script tags should be sanitized' );

            // Cleanup
            $wpdb->delete( $table, array( 'click_id' => $record->click_id ) );
        }
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running AJAX Security Contract Tests...\n";
    echo "These tests MUST FAIL until T013 (AJAX handler) is implemented.\n\n";

    $test = new Test_AJAX_Security();

    $tests = array(
        'test_invalid_nonce_returns_403',
        'test_missing_nonce_returns_error',
        'test_invalid_event_type_returns_400',
        'test_missing_click_id_returns_400',
        'test_empty_click_id_returns_400',
        'test_event_type_whitelist_enforced',
        'test_click_id_sanitization',
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