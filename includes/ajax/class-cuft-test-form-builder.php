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
                'message' => __('Security check failed.', 'choice-uft')
            ), 403);
        }

        // Security check: Verify capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'choice-uft')
            ), 403);
        }

        try {
            // Start timer for performance tracking
            $start_time = microtime(true);

            // Get request parameters
            $framework = isset($_POST['framework']) ? sanitize_text_field($_POST['framework']) : 'elementor';
            $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : 'test_' . uniqid();
            $test_data = isset($_POST['test_data']) ? json_decode(stripslashes($_POST['test_data']), true) : array();

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
                error_log('CUFT: Test form builder exceeded 500ms threshold: ' . $response['performance_ms'] . 'ms');
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('CUFT: Test form builder error - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to build test form.', 'choice-uft'),
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
            return $this->get_fallback_form_response('gravity', $session_id, __('Gravity Forms is not active.', 'choice-uft'));
        }

        // Get existing test form or create new one
        $form_id = get_option('cuft_test_gravity_form_id');

        if (!$form_id || !GFAPI::form_id_exists($form_id)) {
            // Create new test form
            $form = array(
                'title' => 'CUFT Test Form - ' . $session_id,
                'description' => __('Test form for CUFT tracking validation', 'choice-uft'),
                'fields' => array(
                    array(
                        'id' => 1,
                        'type' => 'name',
                        'label' => __('Name', 'choice-uft'),
                        'isRequired' => false,
                        'inputs' => array(
                            array('id' => '1.3', 'label' => __('First', 'choice-uft')),
                            array('id' => '1.6', 'label' => __('Last', 'choice-uft'))
                        )
                    ),
                    array(
                        'id' => 2,
                        'type' => 'email',
                        'label' => __('Email', 'choice-uft'),
                        'isRequired' => true,
                        'defaultValue' => isset($test_data['email']) ? $test_data['email'] : ''
                    ),
                    array(
                        'id' => 3,
                        'type' => 'phone',
                        'label' => __('Phone', 'choice-uft'),
                        'isRequired' => false,
                        'defaultValue' => isset($test_data['phone']) ? $test_data['phone'] : ''
                    ),
                    array(
                        'id' => 4,
                        'type' => 'textarea',
                        'label' => __('Message', 'choice-uft'),
                        'isRequired' => false
                    )
                ),
                'button' => array(
                    'type' => 'text',
                    'text' => __('Submit Test', 'choice-uft')
                ),
                'confirmations' => array(
                    array(
                        'id' => '1',
                        'name' => 'Default Confirmation',
                        'isDefault' => true,
                        'type' => 'message',
                        'message' => __('Test form submitted successfully!', 'choice-uft')
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
            'message' => __('Gravity Forms test form ready.', 'choice-uft')
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
            return $this->get_fallback_form_response('cf7', $session_id, __('Contact Form 7 is not active.', 'choice-uft'));
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
            return $this->get_fallback_form_response('cf7', $session_id, __('No Contact Form 7 forms available.', 'choice-uft'));
        }

        // Pre-fill data using JavaScript (CF7 doesn't support server-side pre-population easily)
        $prefill_script = '';
        if (!empty($test_data)) {
            $prefill_script = sprintf(
                '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(function() {
                        var emailField = document.querySelector(".wpcf7 input[type=email]");
                        var phoneField = document.querySelector(".wpcf7 input[type=tel]");
                        if (emailField) emailField.value = %s;
                        if (phoneField) phoneField.value = %s;
                    }, 100);
                });
                </script>',
                json_encode(isset($test_data['email']) ? $test_data['email'] : ''),
                json_encode(isset($test_data['phone']) ? $test_data['phone'] : '')
            );
        }

        return array(
            'framework' => 'cf7',
            'session_id' => $session_id,
            'form_id' => $form_id,
            'shortcode' => '[contact-form-7 id="' . $form_id . '"]',
            'rendered_html' => do_shortcode('[contact-form-7 id="' . $form_id . '"]') . $prefill_script,
            'message' => __('Contact Form 7 test form ready.', 'choice-uft')
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
            return $this->get_fallback_form_response('ninja', $session_id, __('Ninja Forms is not active.', 'choice-uft'));
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
            return $this->get_fallback_form_response('ninja', $session_id, __('No Ninja Forms available.', 'choice-uft'));
        }

        // Pre-fill data using JavaScript
        $prefill_script = '';
        if (!empty($test_data)) {
            $prefill_script = sprintf(
                '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(function() {
                        var emailField = document.querySelector(".nf-form-cont input[type=email]");
                        var phoneField = document.querySelector(".nf-form-cont input[type=tel]");
                        if (emailField) emailField.value = %s;
                        if (phoneField) phoneField.value = %s;
                    }, 500);
                });
                </script>',
                json_encode(isset($test_data['email']) ? $test_data['email'] : ''),
                json_encode(isset($test_data['phone']) ? $test_data['phone'] : '')
            );
        }

        return array(
            'framework' => 'ninja',
            'session_id' => $session_id,
            'form_id' => $form_id,
            'shortcode' => '[ninja_form id=' . $form_id . ']',
            'rendered_html' => do_shortcode('[ninja_form id=' . $form_id . ']') . $prefill_script,
            'message' => __('Ninja Forms test form ready.', 'choice-uft')
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
            return $this->get_fallback_form_response('avada', $session_id, __('Avada/Fusion Builder is not active.', 'choice-uft'));
        }

        // Get test form page ID
        $page_id = get_option('cuft_test_avada_page_id');

        if (!$page_id) {
            // Look for a page with Fusion forms
            $pages = get_posts(array(
                'post_type' => 'page',
                'posts_per_page' => 1,
                'meta_key' => '_fusion',
                'meta_compare' => 'EXISTS'
            ));

            if (!empty($pages)) {
                $page_id = $pages[0]->ID;
                update_option('cuft_test_avada_page_id', $page_id);
            }
        }

        // Pre-fill data using JavaScript
        $prefill_script = '';
        if (!empty($test_data)) {
            $prefill_script = sprintf(
                '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(function() {
                        var emailField = document.querySelector(".fusion-form input[type=email]");
                        var phoneField = document.querySelector(".fusion-form input[type=tel]");
                        if (emailField) emailField.value = %s;
                        if (phoneField) phoneField.value = %s;
                    }, 500);
                });
                </script>',
                json_encode(isset($test_data['email']) ? $test_data['email'] : ''),
                json_encode(isset($test_data['phone']) ? $test_data['phone'] : '')
            );
        }

        return array(
            'framework' => 'avada',
            'session_id' => $session_id,
            'page_id' => $page_id,
            'page_url' => $page_id ? get_permalink($page_id) : '',
            'rendered_html' => '<p>' . __('Avada forms must be tested on their page.', 'choice-uft') . '</p>' .
                              ($page_id ? '<a href="' . get_permalink($page_id) . '" target="_blank" class="button">' . __('Open Test Page', 'choice-uft') . '</a>' : '') .
                              $prefill_script,
            'message' => __('Avada test form ready.', 'choice-uft')
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
            return $this->get_fallback_form_response('elementor', $session_id, __('Elementor Pro is not active.', 'choice-uft'));
        }

        // Get test form page ID
        $page_id = get_option('cuft_test_elementor_page_id');

        if (!$page_id) {
            // Look for a page with Elementor forms
            $pages = get_posts(array(
                'post_type' => 'page',
                'posts_per_page' => 1,
                'meta_key' => '_elementor_edit_mode',
                'meta_value' => 'builder'
            ));

            if (!empty($pages)) {
                $page_id = $pages[0]->ID;
                update_option('cuft_test_elementor_page_id', $page_id);
            }
        }

        // Pre-fill data using JavaScript
        $prefill_script = '';
        if (!empty($test_data)) {
            $prefill_script = sprintf(
                '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(function() {
                        var emailField = document.querySelector(".elementor-form input[type=email]");
                        var phoneField = document.querySelector(".elementor-form input[type=tel]");
                        if (emailField) emailField.value = %s;
                        if (phoneField) phoneField.value = %s;
                    }, 500);
                });
                </script>',
                json_encode(isset($test_data['email']) ? $test_data['email'] : ''),
                json_encode(isset($test_data['phone']) ? $test_data['phone'] : '')
            );
        }

        return array(
            'framework' => 'elementor',
            'session_id' => $session_id,
            'page_id' => $page_id,
            'page_url' => $page_id ? get_permalink($page_id) : '',
            'rendered_html' => '<p>' . __('Elementor forms must be tested on their page.', 'choice-uft') . '</p>' .
                              ($page_id ? '<a href="' . get_permalink($page_id) . '" target="_blank" class="button">' . __('Open Test Page', 'choice-uft') . '</a>' : '') .
                              $prefill_script,
            'message' => __('Elementor test form ready.', 'choice-uft')
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