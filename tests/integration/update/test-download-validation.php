<?php
/**
 * Integration Test: Download Validation (T025)
 *
 * Tests download validation workflow including file size checking,
 * ZIP format validation, and cleanup operations.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 */

/**
 * Class CUFT_Test_Download_Validation
 *
 * Integration tests for FR-401: Download Validation
 *
 * @since 3.17.0
 */
class CUFT_Test_Download_Validation extends WP_UnitTestCase {

	/**
	 * Validator instance
	 *
	 * @var CUFT_Update_Validator
	 */
	private $validator;

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

		// Load validator class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-update-validator.php';

		$this->validator = new CUFT_Update_Validator();
	}

	/**
	 * Clean up test files after each test
	 */
	public function tearDown() {
		// Delete temporary test files
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		$this->temp_files = array();

		parent::tearDown();
	}

	/**
	 * Test: Simulate partial download (size mismatch)
	 *
	 * Verifies that size validation detects downloads outside ±5% tolerance.
	 *
	 * @since 3.17.0
	 */
	public function test_partial_download_size_mismatch() {
		// Create a test file smaller than expected
		$test_file = $this->create_test_zip( 'partial', 1048576 ); // 1 MB
		$expected_size = 5242880; // 5 MB

		// Validate file size
		$result = $this->validator->validate_file_size( $test_file, $expected_size );

		// Assert validation failed
		$this->assertWPError( $result, 'File size validation should detect size mismatch' );
		$this->assertEquals( 'file_size_mismatch', $result->get_error_code() );

		// Assert error message contains expected and actual sizes
		$error_message = $result->get_error_message();
		$this->assertContains( 'File size mismatch', $error_message );
		$this->assertContains( '5.00 MB', $error_message ); // Expected
		$this->assertContains( '1.00 MB', $error_message ); // Actual
	}

	/**
	 * Test: File size within tolerance passes validation
	 *
	 * Verifies that ±5% tolerance is correctly applied.
	 *
	 * @since 3.17.0
	 */
	public function test_file_size_within_tolerance() {
		$expected_size = 5242880; // 5 MB

		// Test lower boundary (5% smaller)
		$test_file_min = $this->create_test_zip( 'min_size', $expected_size * 0.95 );
		$result_min = $this->validator->validate_file_size( $test_file_min, $expected_size );
		$this->assertTrue( $result_min, 'File 5% smaller should pass validation' );

		// Test upper boundary (5% larger)
		$test_file_max = $this->create_test_zip( 'max_size', $expected_size * 1.05 );
		$result_max = $this->validator->validate_file_size( $test_file_max, $expected_size );
		$this->assertTrue( $result_max, 'File 5% larger should pass validation' );

		// Test exact size
		$test_file_exact = $this->create_test_zip( 'exact_size', $expected_size );
		$result_exact = $this->validator->validate_file_size( $test_file_exact, $expected_size );
		$this->assertTrue( $result_exact, 'File with exact size should pass validation' );
	}

	/**
	 * Test: File size outside tolerance fails validation
	 *
	 * Verifies that files outside ±5% tolerance are rejected.
	 *
	 * @since 3.17.0
	 */
	public function test_file_size_outside_tolerance() {
		$expected_size = 5242880; // 5 MB

		// Test 6% smaller (should fail)
		$test_file_too_small = $this->create_test_zip( 'too_small', $expected_size * 0.94 );
		$result_small = $this->validator->validate_file_size( $test_file_too_small, $expected_size );
		$this->assertWPError( $result_small, 'File 6% smaller should fail validation' );

		// Test 6% larger (should fail)
		$test_file_too_large = $this->create_test_zip( 'too_large', $expected_size * 1.06 );
		$result_large = $this->validator->validate_file_size( $test_file_too_large, $expected_size );
		$this->assertWPError( $result_large, 'File 6% larger should fail validation' );
	}

	/**
	 * Test: Invalid ZIP format detected
	 *
	 * Verifies that non-ZIP files are rejected.
	 *
	 * @since 3.17.0
	 */
	public function test_invalid_zip_format() {
		// Create a non-ZIP file
		$test_file = $this->create_invalid_zip();

		// Validate ZIP format
		$result = $this->validator->validate_zip_format( $test_file );

		// Assert validation failed
		$this->assertWPError( $result, 'Invalid ZIP format should be detected' );
		$this->assertEquals( 'invalid_zip_format', $result->get_error_code() );
	}

	/**
	 * Test: Valid ZIP format passes validation
	 *
	 * Verifies that valid ZIP files pass format validation.
	 *
	 * @since 3.17.0
	 */
	public function test_valid_zip_format() {
		// Create a valid ZIP file
		$test_file = $this->create_valid_zip();

		// Validate ZIP format
		$result = $this->validator->validate_zip_format( $test_file );

		// Assert validation passed
		$this->assertTrue( $result, 'Valid ZIP format should pass validation' );
	}

	/**
	 * Test: Empty ZIP file detected
	 *
	 * Verifies that empty ZIP archives are rejected.
	 *
	 * @since 3.17.0
	 */
	public function test_empty_zip_file() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not available' );
		}

		// Create an empty ZIP file
		$test_file = $this->create_empty_zip();

		// Validate ZIP format
		$result = $this->validator->validate_zip_format( $test_file );

		// Assert validation failed
		$this->assertWPError( $result, 'Empty ZIP should be detected' );
		$this->assertEquals( 'zip_empty', $result->get_error_code() );
	}

	/**
	 * Test: Corrupted ZIP file detected
	 *
	 * Verifies that corrupted ZIP files are rejected.
	 *
	 * @since 3.17.0
	 */
	public function test_corrupted_zip_file() {
		// Create a corrupted ZIP file (starts with PK but invalid structure)
		$test_file = $this->create_corrupted_zip();

		// Validate ZIP format
		$result = $this->validator->validate_zip_format( $test_file );

		// Assert validation failed
		$this->assertWPError( $result, 'Corrupted ZIP should be detected' );
	}

	/**
	 * Test: Cleanup invalid download
	 *
	 * Verifies that failed downloads are deleted immediately.
	 *
	 * @since 3.17.0
	 */
	public function test_cleanup_invalid_download() {
		// Create a test file
		$test_file = $this->create_test_zip( 'cleanup_test', 1024 );

		// Verify file exists
		$this->assertFileExists( $test_file );

		// Clean up the file
		$result = $this->validator->cleanup_invalid_download( $test_file );

		// Assert cleanup succeeded
		$this->assertTrue( $result, 'Cleanup should succeed' );

		// Assert file was deleted
		$this->assertFileNotExists( $test_file, 'File should be deleted after cleanup' );
	}

	/**
	 * Test: Orphaned file cleanup (daily cron)
	 *
	 * Verifies that orphaned files older than 24 hours are removed.
	 *
	 * @since 3.17.0
	 */
	public function test_cleanup_orphaned_downloads() {
		// Create old test files in temp directory
		$temp_dir = get_temp_dir();

		// Old file (25 hours old) - should be deleted
		$old_file = $temp_dir . 'choice-uft-old-test.zip';
		file_put_contents( $old_file, 'test' );
		touch( $old_file, time() - ( 25 * HOUR_IN_SECONDS ) );
		$this->temp_files[] = $old_file;

		// Recent file (1 hour old) - should be kept
		$recent_file = $temp_dir . 'choice-uft-recent-test.zip';
		file_put_contents( $recent_file, 'test' );
		touch( $recent_file, time() - HOUR_IN_SECONDS );
		$this->temp_files[] = $recent_file;

		// Non-CUFT file (old) - should be ignored
		$other_file = $temp_dir . 'other-plugin-old-test.zip';
		file_put_contents( $other_file, 'test' );
		touch( $other_file, time() - ( 25 * HOUR_IN_SECONDS ) );
		$this->temp_files[] = $other_file;

		// Run cleanup
		$deleted_count = $this->validator->cleanup_orphaned_downloads();

		// Assert old CUFT file was deleted
		$this->assertFileNotExists( $old_file, 'Old CUFT file should be deleted' );

		// Assert recent file was kept
		$this->assertFileExists( $recent_file, 'Recent CUFT file should be kept' );

		// Assert non-CUFT file was ignored
		$this->assertFileExists( $other_file, 'Non-CUFT file should be ignored' );

		// Assert correct count
		$this->assertEquals( 1, $deleted_count, 'Should delete exactly 1 file' );
	}

	/**
	 * Test: Full validation workflow
	 *
	 * Verifies complete validation process from download to cleanup.
	 *
	 * @since 3.17.0
	 */
	public function test_full_validation_workflow() {
		// Create a valid ZIP file with correct size
		$expected_size = 2621440; // ~2.5 MB
		$test_file = $this->create_valid_zip_with_size( $expected_size );

		// Step 1: Validate file size
		$size_result = $this->validator->validate_file_size( $test_file, $expected_size );
		$this->assertTrue( $size_result, 'Size validation should pass' );

		// Step 2: Validate ZIP format
		$zip_result = $this->validator->validate_zip_format( $test_file );
		$this->assertTrue( $zip_result, 'ZIP validation should pass' );

		// Step 3: Clean up after successful validation
		// (In real workflow, WordPress would extract and install)
		$this->assertFileExists( $test_file, 'File should exist after validation' );
	}

	/**
	 * Test: Validation workflow with failure
	 *
	 * Verifies that failed validation triggers immediate cleanup.
	 *
	 * @since 3.17.0
	 */
	public function test_validation_workflow_with_failure() {
		// Create an invalid ZIP file
		$test_file = $this->create_invalid_zip();
		$expected_size = filesize( $test_file );

		// Step 1: Size validation passes (correct size)
		$size_result = $this->validator->validate_file_size( $test_file, $expected_size );
		$this->assertTrue( $size_result, 'Size validation should pass' );

		// Step 2: ZIP validation fails (invalid format)
		$zip_result = $this->validator->validate_zip_format( $test_file );
		$this->assertWPError( $zip_result, 'ZIP validation should fail' );

		// Step 3: Cleanup triggered
		$cleanup_result = $this->validator->cleanup_invalid_download( $test_file );
		$this->assertTrue( $cleanup_result, 'Cleanup should succeed' );

		// Assert file was deleted
		$this->assertFileNotExists( $test_file, 'Invalid file should be deleted' );
	}

	/**
	 * Helper: Create test ZIP file with specific size
	 *
	 * @param string $name Unique name for the test file.
	 * @param int    $size Desired file size in bytes.
	 * @return string Path to created file.
	 */
	private function create_test_zip( $name, $size ) {
		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-' . $name . '-' . uniqid() . '.zip';

		// Create file with specific size
		$content = str_repeat( 'X', $size );
		file_put_contents( $file_path, $content );

		$this->temp_files[] = $file_path;

		return $file_path;
	}

	/**
	 * Helper: Create invalid ZIP file (non-ZIP content)
	 *
	 * @return string Path to created file.
	 */
	private function create_invalid_zip() {
		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-invalid-' . uniqid() . '.zip';

		// Create file with non-ZIP content
		file_put_contents( $file_path, 'This is not a ZIP file' );

		$this->temp_files[] = $file_path;

		return $file_path;
	}

	/**
	 * Helper: Create valid ZIP file
	 *
	 * @return string Path to created file.
	 */
	private function create_valid_zip() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not available' );
		}

		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-valid-' . uniqid() . '.zip';

		// Create valid ZIP file
		$zip = new ZipArchive();
		$zip->open( $file_path, ZipArchive::CREATE );
		$zip->addFromString( 'test.txt', 'Test content' );
		$zip->close();

		$this->temp_files[] = $file_path;

		return $file_path;
	}

	/**
	 * Helper: Create valid ZIP file with specific size
	 *
	 * @param int $target_size Target size in bytes.
	 * @return string Path to created file.
	 */
	private function create_valid_zip_with_size( $target_size ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not available' );
		}

		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-sized-' . uniqid() . '.zip';

		// Create valid ZIP file with content that approximates target size
		$zip = new ZipArchive();
		$zip->open( $file_path, ZipArchive::CREATE );

		// Add files until we reach approximate size
		// (accounting for ZIP compression)
		$content_size = (int) ( $target_size * 1.2 ); // Assume ~20% compression
		$content = str_repeat( 'A', $content_size );
		$zip->addFromString( 'data.txt', $content );

		$zip->close();

		$this->temp_files[] = $file_path;

		return $file_path;
	}

	/**
	 * Helper: Create empty ZIP file
	 *
	 * @return string Path to created file.
	 */
	private function create_empty_zip() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not available' );
		}

		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-empty-' . uniqid() . '.zip';

		// Create empty ZIP file (no files added)
		$zip = new ZipArchive();
		$zip->open( $file_path, ZipArchive::CREATE );
		$zip->close();

		$this->temp_files[] = $file_path;

		return $file_path;
	}

	/**
	 * Helper: Create corrupted ZIP file
	 *
	 * @return string Path to created file.
	 */
	private function create_corrupted_zip() {
		$temp_dir = get_temp_dir();
		$file_path = $temp_dir . 'cuft-test-corrupted-' . uniqid() . '.zip';

		// Create file that starts with PK but has corrupted structure
		$content = "PK\x03\x04" . str_repeat( "\xFF", 100 );
		file_put_contents( $file_path, $content );

		$this->temp_files[] = $file_path;

		return $file_path;
	}
}
