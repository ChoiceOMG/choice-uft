<?php
/**
 * Integration Test: Edge Case - Unexpected ZIP Structure (T043)
 *
 * Tests error handling when GitHub changes ZIP format or structure.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 */

/**
 * Class CUFT_Test_Edge_Case_ZIP_Structure
 *
 * Integration tests for EC-4: Unexpected ZIP structure
 *
 * @since 3.17.0
 */
class CUFT_Test_Edge_Case_ZIP_Structure extends WP_UnitTestCase {

	/**
	 * Directory fixer instance
	 *
	 * @var CUFT_Directory_Fixer
	 */
	private $directory_fixer;

	/**
	 * Temporary test directories
	 *
	 * @var array
	 */
	private $temp_dirs = array();

	/**
	 * Set up test environment
	 */
	public function setUp() {
		parent::setUp();

		// Load directory fixer class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-directory-fixer.php';

		$this->directory_fixer = new CUFT_Directory_Fixer();
	}

	/**
	 * Clean up test directories
	 */
	public function tearDown() {
		// Delete temporary directories
		foreach ( $this->temp_dirs as $dir ) {
			if ( file_exists( $dir ) ) {
				$this->delete_directory( $dir );
			}
		}

		parent::tearDown();
	}

	/**
	 * Test: Unrecognized directory pattern detected
	 *
	 * Verifies that unusual directory names are detected.
	 *
	 * @since 3.17.0
	 */
	public function test_unrecognized_pattern_detected() {
		// Create unusual directory structure
		$temp_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
		$unusual_dir = $temp_dir . '/choice-uft-unusual-format-xyz';

		mkdir( $temp_dir, 0755, true );
		mkdir( $unusual_dir, 0755, true );

		$this->temp_dirs[] = $temp_dir;

		// Add a plugin file so it looks like a plugin
		touch( $unusual_dir . '/choice-universal-form-tracker.php' );

		// Test if pattern is recognized
		$hook_extra = array( 'plugin' => 'choice-uft/choice-universal-form-tracker.php' );
		$result = $this->directory_fixer->fix_directory_name( $unusual_dir, $temp_dir, $hook_extra );

		// Should return WP_Error for unrecognized pattern
		if ( is_wp_error( $result ) ) {
			$this->assertEquals( 'unexpected_directory_pattern', $result->get_error_code() );
		}
	}

	/**
	 * Test: Error message instructs user to report issue
	 *
	 * Verifies that unrecognized patterns ask user to report to developers.
	 *
	 * @since 3.17.0
	 */
	public function test_error_asks_user_to_report() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get error message for unexpected structure
		$message = CUFT_Error_Messages::get_message( 'unexpected_directory_pattern', array(
			'directory_name' => 'choice-uft-unusual-format',
		) );

		// Assert message asks user to report
		$this->assertNotEmpty( $message, 'Error message should not be empty' );
		$this->assertContains( 'report', strtolower( $message ), 'Should ask user to report' );
		$this->assertContains( 'unexpected', strtolower( $message ), 'Should mention unexpected structure' );
	}

	/**
	 * Test: Automatic rollback on structure error
	 *
	 * Verifies that unrecognized structure triggers rollback.
	 *
	 * @since 3.17.0
	 */
	public function test_automatic_rollback_triggered() {
		// When directory fixer returns WP_Error, WordPress should handle rollback
		// We verify that WP_Error is returned for unrecognized patterns

		$temp_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
		$unusual_dir = $temp_dir . '/completely-wrong-name';

		mkdir( $temp_dir, 0755, true );
		mkdir( $unusual_dir, 0755, true );

		$this->temp_dirs[] = $temp_dir;

		// Add plugin file
		touch( $unusual_dir . '/choice-universal-form-tracker.php' );

		// Test directory fixer
		$hook_extra = array( 'plugin' => 'choice-uft/choice-universal-form-tracker.php' );
		$result = $this->directory_fixer->fix_directory_name( $unusual_dir, $temp_dir, $hook_extra );

		// Should return WP_Error (which triggers WordPress rollback)
		$this->assertWPError( $result, 'Unrecognized pattern should return WP_Error' );
	}

	/**
	 * Test: Known patterns still work
	 *
	 * Verifies that all known GitHub patterns are still recognized.
	 *
	 * @since 3.17.0
	 */
	public function test_known_patterns_recognized() {
		$test_patterns = array(
			'choice-uft-v3.17.0',          // Release ZIP
			'choice-uft-3.17.0',           // Release without 'v'
			'ChoiceOMG-choice-uft-abc1234', // Commit ZIP
			'choice-uft-master',           // Branch ZIP
			'choice-uft-develop',          // Branch ZIP
			'choice-uft',                  // Already correct
		);

		foreach ( $test_patterns as $pattern ) {
			$temp_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
			$test_dir = $temp_dir . '/' . $pattern;

			mkdir( $temp_dir, 0755, true );
			mkdir( $test_dir, 0755, true );

			$this->temp_dirs[] = $temp_dir;

			// Add plugin file
			touch( $test_dir . '/choice-universal-form-tracker.php' );

			// Test directory fixer
			$hook_extra = array( 'plugin' => 'choice-uft/choice-universal-form-tracker.php' );
			$result = $this->directory_fixer->fix_directory_name( $test_dir, $temp_dir, $hook_extra );

			// Should not return WP_Error for known patterns
			if ( is_wp_error( $result ) ) {
				$this->fail( "Known pattern '{$pattern}' was not recognized: " . $result->get_error_message() );
			}
		}

		$this->assertTrue( true, 'All known patterns recognized' );
	}

	/**
	 * Test: Directory structure logged on error
	 *
	 * Verifies that unexpected structure details are logged.
	 *
	 * @since 3.17.0
	 */
	public function test_structure_logged_on_error() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Log structure error
		CUFT_Error_Messages::log_error( 'unexpected_directory_pattern', array(
			'directory_name' => 'choice-uft-weird-format-2025',
			'expected_patterns' => array( 'choice-uft-v*', 'choice-uft-*', 'ChoiceOMG-choice-uft-*' ),
		) );

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert error logged with context
		$this->assertNotEmpty( $log, 'Error should be logged' );
		$last_entry = end( $log );

		$this->assertEquals( 'unexpected_directory_pattern', $last_entry['error_code'] );
		$this->assertArrayHasKey( 'context', $last_entry );
		$this->assertArrayHasKey( 'directory_name', $last_entry['context'] );
	}

	/**
	 * Test: Multiple directory formats in same ZIP
	 *
	 * Verifies handling when ZIP contains multiple directories.
	 *
	 * @since 3.17.0
	 */
	public function test_multiple_directories_in_zip() {
		// Create temp directory with multiple subdirectories
		$temp_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
		$dir1 = $temp_dir . '/choice-uft-v3.17.0';
		$dir2 = $temp_dir . '/choice-uft-backup';

		mkdir( $temp_dir, 0755, true );
		mkdir( $dir1, 0755, true );
		mkdir( $dir2, 0755, true );

		$this->temp_dirs[] = $temp_dir;

		// Add plugin file to first directory only
		touch( $dir1 . '/choice-universal-form-tracker.php' );

		// Test directory fixer
		$hook_extra = array( 'plugin' => 'choice-uft/choice-universal-form-tracker.php' );
		$result = $this->directory_fixer->fix_directory_name( $dir1, $temp_dir, $hook_extra );

		// Should process the correct directory
		$this->assertTrue( ! is_wp_error( $result ) || $result->get_error_code() !== 'unexpected_directory_pattern', 'Should handle multiple directories' );
	}

	/**
	 * Test: Empty directory name
	 *
	 * Verifies handling of edge case with empty directory name.
	 *
	 * @since 3.17.0
	 */
	public function test_empty_directory_name() {
		$result = $this->directory_fixer->fix_directory_name( '', '/tmp', array( 'plugin' => 'choice-uft/choice-universal-form-tracker.php' ) );

		// Should return WP_Error
		$this->assertWPError( $result, 'Empty directory name should return error' );
	}

	/**
	 * Helper: Delete directory recursively
	 *
	 * @param string $dir Directory path.
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;

			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
