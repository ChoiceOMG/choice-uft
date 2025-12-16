<?php
/**
 * Contract Test: AJAX Send Test Email
 *
 * Tests the cuft_send_test_bcc_email AJAX endpoint for test email functionality.
 * This test MUST FAIL until T020 (AJAX handler) is implemented.
 *
 * Contract: /specs/010-auto-bcc-everyting/contracts/admin-ajax-send-test-email.md
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	// For standalone testing, load WordPress
	require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_Send_Test_Email_Contract extends WP_UnitTestCase {

	/**
	 * Test nonce validation fails → 403 error
	 *
	 * @test
	 */
	public function test_nonce_validation_fails() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_send_test_bcc_email';
		$_POST['nonce'] = 'invalid_nonce';
		$_POST['bcc_email'] = 'test@example.com';

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_send_test_bcc_email' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertFalse( $data['success'], 'Response should indicate failure for invalid nonce' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Test capability check fails → 403 error
	 *
	 * @test
	 */
	public function test_capability_check_fails() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_send_test_bcc_email';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_test_email' );
		$_POST['bcc_email'] = 'test@example.com';

		// Create subscriber user (no update_plugins capability)
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_send_test_bcc_email' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertFalse( $data['success'], 'Response should indicate failure for insufficient capability' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Test email sent successfully → success response with subject and timestamp
	 *
	 * @test
	 */
	public function test_email_sent_successfully() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_send_test_bcc_email';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_test_email' );
		$_POST['bcc_email'] = 'testing@example.com';

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_send_test_bcc_email' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertTrue( $data['success'], 'Response should indicate success' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
			$this->assertArrayHasKey( 'message', $data['data'], 'Response should have message' );
			$this->assertArrayHasKey( 'subject', $data['data'], 'Response should have subject' );
			$this->assertArrayHasKey( 'sent_at', $data['data'], 'Response should have sent_at timestamp' );

			// Verify subject contains test identifier
			$this->assertStringContainsString( '[CUFT Test Email]', $data['data']['subject'], 'Subject should contain test identifier' );

			// Verify timestamp is recent (within last 60 seconds)
			$this->assertGreaterThan( time() - 60, $data['data']['sent_at'], 'Timestamp should be recent' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Test email send fails → error response with wp_mail failure message
	 *
	 * @test
	 */
	public function test_email_send_fails() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_send_test_bcc_email';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_test_email' );
		$_POST['bcc_email'] = 'testing@example.com';

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock wp_mail to return false
		add_filter( 'wp_mail', function( $args ) {
			return false;
		}, 999 );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_send_test_bcc_email' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertFalse( $data['success'], 'Response should indicate failure' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
			$this->assertArrayHasKey( 'message', $data['data'], 'Response should have error message' );
			$this->assertArrayHasKey( 'error', $data['data'], 'Response should have error field' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();
		unset( $_POST );
		remove_all_filters( 'wp_mail' );
	}
}
