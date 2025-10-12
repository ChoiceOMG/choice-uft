<?php
/**
 * Integration Test: Directory Naming Correction (T007)
 *
 * Tests the directory naming fix that renames extracted plugin directories
 * from GitHub format (choice-uft-v3.17.0) to WordPress format (choice-uft).
 * This validates FR-103 implementation.
 *
 * Reference: quickstart.md QS-7
 *
 * @package Choice_UFT
 * @subpackage Tests
 */

/**
 * Integration test class for directory naming correction
 */
class CUFT_Test_Directory_Naming extends WP_UnitTestCase {

	/**
	 * Temporary test directory
	 *
	 * @var string
	 */
	private $test_dir;

	/**
	 * WP_Filesystem instance
	 *
	 * @var WP_Filesystem_Base
	 */
	private $wp_filesystem;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize WP_Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$this->wp_filesystem = $wp_filesystem;

		// Create temporary test directory
		$this->test_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
		$this->wp_filesystem->mkdir( $this->test_dir );

		// Set up user with update_plugins capability
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		// Clean up test directory
		if ( $this->wp_filesystem->is_dir( $this->test_dir ) ) {
			$this->wp_filesystem->rmdir( $this->test_dir, true );
		}

		parent::tearDown();
	}

	/**
	 * Helper: Create test plugin directory structure
	 *
	 * @param string $dir_name Directory name to create
	 * @return string Full path to created directory
	 */
	private function create_test_plugin_directory( $dir_name ) {
		$source = trailingslashit( $this->test_dir ) . $dir_name . '/';
		$this->wp_filesystem->mkdir( $source );

		// Create main plugin file
		$plugin_file = $source . 'choice-universal-form-tracker.php';
		$this->wp_filesystem->put_contents( $plugin_file, '<?php // Test plugin file' );

		// Create includes directory
		$includes_dir = $source . 'includes/';
		$this->wp_filesystem->mkdir( $includes_dir );

		return $source;
	}

	/**
	 * Test: Directory renamed from GitHub format to WordPress format
	 *
	 * Scenario: GitHub ZIP extracts as choice-uft-v3.17.0
	 * Expected: Filter renames to choice-uft
	 */
	public function test_directory_renamed_from_github_format() {
		// Create test directory with version suffix
		$source = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		// Simulate upgrader_source_selection filter
		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'', // remote_source not used
			null, // upgrader instance
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify result is a string (not WP_Error)
		$this->assertIsString( $result, 'upgrader_source_selection should return string path' );
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should not return WP_Error' );

		// Verify directory was renamed
		$expected_basename = 'choice-uft';
		$actual_basename   = basename( rtrim( $result, '/' ) );
		$this->assertEquals(
			$expected_basename,
			$actual_basename,
			'Directory should be renamed from choice-uft-v3.17.0 to choice-uft'
		);

		// Verify renamed directory exists
		$this->assertTrue(
			$this->wp_filesystem->is_dir( $result ),
			'Renamed directory should exist'
		);

		// Verify plugin file exists in renamed directory
		$plugin_file = trailingslashit( $result ) . 'choice-universal-form-tracker.php';
		$this->assertTrue(
			$this->wp_filesystem->exists( $plugin_file ),
			'Plugin file should exist in renamed directory'
		);
	}

	/**
	 * Test: WordPress installs to /wp-content/plugins/choice-uft/
	 *
	 * Scenario: Update process completes
	 * Expected: Plugin installed to correct location without version suffix
	 */
	public function test_wordpress_installs_to_correct_location() {
		$source = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify final directory name matches WordPress expectations
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertEquals( 'choice-uft', $basename, 'Final directory should be choice-uft' );

		// Verify path structure is correct
		$this->assertStringContainsString(
			'/choice-uft/',
			$result,
			'Path should contain /choice-uft/ directory'
		);

		// Verify no version suffix in path
		$this->assertStringNotContainsString(
			'-v3.17.0',
			$result,
			'Path should not contain version suffix'
		);
	}

	/**
	 * Test: No "directory mismatch" errors occur
	 *
	 * Scenario: Directory is renamed correctly
	 * Expected: Filter returns valid path, no WP_Error
	 */
	public function test_no_directory_mismatch_errors() {
		$source = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify no WP_Error returned
		$this->assertNotInstanceOf(
			'WP_Error',
			$result,
			'Filter should not return WP_Error for valid directory'
		);

		// Verify result is a valid path
		$this->assertIsString( $result, 'Result should be a string path' );
		$this->assertStringStartsWith( '/', $result, 'Path should be absolute' );
		$this->assertStringEndsWith( '/', $result, 'Path should end with trailing slash' );
	}

	/**
	 * Test: Already correct directory name (no rename needed)
	 *
	 * Scenario: Directory already named choice-uft
	 * Expected: Filter returns path unchanged
	 */
	public function test_already_correct_directory_name() {
		$source = $this->create_test_plugin_directory( 'choice-uft' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify path unchanged
		$this->assertEquals( $source, $result, 'Already correct directory should be unchanged' );

		// Verify no WP_Error
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should not return WP_Error' );
	}

	/**
	 * Test: GitHub commit ZIP format (ChoiceOMG-choice-uft-abc1234)
	 *
	 * Scenario: GitHub ZIP from commit download
	 * Expected: Directory renamed to choice-uft
	 */
	public function test_github_commit_zip_format() {
		$source = $this->create_test_plugin_directory( 'ChoiceOMG-choice-uft-abc1234' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify renamed to correct format
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertEquals(
			'choice-uft',
			$basename,
			'GitHub commit format should be renamed to choice-uft'
		);

		// Verify directory exists
		$this->assertTrue( $this->wp_filesystem->is_dir( $result ) );
	}

	/**
	 * Test: Branch ZIP format (choice-uft-master)
	 *
	 * Scenario: GitHub ZIP from branch download
	 * Expected: Directory renamed to choice-uft
	 */
	public function test_branch_zip_format() {
		$source = $this->create_test_plugin_directory( 'choice-uft-master' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify renamed to correct format
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertEquals(
			'choice-uft',
			$basename,
			'Branch format should be renamed to choice-uft'
		);
	}

	/**
	 * Test: Version without 'v' prefix (choice-uft-3.17.0)
	 *
	 * Scenario: Directory named with numeric version
	 * Expected: Directory renamed to choice-uft
	 */
	public function test_version_without_v_prefix() {
		$source = $this->create_test_plugin_directory( 'choice-uft-3.17.0' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify renamed
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertEquals(
			'choice-uft',
			$basename,
			'Numeric version format should be renamed to choice-uft'
		);
	}

	/**
	 * Test: Pass-through for non-CUFT plugins
	 *
	 * Scenario: Filter called for other plugins
	 * Expected: Source path returned unchanged
	 */
	public function test_pass_through_for_other_plugins() {
		$source = $this->create_test_plugin_directory( 'other-plugin-v1.0.0' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'other-plugin/other-plugin.php',
			)
		);

		// Verify path unchanged (pass-through)
		$this->assertEquals( $source, $result, 'Non-CUFT plugins should pass through unchanged' );
	}

	/**
	 * Test: Pass-through for theme updates
	 *
	 * Scenario: Filter called for theme update
	 * Expected: Source path returned unchanged
	 */
	public function test_pass_through_for_theme_updates() {
		$source = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'theme', // Theme, not plugin
				'action' => 'update',
			)
		);

		// Verify path unchanged (not a plugin)
		$this->assertEquals( $source, $result, 'Theme updates should pass through unchanged' );
	}

	/**
	 * Test: WP_Error when source directory missing
	 *
	 * Scenario: Source path points to non-existent directory
	 * Expected: Filter returns WP_Error
	 */
	public function test_error_when_source_directory_missing() {
		$source = trailingslashit( $this->test_dir ) . 'nonexistent-directory/';

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify WP_Error returned
		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'Should return WP_Error when source directory missing'
		);

		// Verify error code
		$this->assertEquals(
			'source_directory_missing',
			$result->get_error_code(),
			'Error code should be source_directory_missing'
		);
	}

	/**
	 * Test: WP_Error when main plugin file missing
	 *
	 * Scenario: Directory exists but doesn't contain main plugin file
	 * Expected: Filter returns WP_Error
	 */
	public function test_error_when_plugin_file_missing() {
		// Create directory WITHOUT plugin file
		$source = trailingslashit( $this->test_dir ) . 'choice-uft-v3.17.0/';
		$this->wp_filesystem->mkdir( $source );
		// Don't create choice-universal-form-tracker.php

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify WP_Error returned
		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'Should return WP_Error when main plugin file missing'
		);

		// Verify error code
		$this->assertEquals(
			'invalid_plugin_structure',
			$result->get_error_code(),
			'Error code should be invalid_plugin_structure'
		);
	}

	/**
	 * Test: WP_Error when unrecognized directory pattern
	 *
	 * Scenario: Directory name doesn't match any expected pattern
	 * Expected: Filter returns WP_Error
	 */
	public function test_error_when_unrecognized_directory_pattern() {
		$source = $this->create_test_plugin_directory( 'totally-different-name' );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify WP_Error returned
		$this->assertInstanceOf(
			'WP_Error',
			$result,
			'Should return WP_Error for unrecognized directory pattern'
		);

		// Verify error code
		$this->assertEquals(
			'incompatible_plugin_archive',
			$result->get_error_code(),
			'Error code should be incompatible_plugin_archive'
		);
	}

	/**
	 * Test: Overwrite existing directory
	 *
	 * Scenario: Target directory already exists from failed update
	 * Expected: Filter successfully overwrites existing directory
	 */
	public function test_overwrite_existing_directory() {
		// Create source directory
		$source = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		// Create destination directory (from previous failed update)
		$destination = trailingslashit( $this->test_dir ) . 'choice-uft/';
		$this->wp_filesystem->mkdir( $destination );

		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify successful rename (overwrite allowed)
		$this->assertIsString( $result, 'Should successfully overwrite existing directory' );
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should not return error' );

		// Verify result points to correct directory
		$basename = basename( rtrim( $result, '/' ) );
		$this->assertEquals( 'choice-uft', $basename );
	}

	/**
	 * Test: Trailing slash handling
	 *
	 * Scenario: Input and output paths should have consistent trailing slashes
	 * Expected: Output always has trailing slash
	 */
	public function test_trailing_slash_handling() {
		// Test with trailing slash
		$source_with_slash = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );

		$result1 = apply_filters(
			'upgrader_source_selection',
			$source_with_slash,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify result has trailing slash
		$this->assertStringEndsWith(
			'/',
			$result1,
			'Result should have trailing slash'
		);

		// Test without trailing slash (edge case)
		$source_no_slash = rtrim( $this->create_test_plugin_directory( 'choice-uft-v3.17.1' ), '/' );

		$result2 = apply_filters(
			'upgrader_source_selection',
			$source_no_slash,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Verify result still has trailing slash
		$this->assertStringEndsWith(
			'/',
			$result2,
			'Result should have trailing slash even if input did not'
		);
	}

	/**
	 * Test: Numeric version format variations
	 *
	 * Scenario: Various version number formats
	 * Expected: All formats correctly renamed
	 */
	public function test_numeric_version_format_variations() {
		// Test major.minor.patch
		$source1 = $this->create_test_plugin_directory( 'choice-uft-v3.17.0' );
		$result1 = apply_filters(
			'upgrader_source_selection',
			$source1,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);
		$this->assertEquals( 'choice-uft', basename( rtrim( $result1, '/' ) ) );

		// Test major.minor (no patch)
		$source2 = $this->create_test_plugin_directory( 'choice-uft-v3.17' );
		$result2 = apply_filters(
			'upgrader_source_selection',
			$source2,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);
		$this->assertEquals( 'choice-uft', basename( rtrim( $result2, '/' ) ) );

		// Test with prerelease suffix
		$source3 = $this->create_test_plugin_directory( 'choice-uft-v3.17.0-beta.1' );
		$result3 = apply_filters(
			'upgrader_source_selection',
			$source3,
			'',
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);
		$this->assertEquals( 'choice-uft', basename( rtrim( $result3, '/' ) ) );
	}
}
