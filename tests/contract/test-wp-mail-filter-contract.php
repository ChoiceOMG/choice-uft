<?php
/**
 * Contract Test: wp_mail Filter Hook
 *
 * Tests the wp_mail filter hook for BCC email interception.
 * This test MUST FAIL until T018 (Email Interceptor) is implemented.
 *
 * Contract: /specs/010-auto-bcc-everyting/contracts/wp-mail-filter.md
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	// For standalone testing, load WordPress
	require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_WP_Mail_Filter_Contract extends WP_UnitTestCase {

	/**
	 * Test filter registered at priority 10
	 *
	 * @test
	 */
	public function test_filter_registered_at_priority_10() {
		global $wp_filter;

		$this->assertArrayHasKey( 'wp_mail', $wp_filter, 'wp_mail filter should be registered' );

		// Check if filter exists at priority 10
		$has_filter_at_10 = false;
		if ( isset( $wp_filter['wp_mail'][10] ) ) {
			foreach ( $wp_filter['wp_mail'][10] as $callback ) {
				// Check if callback contains interceptor class
				if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
					$class_name = get_class( $callback['function'][0] );
					if ( strpos( $class_name, 'Email_Interceptor' ) !== false ) {
						$has_filter_at_10 = true;
						break;
					}
				}
			}
		}

		$this->assertTrue( $has_filter_at_10, 'Email interceptor filter should be registered at priority 10' );
	}

	/**
	 * Test BCC header added when feature enabled and email type matches
	 *
	 * @test
	 */
	public function test_bcc_header_added_when_conditions_met() {
		// Enable Auto-BCC feature
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
			'rate_limit_threshold' => 100,
			'rate_limit_action' => 'log_only',
			'last_modified' => time(),
			'last_modified_by' => 1,
		) );

		// Create email arguments
		$args = array(
			'to' => 'user@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array( 'Content-Type: text/html' ),
			'attachments' => array(),
		);

		// Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $args );

		// Assertions per contract
		$this->assertIsArray( $result, 'Filtered result should be array' );
		$this->assertArrayHasKey( 'headers', $result, 'Result should have headers' );

		// Check if BCC header was added
		$headers = is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] );
		$has_bcc = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false && stripos( $header, 'testing@example.com' ) !== false ) {
				$has_bcc = true;
				break;
			}
		}

		$this->assertTrue( $has_bcc, 'BCC header should be added when conditions are met' );
	}

	/**
	 * Test BCC header NOT added when feature disabled
	 *
	 * @test
	 */
	public function test_bcc_not_added_when_disabled() {
		// Disable Auto-BCC feature
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => false,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
		) );

		// Create email arguments
		$args = array(
			'to' => 'user@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		// Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $args );

		// Check if BCC header was NOT added
		$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
		$has_bcc = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false ) {
				$has_bcc = true;
				break;
			}
		}

		$this->assertFalse( $has_bcc, 'BCC header should NOT be added when feature is disabled' );
	}

	/**
	 * Test BCC header NOT added when email type not selected
	 *
	 * @test
	 */
	public function test_bcc_not_added_when_type_not_selected() {
		// Enable Auto-BCC but only for user_registration (not form_submission)
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'user_registration' ),
		) );

		// Create form submission email (type not selected)
		$args = array(
			'to' => 'user@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		// Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $args );

		// Check if BCC header was NOT added
		$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
		$has_bcc = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false ) {
				$has_bcc = true;
				break;
			}
		}

		$this->assertFalse( $has_bcc, 'BCC header should NOT be added when email type is not selected' );
	}

	/**
	 * Test BCC header NOT added when BCC address already in TO/CC
	 *
	 * @test
	 */
	public function test_bcc_not_added_when_duplicate() {
		// Enable Auto-BCC feature
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
		) );

		// Create email where TO address matches BCC address
		$args = array(
			'to' => 'testing@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		// Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $args );

		// Check if BCC header was NOT added (duplicate prevention)
		$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
		$bcc_count = 0;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Bcc:' ) !== false && stripos( $header, 'testing@example.com' ) !== false ) {
				$bcc_count++;
			}
		}

		$this->assertEquals( 0, $bcc_count, 'BCC header should NOT be added when address is already a recipient' );
	}

	/**
	 * Test original email arguments preserved (no modification except headers)
	 *
	 * @test
	 */
	public function test_original_arguments_preserved() {
		// Enable Auto-BCC feature
		update_option( 'cuft_auto_bcc_config', array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
		) );

		// Create email arguments
		$original_args = array(
			'to' => 'user@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message body',
			'headers' => array( 'Content-Type: text/html' ),
			'attachments' => array( '/path/to/file.pdf' ),
		);

		// Apply wp_mail filter
		$result = apply_filters( 'wp_mail', $original_args );

		// Assertions per contract - all fields except headers should be unchanged
		$this->assertEquals( $original_args['to'], $result['to'], 'TO address should not be modified' );
		$this->assertEquals( $original_args['subject'], $result['subject'], 'Subject should not be modified' );
		$this->assertEquals( $original_args['message'], $result['message'], 'Message body should not be modified' );
		$this->assertEquals( $original_args['attachments'], $result['attachments'], 'Attachments should not be modified' );
	}

	/**
	 * Test rate limit respected (BCC skipped when limit exceeded)
	 *
	 * @test
	 */
	public function test_rate_limit_respected() {
		// Enable Auto-BCC with low rate limit
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

		// Create email arguments
		$args = array(
			'to' => 'user@example.com',
			'subject' => 'Contact Form Submission',
			'message' => 'Test message',
			'headers' => array(),
			'attachments' => array(),
		);

		// Send 3 emails, expect 3rd to NOT have BCC (rate limit exceeded)
		for ( $i = 1; $i <= 3; $i++ ) {
			$result = apply_filters( 'wp_mail', $args );

			$headers = isset( $result['headers'] ) ? ( is_array( $result['headers'] ) ? $result['headers'] : array( $result['headers'] ) ) : array();
			$has_bcc = false;
			foreach ( $headers as $header ) {
				if ( stripos( $header, 'Bcc:' ) !== false ) {
					$has_bcc = true;
					break;
				}
			}

			if ( $i <= 2 ) {
				$this->assertTrue( $has_bcc, "Email $i should have BCC (under limit)" );
			} else {
				$this->assertFalse( $has_bcc, "Email $i should NOT have BCC (rate limit exceeded)" );
			}
		}
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
		$hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
		delete_transient( $hour_key );
	}
}
