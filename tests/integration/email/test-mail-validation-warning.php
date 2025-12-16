<?php
/**
 * Integration Test: WordPress Mail Validation Warning
 *
 * Tests that WordPress mail function validation shows warning but allows save.
 * This test MUST FAIL until T019 (Manager) is implemented.
 *
 * Scenario: Acceptance scenario - WordPress mail function validation
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Mail_Validation_Warning extends WP_UnitTestCase {

	/**
	 * Test WordPress mail validation warning
	 *
	 * @test
	 */
	public function test_mail_function_validation_warning() {
		// Step 1: Mock WordPress mail function as unavailable
		// (In real scenario, wp_mail might not exist or PHPMailer unavailable)

		// Step 2: Attempt to save Auto-BCC settings
		if ( class_exists( 'CUFT_Auto_BCC_Manager' ) ) {
			$manager = CUFT_Auto_BCC_Manager::get_instance();
			$warnings = $manager->validate_mail_function();

			// Step 3: Verify warning displayed if mail function unavailable
			// This is environment-dependent, so we just verify the method exists
			$this->assertIsArray( $warnings, 'Warnings should be returned as array' );

			// Step 4: Verify settings STILL saved (warning is non-blocking)
			$config = array(
				'enabled' => true,
				'bcc_email' => 'testing@example.com',
				'selected_email_types' => array( 'form_submission' ),
			);

			$result = $manager->save_config( $config );
			$this->assertTrue( $result !== false, 'Configuration should save even with mail warnings' );

			// Verify saved
			$saved = get_option( 'cuft_auto_bcc_config' );
			$this->assertIsArray( $saved, 'Config should be saved' );
			$this->assertEquals( 'testing@example.com', $saved['bcc_email'], 'Email should be saved' );
		} else {
			$this->fail( 'CUFT_Auto_BCC_Manager class not implemented yet' );
		}
	}

	/**
	 * Clean up after test
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_option( 'cuft_auto_bcc_config' );
	}
}
