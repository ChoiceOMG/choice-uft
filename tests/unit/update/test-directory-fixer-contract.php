<?php
/**
 * Contract Tests: upgrader_source_selection Filter
 *
 * Tests for directory naming fix during plugin updates (FR-103)
 *
 * Purpose: Verify that the upgrader_source_selection filter correctly renames
 * extracted plugin directories from GitHub's versioned format (e.g., choice-uft-v3.17.0/)
 * to WordPress's expected format (choice-uft/)
 *
 * @package Choice_UTM_Form_Tracker
 * @subpackage Tests
 */

class Test_Directory_Fixer_Contract extends WP_UnitTestCase {

    /**
     * Mock WP_Filesystem instance
     *
     * @var object
     */
    private $mock_filesystem;

    /**
     * Temporary test directory
     *
     * @var string
     */
    private $temp_dir;

    /**
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Create temporary test directory
        $this->temp_dir = sys_get_temp_dir() . '/cuft-test-' . uniqid();
        mkdir($this->temp_dir, 0755, true);

        // Initialize global $wp_filesystem if needed
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->mock_filesystem = $wp_filesystem;
    }

    /**
     * Clean up test environment after each test
     */
    public function tearDown(): void {
        // Clean up temporary directory
        if (is_dir($this->temp_dir)) {
            $this->remove_directory($this->temp_dir);
        }

        parent::tearDown();
    }

    /**
     * Recursively remove directory and contents
     *
     * @param string $dir Directory path
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->remove_directory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a test plugin directory structure
     *
     * @param string $dir_name Directory name (e.g., 'choice-uft-v3.17.0')
     * @return string Full path to created directory
     */
    private function create_test_plugin_directory($dir_name) {
        $dir_path = $this->temp_dir . '/' . $dir_name;
        mkdir($dir_path, 0755, true);

        // Create main plugin file
        file_put_contents(
            $dir_path . '/choice-universal-form-tracker.php',
            "<?php\n// Test plugin file\n"
        );

        // Create includes directory
        mkdir($dir_path . '/includes', 0755);

        return trailingslashit($dir_path);
    }

    /**
     * TC-001: Versioned Directory (Normal Case)
     *
     * Verify that a versioned directory (choice-uft-v3.17.0/) is renamed
     * to the expected WordPress slug (choice-uft/)
     */
    public function test_versioned_directory_renamed() {
        // Create test directory with version
        $source = $this->create_test_plugin_directory('choice-uft-v3.17.0');
        $remote_source = $this->temp_dir . '/choice-uft-v3.17.0.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        // Create mock upgrader
        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error for valid versioned directory');
        $this->assertStringEndsWith('choice-uft/', $result, 'Result should end with choice-uft/');
        $this->assertStringNotContainsString('v3.17.0', $result, 'Result should not contain version number');
        $this->assertDirectoryExists(rtrim($result, '/'), 'Renamed directory should exist');
    }

    /**
     * TC-002: Already Correct Directory
     *
     * Verify that if the directory is already correctly named (choice-uft/),
     * no rename occurs and source is returned unchanged
     */
    public function test_already_correct_directory() {
        // Create test directory with correct name
        $source = $this->create_test_plugin_directory('choice-uft');
        $remote_source = $this->temp_dir . '/choice-uft.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error for correctly named directory');
        $this->assertSame($source, $result, 'Source should be returned unchanged when already correct');
    }

    /**
     * TC-003: Not Our Plugin (Pass-Through)
     *
     * Verify that the filter passes through (returns source unchanged)
     * when the plugin being updated is not choice-uft
     */
    public function test_not_our_plugin_passthrough() {
        // Create test directory for different plugin
        $source = $this->create_test_plugin_directory('other-plugin-v1.0.0');
        $remote_source = $this->temp_dir . '/other-plugin-v1.0.0.zip';

        $hook_extra = array(
            'plugin' => 'other-plugin/other-plugin.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertSame($source, $result, 'Source should be returned unchanged for other plugins');
    }

    /**
     * TC-004: Theme Update (Pass-Through)
     *
     * Verify that the filter passes through when the update type is 'theme',
     * not 'plugin'
     */
    public function test_theme_update_passthrough() {
        $source = $this->create_test_plugin_directory('some-theme-v1.0');
        $remote_source = $this->temp_dir . '/some-theme-v1.0.zip';

        $hook_extra = array(
            'theme' => 'some-theme',
            'type' => 'theme',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertSame($source, $result, 'Source should be returned unchanged for theme updates');
    }

    /**
     * TC-005: GitHub Commit ZIP Format
     *
     * Verify that GitHub commit ZIP format (ChoiceOMG-choice-uft-abc1234/)
     * is correctly renamed to choice-uft/
     */
    public function test_github_commit_format_renamed() {
        // Create test directory with GitHub commit format
        $source = $this->create_test_plugin_directory('ChoiceOMG-choice-uft-abc1234');
        $remote_source = $this->temp_dir . '/abc1234.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error for GitHub commit format');
        $this->assertStringEndsWith('choice-uft/', $result, 'Result should end with choice-uft/');
        $this->assertStringNotContainsString('ChoiceOMG', $result, 'Result should not contain GitHub org name');
        $this->assertStringNotContainsString('abc1234', $result, 'Result should not contain commit hash');
    }

    /**
     * TC-006: Branch ZIP Format
     *
     * Verify that branch ZIP format (choice-uft-master/) is correctly
     * renamed to choice-uft/
     */
    public function test_branch_format_renamed() {
        // Create test directory with branch name
        $source = $this->create_test_plugin_directory('choice-uft-master');
        $remote_source = $this->temp_dir . '/master.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error for branch format');
        $this->assertStringEndsWith('choice-uft/', $result, 'Result should end with choice-uft/');
        $this->assertStringNotContainsString('master', $result, 'Result should not contain branch name');
    }

    /**
     * TC-007: Unrecognized Directory Pattern
     *
     * Verify that an unrecognized directory pattern returns WP_Error
     */
    public function test_unrecognized_pattern_returns_error() {
        // Create test directory with unrecognized pattern
        $source = $this->create_test_plugin_directory('something-else-choice-uft');
        $remote_source = $this->temp_dir . '/unknown.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertWPError($result, 'Filter should return WP_Error for unrecognized pattern');
        $this->assertSame('incompatible_plugin_archive', $result->get_error_code(), 'Error code should be incompatible_plugin_archive');
    }

    /**
     * TC-008: Source Directory Missing
     *
     * Verify that WP_Error is returned when source directory doesn't exist
     */
    public function test_source_directory_missing_returns_error() {
        // Use non-existent directory path
        $source = $this->temp_dir . '/nonexistent/';
        $remote_source = $this->temp_dir . '/test.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertWPError($result, 'Filter should return WP_Error when source directory is missing');
        $this->assertSame('source_directory_missing', $result->get_error_code(), 'Error code should be source_directory_missing');
    }

    /**
     * TC-009: Rename Operation Fails
     *
     * Verify that WP_Error is returned when rename operation fails
     * (simulated by making destination unwritable)
     */
    public function test_rename_fails_returns_error() {
        // Create test directory
        $source = $this->create_test_plugin_directory('choice-uft-v3.17.0');
        $remote_source = $this->temp_dir . '/choice-uft-v3.17.0.zip';

        // Make parent directory read-only to simulate rename failure
        chmod($this->temp_dir, 0555);

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Restore permissions for cleanup
        chmod($this->temp_dir, 0755);

        // Assertions
        $this->assertWPError($result, 'Filter should return WP_Error when rename fails');
        $this->assertSame('unable_to_rename_directory', $result->get_error_code(), 'Error code should be unable_to_rename_directory');
    }

    /**
     * TC-010: Main Plugin File Missing
     *
     * Verify that WP_Error is returned when the main plugin file is missing
     * from the extracted directory
     */
    public function test_missing_plugin_file_returns_error() {
        // Create directory without main plugin file
        $dir_path = $this->temp_dir . '/choice-uft-v3.17.0';
        mkdir($dir_path, 0755, true);
        mkdir($dir_path . '/includes', 0755);
        // Intentionally NOT creating choice-universal-form-tracker.php

        $source = trailingslashit($dir_path);
        $remote_source = $this->temp_dir . '/choice-uft-v3.17.0.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertWPError($result, 'Filter should return WP_Error when main plugin file is missing');
        $this->assertSame('invalid_plugin_structure', $result->get_error_code(), 'Error code should be invalid_plugin_structure');
    }

    /**
     * TC-011: Overwrite Existing Directory
     *
     * Verify that existing destination directory is overwritten successfully
     * (from previous failed update)
     */
    public function test_overwrite_existing_directory() {
        // Create source directory
        $source = $this->create_test_plugin_directory('choice-uft-v3.17.0');

        // Pre-create destination directory (simulating previous failed update)
        $destination = $this->temp_dir . '/choice-uft';
        mkdir($destination, 0755, true);
        file_put_contents($destination . '/old-file.txt', 'old content');

        $remote_source = $this->temp_dir . '/choice-uft-v3.17.0.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error when overwriting existing directory');
        $this->assertStringEndsWith('choice-uft/', $result, 'Result should end with choice-uft/');
        $this->assertFileExists(rtrim($result, '/') . '/choice-universal-form-tracker.php', 'Plugin file should exist in renamed directory');
    }

    /**
     * Test that filter hook is registered
     */
    public function test_filter_hook_registered() {
        $this->assertGreaterThan(
            0,
            has_filter('upgrader_source_selection'),
            'upgrader_source_selection filter should be registered'
        );
    }

    /**
     * Test that returned path has trailing slash
     */
    public function test_returned_path_has_trailing_slash() {
        $source = $this->create_test_plugin_directory('choice-uft-v3.17.0');
        $remote_source = $this->temp_dir . '/choice-uft-v3.17.0.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error');
        $this->assertStringEndsWith('/', $result, 'Returned path must have trailing slash');
    }

    /**
     * Test numeric version format (choice-uft-3.17.0)
     */
    public function test_numeric_version_format_renamed() {
        // Create test directory with numeric version (no 'v' prefix)
        $source = $this->create_test_plugin_directory('choice-uft-3.17.0');
        $remote_source = $this->temp_dir . '/choice-uft-3.17.0.zip';

        $hook_extra = array(
            'plugin' => 'choice-uft/choice-universal-form-tracker.php',
            'type' => 'plugin',
            'action' => 'update',
        );

        $upgrader = new stdClass();

        // Apply filter
        $result = apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);

        // Assertions
        $this->assertNotWPError($result, 'Filter should not return WP_Error for numeric version format');
        $this->assertStringEndsWith('choice-uft/', $result, 'Result should end with choice-uft/');
        $this->assertStringNotContainsString('3.17.0', $result, 'Result should not contain version number');
    }
}
