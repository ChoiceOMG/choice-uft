<?php
/**
 * Integration Test: SMTP Plugin Compatibility
 *
 * Tests that Auto-BCC works with SMTP plugins (priority ordering).
 * This test MUST FAIL until T018 (Email Interceptor) is implemented.
 *
 * Scenario: Research finding - SMTP plugins use priority 20+
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_SMTP_Plugin_Compatibility extends WP_UnitTestCase {

	/**
	 * Test SMTP plugin compatibility (priority order)
	 *
	 * @test
	 */
	public function test_smtp_plugin_compatibility() {
		// Step 1: Mock SMTP plugin filter at priority 20
		add_filter( 'wp_mail', function( $args ) {
			// SMTP plugin would process headers here
			$args['smtp_processed'] = true;
			return $args;
		}, 20 );

		// Step 2: Enable Auto-BCC (priority 10)
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
		) );

		// Step 3: Send email
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		$result = apply_filters( 'wp_mail', $email_args );

		// Step 4: Verify BCC added BEFORE SMTP plugin processes (priority order)
		$this->assertArrayHasKey( 'smtp_processed', $result, 'SMTP plugin should have processed' );

		// Step 5: Verify BCC header preserved through SMTP processing
		$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
		$has_bcc = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false && stripos( $header, 'testing@example.com' ) !== false ) {
				$has_bcc = true;
				break;
			}
		}

		$this->assertTrue( $has_bcc, 'BCC header should be preserved through SMTP processing' );
	}

	/**
	 * Clean up after test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
		remove_all_filters( 'wp_mail', 20 );
	}
}
