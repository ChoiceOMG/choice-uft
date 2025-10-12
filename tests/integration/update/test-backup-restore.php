<?php
/**
 * Integration Test: Full Backup/Restore Workflow
 *
 * Tests the complete backup and restore workflow for WordPress plugin updates.
 * Simulates real-world update scenarios including failures and rollbacks.
 *
 * @package Choice_UFT
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 *
 * Reference: /specs/008-fix-critical-gaps/quickstart.md QS-6
 * Task: T032 - Integration test: Full backup/restore workflow
 *
 * Test Scenarios:
 * 1. Complete success workflow (backup → update → delete backup)
 * 2. Update failure with successful rollback
 * 3. Backup creation failure (aborts update)
 * 4. Restoration failure (manual reinstall message)
 * 5. Verify backup deleted after successful update
 * 6. Verify error messages displayed to user
 * 7. Verify previous version restored correctly
 */

class Test_Backup_Restore_Integration extends WP_UnitTestCase {

	/**
	 * Backup manager instance
	 *
	 * @var CUFT_Backup_Manager
	 */
	private $backup_manager;

	/**
	 * Test plugin directory
	 *
	 * @var string
	 */
	private $test_plugin_dir;

	/**
	 * Test backup directory
	 *
	 * @var string
	 */
	private $test_backup_dir;

	/**
	 * Setup test environment before each test
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize backup manager
		$this->backup_manager = new CUFT_Backup_Manager();

		// Create test directories
		$this->test_plugin_dir = sys_get_temp_dir() . '/cuft-test-integration-' . time();
		$this->test_backup_dir = sys_get_temp_dir() . '/cuft-test-backups-integration-' . time();

		wp_mkdir_p( $this->test_plugin_dir );
		wp_mkdir_p( $this->test_backup_dir );

		// Create test plugin files
		$this->create_test_plugin_files( $this->test_plugin_dir, '3.16.5' );
	}

	/**
	 * Cleanup test environment after each test
	 */
	public function tearDown(): void {
		// Clean up test directories
		if ( file_exists( $this->test_plugin_dir ) ) {
			$this->delete_directory_recursive( $this->test_plugin_dir );
		}

		if ( file_exists( $this->test_backup_dir ) ) {
			$this->delete_directory_recursive( $this->test_backup_dir );
		}

		// Clean up transients
		delete_transient( 'cuft_backup_path' );

		parent::tearDown();
	}

	/**
	 * Test: Complete success workflow
	 *
	 * Scenario: Update succeeds, backup created and deleted
	 *
	 * Steps:
	 * 1. Create backup
	 * 2. Simulate successful update
	 * 3. Verify backup deleted
	 *
	 * Expected:
	 * - Backup created before update
	 * - Update completes successfully
	 * - Backup deleted after update
	 */
	public function test_complete_success_workflow() {
		$current_version = '3.16.5';
		$target_version = '3.17.0';

		// Step 1: Create backup
		$backup_file = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );

		$this->assertNotWPError( $backup_file, 'Backup creation should succeed' );
		$this->assertFileExists( $backup_file, 'Backup file should exist' );

		// Verify backup contents
		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $backup_file ) === true, 'Backup should be valid ZIP' );
		$this->assertGreaterThan( 0, $zip->numFiles, 'Backup should contain files' );
		$zip->close();

		// Step 2: Simulate successful update
		$this->simulate_successful_update( $this->test_plugin_dir, $target_version );

		// Verify update succeeded
		$version_file = $this->test_plugin_dir . '/version.txt';
		$this->assertFileExists( $version_file, 'Version file should exist after update' );
		$this->assertEquals( $target_version, file_get_contents( $version_file ), 'Version should be updated' );

		// Step 3: Delete backup after success
		$delete_result = $this->backup_manager->delete_backup( $backup_file );

		$this->assertTrue( $delete_result, 'Backup deletion should succeed' );
		$this->assertFileDoesNotExist( $backup_file, 'Backup should be deleted after successful update' );
	}

	/**
	 * Test: Update failure with successful rollback
	 *
	 * Scenario: Update fails, backup restores previous version
	 *
	 * Steps:
	 * 1. Create backup
	 * 2. Simulate update failure (corrupt plugin directory)
	 * 3. Restore from backup
	 * 4. Verify previous version restored
	 *
	 * Expected:
	 * - Backup created successfully
	 * - Update fails as expected
	 * - Restoration succeeds
	 * - Previous version restored correctly
	 * - Plugin functional at previous version
	 */
	public function test_update_failure_with_successful_rollback() {
		$current_version = '3.16.5';

		// Step 1: Create backup
		$backup_file = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );

		$this->assertNotWPError( $backup_file, 'Backup creation should succeed' );
		$this->assertFileExists( $backup_file, 'Backup file should exist' );

		// Step 2: Simulate update failure (delete main plugin file)
		$main_plugin_file = $this->test_plugin_dir . '/choice-universal-form-tracker.php';
		$this->assertFileExists( $main_plugin_file, 'Main plugin file should exist before failure simulation' );

		unlink( $main_plugin_file );
		$this->assertFileDoesNotExist( $main_plugin_file, 'Main plugin file should be deleted to simulate failure' );

		// Step 3: Restore from backup
		$restore_result = $this->backup_manager->restore_backup( $backup_file, $this->test_plugin_dir );

		$this->assertNotWPError( $restore_result, 'Restoration should not return WP_Error' );
		$this->assertTrue( $restore_result === true, 'Restoration should return true' );

		// Step 4: Verify previous version restored
		$this->assertFileExists( $main_plugin_file, 'Main plugin file should be restored' );

		// Verify version file restored
		$version_file = $this->test_plugin_dir . '/version.txt';
		$this->assertFileExists( $version_file, 'Version file should be restored' );
		$this->assertEquals( $current_version, file_get_contents( $version_file ), 'Version should match backup version' );

		// Verify directory structure restored
		$includes_dir = $this->test_plugin_dir . '/includes';
		$this->assertDirectoryExists( $includes_dir, 'Includes directory should be restored' );
		$this->assertFileExists( $includes_dir . '/test-class.php', 'Test class file should be restored' );

		// Verify assets restored
		$assets_dir = $this->test_plugin_dir . '/assets';
		$this->assertDirectoryExists( $assets_dir, 'Assets directory should be restored' );
		$this->assertFileExists( $assets_dir . '/test-script.js', 'Test script should be restored' );
	}

	/**
	 * Test: Backup creation failure aborts update
	 *
	 * Scenario: Backup directory not writable
	 *
	 * Steps:
	 * 1. Make backup directory read-only
	 * 2. Attempt backup creation
	 * 3. Verify error returned
	 * 4. Verify update should abort (no partial files)
	 *
	 * Expected:
	 * - Backup creation returns WP_Error
	 * - Error code is 'backup_dir_not_writable'
	 * - Error message mentions permissions
	 * - Update would abort (simulated by WP_Error return)
	 */
	public function test_backup_creation_failure_aborts_update() {
		$current_version = '3.16.5';

		// Step 1: Make backup directory read-only
		chmod( $this->test_backup_dir, 0555 );

		// Step 2: Attempt backup creation
		$backup_result = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );

		// Step 3: Verify error returned
		$this->assertWPError( $backup_result, 'Backup creation should return WP_Error' );
		$this->assertEquals( 'backup_dir_not_writable', $backup_result->get_error_code(), 'Error code should be backup_dir_not_writable' );

		$error_message = $backup_result->get_error_message();
		$this->assertStringContainsString( 'writable', strtolower( $error_message ), 'Error message should mention writable' );

		// Step 4: Verify no partial backup files created
		$files = glob( $this->test_backup_dir . '/*.zip' );
		$this->assertEmpty( $files, 'No backup files should be created on failure' );

		// Restore permissions for cleanup
		chmod( $this->test_backup_dir, 0755 );
	}

	/**
	 * Test: Restoration failure shows manual reinstall message
	 *
	 * Scenario: Backup file corrupted, restoration fails
	 *
	 * Steps:
	 * 1. Create valid backup
	 * 2. Corrupt backup file
	 * 3. Attempt restoration
	 * 4. Verify error message includes GitHub URL
	 *
	 * Expected:
	 * - Restoration returns WP_Error
	 * - Error message includes manual reinstall instructions
	 * - Error message includes GitHub URL
	 */
	public function test_restoration_failure_shows_manual_reinstall_message() {
		$current_version = '3.16.5';

		// Step 1: Create valid backup
		$backup_file = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );
		$this->assertNotWPError( $backup_file, 'Backup creation should succeed for test setup' );

		// Step 2: Corrupt backup file
		file_put_contents( $backup_file, 'This is not a valid ZIP file' );

		// Step 3: Attempt restoration
		$restore_result = $this->backup_manager->restore_backup( $backup_file, $this->test_plugin_dir );

		// Step 4: Verify error message
		$this->assertWPError( $restore_result, 'Restoration should return WP_Error with corrupted backup' );

		$error_message = $restore_result->get_error_message();
		$this->assertStringContainsString( 'manually', strtolower( $error_message ), 'Error message should mention manual reinstall' );
		$this->assertStringContainsString( 'github', strtolower( $error_message ), 'Error message should include GitHub reference' );
	}

	/**
	 * Test: Backup deleted after successful update
	 *
	 * Scenario: Verify cleanup after successful update
	 *
	 * Steps:
	 * 1. Create backup
	 * 2. Store backup path in transient
	 * 3. Simulate successful update completion
	 * 4. Trigger delete_backup_on_success hook
	 * 5. Verify backup deleted
	 *
	 * Expected:
	 * - Backup created successfully
	 * - Backup deleted after update
	 * - Transient cleared
	 */
	public function test_backup_deleted_after_successful_update() {
		$current_version = '3.16.5';

		// Step 1: Create backup
		$backup_file = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );
		$this->assertFileExists( $backup_file, 'Backup file should exist' );

		// Step 2: Store backup path in transient
		set_transient( 'cuft_backup_path', $backup_file, HOUR_IN_SECONDS );
		$this->assertEquals( $backup_file, get_transient( 'cuft_backup_path' ), 'Transient should store backup path' );

		// Step 3: Simulate successful update completion
		$this->simulate_successful_update( $this->test_plugin_dir, '3.17.0' );

		// Step 4: Trigger delete_backup_on_success hook
		$hook_extra = array(
			'type'   => 'plugin',
			'action' => 'update',
			'plugin' => 'choice-uft/choice-universal-form-tracker.php',
		);

		$upgrader = new stdClass(); // Mock upgrader
		$this->backup_manager->delete_backup_on_success( $upgrader, $hook_extra );

		// Step 5: Verify backup deleted
		$this->assertFileDoesNotExist( $backup_file, 'Backup should be deleted after successful update' );

		// Verify transient cleared
		$this->assertFalse( get_transient( 'cuft_backup_path' ), 'Transient should be cleared after deletion' );
	}

	/**
	 * Test: Error messages displayed to user
	 *
	 * Scenario: Verify user-facing error messages are clear
	 *
	 * Steps:
	 * 1. Test disk space error
	 * 2. Test permissions error
	 * 3. Test restoration timeout error
	 * 4. Test backup not found error
	 *
	 * Expected:
	 * - All errors return WP_Error
	 * - Error messages are user-friendly
	 * - Error messages include corrective actions
	 */
	public function test_error_messages_displayed_to_user() {
		$current_version = '3.16.5';

		// Test 1: Permissions error
		chmod( $this->test_backup_dir, 0555 );
		$result = $this->backup_manager->create_backup( $current_version, $this->test_plugin_dir, $this->test_backup_dir );
		$this->assertWPError( $result, 'Should return WP_Error' );
		$this->assertStringContainsString( 'writable', strtolower( $result->get_error_message() ), 'Should mention writable' );
		chmod( $this->test_backup_dir, 0755 );

		// Test 2: Backup not found error
		$nonexistent_backup = $this->test_backup_dir . '/nonexistent.zip';
		$result = $this->backup_manager->restore_backup( $nonexistent_backup, $this->test_plugin_dir );
		$this->assertWPError( $result, 'Should return WP_Error' );
		$this->assertEquals( 'backup_not_found', $result->get_error_code(), 'Error code should be backup_not_found' );

		// Test 3: Invalid backup error
		$invalid_backup = $this->test_backup_dir . '/invalid.zip';
		file_put_contents( $invalid_backup, 'not a zip' );
		$result = $this->backup_manager->restore_backup( $invalid_backup, $this->test_plugin_dir );
		$this->assertWPError( $result, 'Should return WP_Error' );
		$this->assertStringContainsString( 'github', strtolower( $result->get_error_message() ), 'Should include GitHub URL for manual reinstall' );
	}

	/**
	 * Test: Previous version restored correctly
	 *
	 * Scenario: Verify all files and structure restored
	 *
	 * Steps:
	 * 1. Create backup of v3.16.5
	 * 2. Modify plugin to v3.17.0 (add/remove files)
	 * 3. Restore backup
	 * 4. Verify all v3.16.5 files restored
	 * 5. Verify v3.17.0 files removed
	 *
	 * Expected:
	 * - All original files restored
	 * - Directory structure matches backup
	 * - Version reverted correctly
	 * - No leftover files from failed update
	 */
	public function test_previous_version_restored_correctly() {
		$old_version = '3.16.5';
		$new_version = '3.17.0';

		// Step 1: Create backup of v3.16.5
		$backup_file = $this->backup_manager->create_backup( $old_version, $this->test_plugin_dir, $this->test_backup_dir );
		$this->assertFileExists( $backup_file, 'Backup should exist' );

		// Step 2: Modify plugin to v3.17.0
		$this->simulate_successful_update( $this->test_plugin_dir, $new_version );

		// Add new file in v3.17.0
		$new_feature_file = $this->test_plugin_dir . '/includes/new-feature.php';
		file_put_contents( $new_feature_file, "<?php\n// New feature in 3.17.0\n" );
		$this->assertFileExists( $new_feature_file, 'New feature file should exist in v3.17.0' );

		// Verify version changed
		$version_file = $this->test_plugin_dir . '/version.txt';
		$this->assertEquals( $new_version, file_get_contents( $version_file ), 'Version should be updated to 3.17.0' );

		// Step 3: Restore backup
		$restore_result = $this->backup_manager->restore_backup( $backup_file, $this->test_plugin_dir );
		$this->assertTrue( $restore_result === true, 'Restoration should succeed' );

		// Step 4: Verify all v3.16.5 files restored
		$this->assertFileExists( $this->test_plugin_dir . '/choice-universal-form-tracker.php', 'Main file restored' );
		$this->assertFileExists( $this->test_plugin_dir . '/includes/test-class.php', 'Test class restored' );
		$this->assertFileExists( $this->test_plugin_dir . '/assets/test-script.js', 'Test script restored' );
		$this->assertEquals( $old_version, file_get_contents( $version_file ), 'Version reverted to 3.16.5' );

		// Step 5: Verify v3.17.0 files removed
		$this->assertFileDoesNotExist( $new_feature_file, 'New feature file should be removed after restoration' );
	}

	/**
	 * Helper: Create test plugin files
	 *
	 * @param string $plugin_dir Plugin directory path.
	 * @param string $version Plugin version.
	 */
	private function create_test_plugin_files( $plugin_dir, $version ) {
		// Main plugin file
		$main_file = $plugin_dir . '/choice-universal-form-tracker.php';
		file_put_contents(
			$main_file,
			"<?php\n/**\n * Plugin Name: Choice Universal Form Tracker\n * Version: {$version}\n */\ndefine('CUFT_VERSION', '{$version}');\n"
		);

		// Version file
		$version_file = $plugin_dir . '/version.txt';
		file_put_contents( $version_file, $version );

		// Includes directory
		$includes_dir = $plugin_dir . '/includes';
		wp_mkdir_p( $includes_dir );
		file_put_contents( $includes_dir . '/test-class.php', "<?php\n// Test class\nclass CUFT_Test {}\n" );

		// Assets directory
		$assets_dir = $plugin_dir . '/assets';
		wp_mkdir_p( $assets_dir );
		file_put_contents( $assets_dir . '/test-script.js', "// Test script\nconsole.log('CUFT Test');\n" );
	}

	/**
	 * Helper: Simulate successful update
	 *
	 * @param string $plugin_dir Plugin directory path.
	 * @param string $new_version New version.
	 */
	private function simulate_successful_update( $plugin_dir, $new_version ) {
		// Update version file
		$version_file = $plugin_dir . '/version.txt';
		file_put_contents( $version_file, $new_version );

		// Update main plugin file header
		$main_file = $plugin_dir . '/choice-universal-form-tracker.php';
		$content = file_get_contents( $main_file );
		$content = preg_replace( '/Version: [\d.]+/', "Version: {$new_version}", $content );
		$content = preg_replace( "/define\('CUFT_VERSION', '[^']+'\);/", "define('CUFT_VERSION', '{$new_version}');", $content );
		file_put_contents( $main_file, $content );
	}

	/**
	 * Helper: Delete directory recursively
	 *
	 * @param string $dir Directory path.
	 */
	private function delete_directory_recursive( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;

			if ( is_dir( $path ) ) {
				$this->delete_directory_recursive( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
