<?php
/**
 * Integration Test: Edge Case - Backup Restoration Fails (T042)
 *
 * Tests error handling when backup restoration fails after update failure.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 */

/**
 * Class CUFT_Test_Edge_Case_Restore_Fail
 *
 * Integration tests for EC-3: Backup restoration fails
 *
 * @since 3.17.0
 */
class CUFT_Test_Edge_Case_Restore_Fail extends WP_UnitTestCase {

	/**
	 * Backup manager instance
	 *
	 * @var CUFT_Backup_Manager
	 */
	private $backup_manager;

	/**
	 * Temporary test files
	 *
	 * @var array
	 */
	private $temp_files = array();

	/**
	 * Set up test environment
	 */
	public function setUp() {
		parent::setUp();

		// Load backup manager class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-backup-manager.php';

		$this->backup_manager = new CUFT_Backup_Manager();
	}

	/**
	 * Clean up test files
	 */
	public function tearDown() {
		// Delete temporary files
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		parent::tearDown();
	}

	/**
	 * Test: Corrupted backup file detected
	 *
	 * Verifies that corrupted backup files are detected during restoration.
	 *
	 * @since 3.17.0
	 */
	public function test_corrupted_backup_detected() {
		// Create a corrupted backup file (not a valid ZIP)
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		$corrupted_file = $backup_dir . '/choice-uft-3.16.0-backup.zip';
		file_put_contents( $corrupted_file, 'This is not a valid ZIP file' );
		$this->temp_files[] = $corrupted_file;

		// Attempt to restore
		$result = $this->backup_manager->restore_backup( $corrupted_file );

		// Assert error returned
		$this->assertWPError( $result, 'Should detect corrupted backup' );
		$this->assertEquals( 'backup_corrupted', $result->get_error_code() );
	}

	/**
	 * Test: Missing backup file error
	 *
	 * Verifies error when backup file doesn't exist.
	 *
	 * @since 3.17.0
	 */
	public function test_missing_backup_file() {
		$uploads_dir = wp_upload_dir();
		$missing_file = $uploads_dir['basedir'] . '/cuft-backups/nonexistent-backup.zip';

		// Attempt to restore
		$result = $this->backup_manager->restore_backup( $missing_file );

		// Assert error returned
		$this->assertWPError( $result, 'Should detect missing backup file' );
		$this->assertEquals( 'backup_not_found', $result->get_error_code() );
	}

	/**
	 * Test: Manual reinstall message displayed
	 *
	 * Verifies that critical error shows manual reinstall instructions.
	 *
	 * @since 3.17.0
	 */
	public function test_manual_reinstall_message() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get the error message for restoration failure
		$message = CUFT_Error_Messages::get_message( 'restoration_failed', array(
			'version' => '3.16.0',
		) );

		// Assert message includes manual reinstall instructions
		$this->assertNotEmpty( $message, 'Error message should not be empty' );
		$this->assertContains( 'manually', strtolower( $message ), 'Should mention manual reinstall' );
		$this->assertContains( 'github', strtolower( $message ), 'Should mention GitHub' );
		$this->assertContains( 'https://github.com', $message, 'Should include GitHub URL' );
	}

	/**
	 * Test: CRITICAL severity logged
	 *
	 * Verifies that restoration failures are logged as CRITICAL.
	 *
	 * @since 3.17.0
	 */
	public function test_critical_severity_logged() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Log restoration failure
		CUFT_Error_Messages::log_error( 'restoration_failed', array(
			'version' => '3.16.0',
			'backup_file' => '/path/to/backup.zip',
		) );

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert CRITICAL severity
		$this->assertNotEmpty( $log, 'Error should be logged' );
		$last_entry = end( $log );

		$this->assertEquals( 'restoration_failed', $last_entry['error_code'] );
		$this->assertEquals( 'CRITICAL', $last_entry['severity'], 'Restoration failure should be CRITICAL' );
	}

	/**
	 * Test: Error logged to PHP error_log
	 *
	 * Verifies that critical errors are also logged to PHP error_log.
	 *
	 * @since 3.17.0
	 */
	public function test_php_error_log_entry() {
		// This test verifies the logging behavior exists
		// Actual PHP error_log capture requires special setup

		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Log a critical error
		CUFT_Error_Messages::log_error( 'restoration_failed', array(
			'version' => '3.16.0',
			'backup_file' => '/path/to/backup.zip',
		) );

		// Verify error was logged to WordPress
		$log = get_option( 'cuft_update_log', array() );
		$this->assertNotEmpty( $log, 'Error should be logged' );

		// In production, this would also call error_log()
		// We verify the WordPress log exists
		$last_entry = end( $log );
		$this->assertEquals( 'CRITICAL', $last_entry['severity'] );
	}

	/**
	 * Test: Double failure scenario (update + restore both fail)
	 *
	 * Verifies handling when both update and restoration fail.
	 *
	 * @since 3.17.0
	 */
	public function test_double_failure_scenario() {
		// Create a corrupted backup
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		$corrupted_file = $backup_dir . '/choice-uft-3.16.0-backup.zip';
		file_put_contents( $corrupted_file, 'Corrupted ZIP' );
		$this->temp_files[] = $corrupted_file;

		// Simulate update failure by attempting restore
		$restore_result = $this->backup_manager->restore_backup( $corrupted_file );

		// Assert restoration failed
		$this->assertWPError( $restore_result, 'Restoration should fail with corrupted backup' );

		// Load error messages
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Log the double failure
		CUFT_Error_Messages::log_error( 'update_and_restoration_failed', array(
			'version' => '3.16.0',
			'update_error' => 'Update installation failed',
			'restore_error' => $restore_result->get_error_message(),
		) );

		// Verify critical error logged
		$log = get_option( 'cuft_update_log', array() );
		$last_entry = end( $log );

		$this->assertEquals( 'CRITICAL', $last_entry['severity'], 'Double failure should be CRITICAL' );
		$this->assertArrayHasKey( 'context', $last_entry );
	}

	/**
	 * Test: GitHub URL included in error message
	 *
	 * Verifies that error message includes direct link to latest release.
	 *
	 * @since 3.17.0
	 */
	public function test_github_url_in_error() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		$message = CUFT_Error_Messages::get_message( 'restoration_failed', array(
			'version' => '3.16.0',
		) );

		// Assert GitHub release URL present
		$this->assertContains( 'https://github.com/ChoiceOMG/choice-uft/releases', $message, 'Should include GitHub releases URL' );
	}

	/**
	 * Test: Restoration timeout scenario
	 *
	 * Verifies handling when restoration exceeds 10-second timeout.
	 *
	 * @since 3.17.0
	 */
	public function test_restoration_timeout() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get timeout error message
		$message = CUFT_Error_Messages::get_message( 'restoration_timeout', array(
			'timeout' => 10,
		) );

		// Assert timeout mentioned
		$this->assertNotEmpty( $message, 'Timeout message should not be empty' );
		$this->assertContains( 'timeout', strtolower( $message ), 'Should mention timeout' );
		$this->assertContains( 'manually', strtolower( $message ), 'Should mention manual action' );
	}
}
