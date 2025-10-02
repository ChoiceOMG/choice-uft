<?php
/**
 * Test Admin Page Access Control
 *
 * Tests for CUFT Testing Dashboard access control and security.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

class Test_Admin_Page_Access extends WP_UnitTestCase {

    /**
     * Test dashboard instance
     *
     * @var CUFT_Testing_Dashboard
     */
    private $dashboard;

    /**
     * Admin user ID
     *
     * @var int
     */
    private $admin_user_id;

    /**
     * Editor user ID
     *
     * @var int
     */
    private $editor_user_id;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create test users
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));

        $this->editor_user_id = $this->factory->user->create(array(
            'role' => 'editor'
        ));

        // Initialize dashboard
        $this->dashboard = new CUFT_Testing_Dashboard();
    }

    /**
     * Clean up after tests
     */
    public function tearDown() {
        wp_delete_user($this->admin_user_id);
        wp_delete_user($this->editor_user_id);
        parent::tearDown();
    }

    /**
     * Test that admin users can access the dashboard
     *
     * @test
     */
    public function test_admin_user_can_access_dashboard() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Check capability
        $this->assertTrue(
            current_user_can('manage_options'),
            'Admin should have manage_options capability'
        );

        // Start output buffering
        ob_start();

        // Try to render dashboard
        $this->dashboard->render_dashboard();

        // Get output
        $output = ob_get_clean();

        // Assert dashboard renders
        $this->assertContains('CUFT Testing Dashboard', $output);
        $this->assertContains('Test Data Generator', $output);
        $this->assertContains('Event Simulator', $output);
        $this->assertContains('Test Form Builder', $output);
        $this->assertContains('Event Viewer', $output);
    }

    /**
     * Test that non-admin users are denied access
     *
     * @test
     */
    public function test_non_admin_user_denied_access() {
        // Set current user as editor
        wp_set_current_user($this->editor_user_id);

        // Check capability
        $this->assertFalse(
            current_user_can('manage_options'),
            'Editor should not have manage_options capability'
        );

        // Expect wp_die to be called
        $this->setExpectedException('WPDieException');

        // Try to render dashboard (should trigger wp_die)
        $this->dashboard->render_dashboard();
    }

    /**
     * Test that anonymous users are denied access
     *
     * @test
     */
    public function test_anonymous_user_denied_access() {
        // Set no current user
        wp_set_current_user(0);

        // Check capability
        $this->assertFalse(
            current_user_can('manage_options'),
            'Anonymous user should not have manage_options capability'
        );

        // Expect wp_die to be called
        $this->setExpectedException('WPDieException');

        // Try to render dashboard (should trigger wp_die)
        $this->dashboard->render_dashboard();
    }

    /**
     * Test nonce field is present in dashboard
     *
     * @test
     */
    public function test_nonce_field_present_in_dashboard() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Start output buffering
        ob_start();

        // Render dashboard
        $this->dashboard->render_dashboard();

        // Get output
        $output = ob_get_clean();

        // Check for nonce field
        $this->assertContains('cuft_testing_nonce', $output);
        $this->assertContains('wp_nonce_field', $output);
    }

    /**
     * Test that dashboard page renders without PHP errors
     *
     * @test
     */
    public function test_dashboard_renders_without_errors() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Enable error reporting
        $error_level = error_reporting(E_ALL);

        // Start output buffering
        ob_start();

        // Track errors
        $errors = array();
        set_error_handler(function($errno, $errstr) use (&$errors) {
            $errors[] = $errstr;
            return true;
        });

        // Render dashboard
        $this->dashboard->render_dashboard();

        // Restore error handler
        restore_error_handler();
        ob_end_clean();
        error_reporting($error_level);

        // Assert no errors
        $this->assertEmpty($errors, 'Dashboard should render without PHP errors');
    }

    /**
     * Test that menu is registered correctly
     *
     * @test
     */
    public function test_menu_registration() {
        global $submenu;

        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Trigger admin_menu action
        do_action('admin_menu');

        // Check if submenu exists under Settings
        $this->assertArrayHasKey('options-general.php', $submenu);

        // Look for our menu item
        $found = false;
        foreach ($submenu['options-general.php'] as $item) {
            if (strpos($item[2], 'cuft-testing-dashboard') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Testing Dashboard menu should be registered under Settings');
    }

    /**
     * Test that scripts are enqueued only on dashboard page
     *
     * @test
     */
    public function test_scripts_enqueued_on_dashboard_page() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Simulate being on the dashboard page
        $_GET['page'] = 'cuft-testing-dashboard';
        $hook = 'settings_page_cuft-testing-dashboard';

        // Trigger enqueue action
        do_action('admin_enqueue_scripts', $hook);

        // Check if scripts are enqueued
        $this->assertTrue(wp_script_is('cuft-testing-dashboard', 'enqueued'));
        $this->assertTrue(wp_script_is('cuft-test-data-manager', 'enqueued'));
        $this->assertTrue(wp_script_is('cuft-datalayer-monitor', 'enqueued'));
        $this->assertTrue(wp_script_is('cuft-event-validator', 'enqueued'));
        $this->assertTrue(wp_script_is('cuft-ajax-client', 'enqueued'));

        // Check if styles are enqueued
        $this->assertTrue(wp_style_is('cuft-testing-dashboard', 'enqueued'));
    }

    /**
     * Test that scripts are NOT enqueued on other pages
     *
     * @test
     */
    public function test_scripts_not_enqueued_on_other_pages() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Simulate being on a different page
        $_GET['page'] = 'some-other-page';
        $hook = 'settings_page_some-other-page';

        // Reset scripts
        wp_dequeue_script('cuft-testing-dashboard');

        // Trigger enqueue action
        do_action('admin_enqueue_scripts', $hook);

        // Check scripts are NOT enqueued
        $this->assertFalse(wp_script_is('cuft-testing-dashboard', 'enqueued'));
    }
}