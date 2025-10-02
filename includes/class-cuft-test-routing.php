<?php
/**
 * CUFT Test Form Routing
 *
 * Handles routing and URL management for test forms.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Form Routing Class
 */
class CUFT_Test_Routing {

    /**
     * Singleton instance
     *
     * @var CUFT_Test_Routing
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return CUFT_Test_Routing
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'handle_test_form_request'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_test_mode_scripts'));
    }

    /**
     * Register custom rewrite rules for test forms
     *
     * @return void
     */
    public function register_rewrite_rules() {
        add_rewrite_rule(
            '^cuft-test-form/([^/]+)/?$',
            'index.php?cuft_test_form=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query vars
     *
     * @param array $vars Query vars
     * @return array
     */
    public function register_query_vars($vars) {
        $vars[] = 'cuft_test_form';
        $vars[] = 'form_id';
        $vars[] = 'test_mode';
        return $vars;
    }

    /**
     * Handle test form requests
     *
     * @return void
     */
    public function handle_test_form_request() {
        $instance_id = get_query_var('cuft_test_form');
        $form_id_param = get_query_var('form_id');

        // Check both query vars
        if (empty($instance_id) && empty($form_id_param)) {
            return;
        }

        // Use whichever is available
        $instance_id = $instance_id ?: $form_id_param;

        // Find form by instance ID
        $form = $this->get_form_by_instance_id($instance_id);

        if (!$form) {
            // Form not found, show 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Redirect to actual form URL with test_mode parameter
        $test_url = get_permalink($form['post_id']);
        if (!$test_url) {
            return;
        }

        // Add test_mode parameter
        $test_url = add_query_arg('test_mode', '1', $test_url);

        // Preserve other query parameters
        if (!empty($_GET)) {
            foreach ($_GET as $key => $value) {
                if ($key !== 'cuft_test_form' && $key !== 'form_id') {
                    $test_url = add_query_arg($key, $value, $test_url);
                }
            }
        }

        wp_redirect($test_url);
        exit;
    }

    /**
     * Get form by instance ID
     *
     * @param string $instance_id Instance ID
     * @return array|null Form data or null
     */
    private function get_form_by_instance_id($instance_id) {
        global $wpdb;

        // Query for post with matching instance_id
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_cuft_instance_id'
                AND meta_value = %s
                LIMIT 1",
                $instance_id
            )
        );

        if (!$post_id) {
            return null;
        }

        // Get post
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // Get metadata
        $framework = get_post_meta($post_id, '_cuft_framework', true);
        $template_id = get_post_meta($post_id, '_cuft_template_id', true);

        return array(
            'post_id' => $post_id,
            'instance_id' => $instance_id,
            'framework' => $framework,
            'template_id' => $template_id,
            'post' => $post,
        );
    }

    /**
     * Enqueue test mode scripts when needed
     *
     * @return void
     */
    public function enqueue_test_mode_scripts() {
        // Only enqueue if test_mode parameter is set
        if (!isset($_GET['test_mode']) || $_GET['test_mode'] !== '1') {
            return;
        }

        // Only for logged-in admins
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue test mode script (already enqueued by testing dashboard)
        // This is a fallback in case the script wasn't loaded
        if (!wp_script_is('cuft-test-mode', 'enqueued')) {
            wp_enqueue_script(
                'cuft-test-mode',
                CUFT_URL . 'assets/admin/js/cuft-test-mode.js',
                array(),
                CUFT_VERSION,
                true
            );

            wp_localize_script('cuft-test-mode', 'cuftTestMode', array(
                'enabled' => true,
                'nonce' => wp_create_nonce('cuft_test_mode_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
            ));
        }
    }

    /**
     * Generate test form URL
     *
     * @param string $instance_id Instance ID
     * @param bool $test_mode Whether to include test_mode parameter
     * @return string
     */
    public static function get_test_form_url($instance_id, $test_mode = false) {
        $url = home_url('cuft-test-form/' . $instance_id);

        if ($test_mode) {
            $url = add_query_arg('test_mode', '1', $url);
        }

        return $url;
    }

    /**
     * Flush rewrite rules (call after plugin activation)
     *
     * @return void
     */
    public static function flush_rules() {
        $instance = self::get_instance();
        $instance->register_rewrite_rules();
        flush_rewrite_rules();
    }
}

// Initialize routing
CUFT_Test_Routing::get_instance();
