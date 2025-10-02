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
        add_action('admin_head', array($this, 'inject_gtm_script'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_test_mode_assets'));
    }

    /**
     * Add menu page under Settings
     *
     * @return void
     */
    public function add_menu_page() {
        add_submenu_page(
            'options-general.php',
            __('CUFT Testing Dashboard', 'choice-uft'),
            __('Testing Dashboard', 'choice-uft'),
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
            wp_die(__('Access Denied: You do not have sufficient permissions to access this page.', 'choice-uft'));
        }

        // Include the view template
        include CUFT_PATH . 'includes/admin/views/testing-dashboard.php';
    }

    /**
     * Inject GTM script in admin head for testing dashboard
     *
     * @return void
     */
    public function inject_gtm_script() {
        // Only inject on our testing dashboard page
        if (!isset($_GET['page']) || $_GET['page'] !== $this->page_slug) {
            return;
        }

        $gtm_id = get_option('cuft_gtm_id');

        // Skip if no GTM ID configured
        if (empty($gtm_id) || !preg_match('/^GTM-[A-Z0-9]+$/', $gtm_id)) {
            ?>
            <!-- GTM Not Configured - Please set GTM ID in CUFT Settings -->
            <script>
            // Initialize empty dataLayer for testing
            window.dataLayer = window.dataLayer || [];
            console.log('CUFT Testing Dashboard: GTM not configured. Please set GTM ID in plugin settings.');
            </script>
            <?php
            return;
        }

        // Check for server-side GTM settings
        $sgtm_enabled = get_option('cuft_sgtm_enabled', false);
        $sgtm_url = get_option('cuft_sgtm_url', '');
        $sgtm_validated = get_option('cuft_sgtm_validated', false);

        // Determine GTM URL
        $gtm_base_url = 'https://www.googletagmanager.com';
        if ($sgtm_enabled && $sgtm_url && $sgtm_validated) {
            $gtm_base_url = rtrim($sgtm_url, '/');
        }

        ?>
        <!-- Google Tag Manager for Testing Dashboard -->
        <script>
        // Initialize dataLayer
        window.dataLayer = window.dataLayer || [];

        // GTM script
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '<?php echo esc_js($gtm_base_url); ?>/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');

        // Add testing dashboard marker
        window.dataLayer.push({
            'event': 'testing_dashboard_loaded',
            'page_type': 'admin_testing_dashboard',
            'test_mode': true,
            'gtm_id': '<?php echo esc_js($gtm_id); ?>'
        });

        console.log('CUFT Testing Dashboard: GTM initialized with ID <?php echo esc_js($gtm_id); ?>');
        </script>
        <!-- End Google Tag Manager for Testing Dashboard -->
        <?php
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

        // Enqueue styles
        wp_enqueue_style(
            'cuft-testing-dashboard',
            CUFT_URL . '/assets/admin/cuft-testing-dashboard.css',
            array(),
            CUFT_VERSION
        );

        // Form Builder styles
        wp_enqueue_style(
            'cuft-form-builder',
            CUFT_URL . '/assets/admin/css/cuft-form-builder.css',
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

        // Iframe Bridge (for postMessage communication)
        wp_enqueue_script(
            'cuft-iframe-bridge',
            CUFT_URL . '/assets/admin/js/cuft-iframe-bridge.js',
            array(),
            CUFT_VERSION,
            true
        );

        // Form Builder (depends on bridge)
        wp_enqueue_script(
            'cuft-form-builder',
            CUFT_URL . '/assets/admin/js/cuft-form-builder.js',
            array('cuft-iframe-bridge'),
            CUFT_VERSION,
            true
        );

        // Main dashboard controller (depends on modules above)
        wp_enqueue_script(
            'cuft-testing-dashboard',
            CUFT_URL . '/assets/admin/cuft-testing-dashboard.js',
            array('cuft-test-data-manager', 'cuft-datalayer-monitor', 'cuft-event-validator', 'cuft-ajax-client', 'cuft-form-builder'),
            CUFT_VERSION . '.1', // Force cache refresh
            true
        );

        // Localize script with AJAX data
        wp_localize_script('cuft-testing-dashboard', 'cuftConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cuft-testing-dashboard'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'version' => CUFT_VERSION
        ));

        // Localize form builder script
        wp_localize_script('cuft-form-builder', 'cuftFormBuilder', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cuft_form_builder_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }

    /**
     * Enqueue test mode assets for iframe pages
     *
     * @return void
     */
    public function enqueue_test_mode_assets() {
        // Only enqueue if test_mode parameter is set
        if (!isset($_GET['test_mode']) || $_GET['test_mode'] !== '1') {
            return;
        }

        // Only for admin users
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue test mode script
        wp_enqueue_script(
            'cuft-test-mode',
            CUFT_URL . '/assets/admin/js/cuft-test-mode.js',
            array(),
            CUFT_VERSION,
            true
        );

        // Localize with configuration
        wp_localize_script('cuft-test-mode', 'cuftTestMode', array(
            'enabled' => true,
            'nonce' => wp_create_nonce('cuft_test_mode_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ));
    }
}