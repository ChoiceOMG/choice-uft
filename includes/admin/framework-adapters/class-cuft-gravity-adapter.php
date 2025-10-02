<?php
/**
 * Gravity Forms Framework Adapter
 *
 * Handles test form generation for Gravity Forms using GFAPI.
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
 * CUFT Gravity Forms Adapter Class
 */
class CUFT_Gravity_Adapter extends Abstract_CUFT_Adapter {

    public function __construct() {
        parent::__construct();

        $this->framework_id = 'gravity';
        $this->framework_name = 'Gravity Forms';
    }

    public function is_available() {
        return class_exists('GFAPI');
    }

    public function get_version() {
        return class_exists('GFForms') ? GFForms::$version : null;
    }

    public function create_form($template_id, $config = array()) {
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Gravity Forms is not available.', 'choice-uft'));
        }

        $validation = $this->validate_template($template_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            $instance_id = $this->generate_instance_id();

            // Build Gravity Forms array structure
            $form_array = $this->build_gravity_form_array($instance_id, $template_id);

            // Create form using GFAPI
            $form_id = GFAPI::add_form($form_array);

            if (is_wp_error($form_id)) {
                return $this->error('gf_creation_failed', $form_id->get_error_message());
            }

            // Get the form to access post_id
            $form = GFAPI::get_form($form_id);

            // Store CUFT metadata on the form's meta
            gform_update_meta($form_id, '_cuft_test_form', 1);
            gform_update_meta($form_id, '_cuft_instance_id', $instance_id);
            gform_update_meta($form_id, '_cuft_template_id', $template_id);
            gform_update_meta($form_id, '_cuft_framework', $this->framework_id);
            gform_update_meta($form_id, '_cuft_created_at', current_time('mysql'));

            // Create page to display form
            $shortcode = sprintf('[gravityform id="%d" title="false" ajax="true"]', $form_id);
            $page_id = $this->create_display_page($instance_id, $shortcode);

            // Get form URLs
            $urls = $this->get_form_urls($page_id, $instance_id);

            $this->log('Created Gravity form', array(
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
            return $this->error('framework_unavailable', __('Gravity Forms is not available.', 'choice-uft'));
        }

        // Get form_id from page meta
        $instance_id = get_post_meta($post_id, '_cuft_instance_id', true);

        if ($instance_id) {
            // Find Gravity form by meta
            $forms = GFAPI::get_forms();
            foreach ($forms as $form) {
                $meta_instance_id = gform_get_meta($form['id'], '_cuft_instance_id');
                if ($meta_instance_id === $instance_id) {
                    GFAPI::delete_form($form['id']);
                    break;
                }
            }
        }

        // Delete display page
        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return $this->error('deletion_failed', __('Failed to delete form page.', 'choice-uft'));
        }

        $this->log('Deleted Gravity form', array('page_id' => $post_id));

        return true;
    }

    private function build_gravity_form_array($instance_id, $template_id) {
        $fields = $this->get_basic_form_fields();

        $gf_fields = array();
        $field_id = 1;

        foreach ($fields as $field) {
            $gf_field = array(
                'id' => $field_id,
                'label' => $field['label'],
                'type' => $this->map_field_type($field['type']),
                'isRequired' => $field['required'],
                'placeholder' => $field['placeholder'] ?? '',
                'cssClass' => $field['name'],
            );

            if ($field['type'] === 'textarea') {
                $gf_field['size'] = 'large';
            }

            $gf_fields[] = $gf_field;
            $field_id++;
        }

        return array(
            'title' => sprintf(__('CUFT Test Form - %s', 'choice-uft'), $instance_id),
            'description' => '',
            'fields' => $gf_fields,
            'button' => array(
                'type' => 'text',
                'text' => __('Submit', 'choice-uft'),
            ),
            'confirmations' => array(
                array(
                    'id' => wp_generate_uuid4(),
                    'name' => 'Default Confirmation',
                    'isDefault' => true,
                    'type' => 'message',
                    'message' => __('Thank you for your submission!', 'choice-uft'),
                ),
            ),
        );
    }

    private function map_field_type($type) {
        $map = array(
            'text' => 'text',
            'email' => 'email',
            'tel' => 'phone',
            'textarea' => 'textarea',
        );

        return $map[$type] ?? 'text';
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
