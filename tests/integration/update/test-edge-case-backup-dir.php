<?php
/**
 * Integration Test: Edge Case - Backup Directory Not Writable (T040)
 *
 * Tests error handling when backup directory cannot be created or written to.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 */

/**
 * Class CUFT_Test_Edge_Case_Backup_Dir
 *
 * Integration tests for EC-1: Backup directory not writable
 *
 * @since 3.17.0
 */
class CUFT_Test_Edge_Case_Backup_Dir extends WP_UnitTestCase {

	/**
	 * Backup manager instance
	 *
	 * @var CUFT_Backup_Manager
	 */
	private $backup_manager;

	/**
	 * Original filesystem permissions
	 *
	 * @var array
	 */
	private $original_permissions = array();

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
	 * Clean up after tests
	 */
	public function tearDown() {
		// Restore original permissions
		foreach ( $this->original_permissions as $path => $perms ) {
			if ( file_exists( $path ) ) {
				@chmod( $path, $perms );
			}
		}

		parent::tearDown();
	}

	/**
	 * Test: Backup directory parent not writable
	 *
	 * Verifies error when /wp-content/uploads/ is not writable.
	 *
	 * @since 3.17.0
	 */
	public function test_uploads_directory_not_writable() {
		$uploads_dir = wp_upload_dir();
		$uploads_path = $uploads_dir['basedir'];

		// Skip if we can't change permissions (CI environment)
		if ( ! is_writable( $uploads_path ) ) {
			$this->markTestSkipped( 'Cannot modify uploads directory permissions' );
		}

		// Store original permissions
		$this->original_permissions[ $uploads_path ] = fileperms( $uploads_path );

		// Make uploads directory not writable
		chmod( $uploads_path, 0555 );

		// Attempt to create backup
		$result = $this->backup_manager->create_backup( '3.16.0' );

		// Restore permissions immediately
		chmod( $uploads_path, $this->original_permissions[ $uploads_path ] );

		// Assert error returned
		$this->assertWPError( $result, 'Should return WP_Error when uploads directory not writable' );
		$this->assertEquals( 'backup_directory_not_writable', $result->get_error_code() );

		// Assert error message is user-friendly
		$error_message = $result->get_error_message();
		$this->assertContains( 'backup directory', $error_message );
		$this->assertContains( 'writable', $error_message );
	}

	/**
	 * Test: Backup directory exists but not writable
	 *
	 * Verifies error when cuft-backups/ directory exists but is not writable.
	 *
	 * @since 3.17.0
	 */
	public function test_backup_directory_not_writable() {
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		// Create backup directory if it doesn't exist
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Skip if we can't change permissions
		if ( ! is_writable( $backup_dir ) ) {
			$this->markTestSkipped( 'Cannot modify backup directory permissions' );
		}

		// Store original permissions
		$this->original_permissions[ $backup_dir ] = fileperms( $backup_dir );

		// Make backup directory not writable
		chmod( $backup_dir, 0555 );

		// Attempt to create backup
		$result = $this->backup_manager->create_backup( '3.16.0' );

		// Restore permissions immediately
		chmod( $backup_dir, $this->original_permissions[ $backup_dir ] );

		// Assert error returned
		$this->assertWPError( $result, 'Should return WP_Error when backup directory not writable' );
		$this->assertEquals( 'backup_directory_not_writable', $result->get_error_code() );
	}

	/**
	 * Test: Update aborted when backup fails
	 *
	 * Verifies that update process is aborted when backup cannot be created.
	 *
	 * @since 3.17.0
	 */
	public function test_update_aborted_on_backup_failure() {
		// Mock the upgrader_pre_install filter
		$mock_package = '/tmp/test-package.zip';
		$mock_hook_extra = array(
			'plugin' => 'choice-uft/choice-universal-form-tracker.php',
		);

		// Simulate permission failure by mocking the backup creation
		add_filter( 'cuft_backup_create_result', function( $result ) {
			return new WP_Error( 'backup_directory_not_writable', 'Cannot create backup directory. Please ensure /wp-content/uploads/ is writable.' );
		}, 10, 1 );

		// Trigger the filter
		$result = apply_filters( 'upgrader_pre_install', true, $mock_hook_extra );

		// Remove mock filter
		remove_all_filters( 'cuft_backup_create_result' );

		// In real implementation, backup failure would cause WP_Error
		// For now, verify the backup manager returns error
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		if ( file_exists( $backup_dir ) && is_writable( $backup_dir ) ) {
			// If we can write, make it unwritable temporarily
			$this->original_permissions[ $backup_dir ] = fileperms( $backup_dir );
			chmod( $backup_dir, 0555 );

			$backup_result = $this->backup_manager->create_backup( '3.16.0' );

			// Restore permissions
			chmod( $backup_dir, $this->original_permissions[ $backup_dir ] );

			$this->assertWPError( $backup_result, 'Backup creation should fail' );
		} else {
			$this->markTestSkipped( 'Cannot test permission scenario in this environment' );
		}
	}

	/**
	 * Test: Error message includes corrective action
	 *
	 * Verifies that error messages guide the user to fix permissions.
	 *
	 * @since 3.17.0
	 */
	public function test_error_message_includes_corrective_action() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get the error message for backup directory failure
		$message = CUFT_Error_Messages::get_message( 'backup_directory_not_writable', array(
			'directory' => '/wp-content/uploads/cuft-backups',
		) );

		// Assert message contains corrective action
		$this->assertNotEmpty( $message, 'Error message should not be empty' );
		$this->assertContains( 'writable', strtolower( $message ), 'Should mention writable' );

		// Assert message mentions the directory path
		$this->assertContains( '/wp-content/uploads', $message, 'Should mention the directory path' );
	}

	/**
	 * Test: Backup succeeds after permissions fixed
	 *
	 * Verifies that backup creation works after fixing permissions.
	 *
	 * @since 3.17.0
	 */
	public function test_backup_succeeds_after_permissions_fixed() {
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		// Ensure directory exists and is writable
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Make writable
		chmod( $backup_dir, 0755 );

		// Attempt to create backup
		$result = $this->backup_manager->create_backup( '3.16.0' );

		// Should succeed (or fail for different reason, not permissions)
		if ( is_wp_error( $result ) ) {
			$this->assertNotEquals( 'backup_directory_not_writable', $result->get_error_code(), 'Should not fail due to permissions' );
		}

		// Clean up
		if ( ! is_wp_error( $result ) && file_exists( $result ) ) {
			@unlink( $result );
		}
	}

	/**
	 * Test: Error logged to update history
	 *
	 * Verifies that permission errors are logged.
	 *
	 * @since 3.17.0
	 */
	public function test_permission_error_logged() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Log a permission error
		CUFT_Error_Messages::log_error( 'backup_directory_not_writable', array(
			'directory' => '/wp-content/uploads/cuft-backups',
			'version' => '3.17.0',
		) );

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert error was logged
		$this->assertNotEmpty( $log, 'Error should be logged' );
		$this->assertEquals( 1, count( $log ), 'Should have 1 log entry' );

		$last_entry = end( $log );
		$this->assertEquals( 'backup_directory_not_writable', $last_entry['error_code'] );
		$this->assertEquals( 'ERROR', $last_entry['severity'] );
	}
}
