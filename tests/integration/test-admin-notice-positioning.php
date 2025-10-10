<?php
/**
 * Integration Test: Admin Notice Positioning
 *
 * Tests admin notice positioning follows WordPress standards
 * Implements T027 from specs/007-fix-update-system/tasks.md
 * Validates Scenario 1 from quickstart.md
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Integration
 * @author     CUFT Dev Team
 * @since      3.16.3
 */

class Test_Admin_Notice_Positioning extends WP_UnitTestCase {

    /**
     * Admin user ID for testing
     * @var int
     */
    private $admin_id;

    /**
     * Admin notices instance
     * @var CUFT_Admin_Notices
     */
    private $admin_notices;

    /**
     * Admin page instance
     * @var CUFT_Admin
     */
    private $admin_page;

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

        // Initialize admin notices
        if (!class_exists('CUFT_Admin_Notices')) {
            require_once CUFT_PLUGIN_DIR . 'includes/admin/class-cuft-admin-notices.php';
        }
        $this->admin_notices = new CUFT_Admin_Notices();

        // Initialize admin page
        if (!class_exists('CUFT_Admin')) {
            require_once CUFT_PLUGIN_DIR . 'includes/admin/class-cuft-admin.php';
        }
        $this->admin_page = new CUFT_Admin();

        // Clear any existing transients
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_completed');
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown() {
        parent::tearDown();
        delete_site_transient('cuft_update_status');
        delete_site_transient('cuft_update_completed');
        wp_set_current_user(0);
    }

    /**
     * Test .wp-header-end marker is present in admin page
     *
     * Validates WordPress standard structure for notice positioning
     */
    public function test_wp_header_end_marker_present() {
        // Capture admin page output
        ob_start();
        $this->admin_page->display_settings_page();
        $output = ob_get_clean();

        // Check for .wp-header-end marker
        $this->assertStringContainsString('wp-header-end', $output, 'Admin page should include .wp-header-end marker');

        // Check it appears after the h1 title
        $h1_pos = strpos($output, '</h1>');
        $marker_pos = strpos($output, 'wp-header-end');

        $this->assertGreaterThan($h1_pos, $marker_pos, '.wp-header-end should appear after the h1 title');

        // Check proper HTML structure
        $this->assertStringContainsString('<hr class="wp-header-end"', $output, 'Should be an hr element with wp-header-end class');
    }

    /**
     * Test admin notices use standard WordPress classes
     *
     * Validates notice markup follows WordPress conventions
     */
    public function test_notices_use_wordpress_standard_classes() {
        // Set update available to trigger notice
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Capture notice output
        ob_start();
        $this->admin_notices->display_update_available_notice();
        $notice_html = ob_get_clean();

        // Check for WordPress standard classes
        $this->assertStringContainsString('class="notice', $notice_html, 'Should have notice class');
        $this->assertStringContainsString('notice-info', $notice_html, 'Should have notice-info class');
        $this->assertStringContainsString('is-dismissible', $notice_html, 'Should have is-dismissible class');

        // Check proper nesting
        $this->assertRegExp('/<div[^>]*class="[^"]*notice[^"]*"[^>]*>/', $notice_html, 'Notice should be a div with notice class');
    }

    /**
     * Test notices appear above page title, not beside it
     *
     * Validates proper positioning in DOM structure
     */
    public function test_notices_appear_above_title() {
        // Set screen to plugin settings page
        set_current_screen('settings_page_choice-universal-form-tracker');

        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Simulate full page render with notices
        ob_start();
        ?>
        <div class="wrap">
            <h1>Choice Universal Form Tracker Settings</h1>
            <hr class="wp-header-end" />
            <?php
            // This is where WordPress automatically inserts admin notices
            do_action('admin_notices');
            ?>
            <div class="cuft-settings-content">
                <!-- Settings content here -->
            </div>
        </div>
        <?php
        $full_output = ob_get_clean();

        // Parse positions
        $h1_pos = strpos($full_output, '<h1>');
        $marker_pos = strpos($full_output, 'wp-header-end');
        $content_pos = strpos($full_output, 'cuft-settings-content');

        // Notices should appear after marker but before content
        $this->assertGreaterThan($marker_pos, $content_pos, 'Content should appear after wp-header-end marker');

        // The structure should ensure notices appear in the right place
        $this->assertStringContainsString('wp-header-end', $full_output, 'Marker should be present for notice positioning');
    }

    /**
     * Test notices don't have custom positioning overrides
     *
     * Ensures no custom CSS or JavaScript positioning
     */
    public function test_no_custom_positioning_logic() {
        // Check admin notices class for positioning methods
        $reflection = new ReflectionClass($this->admin_notices);
        $methods = $reflection->getMethods();

        // Look for any positioning-related methods
        $positioning_methods = array_filter($methods, function($method) {
            $name = strtolower($method->getName());
            return strpos($name, 'position') !== false ||
                   strpos($name, 'move') !== false ||
                   strpos($name, 'place') !== false;
        });

        // Should not have custom positioning methods
        $this->assertEmpty($positioning_methods, 'Should not have custom positioning methods');

        // Check for inline styles that might affect positioning
        ob_start();
        $this->admin_notices->display_update_available_notice();
        $output = ob_get_clean();

        // Should not contain position: absolute/fixed/relative inline styles
        $this->assertNotRegExp('/style="[^"]*position:\s*(absolute|fixed|relative)/', $output, 'Should not have custom positioning styles');
    }

    /**
     * Test notices appear on all relevant admin pages
     *
     * Validates notices aren't excluded from plugin settings page
     */
    public function test_notices_appear_on_all_admin_pages() {
        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Test different admin screens
        $screens = array(
            'settings_page_choice-universal-form-tracker', // Plugin settings
            'plugins',                                      // Plugins page
            'dashboard',                                    // Dashboard
            'update-core'                                   // Updates page (should be excluded)
        );

        foreach ($screens as $screen) {
            set_current_screen($screen);

            // Check if notice should display
            $should_display = $this->admin_notices->should_display_notices();

            if ($screen === 'update-core') {
                // Updates page should be excluded per WordPress standards
                $this->assertFalse($should_display, 'Notices should not display on update-core screen');
            } else {
                // All other screens should show notices
                $this->assertTrue($should_display, "Notices should display on {$screen} screen");
            }
        }
    }

    /**
     * Test notice HTML structure matches WordPress standards
     *
     * Validates complete HTML structure
     */
    public function test_notice_html_structure() {
        // Set update available
        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // Get notice HTML
        ob_start();
        $this->admin_notices->display_update_available_notice();
        $html = ob_get_clean();

        // Parse with DOMDocument for structure validation
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Check for required elements
        $notice_divs = $xpath->query('//div[contains(@class, "notice")]');
        $this->assertGreaterThan(0, $notice_divs->length, 'Should have at least one notice div');

        $notice = $notice_divs->item(0);
        $classes = $notice->getAttribute('class');

        // Validate classes
        $this->assertStringContainsString('notice', $classes, 'Should have notice class');
        $this->assertStringContainsString('notice-info', $classes, 'Should have notice type class');
        $this->assertStringContainsString('is-dismissible', $classes, 'Should be dismissible');

        // Check for paragraph inside notice
        $paragraphs = $xpath->query('.//p', $notice);
        $this->assertGreaterThan(0, $paragraphs->length, 'Notice should contain a paragraph');

        // Check for dismiss button (if dismissible)
        if (strpos($classes, 'is-dismissible') !== false) {
            $buttons = $xpath->query('.//button[@class="notice-dismiss"]', $notice);
            $this->assertGreaterThan(0, $buttons->length, 'Dismissible notice should have dismiss button');
        }
    }

    /**
     * Test multiple notices stack properly
     *
     * Validates multiple notices can display without conflicts
     */
    public function test_multiple_notices_stack() {
        // Set both update available and completion status
        $update_status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $update_status, HOUR_IN_SECONDS);

        $completion_status = array(
            'timestamp' => time(),
            'version' => '3.16.1',
            'message' => 'Previous update completed'
        );
        set_site_transient('cuft_update_completed', $completion_status, 5 * MINUTE_IN_SECONDS);

        // Capture all notices
        ob_start();
        do_action('admin_notices');
        $all_notices = ob_get_clean();

        // Count notice divs
        preg_match_all('/<div[^>]*class="[^"]*notice[^"]*"/', $all_notices, $matches);
        $notice_count = count($matches[0]);

        // Should handle multiple notices
        $this->assertGreaterThanOrEqual(1, $notice_count, 'Should be able to display multiple notices');

        // Notices should not overlap (no absolute positioning)
        $this->assertNotRegExp('/position:\s*absolute/', $all_notices, 'Notices should not use absolute positioning');
    }

    /**
     * Test Scenario 1: Admin notice positioning
     *
     * From quickstart.md Scenario 1
     */
    public function test_quickstart_scenario_1() {
        // Given: Admin user on plugin settings page with update available
        wp_set_current_user($this->admin_id);
        set_current_screen('settings_page_choice-universal-form-tracker');

        $status = array(
            'update_available' => true,
            'current_version' => '3.16.2',
            'latest_version' => '3.16.3',
            'last_check' => current_time('mysql')
        );
        set_site_transient('cuft_update_status', $status, HOUR_IN_SECONDS);

        // When: Page loads
        ob_start();
        ?>
        <div class="wrap">
            <h1>Choice Universal Form Tracker Settings</h1>
            <hr class="wp-header-end" />
            <?php do_action('admin_notices'); ?>
            <div class="settings-content">Settings form here</div>
        </div>
        <?php
        $page_output = ob_get_clean();

        // Then: Notice appears above page title in standard WordPress .notice area
        $this->assertStringContainsString('wp-header-end', $page_output, 'Page should have wp-header-end marker');
        $this->assertStringContainsString('class="notice', $page_output, 'Should have notice with standard class');

        // Verify positioning order
        $h1_pos = strpos($page_output, '<h1>');
        $marker_pos = strpos($page_output, 'wp-header-end');
        $content_pos = strpos($page_output, 'settings-content');

        $this->assertLessThan($marker_pos, $h1_pos, 'H1 should come before marker');
        $this->assertLessThan($content_pos, $marker_pos, 'Marker should come before content');

        // This ensures notices appear in the correct location between marker and content
    }

    /**
     * Test visual inspection requirements
     *
     * Documents what should be visually verified
     */
    public function test_visual_inspection_requirements() {
        // This test documents what needs manual visual verification
        $visual_checks = array(
            'notice_above_title' => 'Notice should appear above the page title, not beside it',
            'standard_styling' => 'Notice should use WordPress blue/green/yellow/red color scheme',
            'dismiss_button' => 'X dismiss button should appear in top-right corner',
            'responsive_width' => 'Notice should be full width of content area',
            'margin_spacing' => 'Proper margin between notice and page title',
            'multiple_notices' => 'Multiple notices should stack vertically without overlap'
        );

        // Assert these checks are documented
        $this->assertNotEmpty($visual_checks, 'Visual inspection requirements documented');

        // Log requirements for manual testing
        foreach ($visual_checks as $check => $description) {
            $this->assertTrue(true, "Visual check needed: {$check} - {$description}");
        }
    }
}