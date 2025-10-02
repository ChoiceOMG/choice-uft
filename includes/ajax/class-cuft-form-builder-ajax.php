<?php
/**
 * Form Builder AJAX Handler
 *
 * Handles all AJAX requests for the testing dashboard form builder.
 *
 * @package    Choice_UTM_Form_Tracker
 * @subpackage Choice_UTM_Form_Tracker/includes/ajax
 * @since      3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Form Builder AJAX Class
 *
 * Manages AJAX endpoints for form creation, deletion, retrieval, and testing.
 */
class CUFT_Form_Builder_Ajax {

    /**
     * Singleton instance
     *
     * @var CUFT_Form_Builder_Ajax
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return CUFT_Form_Builder_Ajax
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
        $this->register_ajax_actions();
    }

    /**
     * Register AJAX action hooks
     */
    private function register_ajax_actions() {
        // Form lifecycle actions
        add_action('wp_ajax_cuft_create_test_form', array($this, 'handle_create_test_form'));
        add_action('wp_ajax_cuft_get_test_forms', array($this, 'handle_get_test_forms'));
        add_action('wp_ajax_cuft_delete_test_form', array($this, 'handle_delete_test_form'));

        // Form interaction actions
        add_action('wp_ajax_cuft_populate_form', array($this, 'handle_populate_form'));
        add_action('wp_ajax_cuft_test_submit', array($this, 'handle_test_submit'));

        // Framework detection
        add_action('wp_ajax_cuft_get_frameworks', array($this, 'handle_get_frameworks'));
    }

    /**
     * Handle create test form AJAX request
     *
     * POST /wp-admin/admin-ajax.php?action=cuft_create_test_form
     */
    public function handle_create_test_form() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to create test forms.', 'choice-uft'), 401);
            return;
        }

        // Get parameters
        $framework = sanitize_text_field($_POST['framework'] ?? '');
        $template_id = sanitize_text_field($_POST['template_id'] ?? 'basic_contact_form');

        // Validate framework
        if (empty($framework)) {
            $this->send_error('missing_framework', __('Framework parameter is required.', 'choice-uft'), 400);
            return;
        }

        // Load adapter factory
        require_once CUFT_PATH . 'includes/admin/class-cuft-adapter-factory.php';

        // Get adapter
        $adapter = CUFT_Adapter_Factory::get_adapter($framework);

        if (is_wp_error($adapter)) {
            $this->send_error('invalid_framework', $adapter->get_error_message(), 400);
            return;
        }

        // Check if framework is available
        if (!$adapter->is_available()) {
            $this->send_error(
                'framework_unavailable',
                sprintf(__('Framework not available: %s', 'choice-uft'), $framework),
                400
            );
            return;
        }

        // Create form
        $result = $adapter->create_form($template_id);

        if (is_wp_error($result)) {
            $this->send_error($result->get_error_code(), $result->get_error_message(), 400);
            return;
        }

        $this->send_success($result);
    }

    /**
     * Handle get test forms AJAX request
     *
     * GET /wp-admin/admin-ajax.php?action=cuft_get_test_forms
     */
    public function handle_get_test_forms() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to view test forms.', 'choice-uft'), 401);
            return;
        }

        // Get status filter
        $status = sanitize_text_field($_GET['status'] ?? 'active');

        // Query test forms
        $form_builder = CUFT_Form_Builder::get_instance();
        $forms_data = $form_builder->get_test_forms(array('status' => $status));

        $this->send_success(array(
            'forms' => $forms_data,
            'total' => count($forms_data),
        ));
    }

    /**
     * Handle delete test form AJAX request
     *
     * POST /wp-admin/admin-ajax.php?action=cuft_delete_test_form
     */
    public function handle_delete_test_form() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to delete test forms.', 'choice-uft'), 401);
            return;
        }

        // Get instance_id
        $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');

        if (empty($instance_id)) {
            $this->send_error('missing_instance_id', __('Instance ID is required.', 'choice-uft'), 400);
            return;
        }

        // Find form by instance_id
        $query = new WP_Query(array(
            'meta_key' => '_cuft_instance_id',
            'meta_value' => $instance_id,
            'post_type' => 'any',
            'posts_per_page' => 1,
        ));

        if (!$query->have_posts()) {
            $this->send_error('form_not_found', __('Test form not found.', 'choice-uft'), 404);
            return;
        }

        $post_id = $query->posts[0]->ID;
        $framework = get_post_meta($post_id, '_cuft_framework', true);

        // Load adapter factory
        require_once CUFT_PATH . 'includes/admin/class-cuft-adapter-factory.php';

        // Get adapter
        $adapter = CUFT_Adapter_Factory::get_adapter($framework);

        if (is_wp_error($adapter)) {
            // Fallback: just delete the post
            wp_delete_post($post_id, true);
        } else {
            // Use adapter's delete method
            $result = $adapter->delete_form($post_id);

            if (is_wp_error($result)) {
                $this->send_error($result->get_error_code(), $result->get_error_message(), 400);
                return;
            }
        }

        $this->send_success(array(
            'message' => __('Test form deleted successfully', 'choice-uft'),
            'instance_id' => $instance_id,
        ));
    }

    /**
     * Handle populate form AJAX request
     *
     * POST /wp-admin/admin-ajax.php?action=cuft_populate_form
     */
    public function handle_populate_form() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to populate forms.', 'choice-uft'), 401);
            return;
        }

        // Get parameters
        $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');
        $use_test_data = filter_var($_POST['use_test_data'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Generate test data
        $timestamp = time();
        $test_data = array(
            'name' => 'Test User',
            'email' => "test-{$timestamp}@example.com",
            'phone' => '555-0123',
            'message' => 'This is a test submission from CUFT Testing Dashboard',
        );

        $this->send_success(array(
            'message_sent' => true,
            'test_data' => $test_data,
        ));
    }

    /**
     * Handle test submission AJAX request
     *
     * POST /wp-admin/admin-ajax.php?action=cuft_test_submit
     */
    public function handle_test_submit() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to submit test forms.', 'choice-uft'), 401);
            return;
        }

        // Get submission data
        $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');
        $form_data = isset($_POST['form_data']) ? json_decode(stripslashes($_POST['form_data']), true) : array();
        $tracking_event = isset($_POST['tracking_event']) ? json_decode(stripslashes($_POST['tracking_event']), true) : array();

        // Validate constitutional compliance
        $validation = $this->validate_tracking_event($tracking_event);

        // Log submission (in production, this would go to a dedicated table)
        do_action('cuft_test_submission_logged', array(
            'instance_id' => $instance_id,
            'form_data' => $form_data,
            'tracking_event' => $tracking_event,
            'validation' => $validation,
            'timestamp' => current_time('mysql'),
        ));

        $this->send_success(array(
            'logged' => true,
            'validation' => $validation,
            'message' => __('Test submission logged successfully', 'choice-uft'),
        ));
    }

    /**
     * Validate tracking event constitutional compliance
     *
     * @param array $event Tracking event data
     * @return array Validation results
     */
    private function validate_tracking_event($event) {
        $validation = array(
            'has_cuft_tracked' => isset($event['cuft_tracked']) && $event['cuft_tracked'] === true,
            'has_cuft_source' => isset($event['cuft_source']) && !empty($event['cuft_source']),
            'uses_snake_case' => $this->check_snake_case($event),
            'required_fields_present' => isset($event['event']) && isset($event['form_type']),
            'click_ids_tracked' => $this->get_tracked_click_ids($event),
        );

        return $validation;
    }

    /**
     * Check if event uses snake_case naming
     *
     * @param array $event Event data
     * @return bool True if using snake_case
     */
    private function check_snake_case($event) {
        $camel_case_patterns = array('formType', 'formId', 'formName', 'userEmail', 'userPhone', 'submittedAt');

        foreach ($camel_case_patterns as $pattern) {
            if (isset($event[$pattern])) {
                return false; // Found camelCase
            }
        }

        return true;
    }

    /**
     * Get tracked click IDs from event
     *
     * @param array $event Event data
     * @return array Click IDs found
     */
    private function get_tracked_click_ids($event) {
        $click_id_fields = array('click_id', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid', 'snap_click_id', 'pclid');
        $tracked = array();

        foreach ($click_id_fields as $field) {
            if (isset($event[$field]) && !empty($event[$field])) {
                $tracked[] = $field;
            }
        }

        return $tracked;
    }

    /**
     * Handle get frameworks AJAX request
     *
     * GET /wp-admin/admin-ajax.php?action=cuft_get_frameworks
     */
    public function handle_get_frameworks() {
        // Verify nonce
        if (!$this->verify_nonce()) {
            $this->send_error('invalid_nonce', __('Security check failed.', 'choice-uft'), 403);
            return;
        }

        // Check capabilities
        if (!$this->check_capabilities()) {
            $this->send_error('insufficient_permissions', __('You do not have permission to view frameworks.', 'choice-uft'), 401);
            return;
        }

        // Load adapter factory
        require_once CUFT_PATH . 'includes/admin/class-cuft-adapter-factory.php';

        // Get frameworks info from factory
        $frameworks = CUFT_Adapter_Factory::get_frameworks_info();

        $this->send_success(array(
            'frameworks' => $frameworks,
        ));
    }

    /**
     * Verify AJAX nonce
     *
     * @return bool True if valid
     */
    private function verify_nonce() {
        $nonce = $_REQUEST['nonce'] ?? '';
        return wp_verify_nonce($nonce, 'cuft_form_builder_nonce');
    }

    /**
     * Check user capabilities
     *
     * @return bool True if user has permissions
     */
    private function check_capabilities() {
        return current_user_can('manage_options');
    }

    /**
     * Send success response
     *
     * @param mixed $data Response data
     */
    private function send_success($data) {
        wp_send_json_success($data);
    }

    /**
     * Send error response
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status_code HTTP status code
     */
    private function send_error($code, $message, $status_code = 400) {
        wp_send_json_error(array(
            'code' => $code,
            'message' => $message,
        ), $status_code);
    }
}

// Initialize
CUFT_Form_Builder_Ajax::get_instance();
