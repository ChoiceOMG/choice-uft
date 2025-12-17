<?php
/**
 * Contract Test: AJAX Save Auto-BCC Settings
 *
 * Tests the cuft_save_auto_bcc_settings AJAX endpoint for save functionality.
 * This test MUST FAIL until T020 (AJAX handler) is implemented.
 *
 * Contract: /specs/010-auto-bcc-everyting/contracts/admin-ajax-save-settings.md
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	// For standalone testing, load WordPress
	require_once dirname( dirname( __FILE__ ) ) . '/choice-universal-form-tracker.php';
}

class Test_AJAX_Save_Settings_Contract extends WP_UnitTestCase {

	/**
	 * Test nonce validation fails → 403 error
	 *
	 * @test
	 */
	public function test_nonce_validation_fails() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_save_auto_bcc_settings';
		$_POST['nonce'] = 'invalid_nonce';
		$_POST['enabled'] = true;
		$_POST['bcc_email'] = 'test@example.com';

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_save_auto_bcc_settings' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertFalse( $data['success'], 'Response should indicate failure for invalid nonce' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
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
		$_POST['action'] = 'cuft_save_auto_bcc_settings';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_settings' );
		$_POST['enabled'] = true;
		$_POST['bcc_email'] = 'test@example.com';

		// Create subscriber user (no update_plugins capability)
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_save_auto_bcc_settings' );
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
	 * Test invalid email format → error response with message
	 *
	 * @test
	 */
	public function test_invalid_email_format() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_save_auto_bcc_settings';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_settings' );
		$_POST['enabled'] = true;
		$_POST['bcc_email'] = 'not-an-email';
		$_POST['selected_email_types'] = array( 'form_submission' );

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_save_auto_bcc_settings' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertFalse( $data['success'], 'Response should indicate failure for invalid email' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
			$this->assertArrayHasKey( 'message', $data['data'], 'Response should have error message' );
			$this->assertStringContainsString( 'email', strtolower( $data['data']['message'] ), 'Error message should mention email' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Test valid configuration → success response with saved config
	 *
	 * @test
	 */
	public function test_valid_configuration_saves() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_save_auto_bcc_settings';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_settings' );
		$_POST['enabled'] = true;
		$_POST['bcc_email'] = 'testing@example.com';
		$_POST['selected_email_types'] = array( 'form_submission', 'user_registration' );
		$_POST['rate_limit_threshold'] = 100;
		$_POST['rate_limit_action'] = 'log_only';

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_save_auto_bcc_settings' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertTrue( $data['success'], 'Response should indicate success' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );
			$this->assertArrayHasKey( 'message', $data['data'], 'Response should have success message' );
			$this->assertArrayHasKey( 'config', $data['data'], 'Response should have saved config' );

			// Verify config structure
			$config = $data['data']['config'];
			$this->assertEquals( true, $config['enabled'], 'Config enabled should be true' );
			$this->assertEquals( 'testing@example.com', $config['bcc_email'], 'Config email should match' );
			$this->assertIsArray( $config['selected_email_types'], 'Selected email types should be array' );
			$this->assertContains( 'form_submission', $config['selected_email_types'], 'Should contain form_submission' );
			$this->assertEquals( 100, $config['rate_limit_threshold'], 'Rate limit threshold should match' );
			$this->assertEquals( 'log_only', $config['rate_limit_action'], 'Rate limit action should match' );
		} catch ( Exception $e ) {
			$this->fail( 'AJAX endpoint not implemented yet: ' . $e->getMessage() );
		}
	}

	/**
	 * Test WordPress mail validation warning included if mail function unavailable
	 *
	 * @test
	 */
	public function test_mail_function_validation_warning() {
		// Set up WordPress AJAX environment
		$_POST['action'] = 'cuft_save_auto_bcc_settings';
		$_POST['nonce'] = wp_create_nonce( 'cuft_auto_bcc_settings' );
		$_POST['enabled'] = true;
		$_POST['bcc_email'] = 'testing@example.com';
		$_POST['selected_email_types'] = array( 'form_submission' );

		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Simulate AJAX request
		try {
			ob_start();
			do_action( 'wp_ajax_cuft_save_auto_bcc_settings' );
			$response = ob_get_clean();

			// Parse JSON response
			$data = json_decode( $response, true );

			// Assertions per contract
			$this->assertIsArray( $data, 'Response should be JSON array' );
			$this->assertTrue( $data['success'], 'Response should indicate success' );
			$this->assertArrayHasKey( 'data', $data, 'Response should have data key' );

			// Check if warnings array exists (may be empty if mail function is available)
			if ( isset( $data['data']['warnings'] ) ) {
				$this->assertIsArray( $data['data']['warnings'], 'Warnings should be array' );
			}
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
		delete_option( 'cuft_auto_bcc_config' );
	}
}
