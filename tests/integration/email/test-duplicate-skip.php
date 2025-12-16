<?php
/**
 * Integration Test: Duplicate Email Skip Logic
 *
 * Tests that BCC is skipped when address is already a recipient.
 * This test MUST FAIL until T018 (Email Interceptor) is implemented.
 *
 * Scenario: Acceptance scenario - BCC address already a recipient
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Duplicate_Skip extends WP_UnitTestCase {

	/**
	 * Test duplicate email skip logic
	 *
	 * @test
	 */
	public function test_duplicate_email_skip_logic() {
		// Step 1: Enable Auto-BCC with email "test@example.com"
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'test@example.com',
			'selected_email_types' => array( 'form_submission' ),
		) );

		// Step 2: Send email where TO = "test@example.com"
		$email_args_duplicate = array(
			'to' => 'test@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		$result_duplicate = apply_filters( 'wp_mail', $email_args_duplicate );

		// Step 3: Verify BCC header NOT added (duplicate prevention)
		$headers_duplicate = isset( $result_duplicate['headers'] ) ? ( is_array( $result_duplicate['headers'] ) ? $result_duplicate['headers'] : array( $result_duplicate['headers'] ) ) : array();
		$has_bcc_duplicate = false;
		foreach ( $headers_duplicate as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false ) {
				$has_bcc_duplicate = true;
				break;
			}
		}

		$this->assertFalse( $has_bcc_duplicate, 'BCC should NOT be added when address is already TO recipient' );

		// Step 4: Send email where TO = "other@example.com"
		$email_args_different = array(
			'to' => 'other@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		$result_different = apply_filters( 'wp_mail', $email_args_different );

		// Step 5: Verify BCC header IS added
		$headers_different = isset( $result_different['headers'] ) ? ( is_array( $result_different['headers'] ) ? $result_different['headers'] : array( $result_different['headers'] ) ) : array();
		$has_bcc_different = false;
		foreach ( $headers_different as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false && stripos( $header, 'test@example.com' ) !== false ) {
				$has_bcc_different = true;
				break;
			}
		}

		$this->assertTrue( $has_bcc_different, 'BCC should be added when address is different from TO recipient' );
	}

	/**
	 * Clean up after test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
	}
}
