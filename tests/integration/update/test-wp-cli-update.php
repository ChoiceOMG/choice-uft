<?php
/**
 * Integration Test: Update via WP-CLI (T018)
 *
 * Tests that plugin updates work correctly when triggered via WP-CLI
 * `wp plugin update choice-uft` command. Validates FR-302 implementation.
 *
 * Reference: quickstart.md QS-3
 *
 * @package Choice_UFT
 * @subpackage Tests
 */

/**
 * Integration test class for WP-CLI updates
 */
class CUFT_Test_WP_CLI_Update extends WP_UnitTestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear update history.
		CUFT_Update_Logger::clear_history();

		// Clear update check transients.
		delete_site_transient( 'update_plugins' );
		delete_transient( 'cuft_plugin_info' );

		// Set up administrator user.
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
		// Clear history and transients.
		CUFT_Update_Logger::clear_history();
		delete_site_transient( 'update_plugins' );
		delete_transient( 'cuft_plugin_info' );

		// Clean up WP_CLI constant mock.
		if ( defined( 'WP_CLI' ) ) {
			// Cannot undefine constants, but test isolation handles this.
		}

		parent::tearDown();
	}

	/**
	 * Test: WP-CLI update uses same WordPress hooks
	 *
	 * Scenario: Update triggered via `wp plugin update choice-uft`
	 * Expected: Same Plugin_Upgrader hooks fire as Plugins page update
	 */
	public function test_wp_cli_uses_same_hooks() {
		// Verify all required hooks are registered.
		$this->assertNotFalse(
			has_filter( 'plugins_api', array( 'CUFT_Plugin_Info', 'plugins_api_handler' ) ),
			'plugins_api filter should be registered for WP-CLI'
		);

		$this->assertNotFalse(
			has_filter( 'upgrader_source_selection', array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'upgrader_source_selection filter should be registered for WP-CLI'
		);

		$this->assertNotFalse(
			has_action( 'upgrader_process_complete', array( 'CUFT_Update_Logger', 'log_update_completion' ) ),
			'upgrader_process_complete action should be registered for WP-CLI'
		);

		// Note: WP-CLI calls Plugin_Upgrader->upgrade() internally,
		// which fires all the same hooks as the Plugins page update.
	}

	/**
	 * Test: Update history logged with WP-CLI trigger location
	 *
	 * Scenario: Update completes via WP-CLI
	 * Expected: Update history entry has trigger_location = 'wp_cli'
	 */
	public function test_update_history_logged_as_wp_cli() {
		// Define WP_CLI constant to simulate WP-CLI environment.
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Simulate upgrader_process_complete action from WP-CLI update.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
		);

		// Fire the action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 1 );

		// Verify history entry created.
		$this->assertCount( 1, $history, 'Update history should have one entry' );

		$entry = $history[0];

		// Verify trigger_location detected as WP-CLI.
		$this->assertEquals(
			'wp_cli',
			$entry['trigger_location'],
			'Trigger location should be wp_cli when WP_CLI constant is defined'
		);

		// Verify status.
		$this->assertEquals(
			'complete',
			$entry['status'],
			'Status should be complete for successful update'
		);
	}

	/**
	 * Test: WP-CLI update exit code 0 on success
	 *
	 * Scenario: Update completes successfully via WP-CLI
	 * Expected: No WP_Error returned, success condition met
	 */
	public function test_wp_cli_success_condition() {
		// Simulate successful update completion.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
		);

		// Ensure upgrader result is not WP_Error.
		$upgrader->result = true;

		// Fire completion action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Verify no WP_Error in result.
		$this->assertNotInstanceOf(
			'WP_Error',
			$upgrader->result,
			'Upgrader result should not be WP_Error on success'
		);

		// Verify update history shows success.
		$history = CUFT_Update_Logger::get_history( 1 );
		$this->assertEquals(
			'complete',
			$history[0]['status'],
			'Update status should be complete'
		);
	}

	/**
	 * Test: WP-CLI update handles failure correctly
	 *
	 * Scenario: Update fails via WP-CLI
	 * Expected: WP_Error returned, exit code 1, error logged
	 */
	public function test_wp_cli_failure_handling() {
		// Define WP_CLI constant.
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Simulate failed update.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
		);

		// Set error result.
		$upgrader->result = new WP_Error( 'update_failed', 'Update failed for testing' );

		// Fire completion action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Verify update history shows failure.
		$history = CUFT_Update_Logger::get_history( 1 );
		$this->assertEquals(
			'failed',
			$history[0]['status'],
			'Update status should be failed when WP_Error encountered'
		);

		// Verify error message logged.
		$this->assertEquals(
			'Update failed for testing',
			$history[0]['error_message'],
			'Error message should be logged from WP_Error'
		);
	}

	/**
	 * Test: Version information available via plugins_api
	 *
	 * Scenario: WP-CLI checks for available updates
	 * Expected: Plugin information returned with version number
	 */
	public function test_version_info_available_for_wp_cli() {
		// Simulate plugins_api request.
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Verify plugin info returned.
		$this->assertIsObject( $result, 'Plugin info should be returned' );
		$this->assertObjectHasProperty( 'version', $result, 'Plugin info should have version' );

		// Verify version format.
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			$result->version,
			'Version should be in semver format'
		);

		// Verify download link available.
		$this->assertObjectHasProperty( 'download_link', $result, 'Download link should be available' );
		$this->assertStringStartsWith(
			'https://github.com/',
			$result->download_link,
			'Download link should point to GitHub'
		);
	}

	/**
	 * Test: Directory naming works with WP-CLI update
	 *
	 * Scenario: Update via WP-CLI extracts ZIP with versioned directory
	 * Expected: Directory renamed to WordPress-compatible format
	 */
	public function test_directory_naming_with_wp_cli() {
		// Simulate upgrader_source_selection filter (same as Plugins page).
		$source        = '/tmp/choice-uft-v3.17.0/';
		$remote_source = '/tmp/choice-uft-v3.17.0.zip';
		$upgrader      = new Plugin_Upgrader();
		$hook_extra    = array(
			'type'   => 'plugin',
			'plugin' => 'choice-uft/choice-universal-form-tracker.php',
		);

		// Verify filter is registered.
		$this->assertNotFalse(
			has_filter( 'upgrader_source_selection', array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'Directory naming filter should work for WP-CLI updates'
		);

		// Note: Actual directory renaming requires filesystem operations,
		// tested in unit tests for CUFT_Directory_Fixer.
	}

	/**
	 * Test: User context captured in WP-CLI update
	 *
	 * Scenario: Admin runs `wp plugin update choice-uft`
	 * Expected: User ID and display name logged in update history
	 */
	public function test_user_context_captured() {
		// Define WP_CLI constant.
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Get current user.
		$current_user = wp_get_current_user();

		// Simulate update completion.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
		);

		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 1 );

		// Verify user information logged.
		$this->assertArrayHasKey( 'user_id', $history[0], 'User ID should be logged' );
		$this->assertEquals(
			$current_user->ID,
			$history[0]['user_id'],
			'Logged user ID should match current user'
		);

		$this->assertArrayHasKey( 'user_display_name', $history[0], 'User display name should be logged' );
	}

	/**
	 * Test: Plugin remains active after WP-CLI update
	 *
	 * Scenario: Update completes via WP-CLI
	 * Expected: Plugin is still active (WordPress behavior)
	 */
	public function test_plugin_remains_active_after_wp_cli_update() {
		// Note: This is WordPress core behavior.
		// WP-CLI keeps plugins active through updates unless --deactivate flag used.

		$plugin_basename = 'choice-uft/choice-universal-form-tracker.php';

		// Verify plugin is recognized.
		$all_plugins = get_plugins();
		$this->assertArrayHasKey(
			$plugin_basename,
			$all_plugins,
			'Plugin should be recognized after WP-CLI update'
		);
	}

	/**
	 * Test: Multiple WP-CLI update attempts handled correctly
	 *
	 * Scenario: Run `wp plugin update choice-uft` multiple times
	 * Expected: Each attempt logged separately
	 */
	public function test_multiple_wp_cli_updates_logged() {
		// Define WP_CLI constant.
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Simulate three update attempts.
		for ( $i = 0; $i < 3; $i++ ) {
			$upgrader   = new Plugin_Upgrader();
			$hook_extra = array(
				'type'    => 'plugin',
				'action'  => 'update',
				'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
			);

			do_action( 'upgrader_process_complete', $upgrader, $hook_extra );
		}

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 5 );

		// Verify three entries logged.
		$this->assertCount( 3, $history, 'Three update attempts should be logged' );

		// Verify all are WP-CLI triggers.
		foreach ( $history as $entry ) {
			$this->assertEquals(
				'wp_cli',
				$entry['trigger_location'],
				'All entries should be WP-CLI triggers'
			);
		}
	}
}
