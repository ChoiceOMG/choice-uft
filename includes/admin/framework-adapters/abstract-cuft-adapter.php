<?php
/**
 * Abstract Framework Adapter Base Class
 *
 * Defines the interface and common functionality for all form framework adapters.
 * Each framework (Elementor, CF7, Gravity, etc.) must extend this class.
 *
 * @package    Choice_UTM_Form_Tracker
 * @subpackage Choice_UTM_Form_Tracker/includes/admin/framework-adapters
 * @since      3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract CUFT Framework Adapter Class
 *
 * Provides base functionality for framework-specific form generation adapters.
 */
abstract class Abstract_CUFT_Adapter {

    /**
     * Framework identifier
     *
     * @var string
     */
    protected $framework_id = '';

    /**
     * Framework name
     *
     * @var string
     */
    protected $framework_name = '';

    /**
     * Debug mode
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if framework is available
     *
     * Each adapter must implement framework detection logic.
     *
     * @return bool True if framework is installed and active
     */
    abstract public function is_available();

    /**
     * Create a test form
     *
     * Each adapter must implement form creation logic specific to their framework.
     *
     * @param string $template_id Template identifier
     * @param array  $config      Additional configuration
     * @return array|WP_Error Array with form data on success, WP_Error on failure
     */
    abstract public function create_form($template_id, $config = array());

    /**
     * Delete a test form
     *
     * Each adapter must implement form deletion logic with proper cleanup.
     *
     * @param int $post_id Post ID of the form
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    abstract public function delete_form($post_id);

    /**
     * Get framework version
     *
     * @return string|null Version string or null if not available
     */
    abstract public function get_version();

    /**
     * Prepare form for test mode
     *
     * Optional: Add test mode specific configuration to the form.
     *
     * @param int $post_id Post ID of the form
     * @return bool True on success
     */
    public function prepare_test_mode($post_id) {
        // Default implementation: add test mode meta
        update_post_meta($post_id, '_cuft_test_mode', 1);
        return true;
    }

    /**
     * Get framework ID
     *
     * @return string Framework identifier
     */
    public function get_framework_id() {
        return $this->framework_id;
    }

    /**
     * Get framework name
     *
     * @return string Framework display name
     */
    public function get_framework_name() {
        return $this->framework_name;
    }

    /**
     * Generate unique form instance ID
     *
     * @return string Unique instance ID
     */
    protected function generate_instance_id() {
        return 'cuft_test_' . time() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Store form metadata
     *
     * @param int    $post_id     Post ID
     * @param string $instance_id Instance ID
     * @param string $template_id Template ID
     * @param array  $extra_meta  Additional metadata
     * @return bool True on success
     */
    protected function store_form_metadata($post_id, $instance_id, $template_id, $extra_meta = array()) {
        // Mark as CUFT test form
        update_post_meta($post_id, '_cuft_test_form', 1);

        // Store instance ID
        update_post_meta($post_id, '_cuft_instance_id', $instance_id);

        // Store template ID
        update_post_meta($post_id, '_cuft_template_id', $template_id);

        // Store framework
        update_post_meta($post_id, '_cuft_framework', $this->framework_id);

        // Store creation timestamp
        update_post_meta($post_id, '_cuft_created_at', current_time('mysql'));

        // Store test count
        update_post_meta($post_id, '_cuft_test_count', 0);

        // Store extra metadata
        foreach ($extra_meta as $key => $value) {
            update_post_meta($post_id, '_cuft_' . $key, $value);
        }

        return true;
    }

    /**
     * Build form fields array
     *
     * Returns standard field configuration for basic contact form.
     *
     * @return array Array of field definitions
     */
    protected function get_basic_form_fields() {
        return array(
            array(
                'type' => 'text',
                'name' => 'name',
                'label' => __('Name', 'choice-uft'),
                'required' => true,
                'placeholder' => __('Your Name', 'choice-uft'),
            ),
            array(
                'type' => 'email',
                'name' => 'email',
                'label' => __('Email', 'choice-uft'),
                'required' => true,
                'placeholder' => __('your@email.com', 'choice-uft'),
            ),
            array(
                'type' => 'tel',
                'name' => 'phone',
                'label' => __('Phone', 'choice-uft'),
                'required' => true,
                'placeholder' => __('555-0123', 'choice-uft'),
            ),
            array(
                'type' => 'textarea',
                'name' => 'message',
                'label' => __('Message', 'choice-uft'),
                'required' => false,
                'placeholder' => __('Your message...', 'choice-uft'),
                'rows' => 4,
            ),
        );
    }

    /**
     * Get form URLs
     *
     * @param int    $post_id     Post ID
     * @param string $instance_id Instance ID
     * @return array URLs for test and iframe access
     */
    protected function get_form_urls($post_id, $instance_id) {
        $base_url = get_permalink($post_id);

        // Ensure base URL has query string support
        $separator = (strpos($base_url, '?') === false) ? '?' : '&';

        return array(
            'test_url' => $base_url . $separator . 'form_id=' . $instance_id,
            'iframe_url' => $base_url . $separator . 'form_id=' . $instance_id . '&test_mode=1',
        );
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param mixed  $data    Additional data to log
     */
    protected function log($message, $data = null) {
        if (!$this->debug) {
            return;
        }

        $log_message = sprintf('[CUFT %s Adapter] %s', $this->framework_name, $message);

        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }

    /**
     * Handle errors gracefully
     *
     * @param string $code    Error code
     * @param string $message Error message
     * @param mixed  $data    Additional error data
     * @return WP_Error
     */
    protected function error($code, $message, $data = null) {
        $this->log('ERROR: ' . $message, $data);

        return new WP_Error($code, $message, $data);
    }

    /**
     * Validate template ID
     *
     * @param string $template_id Template identifier
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    protected function validate_template($template_id) {
        $valid_templates = array('basic_contact_form');

        if (!in_array($template_id, $valid_templates, true)) {
            return $this->error(
                'invalid_template',
                sprintf(__('Invalid template ID: %s', 'choice-uft'), $template_id)
            );
        }

        return true;
    }

    /**
     * Silent exit for non-relevant contexts
     *
     * Constitutional compliance: Exit silently if framework not available.
     */
    protected function silent_exit_if_unavailable() {
        if (!$this->is_available()) {
            $this->log('Framework not available, exiting silently');
            return true;
        }
        return false;
    }

    /**
     * Sanitize form input
     *
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    protected function sanitize_input($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_input'), $input);
        }

        return sanitize_text_field($input);
    }
}
