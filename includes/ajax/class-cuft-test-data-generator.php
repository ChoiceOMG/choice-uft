<?php
/**
 * Test Data Generator AJAX Handler
 *
 * Generates realistic test data for conversion tracking testing.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Test Data Generator Class
 */
class CUFT_Test_Data_Generator {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_cuft_generate_test_data', array($this, 'handle_generate_test_data'));
    }

    /**
     * Handle test data generation AJAX request
     *
     * @return void
     */
    public function handle_generate_test_data() {
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

            // Generate session ID
            $session_id = 'test_' . uniqid();

            // Generate click IDs
            $click_ids = $this->generate_click_ids();

            // Generate UTM parameters
            $utm_params = $this->generate_utm_params();

            // Generate contact info
            $contact_info = $this->generate_contact_info();

            // Calculate execution time
            $execution_time = (microtime(true) - $start_time) * 1000; // Convert to ms

            // Prepare response
            $response = array(
                'success' => true,
                'session_id' => $session_id,
                'generated_at' => current_time('mysql'),
                'click_ids' => $click_ids,
                'utm_params' => $utm_params,
                'contact' => $contact_info,
                'execution_time_ms' => round($execution_time, 2)
            );

            // Performance check: Must be under 500ms
            if ($execution_time > 500) {
                error_log('CUFT Test Data Generator: Performance warning - execution time ' . $execution_time . 'ms');
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('CUFT Test Data Generator Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to generate test data.', 'choice-uft'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Generate realistic click IDs
     * Only include the primary click IDs that are commonly used
     *
     * @return array
     */
    private function generate_click_ids() {
        // Only generate one or two click IDs to be more realistic
        $click_ids = array();

        // Always generate a generic click_id
        $click_ids['click_id'] = 'test_' . substr(md5(uniqid()), 0, 16);

        // Randomly pick one platform-specific click ID
        $rand = rand(1, 3);
        switch($rand) {
            case 1:
                // Google Ads
                $click_ids['gclid'] = 'Cj0KCQiA' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 20);
                break;
            case 2:
                // Facebook/Meta
                $click_ids['fbclid'] = 'IwAR' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-'), 0, 25);
                break;
            case 3:
                // Microsoft/Bing
                $click_ids['msclkid'] = substr(md5(uniqid('ms', true)), 0, 32);
                break;
        }

        return $click_ids;
    }

    /**
     * Generate realistic UTM parameters
     * Only include when it makes sense (matching the click ID source)
     *
     * @return array
     */
    private function generate_utm_params() {
        $campaigns = array('summer_sale', 'black_friday', 'new_product', 'brand_awareness', 'q4_push', 'holiday_promo');

        // Simplified, more realistic UTM params
        return array(
            'utm_campaign' => $campaigns[array_rand($campaigns)] . '_' . date('Y')
        );
    }

    /**
     * Generate test contact information
     *
     * @return array
     */
    private function generate_contact_info() {
        $unique_id = substr(md5(uniqid()), 0, 6);
        $first_names = array('John', 'Jane', 'Test', 'Demo', 'Sample');
        $last_names = array('Doe', 'Smith', 'User', 'Tester', 'Example');

        $first = $first_names[array_rand($first_names)];
        $last = $last_names[array_rand($last_names)];

        return array(
            'name' => $first . ' ' . $last,
            'email' => strtolower($first) . '+test_' . $unique_id . '@example.com',
            'phone' => '555-01' . sprintf('%02d-%04d', rand(0, 99), rand(0, 9999)),
            'company' => 'Test Company ' . rand(1, 999),
            'message' => 'This is a test message generated at ' . current_time('mysql') . ' for testing conversion tracking functionality.'
        );
    }
}