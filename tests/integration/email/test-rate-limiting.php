<?php
/**
 * Integration Test: Rate Limiting Enforcement
 *
 * Tests that rate limiting prevents excessive BCC emails.
 * This test MUST FAIL until T017 (Rate Limiter) is implemented.
 *
 * Scenario: Edge case - high-volume email scenarios
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Rate_Limiting extends WP_UnitTestCase {

	/**
	 * Test rate limiting enforcement
	 *
	 * @test
	 */
	public function test_rate_limiting_enforcement() {
		// Step 1: Enable Auto-BCC with rate limit threshold = 2
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
			'rate_limit_threshold' => 2,
			'rate_limit_action' => 'pause_until_next_period',
		) );

		// Clear rate limit transient
		$hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
		delete_transient( $hour_key );

		// Step 2: Send 3 emails
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		$results = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$result = apply_filters( 'wp_mail', $email_args );

			$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
			$has_bcc = false;
			foreach ( $headers as $header ) {
				if ( stripos( $header, 'Bcc:' ) !== false ) {
					$has_bcc = true;
					break;
				}
			}

			$results[] = array(
				'email_number' => $i,
				'has_bcc' => $has_bcc,
			);
		}

		// Step 3: Verify first 2 emails have BCC header
		$this->assertTrue( $results[0]['has_bcc'], 'Email 1 should have BCC (under limit)' );
		$this->assertTrue( $results[1]['has_bcc'], 'Email 2 should have BCC (under limit)' );

		// Step 4: Verify 3rd email does NOT have BCC (rate limit exceeded)
		$this->assertFalse( $results[2]['has_bcc'], 'Email 3 should NOT have BCC (rate limit exceeded)' );

		// Step 5: Verify warning logged to debug.log (check transient value)
		$current_count = get_transient( $hour_key );
		$this->assertGreaterThanOrEqual( 2, $current_count, 'Rate limit counter should be at or above threshold' );
	}

	/**
	 * Clean up after test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
		$hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
		delete_transient( $hour_key );
	}
}
