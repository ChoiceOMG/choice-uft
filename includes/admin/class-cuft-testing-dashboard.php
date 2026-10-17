<?php
/**
 * Admin Testing Dashboard Class
 *
 * Provides an admin-only testing dashboard for validating conversion tracking
 * features without affecting production analytics.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Testing Dashboard Class
 *
 * Manages the admin testing dashboard page, scripts, and styles.
 */
class CUFT_Testing_Dashboard {

    /**
     * Page slug
     *
     * @var string
     */
    private $page_slug = 'cuft-testing-dashboard';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Add menu page under Settings
     *
     * @return void
     */
    public function add_menu_page() {
        add_submenu_page(
            'options-general.php',
            __('CUFT Testing Dashboard', 'choice-universal-form-tracker'),
            __('Testing Dashboard', 'choice-universal-form-tracker'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_dashboard')
        );
    }

    /**
     * Render the dashboard page
     *
     * @return void
     */
    public function render_dashboard() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access Denied: You do not have sufficient permissions to access this page.', 'choice-universal-form-tracker'));
        }

        // Include the view template
        include CUFT_PATH . 'includes/admin/views/testing-dashboard.php';
    }

    /**
     * Add the GTM loader for the testing dashboard as an inline script
     *
     * Registered as a header script handle with no source so the loader still
     * runs in the admin head, as it did when it was echoed from admin_head.
     *
     * @return void
     */
    private function enqueue_gtm_script() {
        $handle = 'cuft-testing-dashboard-gtm';
        wp_register_script($handle, false, array(), CUFT_VERSION, false);
        wp_enqueue_script($handle);

        $gtm_id = CUFT_SGTM::sanitize_gtm_id((string) get_option('cuft_gtm_id', ''));

        // Skip if no GTM ID configured
        if (!$gtm_id) {
            wp_add_inline_script(
                $handle,
                "window.dataLayer = window.dataLayer || [];\n" .
                "console.log('CUFT Testing Dashboard: GTM not configured. Please set GTM ID in plugin settings.');"
            );
            return;
        }

        // Server-side GTM host once it has been validated, otherwise Google.
        $host = CUFT_SGTM::DEFAULT_HOST;
        if (get_option('cuft_sgtm_enabled', false) && get_option('cuft_sgtm_validated', false) && '' !== CUFT_SGTM::get_host()) {
            $host = CUFT_SGTM::get_host();
        }
        $src = esc_url(CUFT_SGTM::script_url($host, $gtm_id), array('https'));

        $script = 'window.dataLayer = window.dataLayer || [];' . "\n" .
            '(function(w,d,s,l){w[l]=w[l]||[];w[l].push({\'gtm.start\':' . "\n" .
            'new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],' . "\n" .
            'j=d.createElement(s);j.async=true;j.src=' . CUFT_Utils::js_encode($src) . ';f.parentNode.insertBefore(j,f);' . "\n" .
            '})(window,document,\'script\',\'dataLayer\');' . "\n" .
            'window.dataLayer.push({' .
            '\'event\': \'testing_dashboard_loaded\',' .
            '\'page_type\': \'admin_testing_dashboard\',' .
            '\'test_mode\': true,' .
            '\'gtm_id\': ' . CUFT_Utils::js_encode($gtm_id) .
            '});' . "\n" .
            'console.log(' . CUFT_Utils::js_encode('CUFT Testing Dashboard: GTM initialized with ID ' . $gtm_id) . ');';

        wp_add_inline_script($handle, $script);
    }

    /**
     * Enqueue scripts and styles for the dashboard
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_assets($hook) {
        // Only enqueue on our dashboard page
        if ($hook !== 'settings_page_' . $this->page_slug) {
            return;
        }

        // GTM loader for the dashboard (header, before the dashboard modules)
        $this->enqueue_gtm_script();

        // Enqueue styles
        wp_enqueue_style(
            'cuft-testing-dashboard',
            CUFT_URL . '/assets/admin/cuft-testing-dashboard.css',
            array(),
            CUFT_VERSION
        );

        // Enqueue JavaScript modules (dependencies first)
        wp_enqueue_script(
            'cuft-test-data-manager',
            CUFT_URL . '/assets/admin/cuft-test-data-manager.js',
            array(),
            CUFT_VERSION,
            true
        );

        wp_enqueue_script(
            'cuft-datalayer-monitor',
            CUFT_URL . '/assets/admin/cuft-datalayer-monitor.js',
            array(),
            CUFT_VERSION,
            true
        );

        wp_enqueue_script(
            'cuft-event-validator',
            CUFT_URL . '/assets/admin/cuft-event-validator.js',
            array(),
            CUFT_VERSION,
            true
        );

        wp_enqueue_script(
            'cuft-ajax-client',
            CUFT_URL . '/assets/admin/cuft-ajax-client.js',
            array(),
            CUFT_VERSION,
            true
        );

        // Event Viewer and Test Database Events panels
        wp_enqueue_script(
            'cuft-event-viewer',
            CUFT_URL . '/assets/admin/cuft-event-viewer.js',
            array('cuft-datalayer-monitor', 'cuft-event-validator'),
            CUFT_VERSION,
            true
        );

        // Main dashboard controller (depends on modules above)
        wp_enqueue_script(
            'cuft-testing-dashboard',
            CUFT_URL . '/assets/admin/cuft-testing-dashboard.js',
            array('cuft-test-data-manager', 'cuft-datalayer-monitor', 'cuft-event-validator', 'cuft-ajax-client'),
            CUFT_VERSION . '.1', // Force cache refresh
            true
        );

        // Localize script with AJAX data
        CUFT_Utils::localize('cuft-testing-dashboard', 'cuftConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cuft-testing-dashboard'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'version' => CUFT_VERSION
        ));
    }
}