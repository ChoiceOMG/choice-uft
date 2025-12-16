<?php
/**
 * Integration Test: Enable → Configure → Test Email Workflow
 *
 * Tests the complete Auto-BCC setup and test email workflow.
 * This test MUST FAIL until full implementation is complete.
 *
 * Scenario: Acceptance scenario #7 from spec.md
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

// Bootstrap WordPress test environment
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class Test_Auto_BCC_End_To_End extends WP_UnitTestCase {

	/**
	 * Test complete enable → configure → test email workflow
	 *
	 * @test
	 */
	public function test_enable_configure_test_email_workflow() {
		// Step 1: Enable Auto-BCC feature
		$config = array(
			'enabled' => true,
			'bcc_email' => 'testing@example.com',
			'selected_email_types' => array( 'form_submission' ),
			'rate_limit_threshold' => 100,
			'rate_limit_action' => 'log_only',
			'last_modified' => time(),
			'last_modified_by' => 1,
		);
		update_option( 'cuft_auto_bcc_config', $config );

		// Step 2: Verify configuration saved
		$saved_config = get_option( 'cuft_auto_bcc_config' );
		$this->assertIsArray( $saved_config, 'Config should be saved as array' );
		$this->assertTrue( $saved_config['enabled'], 'Feature should be enabled' );
		$this->assertEquals( 'testing@example.com', $saved_config['bcc_email'], 'Email should match' );

		// Step 3: Send test email via manager
		if ( class_exists( 'CUFT_Auto_BCC_Manager' ) ) {
			$manager = CUFT_Auto_BCC_Manager::get_instance();
			$result = $manager->send_test_email( 'testing@example.com' );

			$this->assertTrue( $result['success'], 'Test email should send successfully' );
			$this->assertArrayHasKey( 'subject', $result, 'Result should contain subject' );
			$this->assertStringContainsString( '[CUFT Test Email]', $result['subject'], 'Subject should contain test identifier' );
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
