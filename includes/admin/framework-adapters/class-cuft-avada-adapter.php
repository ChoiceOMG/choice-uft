<?php
/**
 * Avada Forms Framework Adapter
 *
 * Handles test form generation for Avada/Fusion Builder forms.
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
 * CUFT Avada Forms Adapter Class
 */
class CUFT_Avada_Adapter extends Abstract_CUFT_Adapter {

    public function __construct() {
        parent::__construct();

        $this->framework_id = 'avada';
        $this->framework_name = 'Avada Forms';
    }

    public function is_available() {
        return class_exists('Fusion_Builder');
    }

    public function get_version() {
        return defined('FUSION_BUILDER_VERSION') ? FUSION_BUILDER_VERSION : null;
    }

    public function create_form($template_id, $config = array()) {
        if ($this->silent_exit_if_unavailable()) {
            return $this->error('framework_unavailable', __('Avada Forms is not available.', 'choice-uft'));
        }

        $validation = $this->validate_template($template_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            $instance_id = $this->generate_instance_id();

            // Create fusion_form post
            $post_data = array(
                'post_title' => sprintf(__('CUFT Test Form - %s', 'choice-uft'), $instance_id),
                'post_content' => $this->build_avada_form_content($template_id),
                'post_status' => 'publish',
                'post_type' => 'fusion_form',
                'post_author' => get_current_user_id(),
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                return $this->error('post_creation_failed', $post_id->get_error_message());
            }

            // Store CUFT metadata
            $this->store_form_metadata($post_id, $instance_id, $template_id);

            // Create page to display form
            $shortcode = sprintf('[fusion_form form_post_id="%d"]', $post_id);
            $page_id = $this->create_display_page($instance_id, $shortcode);

            // Get form URLs
            $urls = $this->get_form_urls($page_id, $instance_id);

            $this->log('Created Avada form', array(
                'post_id' => $post_id,
                'page_id' => $page_id,
                'instance_id' => $instance_id,
            ));

            return array(
                'instance_id' => $instance_id,
                'framework' => $this->framework_id,
                'post_id' => $post_id,
                'form_id' => (string) $post_id,
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
            return $this->error('framework_unavailable', __('Avada Forms is not available.', 'choice-uft'));
        }

        $is_test_form = get_post_meta($post_id, '_cuft_test_form', true);
        if (!$is_test_form) {
            return $this->error('not_test_form', __('Post is not a CUFT test form.', 'choice-uft'));
        }

        // Delete associated page if exists
        $instance_id = get_post_meta($post_id, '_cuft_instance_id', true);
        if ($instance_id) {
            $page_query = new WP_Query(array(
                'meta_key' => '_cuft_instance_id',
                'meta_value' => $instance_id,
                'post_type' => 'page'
            ));

            if ($page_query->have_posts()) {
                wp_delete_post($page_query->posts[0]->ID, true);
            }
        }

        $deleted = wp_delete_post($post_id, true);

        if (!$deleted) {
            return $this->error('deletion_failed', __('Failed to delete form.', 'choice-uft'));
        }

        $this->log('Deleted Avada form', array('post_id' => $post_id));

        return true;
    }

    private function build_avada_form_content($template_id) {
        $fields = $this->get_basic_form_fields();

        $shortcodes = array();

        foreach ($fields as $field) {
            $required = $field['required'] ? 'yes' : 'no';
            $type = $this->map_field_type($field['type']);

            $shortcodes[] = sprintf(
                '[fusion_form_text name="%s" label="%s" placeholder="%s" required="%s"]',
                $field['name'],
                $field['label'],
                $field['placeholder'] ?? '',
                $required
            );
        }

        $shortcodes[] = '[fusion_form_submit value="Submit" /]';

        return implode("\n", $shortcodes);
    }

    private function map_field_type($type) {
        // Avada uses fusion_form_{type} shortcodes
        return $type; // text, email, textarea, etc.
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
