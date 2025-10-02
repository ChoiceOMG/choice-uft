<?php
/**
 * Form Builder Core Class
 *
 * Manages test form generation, deletion, and retrieval across multiple form frameworks.
 *
 * @package    Choice_UTM_Form_Tracker
 * @subpackage Choice_UTM_Form_Tracker/includes/admin
 * @since      3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Form Builder Class
 *
 * Core functionality for generating and managing test forms within the testing dashboard.
 */
class CUFT_Form_Builder {

    /**
     * Singleton instance
     *
     * @var CUFT_Form_Builder
     */
    private static $instance = null;

    /**
     * Available framework adapters
     *
     * @var array
     */
    private $adapters = array();

    /**
     * Get singleton instance
     *
     * @return CUFT_Form_Builder
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
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Form lifecycle hooks
        add_action('admin_init', array($this, 'register_form_builder'));

        // Cleanup hooks
        add_action('deleted_post', array($this, 'cleanup_test_form_meta'), 10, 1);
    }

    /**
     * Register form builder functionality
     */
    public function register_form_builder() {
        // Only register for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        // Load framework adapters
        $this->load_adapters();
    }

    /**
     * Load framework adapters
     */
    private function load_adapters() {
        // Adapters will be lazy-loaded via factory pattern
        // This just registers available adapters
        $this->adapters = array(
            'elementor' => 'CUFT_Elementor_Adapter',
            'cf7'       => 'CUFT_CF7_Adapter',
            'gravity'   => 'CUFT_Gravity_Adapter',
            'ninja'     => 'CUFT_Ninja_Adapter',
            'avada'     => 'CUFT_Avada_Adapter',
        );
    }

    /**
     * Detect available form frameworks
     *
     * @return array Array of available frameworks with metadata
     */
    public function detect_frameworks() {
        $frameworks = array();

        // Elementor Pro
        if (defined('ELEMENTOR_PRO_VERSION')) {
            $frameworks['elementor'] = array(
                'name'               => 'Elementor Pro',
                'version'            => ELEMENTOR_PRO_VERSION,
                'available'          => true,
                'supports_generation' => true,
                'icon'               => 'dashicons-elementor',
            );
        }

        // Contact Form 7
        if (class_exists('WPCF7')) {
            $frameworks['cf7'] = array(
                'name'               => 'Contact Form 7',
                'version'            => defined('WPCF7_VERSION') ? WPCF7_VERSION : null,
                'available'          => true,
                'supports_generation' => true,
                'icon'               => 'dashicons-email',
            );
        }

        // Gravity Forms
        if (class_exists('GFAPI')) {
            $frameworks['gravity'] = array(
                'name'               => 'Gravity Forms',
                'version'            => class_exists('GFForms') ? GFForms::$version : null,
                'available'          => true,
                'supports_generation' => true,
                'icon'               => 'dashicons-list-view',
            );
        }

        // Ninja Forms
        if (function_exists('Ninja_Forms')) {
            $frameworks['ninja'] = array(
                'name'               => 'Ninja Forms',
                'version'            => get_option('ninja_forms_version'),
                'available'          => true,
                'supports_generation' => true,
                'icon'               => 'dashicons-forms',
            );
        }

        // Avada Forms
        if (class_exists('Fusion_Builder')) {
            $frameworks['avada'] = array(
                'name'               => 'Avada Forms',
                'version'            => defined('FUSION_BUILDER_VERSION') ? FUSION_BUILDER_VERSION : null,
                'available'          => true,
                'supports_generation' => true,
                'icon'               => 'dashicons-admin-customizer',
            );
        }

        return $frameworks;
    }

    /**
     * Create a test form
     *
     * @param string $framework Framework identifier
     * @param string $template_id Template to use
     * @return array|WP_Error Form data or error
     */
    public function create_test_form($framework, $template_id = 'basic_contact_form') {
        // Security check
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to create test forms.', 'choice-uft'));
        }

        // Validate framework
        if (!isset($this->adapters[$framework])) {
            return new WP_Error('invalid_framework', __('Invalid framework specified.', 'choice-uft'));
        }

        // Get adapter (will be implemented via factory)
        // For now, return stub response
        return new WP_Error('not_implemented', __('Form creation will be implemented via framework adapters.', 'choice-uft'));
    }

    /**
     * Delete a test form
     *
     * @param string $instance_id Test form instance ID
     * @return bool|WP_Error Success or error
     */
    public function delete_test_form($instance_id) {
        // Security check
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to delete test forms.', 'choice-uft'));
        }

        // Validate instance_id
        if (empty($instance_id)) {
            return new WP_Error('invalid_instance_id', __('Invalid instance ID.', 'choice-uft'));
        }

        // Will be implemented with adapter support
        return new WP_Error('not_implemented', __('Form deletion will be implemented via framework adapters.', 'choice-uft'));
    }

    /**
     * Get test forms
     *
     * @param array $args Query arguments
     * @return array Array of test forms
     */
    public function get_test_forms($args = array()) {
        // Security check
        if (!current_user_can('manage_options')) {
            return array();
        }

        $defaults = array(
            'status' => 'active',
        );

        $args = wp_parse_args($args, $defaults);

        // Query test forms
        $query_args = array(
            'meta_key' => '_cuft_test_form',
            'meta_value' => '1',
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $query = new WP_Query($query_args);

        $forms = array();

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $instance_id = get_post_meta($post->ID, '_cuft_instance_id', true);
                $framework = get_post_meta($post->ID, '_cuft_framework', true);
                $template_id = get_post_meta($post->ID, '_cuft_template_id', true);
                $form_id = get_post_meta($post->ID, '_cuft_form_id', true);
                $created_at = get_post_meta($post->ID, '_cuft_created_at', true);
                $test_count = get_post_meta($post->ID, '_cuft_test_count', true);

                // Get framework label
                $framework_label = $this->get_framework_label($framework);

                // Build URLs
                $base_url = get_permalink($post->ID);
                $separator = (strpos($base_url, '?') === false) ? '?' : '&';

                $forms[] = array(
                    'instance_id' => $instance_id,
                    'framework' => $framework,
                    'framework_label' => $framework_label,
                    'post_id' => $post->ID,
                    'form_id' => $form_id ?: (string) $post->ID,
                    'template_name' => 'Basic Contact Form',
                    'status' => 'active',
                    'test_url' => $base_url . $separator . 'form_id=' . $instance_id,
                    'iframe_url' => $base_url . $separator . 'form_id=' . $instance_id . '&test_mode=1',
                    'created_at' => $created_at ? gmdate('c', strtotime($created_at)) : gmdate('c'),
                    'last_tested' => null,
                    'test_count' => (int) $test_count,
                );
            }
        }

        return $forms;
    }

    /**
     * Get framework display label
     *
     * @param string $framework Framework identifier
     * @return string Framework label
     */
    private function get_framework_label($framework) {
        $labels = array(
            'elementor' => 'Elementor Pro',
            'cf7' => 'Contact Form 7',
            'gravity' => 'Gravity Forms',
            'ninja' => 'Ninja Forms',
            'avada' => 'Avada Forms',
        );

        return $labels[$framework] ?? ucfirst($framework);
    }

    /**
     * Generate unique instance ID
     *
     * @return string Unique instance ID
     */
    public function generate_instance_id() {
        return 'cuft_test_' . time() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Cleanup test form metadata when post is deleted
     *
     * @param int $post_id Post ID being deleted
     */
    public function cleanup_test_form_meta($post_id) {
        // Check if this is a CUFT test form
        $is_test_form = get_post_meta($post_id, '_cuft_test_form', true);

        if ($is_test_form) {
            // Clean up all CUFT-related metadata
            delete_post_meta($post_id, '_cuft_test_form');
            delete_post_meta($post_id, '_cuft_instance_id');
            delete_post_meta($post_id, '_cuft_template_id');
            delete_post_meta($post_id, '_cuft_framework');
            delete_post_meta($post_id, '_cuft_test_count');
        }
    }

    /**
     * Verify nonce for form builder operations
     *
     * @param string $nonce Nonce to verify
     * @return bool True if valid
     */
    public function verify_nonce($nonce) {
        return wp_verify_nonce($nonce, 'cuft_form_builder_nonce');
    }

    /**
     * Check if user has required capabilities
     *
     * @return bool True if user can manage forms
     */
    public function check_capabilities() {
        return current_user_can('manage_options');
    }
}

// Initialize
CUFT_Form_Builder::get_instance();
