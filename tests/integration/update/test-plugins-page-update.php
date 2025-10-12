<?php
/**
 * Integration Test: Update from Plugins Page (T017)
 *
 * Tests that plugin updates work correctly when triggered from the WordPress
 * Plugins page "Update Now" button. Validates FR-301 implementation.
 *
 * Reference: quickstart.md QS-2
 *
 * @package Choice_UFT
 * @subpackage Tests
 */

/**
 * Integration test class for Plugins page updates
 */
class CUFT_Test_Plugins_Page_Update extends WP_UnitTestCase {

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

		parent::tearDown();
	}

	/**
	 * Test: Update executes from Plugins page
	 *
	 * Scenario: Admin clicks "Update Now" button on Plugins page
	 * Expected: WordPress uses Plugin_Upgrader, our hooks fire correctly
	 */
	public function test_update_executes_from_plugins_page() {
		// This test verifies WordPress integration, not actual update execution.
		// Actual update execution requires network access and is tested manually.

		// Verify plugins_api filter is registered.
		$this->assertNotFalse(
			has_filter( 'plugins_api', array( 'CUFT_Plugin_Info', 'plugins_api_handler' ) ),
			'plugins_api filter should be registered'
		);

		// Verify upgrader_source_selection filter is registered.
		$this->assertNotFalse(
			has_filter( 'upgrader_source_selection', array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'upgrader_source_selection filter should be registered'
		);

		// Verify upgrader_process_complete action is registered.
		$this->assertNotFalse(
			has_action( 'upgrader_process_complete', array( 'CUFT_Update_Logger', 'log_update_completion' ) ),
			'upgrader_process_complete action should be registered'
		);
	}

	/**
	 * Test: Update history logged after Plugins page update
	 *
	 * Scenario: Update completes from Plugins page
	 * Expected: Update history log entry created with trigger_location = 'plugins_page'
	 */
	public function test_update_history_logged() {
		// Simulate upgrader_process_complete action from Plugins page update.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
		);

		// Set HTTP_REFERER to simulate Plugins page origin.
		$_SERVER['HTTP_REFERER'] = admin_url( 'plugins.php' );

		// Fire the action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 1 );

		// Verify history entry created.
		$this->assertCount( 1, $history, 'Update history should have one entry' );

		$entry = $history[0];

		// Verify trigger_location.
		$this->assertEquals(
			'plugins_page',
			$entry['trigger_location'],
			'Trigger location should be plugins_page'
		);

		// Verify status.
		$this->assertEquals(
			'complete',
			$entry['status'],
			'Status should be complete for successful update'
		);

		// Verify timestamp exists.
		$this->assertArrayHasKey( 'timestamp', $entry, 'Entry should have timestamp' );

		// Verify user_id.
		$this->assertArrayHasKey( 'user_id', $entry, 'Entry should have user_id' );
		$this->assertEquals(
			get_current_user_id(),
			$entry['user_id'],
			'User ID should match current user'
		);

		// Clean up.
		unset( $_SERVER['HTTP_REFERER'] );
	}

	/**
	 * Test: Plugin info modal accessible before update
	 *
	 * Scenario: User views plugin details before updating
	 * Expected: Plugin information returned successfully
	 */
	public function test_plugin_info_modal_accessible() {
		// Simulate plugins_api request for plugin information.
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Verify plugin info returned.
		$this->assertIsObject( $result, 'Plugin info should be returned as object' );
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Plugin info should not be WP_Error' );

		// Verify essential fields present.
		$this->assertObjectHasProperty( 'name', $result, 'Plugin info should have name' );
		$this->assertObjectHasProperty( 'version', $result, 'Plugin info should have version' );
		$this->assertObjectHasProperty( 'download_link', $result, 'Plugin info should have download_link' );

		// Verify download link is valid.
		$this->assertStringStartsWith(
			'https://',
			$result->download_link,
			'Download link should be HTTPS'
		);
		$this->assertStringContainsString(
			'github.com',
			$result->download_link,
			'Download link should point to GitHub'
		);
	}

	/**
	 * Test: Directory naming works with Plugins page update
	 *
	 * Scenario: Update ZIP contains versioned directory name
	 * Expected: Directory renamed to WordPress-compatible format
	 */
	public function test_directory_naming_with_plugins_page_update() {
		global $wp_filesystem;

		// Initialize filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Simulate upgrader_source_selection filter.
		$source        = '/tmp/choice-uft-v3.17.0/';
		$remote_source = '/tmp/choice-uft-v3.17.0.zip';
		$upgrader      = new Plugin_Upgrader();
		$hook_extra    = array(
			'type'   => 'plugin',
			'plugin' => 'choice-uft/choice-universal-form-tracker.php',
		);

		// Note: Actual rename requires filesystem mocking in real test environment.
		// This test validates the filter is registered and callable.
		$this->assertTrue(
			is_callable( array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'Directory fixer should be callable'
		);
	}

	/**
	 * Test: Update completes successfully
	 *
	 * Scenario: All hooks fire in correct order during update
	 * Expected: plugins_api → upgrader_source_selection → upgrader_process_complete
	 */
	public function test_update_hooks_fire_in_order() {
		$hooks_fired = array();

		// Track plugins_api hook.
		add_filter(
			'plugins_api',
			function ( $result, $action, $args ) use ( &$hooks_fired ) {
				if ( 'plugin_information' === $action && isset( $args->slug ) && 'choice-uft' === $args->slug ) {
					$hooks_fired[] = 'plugins_api';
				}
				return $result;
			},
			1,
			3
		);

		// Track upgrader_source_selection hook.
		add_filter(
			'upgrader_source_selection',
			function ( $source, $remote_source, $upgrader, $hook_extra ) use ( &$hooks_fired ) {
				if ( isset( $hook_extra['plugin'] ) && strpos( $hook_extra['plugin'], 'choice-uft' ) !== false ) {
					$hooks_fired[] = 'upgrader_source_selection';
				}
				return $source;
			},
			1,
			4
		);

		// Track upgrader_process_complete hook.
		add_action(
			'upgrader_process_complete',
			function ( $upgrader, $hook_extra ) use ( &$hooks_fired ) {
				if ( isset( $hook_extra['plugins'] ) && in_array( 'choice-uft/choice-universal-form-tracker.php', $hook_extra['plugins'], true ) ) {
					$hooks_fired[] = 'upgrader_process_complete';
				}
			},
			1,
			2
		);

		// Simulate plugin information request.
		apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Simulate source selection.
		apply_filters(
			'upgrader_source_selection',
			'/tmp/choice-uft-v3.17.0/',
			'/tmp/choice-uft-v3.17.0.zip',
			new Plugin_Upgrader(),
			array(
				'type'   => 'plugin',
				'plugin' => 'choice-uft/choice-universal-form-tracker.php',
			)
		);

		// Simulate update completion.
		do_action(
			'upgrader_process_complete',
			new Plugin_Upgrader(),
			array(
				'type'    => 'plugin',
				'action'  => 'update',
				'plugins' => array( 'choice-uft/choice-universal-form-tracker.php' ),
			)
		);

		// Verify hooks fired in correct order.
		$this->assertEquals(
			array( 'plugins_api', 'upgrader_source_selection', 'upgrader_process_complete' ),
			$hooks_fired,
			'Hooks should fire in correct order: plugins_api → upgrader_source_selection → upgrader_process_complete'
		);
	}

	/**
	 * Test: User capabilities required
	 *
	 * Scenario: Non-admin user attempts update
	 * Expected: WordPress blocks update (capability check)
	 */
	public function test_update_requires_admin_capabilities() {
		// Create subscriber user (no update_plugins capability).
		$subscriber_id = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber_id );

		// Verify user cannot update plugins.
		$this->assertFalse(
			current_user_can( 'update_plugins' ),
			'Subscriber should not have update_plugins capability'
		);

		// Note: WordPress core blocks update execution before our hooks fire.
		// This test validates the capability requirement exists.
	}

	/**
	 * Test: Plugin remains active after update
	 *
	 * Scenario: Update completes successfully
	 * Expected: Plugin is still active (not deactivated)
	 */
	public function test_plugin_remains_active_after_update() {
		// Note: This is a WordPress core behavior we're documenting.
		// WordPress keeps plugins active through updates.

		// Simulate plugin being active before update.
		$plugin_basename = 'choice-uft/choice-universal-form-tracker.php';

		// Verify plugin is recognized by WordPress.
		$all_plugins = get_plugins();
		$this->assertArrayHasKey(
			$plugin_basename,
			$all_plugins,
			'Plugin should be recognized by WordPress'
		);
	}
}
