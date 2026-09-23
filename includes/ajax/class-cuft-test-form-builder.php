<?php
/**
 * Test Form Builder AJAX Handler
 *
 * Creates and manages test forms for different frameworks.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Test Form Builder Class
 */
class CUFT_Test_Form_Builder {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_cuft_build_test_form', array($this, 'handle_build_test_form'));
    }

    /**
     * Handle test form building AJAX request
     *
     * @return void
     */
    public function handle_build_test_form() {
        // Security check: Verify nonce
        if (!check_ajax_referer('cuft-testing-dashboard', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'choice-universal-form-tracker')
            ), 403);
        }

        // Security check: Verify capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'choice-universal-form-tracker')
            ), 403);
        }

        try {
            // Start timer for performance tracking
            $start_time = microtime(true);

            // Get request parameters
            $framework = isset($_POST['framework']) ? sanitize_key(wp_unslash($_POST['framework'])) : 'elementor';
            $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : 'test_' . uniqid();
            $test_data = array();
            if (isset($_POST['test_data'])) {
                $test_data = json_decode(wp_unslash($_POST['test_data']), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; every decoded value is sanitized by map_deep() on the next line.
                $test_data = is_array($test_data) ? map_deep($test_data, 'sanitize_text_field') : array();
            }

            // Validate framework
            $allowed_frameworks = array('elementor', 'cf7', 'ninja', 'gravity', 'avada');
            if (!in_array($framework, $allowed_frameworks, true)) {
                $framework = 'elementor';
            }

            // Build response based on framework
            $response = $this->build_form_for_framework($framework, $session_id, $test_data);

            // Add performance metric
            $response['performance_ms'] = round((microtime(true) - $start_time) * 1000, 2);

            // Ensure under 500ms threshold
            if ($response['performance_ms'] > 500) {
                CUFT_Logger::debug_log('CUFT: Test form builder exceeded 500ms threshold: ' . $response['performance_ms'] . 'ms');
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            CUFT_Logger::debug_log('CUFT: Test form builder error - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to build test form.', 'choice-universal-form-tracker'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Build form for specific framework
     *
     * @param string $framework Framework identifier
     * @param string $session_id Test session ID
     * @param array  $test_data Test data to pre-populate
     * @return array Form data
     */
    private function build_form_for_framework($framework, $session_id, $test_data) {
        $response = array(
            'framework' => $framework,
            'session_id' => $session_id
        );

        switch ($framework) {
            case 'gravity':
                $response = $this->build_gravity_form($session_id, $test_data);
                break;

            case 'cf7':
                $response = $this->build_cf7_form($session_id, $test_data);
                break;

            case 'ninja':
                $response = $this->build_ninja_form($session_id, $test_data);
                break;

            case 'avada':
                $response = $this->build_avada_form($session_id, $test_data);
                break;

            case 'elementor':
            default:
                $response = $this->build_elementor_form($session_id, $test_data);
                break;
        }

        return $response;
    }

    /**
     * Build Gravity Forms test form
     *
     * @param string $session_id Test session ID
     * @param array  $test_data Test data
     * @return array Form data
     */
    private function build_gravity_form($session_id, $test_data) {
        // Check if Gravity Forms is active
        if (!class_exists('GFAPI')) {
            return $this->get_fallback_form_response('gravity', $session_id, __('Gravity Forms is not active.', 'choice-universal-form-tracker'));
        }

        // Get existing test form or create new one
        $form_id = get_option('cuft_test_gravity_form_id');

        if (!$form_id || !GFAPI::form_id_exists($form_id)) {
            // Create new test form
            $form = array(
                'title' => 'CUFT Test Form - ' . $session_id,
                'description' => __('Test form for CUFT tracking validation', 'choice-universal-form-tracker'),
                'fields' => array(
                    array(
                        'id' => 1,
                        'type' => 'name',
                        'label' => __('Name', 'choice-universal-form-tracker'),
                        'isRequired' => false,
                        'inputs' => array(
                            array('id' => '1.3', 'label' => __('First', 'choice-universal-form-tracker')),
                            array('id' => '1.6', 'label' => __('Last', 'choice-universal-form-tracker'))
                        )
                    ),
                    array(
                        'id' => 2,
                        'type' => 'email',
                        'label' => __('Email', 'choice-universal-form-tracker'),
                        'isRequired' => true,
                        'defaultValue' => isset($test_data['email']) ? $test_data['email'] : ''
                    ),
                    array(
                        'id' => 3,
                        'type' => 'phone',
                        'label' => __('Phone', 'choice-universal-form-tracker'),
                        'isRequired' => false,
                        'defaultValue' => isset($test_data['phone']) ? $test_data['phone'] : ''
                    ),
                    array(
                        'id' => 4,
                        'type' => 'textarea',
                        'label' => __('Message', 'choice-universal-form-tracker'),
                        'isRequired' => false
                    )
                ),
                'button' => array(
                    'type' => 'text',
                    'text' => __('Submit Test', 'choice-universal-form-tracker')
                ),
                'confirmations' => array(
                    array(
                        'id' => '1',
                        'name' => 'Default Confirmation',
                        'isDefault' => true,
                        'type' => 'message',
                        'message' => __('Test form submitted successfully!', 'choice-universal-form-tracker')
                    )
                )
            );

            $form_id = GFAPI::add_form($form);
            update_option('cuft_test_gravity_form_id', $form_id);
        }

        // Pre-populate fields with test data
        if ($form_id && !empty($test_data)) {
            add_filter('gform_field_value', function($value, $field) use ($test_data) {
                if ($field['type'] === 'email' && isset($test_data['email'])) {
                    return $test_data['email'];
                }
                if ($field['type'] === 'phone' && isset($test_data['phone'])) {
                    return $test_data['phone'];
                }
                return $value;
            }, 10, 2);
        }

        return array(
            'framework' => 'gravity',
            'session_id' => $session_id,
            'form_id' => $form_id,
            'shortcode' => '[gravityform id="' . $form_id . '" title="false" description="false" ajax="true"]',
            'rendered_html' => do_shortcode('[gravityform id="' . $form_id . '" title="false" description="false" ajax="true"]'),
            'message' => __('Gravity Forms test form ready.', 'choice-universal-form-tracker')
        );
    }

    /**
     * Build Contact Form 7 test form
     *
     * @param string $session_id Test session ID
     * @param array  $test_data Test data
     * @return array Form data
     */
    private function build_cf7_form($session_id, $test_data) {
        // Check if Contact Form 7 is active
        if (!function_exists('wpcf7_contact_form')) {
            return $this->get_fallback_form_response('cf7', $session_id, __('Contact Form 7 is not active.', 'choice-universal-form-tracker'));
        }

        // Get existing test form ID or use first available
        $form_id = get_option('cuft_test_cf7_form_id');

        if (!$form_id) {
            // Get first available CF7 form
            $forms = get_posts(array(
                'post_type' => 'wpcf7_contact_form',
                'posts_per_page' => 1
            ));

            if (!empty($forms)) {
                $form_id = $forms[0]->ID;
                update_option('cuft_test_cf7_form_id', $form_id);
            }
        }

        if (!$form_id) {
            return $this->get_fallback_form_response('cf7', $session_id, __('No Contact Form 7 forms available.', 'choice-universal-form-tracker'));
        }

        // Pre-fill data is applied by assets/admin/cuft-testing-dashboard.js after the form is inserted.
        $prefill = $this->get_prefill_data('.wpcf7', $test_data);

        return array(
            'framework' => 'cf7',
            'session_id' => $session_id,
            'form_id' => $form_id,
            'shortcode' => '[contact-form-7 id="' . $form_id . '"]',
            'rendered_html' => do_shortcode('[contact-form-7 id="' . $form_id . '"]'),
            'prefill' => $prefill,
            'message' => __('Contact Form 7 test form ready.', 'choice-universal-form-tracker')
        );
    }

    /**
     * Build Ninja Forms test form
     *
     * @param string $session_id Test session ID
     * @param array  $test_data Test data
     * @return array Form data
     */
    private function build_ninja_form($session_id, $test_data) {
        // Check if Ninja Forms is active
        if (!function_exists('Ninja_Forms')) {
            return $this->get_fallback_form_response('ninja', $session_id, __('Ninja Forms is not active.', 'choice-universal-form-tracker'));
        }

        // Get existing test form ID or use first available
        $form_id = get_option('cuft_test_ninja_form_id');

        if (!$form_id) {
            // Get first available Ninja form
            $forms = Ninja_Forms()->form()->get_forms();
            if (!empty($forms)) {
                $form_id = $forms[0]->get_id();
                update_option('cuft_test_ninja_form_id', $form_id);
            }
        }

        if (!$form_id) {
            return $this->get_fallback_form_response('ninja', $session_id, __('No Ninja Forms available.', 'choice-universal-form-tracker'));
        }

        // Pre-fill data is applied by assets/admin/cuft-testing-dashboard.js after the form is inserted.
        $prefill = $this->get_prefill_data('.nf-form-cont', $test_data);

        return array(
            'framework' => 'ninja',
            'session_id' => $session_id,
            'form_id' => $form_id,
            'shortcode' => '[ninja_form id=' . $form_id . ']',
            'rendered_html' => do_shortcode('[ninja_form id=' . $form_id . ']'),
            'prefill' => $prefill,
            'message' => __('Ninja Forms test form ready.', 'choice-universal-form-tracker')
        );
    }

    /**
     * Build Avada/Fusion Forms test form
     *
     * @param string $session_id Test session ID
     * @param array  $test_data Test data
     * @return array Form data
     */
    private function build_avada_form($session_id, $test_data) {
        // Check if Avada/Fusion Builder is active
        if (!class_exists('FusionBuilder')) {
            return $this->get_fallback_form_response('avada', $session_id, __('Avada/Fusion Builder is not active.', 'choice-universal-form-tracker'));
        }

        // Get test form page ID
        $page_id = get_option('cuft_test_avada_page_id');

        if (!$page_id) {
            // Look for a page with Fusion forms
            $pages = get_posts(array(
                'post_type' => 'page',
                'posts_per_page' => 1,
                'meta_key' => '_fusion', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off admin lookup (limit 1) for an existing Avada page, cached in an option afterwards.
                'meta_compare' => 'EXISTS'
            ));

            if (!empty($pages)) {
                $page_id = $pages[0]->ID;
                update_option('cuft_test_avada_page_id', $page_id);
            }
        }

        // Pre-fill data is applied by assets/admin/cuft-testing-dashboard.js after the form is inserted.
        $prefill = $this->get_prefill_data('.fusion-form', $test_data);

        return array(
            'framework' => 'avada',
            'session_id' => $session_id,
            'page_id' => $page_id,
            'page_url' => $page_id ? get_permalink($page_id) : '',
            'rendered_html' => '<p>' . esc_html__('Avada forms must be tested on their page.', 'choice-universal-form-tracker') . '</p>' .
                              ($page_id ? '<a href="' . esc_url(get_permalink($page_id)) . '" target="_blank" class="button">' . esc_html__('Open Test Page', 'choice-universal-form-tracker') . '</a>' : ''),
            'prefill' => $prefill,
            'message' => __('Avada test form ready.', 'choice-universal-form-tracker')
        );
    }

    /**
     * Build Elementor test form
     *
     * @param string $session_id Test session ID
     * @param array  $test_data Test data
     * @return array Form data
     */
    private function build_elementor_form($session_id, $test_data) {
        // Check if Elementor Pro is active
        if (!defined('ELEMENTOR_PRO_VERSION')) {
            return $this->get_fallback_form_response('elementor', $session_id, __('Elementor Pro is not active.', 'choice-universal-form-tracker'));
        }

        // Get test form page ID
        $page_id = get_option('cuft_test_elementor_page_id');

        if (!$page_id) {
            // Look for a page with Elementor forms
            $pages = get_posts(array(
                'post_type' => 'page',
                'posts_per_page' => 1,
                'meta_key' => '_elementor_edit_mode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off admin lookup (limit 1) for an existing Elementor page, cached in an option afterwards.
                'meta_value' => 'builder' // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same one-off admin lookup as above.
            ));

            if (!empty($pages)) {
                $page_id = $pages[0]->ID;
                update_option('cuft_test_elementor_page_id', $page_id);
            }
        }

        // Pre-fill data is applied by assets/admin/cuft-testing-dashboard.js after the form is inserted.
        $prefill = $this->get_prefill_data('.elementor-form', $test_data);

        return array(
            'framework' => 'elementor',
            'session_id' => $session_id,
            'page_id' => $page_id,
            'page_url' => $page_id ? get_permalink($page_id) : '',
            'rendered_html' => '<p>' . esc_html__('Elementor forms must be tested on their page.', 'choice-universal-form-tracker') . '</p>' .
                              ($page_id ? '<a href="' . esc_url(get_permalink($page_id)) . '" target="_blank" class="button">' . esc_html__('Open Test Page', 'choice-universal-form-tracker') . '</a>' : ''),
            'prefill' => $prefill,
            'message' => __('Elementor test form ready.', 'choice-universal-form-tracker')
        );
    }

    /**
     * Build the pre-fill payload the dashboard script applies to the rendered form
     *
     * @param string $container_selector CSS selector of the form container
     * @param array  $test_data Test data (already sanitized)
     * @return array|null Pre-fill data, or null when there is no test data
     */
    private function get_prefill_data($container_selector, $test_data) {
        if (empty($test_data)) {
            return null;
        }

        return array(
            'selector' => $container_selector,
            'email' => isset($test_data['email']) ? $test_data['email'] : '',
            'phone' => isset($test_data['phone']) ? $test_data['phone'] : '',
        );
    }

    /**
     * Get fallback form response when framework is not available
     *
     * @param string $framework Framework identifier
     * @param string $session_id Test session ID
     * @param string $message Error message
     * @return array Fallback response
     */
    private function get_fallback_form_response($framework, $session_id, $message) {
        return array(
            'framework' => $framework,
            'session_id' => $session_id,
            'form_id' => null,
            'shortcode' => '',
            'rendered_html' => '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>',
            'message' => $message
        );
    }
}