<?php
/**
 * Elementor Pro Framework Adapter
 *
 * Handles test form generation for Elementor Pro forms.
 *
 * @package    Choice_UTM_Form_Tracker
 * @subpackage Choice_UTM_Form_Tracker/includes/admin/framework-adapters
 * @since      3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/abstract-cuft-adapter.php';

/**
 * CUFT Elementor Adapter Class
 *
 * Creates and manages Elementor Pro test forms.
 */
class CUFT_Elementor_Adapter extends Abstract_CUFT_Adapter {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->framework_id = 'elementor';
        $this->framework_name = 'Elementor Pro';
    }

    /**
     * Check if Elementor Pro is available
     *
     * @return bool True if Elementor Pro is active
     */
    public function is_available() {
        return defined('ELEMENTOR_PRO_VERSION');
    }

    /**
     * Get Elementor Pro version
     *
     * @return string|null Version or null
     */
    public function get_version() {
        return defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : null;
    }

    /**
     * Create Elementor Pro test form
     *
     * @param string $template_id Template identifier
     * @param array  $config      Additional configuration
     * @return array|WP_Error Form data or error
     */
    public function create_form($template_id, $config = array()) {
        // Silent exit if Elementor Pro not available
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Elementor Pro is not available.', 'choice-uft'));
        }

        // Validate template
        $validation = $this->validate_template($template_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            // Generate instance ID
            $instance_id = $this->generate_instance_id();

            // Create page for the form
            $post_data = array(
                'post_title' => sprintf(__('CUFT Test Form - %s', 'choice-uft'), $instance_id),
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                return $this->error('post_creation_failed', $post_id->get_error_message());
            }

            // Build Elementor form widget data
            $form_id = 'cuft-test-' . time();
            $elementor_data = $this->build_elementor_data($form_id, $template_id);

            // Save Elementor data
            update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($elementor_data)));
            update_post_meta($post_id, '_elementor_edit_mode', 'builder');
            update_post_meta($post_id, '_elementor_template_type', 'wp-page');
            update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);

            // Store CUFT metadata
            $this->store_form_metadata($post_id, $instance_id, $template_id, array(
                'form_id' => $form_id,
            ));

            // Get form URLs
            $urls = $this->get_form_urls($post_id, $instance_id);

            $this->log('Created Elementor form', array(
                'post_id' => $post_id,
                'instance_id' => $instance_id,
                'form_id' => $form_id,
            ));

            return array(
                'instance_id' => $instance_id,
                'framework' => $this->framework_id,
                'post_id' => $post_id,
                'form_id' => $form_id,
                'test_url' => $urls['test_url'],
                'iframe_url' => $urls['iframe_url'],
                'created_at' => gmdate('c'),
            );

        } catch (Exception $e) {
            return $this->error('creation_failed', $e->getMessage());
        }
    }

    /**
     * Delete Elementor test form
     *
     * @param int $post_id Post ID
     * @return bool|WP_Error True on success
     */
    public function delete_form($post_id) {
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Elementor Pro is not available.', 'choice-uft'));
        }

        // Verify it's a CUFT test form
        $is_test_form = get_post_meta($post_id, '_cuft_test_form', true);
        if (!$is_test_form) {
            return $this->error('not_test_form', __('Post is not a CUFT test form.', 'choice-uft'));
        }

        // Delete the post (this will trigger cleanup hooks)
        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return $this->error('deletion_failed', __('Failed to delete form post.', 'choice-uft'));
        }

        $this->log('Deleted Elementor form', array('post_id' => $post_id));

        return true;
    }

    /**
     * Build Elementor form widget data structure
     *
     * @param string $form_id     Form identifier
     * @param string $template_id Template identifier
     * @return array Elementor widget data
     */
    private function build_elementor_data($form_id, $template_id) {
        $fields = $this->get_basic_form_fields();

        // Convert fields to Elementor format
        $elementor_fields = array();
        $field_index = 0;

        foreach ($fields as $field) {
            $elementor_fields[] = array(
                'custom_id' => $field['name'],
                'field_type' => $this->map_field_type($field['type']),
                'field_label' => $field['label'],
                'placeholder' => $field['placeholder'] ?? '',
                'required' => $field['required'] ? 'true' : 'false',
                'field_options' => array(),
                '_id' => 'field_' . $field_index++,
            );
        }

        // Submit button
        $submit_button = array(
            'button_text' => __('Submit', 'choice-uft'),
            'button_size' => 'md',
        );

        // Build complete widget structure
        return array(
            array(
                'id' => wp_generate_uuid4(),
                'elType' => 'section',
                'elements' => array(
                    array(
                        'id' => wp_generate_uuid4(),
                        'elType' => 'column',
                        'elements' => array(
                            array(
                                'id' => wp_generate_uuid4(),
                                'elType' => 'widget',
                                'widgetType' => 'form',
                                'settings' => array(
                                    'form_name' => 'CUFT Test Form',
                                    'form_fields' => $elementor_fields,
                                    'submit_actions' => array('email'),
                                    'button_text' => $submit_button['button_text'],
                                    'button_size' => $submit_button['button_size'],
                                    'form_id' => $form_id,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Map field type to Elementor field type
     *
     * @param string $type Generic field type
     * @return string Elementor field type
     */
    private function map_field_type($type) {
        $map = array(
            'text' => 'text',
            'email' => 'email',
            'tel' => 'tel',
            'textarea' => 'textarea',
        );

        return $map[$type] ?? 'text';
    }

    /**
     * Prepare Elementor form for test mode
     *
     * @param int $post_id Post ID
     * @return bool True on success
     */
    public function prepare_test_mode($post_id) {
        parent::prepare_test_mode($post_id);

        // Add Elementor-specific test mode flags
        update_post_meta($post_id, '_elementor_test_mode', 1);

        return true;
    }
}
