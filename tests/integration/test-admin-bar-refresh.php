<?php
/**
 * Integration Test: Admin Bar Refresh
 *
 * Tests admin bar refresh functionality without page reload
 * Implements T021 from specs/007-fix-update-system/tasks.md
 * Validates Scenario 2 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Admin_Bar_Refresh extends WP_UnitTestCase {

    /**
     * Admin user ID for testing
     * @var int
     */
    private $admin_id;

    /**
     * Admin bar instance
     * @var CUFT_Admin_Bar
     */
    private $admin_bar;

    /**
     * AJAX handler instance
     * @var CUFT_Updater_Ajax
     */
    private $ajax_handler;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create admin user
        $this->admin_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        wp_set_current_user($this->admin_id);

        // Initialize admin bar
        if (!class_exists('CUFT_Admin_Bar')) {
            require_once CUFT_PLUGIN_DIR . 'includes/admin/class-cuft-admin-bar.php';
        }
        $this->admin_bar = new CUFT_Admin_Bar();

        // Initialize AJAX handler
        if (!class_exists('CUFT_Updater_Ajax')) {
            require_once CUFT_PLUGIN_DIR . 'includes/ajax/class-cuft-updater-ajax.php';
        }
        $this->ajax_handler = new CUFT_Updater_Ajax();

        // Clear any existing update transients
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_progress');
        delete_site_transient('cuft_update_completed');
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();

        // Clear transients
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_progress');
        delete_site_transient('cuft_update_completed');

        // Reset user
        wp_set_current_user(0);
    }

    /**
     * Test admin bar shows update indicator when update available
     *
     * Validates Scenario 2 part 1: Update available indicator
     */
    public function test_admin_bar_shows_update_indicator() {
        // Simulate update available state
        $update_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql'),
            'download_url' => 'https://github.com/ChoiceOMG/choice-uft/releases/download/v3.16.3/choice-uft-v3.16.3.zip'
        );
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        // Build admin bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        // Get nodes
        $nodes = $wp_admin_bar->get_nodes();

        // Assert main node exists
        $this->assertArrayHasKey('cuft-updates', $nodes, 'Admin bar should have CUFT updates node');

        // Check node properties
        $node = $nodes['cuft-updates'];
        $this->assertStringContainsString('Update Available', $node->title, 'Title should indicate update available');
        $this->assertStringContainsString('cuft-update-available', $node->meta['class'], 'Should have update-available CSS class');
    }

    /**
     * Test admin bar reflects current version after update
     *
     * Validates Scenario 2 part 2: Post-update status reflection
     */
    public function test_admin_bar_reflects_current_version_after_update() {
        // Simulate just-completed update
        $completion_data = array(
            'timestamp' => time(),
            'version' => '3.16.3',
            'message' => 'Plugin updated successfully to version 3.16.3'
        );
        set_site_transient('cuft_update_completed', $completion_data, 5 * MINUTE_IN_SECONDS);

        // Set status as up-to-date
        $update_status = array(
            'update_available' => false,
            'current_version' => '3.16.3',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        // Build admin bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        // Get nodes
        $nodes = $wp_admin_bar->get_nodes();

        // Assert node shows up-to-date
        $node = $nodes['cuft-updates'];
        $this->assertStringContainsString('Up to Date', $node->title, 'Title should show up to date');
        $this->assertStringNotContainsString('cuft-update-available', $node->meta['class'], 'Should not have update-available CSS class');
        $this->assertStringContainsString('3.16.3', $node->title, 'Should show current version');
    }

    /**
     * Test periodic polling triggers status update
     *
     * Validates admin bar JavaScript polling implementation
     */
    public function test_periodic_polling_updates_status() {
        // Initial state: update available
        $initial_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        );
        set_site_transient('cuft_update_status', $initial_status, HOUR_IN_SECONDS);

        // Simulate AJAX status check request
        $_POST['action'] = 'cuft_update_status';
        $_POST['nonce'] = wp_create_nonce('cuft_updater_nonce');

        // Capture AJAX response
        ob_start();
        $this->ajax_handler->handle_update_status();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Verify response structure
        $this->assertTrue($data['success'], 'Status check should succeed');
        $this->assertArrayHasKey('update_available', $data['data'], 'Should include update availability');
        $this->assertArrayHasKey('current_version', $data['data'], 'Should include current version');
        $this->assertArrayHasKey('latest_version', $data['data'], 'Should include latest version');
        $this->assertEquals('3.16.2', $data['data']['current_version'], 'Should return correct current version');
        $this->assertEquals('3.16.3', $data['data']['latest_version'], 'Should return correct latest version');
    }

    /**
     * Test badge creation when update available
     *
     * Validates badge appears/disappears correctly
     */
    public function test_badge_creation_for_update() {
        // Set update available
        $update_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        // Build admin bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        // Get node HTML
        $node = $wp_admin_bar->get_node('cuft-updates');

        // Badge should be included in title when update available
        $this->assertStringContainsString('cuft-update-badge', $node->title, 'Badge element should be in title when update available');

        // Now simulate up-to-date state
        $update_status['update_available'] = false;
        $update_status['current_version'] = '3.16.3';
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        // Rebuild admin bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        // Get node again
        $node = $wp_admin_bar->get_node('cuft-updates');

        // Badge should not be present
        $this->assertStringNotContainsString('cuft-update-badge', $node->title, 'Badge should not be present when up to date');
    }

    /**
     * Test DOM updates without page reload
     *
     * Simulates JavaScript DOM manipulation
     */
    public function test_dom_updates_without_reload() {
        // This test validates the JavaScript contract for DOM updates
        // In actual implementation, this would be tested with Selenium or similar

        // Verify JavaScript is enqueued
        $this->admin_bar->enqueue_admin_bar_scripts();
        $scripts = wp_scripts();
        $this->assertContains('cuft-admin-bar', $scripts->queue, 'Admin bar script should be enqueued');

        // Verify localized data includes necessary parameters
        $data = $scripts->get_data('cuft-admin-bar', 'data');
        $this->assertStringContainsString('cuftAdminBar', $data, 'Should have localized object');
        $this->assertStringContainsString('ajaxUrl', $data, 'Should include AJAX URL');
        $this->assertStringContainsString('nonce', $data, 'Should include nonce');

        // Verify CSS for badge styling is included
        $styles = wp_styles();
        $inline_style = $styles->get_data('cuft-admin-bar-css', 'after');
        if (is_array($inline_style)) {
            $inline_style = implode(' ', $inline_style);
        }
        $this->assertStringContainsString('.cuft-update-badge', $inline_style, 'Badge CSS should be included');
        $this->assertStringContainsString('transition', $inline_style, 'Should have transition for smooth appearance');
    }

    /**
     * Test performance of DOM updates
     *
     * Validates performance target: <100ms for DOM updates
     */
    public function test_dom_update_performance() {
        // Measure time for status check
        $start_time = microtime(true);

        // Set up status
        $update_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        // Build admin bar (simulates DOM generation)
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        $elapsed_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        // Assert performance requirement
        $this->assertLessThan(100, $elapsed_time, 'DOM update should complete in less than 100ms');
    }

    /**
     * Test error handling with exponential backoff
     *
     * Validates graceful error handling in polling
     */
    public function test_error_handling_with_backoff() {
        // Simulate invalid nonce (error condition)
        $_POST['action'] = 'cuft_update_status';
        $_POST['nonce'] = 'invalid_nonce';

        // Capture error response
        ob_start();
        $this->ajax_handler->handle_update_status();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Should return error
        $this->assertFalse($data['success'], 'Should fail with invalid nonce');
        $this->assertEquals('invalid_nonce', $data['data']['code'], 'Should return invalid_nonce error code');

        // JavaScript implementation would handle exponential backoff
        // This is validated through the contract that errors don't break the UI
    }

    /**
     * Test polling only when tab is active
     *
     * Validates visibility state checking
     */
    public function test_polling_respects_tab_visibility() {
        // This validates the JavaScript contract for visibility checking
        // The actual implementation uses document.visibilityState

        // Verify the JavaScript includes visibility checking
        $script_path = CUFT_PLUGIN_DIR . 'assets/admin/js/cuft-admin-bar.js';
        $this->assertFileExists($script_path, 'Admin bar script should exist');

        $script_content = file_get_contents($script_path);
        $this->assertStringContainsString('visibilityState', $script_content, 'Script should check visibility state');
        $this->assertStringContainsString('visible', $script_content, 'Script should check for visible state');
    }

    /**
     * Test integration with quickstart Scenario 2
     *
     * Full scenario validation from quickstart.md
     */
    public function test_quickstart_scenario_2() {
        // Scenario 2: Admin Bar Refresh After Update
        // Given: Admin has successfully updated plugin
        $completion_data = array(
            'timestamp' => time(),
            'version' => '3.16.3',
            'message' => 'Plugin updated successfully to version 3.16.3'
        );
        set_site_transient('cuft_update_completed', $completion_data, 5 * MINUTE_IN_SECONDS);

        // When: Viewing admin bar
        $wp_admin_bar = new WP_Admin_Bar();
        $this->admin_bar->add_admin_bar_menu($wp_admin_bar);

        // Then: "CUFT Update" indicator reflects current version immediately
        $node = $wp_admin_bar->get_node('cuft-updates');
        $this->assertNotNull($node, 'Admin bar node should exist');
        $this->assertStringContainsString('3.16.3', $node->title, 'Should show updated version');
        $this->assertStringContainsString('Up to Date', $node->title, 'Should indicate up to date status');

        // And: No page refresh required (validated by JavaScript enqueuing)
        $scripts = wp_scripts();
        $this->assertContains('cuft-admin-bar', $scripts->queue, 'JavaScript should be enqueued for dynamic updates');
    }
}