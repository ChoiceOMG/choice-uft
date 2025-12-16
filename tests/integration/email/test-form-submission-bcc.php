<?php
/**
 * Integration Test: Form Submission Triggers BCC
 *
 * Tests that form submissions trigger BCC email when feature is enabled.
 * This test MUST FAIL until T018 (Email Interceptor) is implemented.
 *
 * Scenario: Acceptance scenario #2 from spec.md
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Form_Submission_BCC extends WP_UnitTestCase {

	/**
	 * Test form submission triggers BCC
	 *
	 * @test
	 */
	public function test_form_submission_triggers_bcc() {
		// Step 1: Enable Auto-BCC with form_submission type
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
			'rate_limit_threshold' => 100,
			'rate_limit_action' => 'log_only',
		) );

		// Step 2: Trigger WordPress email with form submission subject
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Name: Test User\nEmail: test@example.com',
			'headers' => array( 'Content-Type: text/html' ),
			'attachments' => array(),
		);

		// Step 3: Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $email_args );

		// Step 4: Verify BCC header added
		$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
		$has_bcc = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false && stripos( $header, 'testing@example.com' ) !== false ) {
				$has_bcc = true;
				break;
			}
		}

		$this->assertTrue( $has_bcc, 'BCC header should be added for form submission emails' );

		// Step 5: Verify original TO address unchanged
		$this->assertEquals( 'admin@example.com', $result['to'], 'Original TO address should be preserved' );
	}

	/**
	 * Clean up after test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
	}
}
