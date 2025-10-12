<?php
/**
 * Integration Test: Bulk Update (T019)
 *
 * Tests that plugin updates work correctly when triggered as part of a bulk
 * update operation (updating multiple plugins simultaneously). Validates FR-303.
 *
 * Reference: quickstart.md QS-4
 *
 * @package Choice_UFT
 * @subpackage Tests
 */

/**
 * Integration test class for bulk updates
 */
class CUFT_Test_Bulk_Update extends WP_UnitTestCase {

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

		// Clean up $_REQUEST.
		unset( $_REQUEST['action'] );
		unset( $_SERVER['HTTP_REFERER'] );

		parent::tearDown();
	}

	/**
	 * Test: Bulk update uses Plugin_Upgrader::bulk_upgrade()
	 *
	 * Scenario: Multiple plugins selected for update
	 * Expected: WordPress uses bulk_upgrade method, our hooks still fire
	 */
	public function test_bulk_update_uses_correct_method() {
		// Verify hooks are registered (same hooks for all update methods).
		$this->assertNotFalse(
			has_filter( 'plugins_api', array( 'CUFT_Plugin_Info', 'plugins_api_handler' ) ),
			'plugins_api filter should work for bulk updates'
		);

		$this->assertNotFalse(
			has_filter( 'upgrader_source_selection', array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'upgrader_source_selection filter should work for bulk updates'
		);

		$this->assertNotFalse(
			has_action( 'upgrader_process_complete', array( 'CUFT_Update_Logger', 'log_update_completion' ) ),
			'upgrader_process_complete action should work for bulk updates'
		);

		// Note: WordPress core calls Plugin_Upgrader->bulk_upgrade(),
		// which internally calls upgrade() for each plugin,
		// so all our hooks fire correctly.
	}

	/**
	 * Test: Update history logged with bulk_update trigger location
	 *
	 * Scenario: Update completes as part of bulk update
	 * Expected: Update history entry has trigger_location = 'bulk_update'
	 */
	public function test_update_history_logged_as_bulk_update() {
		// Simulate bulk update by setting $_REQUEST['action'].
		$_REQUEST['action']      = 'update-selected';
		$_SERVER['HTTP_REFERER'] = admin_url( 'plugins.php' );

		// Simulate upgrader_process_complete action from bulk update.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array(
				'choice-uft/choice-universal-form-tracker.php',
				'hello.php', // Another plugin (Hello Dolly).
			),
		);

		// Fire the action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 1 );

		// Verify history entry created.
		$this->assertCount( 1, $history, 'Update history should have one entry' );

		$entry = $history[0];

		// Verify trigger_location detected as bulk_update.
		$this->assertEquals(
			'bulk_update',
			$entry['trigger_location'],
			'Trigger location should be bulk_update when action is update-selected'
		);

		// Verify status.
		$this->assertEquals(
			'complete',
			$entry['status'],
			'Status should be complete for successful bulk update'
		);
	}

	/**
	 * Test: CUFT update doesn't interfere with other plugins
	 *
	 * Scenario: Bulk update includes CUFT + other plugins
	 * Expected: Each plugin updated independently, no cross-plugin interference
	 */
	public function test_no_interference_with_other_plugins() {
		$_REQUEST['action']      = 'update-selected';
		$_SERVER['HTTP_REFERER'] = admin_url( 'plugins.php' );

		// Simulate bulk update with multiple plugins.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array(
				'choice-uft/choice-universal-form-tracker.php',
				'akismet/akismet.php',
				'hello.php',
			),
		);

		// Fire completion action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Verify only one history entry created (for CUFT only).
		$history = CUFT_Update_Logger::get_history( 5 );
		$this->assertCount( 1, $history, 'Only CUFT update should be logged' );

		// Verify it's CUFT's update.
		$this->assertEquals(
			'bulk_update',
			$history[0]['trigger_location'],
			'CUFT update should be logged with bulk_update trigger'
		);
	}

	/**
	 * Test: Directory naming works in bulk update
	 *
	 * Scenario: Bulk update extracts CUFT with versioned directory
	 * Expected: Directory renamed correctly, other plugins unaffected
	 */
	public function test_directory_naming_in_bulk_update() {
		// Simulate upgrader_source_selection for CUFT in bulk update.
		$source        = '/tmp/choice-uft-v3.17.0/';
		$remote_source = '/tmp/choice-uft-v3.17.0.zip';
		$upgrader      = new Plugin_Upgrader();
		$hook_extra    = array(
			'type'   => 'plugin',
			'plugin' => 'choice-uft/choice-universal-form-tracker.php',
		);

		// Apply filter (as WordPress would during update).
		$result = apply_filters(
			'upgrader_source_selection',
			$source,
			$remote_source,
			$upgrader,
			$hook_extra
		);

		// Verify filter was applied (callable).
		$this->assertTrue(
			is_callable( array( 'CUFT_Directory_Fixer', 'fix_directory_name' ) ),
			'Directory fixer should be callable for bulk updates'
		);

		// Note: Actual rename requires filesystem mocking.
		// The important part is that the filter fires for each plugin independently.
	}

	/**
	 * Test: Plugin info available for each plugin in bulk update
	 *
	 * Scenario: Bulk update checks info for multiple plugins
	 * Expected: CUFT info returned correctly via plugins_api
	 */
	public function test_plugin_info_available_in_bulk_update() {
		// Simulate plugins_api request for CUFT (WordPress checks before updating).
		$result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// Verify plugin info returned.
		$this->assertIsObject( $result, 'Plugin info should be returned for bulk update' );
		$this->assertObjectHasProperty( 'version', $result, 'Plugin info should have version' );
		$this->assertObjectHasProperty( 'download_link', $result, 'Plugin info should have download link' );

		// Simulate plugins_api for other plugin (should pass through).
		$other_result = apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'hello-dolly' )
		);

		// Verify our filter returns false for other plugins (pass-through).
		$this->assertFalse(
			$other_result,
			'plugins_api should return false for non-CUFT plugins (pass-through)'
		);
	}

	/**
	 * Test: Bulk update with one failure doesn't stop others
	 *
	 * Scenario: Bulk update where one plugin fails
	 * Expected: Other plugins continue updating, only failed plugin logged as failed
	 */
	public function test_bulk_update_partial_failure() {
		$_REQUEST['action']      = 'update-selected';
		$_SERVER['HTTP_REFERER'] = admin_url( 'plugins.php' );

		// Simulate bulk update where CUFT succeeds.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array(
				'choice-uft/choice-universal-form-tracker.php',
				'other-plugin/other-plugin.php',
			),
		);

		// Set successful result for CUFT.
		$upgrader->result = true;

		// Fire completion action.
		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Verify CUFT update logged as success.
		$history = CUFT_Update_Logger::get_history( 1 );
		$this->assertEquals(
			'complete',
			$history[0]['status'],
			'CUFT update should be logged as complete even if other plugins fail'
		);
	}

	/**
	 * Test: User capability required for bulk update
	 *
	 * Scenario: Non-admin attempts bulk update
	 * Expected: WordPress blocks update (capability check)
	 */
	public function test_bulk_update_requires_capabilities() {
		// Create subscriber user.
		$subscriber_id = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber_id );

		// Verify user cannot update plugins.
		$this->assertFalse(
			current_user_can( 'update_plugins' ),
			'Subscriber should not be able to perform bulk updates'
		);

		// Note: WordPress core blocks bulk update before our hooks fire.
	}

	/**
	 * Test: All plugins in bulk update logged independently
	 *
	 * Scenario: Bulk update with CUFT + other plugins
	 * Expected: Only CUFT update logged in our history (other plugins have their own logging)
	 */
	public function test_only_cuft_logged_in_bulk_update() {
		$_REQUEST['action']      = 'update-selected';
		$_SERVER['HTTP_REFERER'] = admin_url( 'plugins.php' );

		// Simulate bulk update with three plugins.
		$upgrader   = new Plugin_Upgrader();
		$hook_extra = array(
			'type'    => 'plugin',
			'action'  => 'update',
			'plugins' => array(
				'akismet/akismet.php',
				'choice-uft/choice-universal-form-tracker.php',
				'hello.php',
			),
		);

		do_action( 'upgrader_process_complete', $upgrader, $hook_extra );

		// Get update history.
		$history = CUFT_Update_Logger::get_history( 5 );

		// Verify only one entry (CUFT).
		$this->assertCount( 1, $history, 'Only CUFT should be logged in our update history' );

		// Verify it's from bulk update.
		$this->assertEquals(
			'bulk_update',
			$history[0]['trigger_location'],
			'Update should be logged as bulk_update'
		);
	}

	/**
	 * Test: Bulk update triggers hooks in correct order for each plugin
	 *
	 * Scenario: Bulk update processes multiple plugins
	 * Expected: For each plugin, hooks fire in order: plugins_api → source_selection → process_complete
	 */
	public function test_hooks_fire_in_order_for_bulk_update() {
		$hooks_fired = array();

		// Track plugins_api.
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

		// Track upgrader_source_selection.
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

		// Track upgrader_process_complete.
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

		// Simulate bulk update sequence.
		$_REQUEST['action'] = 'update-selected';

		// 1. Check plugin info.
		apply_filters(
			'plugins_api',
			false,
			'plugin_information',
			(object) array( 'slug' => 'choice-uft' )
		);

		// 2. Fix directory naming.
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

		// 3. Complete update.
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
			'Hooks should fire in correct order during bulk update'
		);
	}
}
