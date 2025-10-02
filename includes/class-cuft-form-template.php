<?php
/**
 * CUFT Form Template Manager
 *
 * Manages form templates for test form generation.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Template Class
 */
class CUFT_Form_Template {

    /**
     * Option key for storing templates
     */
    const OPTION_KEY = 'cuft_form_templates';

    /**
     * Get all templates
     *
     * @return array
     */
    public static function get_templates() {
        $templates = get_option(self::OPTION_KEY, array());

        // If no templates exist, load defaults
        if (empty($templates)) {
            $templates = self::get_default_templates();
            update_option(self::OPTION_KEY, $templates);
        }

        return $templates;
    }

    /**
     * Get template by ID
     *
     * @param string $template_id Template ID
     * @return array|null Template data or null
     */
    public static function get_template($template_id) {
        $templates = self::get_templates();

        return isset($templates[$template_id]) ? $templates[$template_id] : null;
    }

    /**
     * Add new template
     *
     * @param string $template_id Template ID
     * @param array $template_data Template data
     * @return bool Success
     */
    public static function add_template($template_id, $template_data) {
        // Validate template data
        if (!self::validate_template($template_data)) {
            return false;
        }

        $templates = self::get_templates();
        $templates[$template_id] = $template_data;

        return update_option(self::OPTION_KEY, $templates);
    }

    /**
     * Update existing template
     *
     * @param string $template_id Template ID
     * @param array $template_data Template data
     * @return bool Success
     */
    public static function update_template($template_id, $template_data) {
        $templates = self::get_templates();

        if (!isset($templates[$template_id])) {
            return false;
        }

        // Validate template data
        if (!self::validate_template($template_data)) {
            return false;
        }

        $templates[$template_id] = $template_data;

        return update_option(self::OPTION_KEY, $templates);
    }

    /**
     * Delete template
     *
     * @param string $template_id Template ID
     * @return bool Success
     */
    public static function delete_template($template_id) {
        $templates = self::get_templates();

        if (!isset($templates[$template_id])) {
            return false;
        }

        unset($templates[$template_id]);

        return update_option(self::OPTION_KEY, $templates);
    }

    /**
     * Validate template data
     *
     * @param array $template Template data
     * @return bool Valid
     */
    public static function validate_template($template) {
        // Required fields
        $required_fields = array('name', 'fields');

        foreach ($required_fields as $field) {
            if (!isset($template[$field]) || empty($template[$field])) {
                return false;
            }
        }

        // Validate fields array
        if (!is_array($template['fields']) || empty($template['fields'])) {
            return false;
        }

        // Validate each field
        foreach ($template['fields'] as $field) {
            if (!isset($field['name']) || !isset($field['type'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get default templates
     *
     * @return array
     */
    public static function get_default_templates() {
        return array(
            'basic_contact_form' => array(
                'name' => 'Basic Contact Form',
                'description' => 'Simple contact form with name, email, phone, and message fields',
                'fields' => array(
                    array(
                        'name' => 'name',
                        'type' => 'text',
                        'label' => 'Name',
                        'placeholder' => 'Your Name',
                        'required' => true,
                    ),
                    array(
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'placeholder' => 'your@email.com',
                        'required' => true,
                    ),
                    array(
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Phone',
                        'placeholder' => '555-0123',
                        'required' => false,
                    ),
                    array(
                        'name' => 'message',
                        'type' => 'textarea',
                        'label' => 'Message',
                        'placeholder' => 'Your message here...',
                        'required' => false,
                    ),
                ),
            ),
            'lead_generation_form' => array(
                'name' => 'Lead Generation Form',
                'description' => 'Comprehensive lead capture with company info',
                'fields' => array(
                    array(
                        'name' => 'first_name',
                        'type' => 'text',
                        'label' => 'First Name',
                        'placeholder' => 'John',
                        'required' => true,
                    ),
                    array(
                        'name' => 'last_name',
                        'type' => 'text',
                        'label' => 'Last Name',
                        'placeholder' => 'Doe',
                        'required' => true,
                    ),
                    array(
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'placeholder' => 'john@company.com',
                        'required' => true,
                    ),
                    array(
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Phone',
                        'placeholder' => '555-0123',
                        'required' => true,
                    ),
                    array(
                        'name' => 'company',
                        'type' => 'text',
                        'label' => 'Company',
                        'placeholder' => 'Company Name',
                        'required' => false,
                    ),
                    array(
                        'name' => 'message',
                        'type' => 'textarea',
                        'label' => 'How can we help?',
                        'placeholder' => 'Tell us about your needs...',
                        'required' => false,
                    ),
                ),
            ),
        );
    }

    /**
     * Get template field configuration for test data generation
     *
     * @param string $template_id Template ID
     * @return array Field configuration
     */
    public static function get_test_data_config($template_id) {
        $template = self::get_template($template_id);

        if (!$template) {
            return array();
        }

        $test_data = array();

        foreach ($template['fields'] as $field) {
            $test_data[$field['name']] = self::generate_test_value($field);
        }

        return $test_data;
    }

    /**
     * Generate test value for field
     *
     * @param array $field Field configuration
     * @return string Test value
     */
    private static function generate_test_value($field) {
        $timestamp = time();

        switch ($field['type']) {
            case 'email':
                return "test-{$timestamp}@example.com";

            case 'tel':
                return '555-0' . substr($timestamp, -3);

            case 'text':
                if (stripos($field['name'], 'name') !== false) {
                    return 'Test User';
                } elseif (stripos($field['name'], 'company') !== false) {
                    return 'Test Company';
                } else {
                    return 'Test ' . ucfirst($field['name']);
                }

            case 'textarea':
                return 'This is a test submission from CUFT Testing Dashboard at ' . date('Y-m-d H:i:s');

            default:
                return 'Test Value';
        }
    }
}
