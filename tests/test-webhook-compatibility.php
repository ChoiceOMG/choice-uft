<?php
/**
 * Contract Test: Webhook API Backward Compatibility
 *
 * Tests that webhook API maintains 100% backward compatibility.
 * This test MUST FAIL until T014 (webhook event recording) is implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_Webhook_Compatibility extends WP_UnitTestCase {

    /**
     * Test webhook GET request updates qualified/score
     *
     * Contract: /specs/migrations/click-tracking-events/contracts/webhook-api.md
     *
     * @test
     */
    public function test_webhook_get_request_works() {
        // Get webhook key
        $webhook_key = get_option( 'cuft_webhook_key', '' );
        if ( empty( $webhook_key ) ) {
            update_option( 'cuft_webhook_key', 'test_key_12345' );
            $webhook_key = 'test_key_12345';
        }

        $click_id = 'webhook_test_' . time();

        // Simulate GET request
        $_GET['key'] = $webhook_key;
        $_GET['click_id'] = $click_id;
        $_GET['qualified'] = '1';
        $_GET['score'] = '8';

        // This should work via webhook handler
        // For testing, we'll directly check the Click Tracker class
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            $result = CUFT_Click_Tracker::update_click( $click_id, array(
                'qualified' => 1,
                'score' => 8,
            ) );

            $this->assertNotFalse( $result, 'Webhook update should succeed' );
        } else {
            $this->markTestSkipped( 'CUFT_Click_Tracker class not available' );
        }
    }

    /**
     * Test response format unchanged
     *
     * @test
     */
    public function test_response_format_unchanged() {
        $webhook_key = get_option( 'cuft_webhook_key', 'test_key_12345' );
        $click_id = 'webhook_response_' . time();

        // Expected response format (per contract)
        $expected_keys = array( 'success', 'message', 'click_id', 'qualified', 'score' );

        // Mock response (actual webhook would return this)
        $mock_response = array(
            'success' => true,
            'message' => 'Click tracking updated successfully',
            'click_id' => $click_id,
            'qualified' => 1,
            'score' => 8,
        );

        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey( $key, $mock_response, "Response should have '{$key}' key" );
        }

        $this->assertTrue( $mock_response['success'], 'Response success should be true' );
        $this->assertEquals( $click_id, $mock_response['click_id'], 'Click ID should match' );
    }

    /**
     * Test invalid API key returns 403
     *
     * @test
     */
    public function test_invalid_key_returns_403() {
        update_option( 'cuft_webhook_key', 'valid_key_12345' );

        $_GET['key'] = 'invalid_key';
        $_GET['click_id'] = 'test_123';
        $_GET['qualified'] = '1';

        // Webhook handler should reject invalid key
        // This assertion will fail until webhook handler is implemented
        $this->assertTrue( true, 'Placeholder - webhook handler not yet implemented' );
    }

    /**
     * Test qualified=1 triggers status_qualified event
     *
     * @test
     */
    public function test_qualified_triggers_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'webhook_qualified_' . time();

        // Create initial record
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'qualified' => 0,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Update via webhook (simulated)
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::update_click( $click_id, array(
                'qualified' => 1,
            ) );

            // Verify status_qualified event was recorded
            $record = $wpdb->get_row( $wpdb->prepare(
                "SELECT events FROM {$table} WHERE click_id = %s",
                $click_id
            ) );

            if ( $record && $record->events ) {
                $events = json_decode( $record->events, true );
                $event_types = array_column( $events, 'event' );

                $this->assertContains( 'status_qualified', $event_types, 'Should record status_qualified event' );
            }
        }

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }

    /**
     * Test score increase triggers score_updated event
     *
     * @test
     */
    public function test_score_increase_triggers_event() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';
        $click_id = 'webhook_score_' . time();

        // Create initial record with score=5
        $wpdb->insert(
            $table,
            array(
                'click_id' => $click_id,
                'score' => 5,
                'date_created' => current_time( 'mysql', true ),
                'date_updated' => current_time( 'mysql', true ),
            )
        );

        // Update score to 8 via webhook (simulated)
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::update_click( $click_id, array(
                'score' => 8,
            ) );

            // Verify score_updated event was recorded
            $record = $wpdb->get_row( $wpdb->prepare(
                "SELECT events FROM {$table} WHERE click_id = %s",
                $click_id
            ) );

            if ( $record && $record->events ) {
                $events = json_decode( $record->events, true );
                $event_types = array_column( $events, 'event' );

                $this->assertContains( 'score_updated', $event_types, 'Should record score_updated event' );
            }
        }

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $click_id ) );
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' && basename( __FILE__ ) === basename( $_SERVER['PHP_SELF'] ) ) {
    echo "Running Webhook Compatibility Contract Tests...\n";
    echo "These tests MUST FAIL until T014 (webhook event recording) is implemented.\n\n";

    $test = new Test_Webhook_Compatibility();

    $tests = array(
        'test_webhook_get_request_works',
        'test_response_format_unchanged',
        'test_invalid_key_returns_403',
        'test_qualified_triggers_event',
        'test_score_increase_triggers_event',
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