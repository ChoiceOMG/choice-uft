<?php
/**
 * Ninja Forms Framework Adapter
 *
 * Handles test form generation for Ninja Forms.
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
 * CUFT Ninja Forms Adapter Class
 */
class CUFT_Ninja_Adapter extends Abstract_CUFT_Adapter {

    public function __construct() {
        parent::__construct();

        $this->framework_id = 'ninja';
        $this->framework_name = 'Ninja Forms';
    }

    public function is_available() {
        return function_exists('Ninja_Forms');
    }

    public function get_version() {
        return get_option('ninja_forms_version');
    }

    public function create_form($template_id, $config = array()) {
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Ninja Forms is not available.', 'choice-uft'));
        }

        $validation = $this->validate_template($template_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            $instance_id = $this->generate_instance_id();

            // Create Ninja form using API
            $form_data = array(
                'title' => sprintf(__('CUFT Test Form - %s', 'choice-uft'), $instance_id),
            );

            $form_id = Ninja_Forms()->form()->create($form_data);

            if (!$form_id) {
                return $this->error('nf_creation_failed', __('Failed to create Ninja form.', 'choice-uft'));
            }

            // Add fields
            $this->add_ninja_fields($form_id);

            // Store CUFT metadata
            update_post_meta($form_id, '_cuft_test_form', 1);
            update_post_meta($form_id, '_cuft_instance_id', $instance_id);
            update_post_meta($form_id, '_cuft_template_id', $template_id);
            update_post_meta($form_id, '_cuft_framework', $this->framework_id);
            update_post_meta($form_id, '_cuft_created_at', current_time('mysql'));

            // Create page to display form
            $shortcode = sprintf('[ninja_form id="%d"]', $form_id);
            $page_id = $this->create_display_page($instance_id, $shortcode);

            // Get form URLs
            $urls = $this->get_form_urls($page_id, $instance_id);

            $this->log('Created Ninja form', array(
                'form_id' => $form_id,
                'page_id' => $page_id,
                'instance_id' => $instance_id,
            ));

            return array(
                'instance_id' => $instance_id,
                'framework' => $this->framework_id,
                'post_id' => $page_id,
                'form_id' => (string) $form_id,
                'test_url' => $urls['test_url'],
                'iframe_url' => $urls['iframe_url'],
                'created_at' => gmdate('c'),
            );

        } catch (Exception $e) {
            return $this->error('creation_failed', $e->getMessage());
        }
    }

    public function delete_form($post_id) {
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Ninja Forms is not available.', 'choice-uft'));
        }

        // Get instance_id
        $instance_id = get_post_meta($post_id, '_cuft_instance_id', true);

        if ($instance_id) {
            // Find and delete Ninja form
            $args = array(
                'post_type' => 'nf_sub',
                'meta_key' => '_cuft_instance_id',
                'meta_value' => $instance_id,
            );

            $forms = get_posts($args);
            foreach ($forms as $form) {
                wp_delete_post($form->ID, true);
            }
        }

        // Delete display page
        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return $this->error('deletion_failed', __('Failed to delete form page.', 'choice-uft'));
        }

        $this->log('Deleted Ninja form', array('page_id' => $post_id));

        return true;
    }

    private function add_ninja_fields($form_id) {
        $fields = $this->get_basic_form_fields();

        $order = 0;

        foreach ($fields as $field) {
            $field_data = array(
                'parent_id' => $form_id,
                'type' => $this->map_field_type($field['type']),
                'label' => $field['label'],
                'key' => $field['name'],
                'placeholder' => $field['placeholder'] ?? '',
                'required' => $field['required'] ? 1 : 0,
                'order' => $order++,
            );

            Ninja_Forms()->form($form_id)->field()->create($field_data);
        }

        // Add submit button
        $submit_data = array(
            'parent_id' => $form_id,
            'type' => 'submit',
            'label' => __('Submit', 'choice-uft'),
            'key' => 'submit',
            'order' => $order,
        );

        Ninja_Forms()->form($form_id)->field()->create($submit_data);
    }

    private function map_field_type($type) {
        $map = array(
            'text' => 'textbox',
            'email' => 'email',
            'tel' => 'phone',
            'textarea' => 'textarea',
        );

        return $map[$type] ?? 'textbox';
    }

    private function create_display_page($instance_id, $shortcode) {
        $post_data = array(
            'post_title' => sprintf(__('CUFT Test Form Page - %s', 'choice-uft'), $instance_id),
            'post_content' => $shortcode,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        );

        $page_id = wp_insert_post($post_data);

        if (!is_wp_error($page_id)) {
            update_post_meta($page_id, '_cuft_test_form', 1);
            update_post_meta($page_id, '_cuft_instance_id', $instance_id);
        }

        return $page_id;
    }
}
