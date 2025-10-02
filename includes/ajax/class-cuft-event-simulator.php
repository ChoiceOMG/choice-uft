<?php
/**
 * Event Simulator AJAX Handler
 *
 * Simulates various tracking events for testing purposes.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Event Simulator Class
 */
class CUFT_Event_Simulator {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_cuft_simulate_event', array($this, 'handle_simulate_event'));
    }

    /**
     * Handle event simulation AJAX request
     *
     * @return void
     */
    public function handle_simulate_event() {
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

        // Get event type from request
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : 'test_' . uniqid();
        $test_data = isset($_POST['test_data']) ? json_decode(stripslashes($_POST['test_data']), true) : array();

        // Validate event type
        $valid_event_types = array('phone_click', 'email_click', 'form_submit', 'generate_lead');
        if (!in_array($event_type, $valid_event_types, true)) {
            wp_send_json_error(array(
                'message' => __('Invalid event type.', 'choice-uft'),
                'event_type' => $event_type
            ), 400);
        }

        try {
            // Start timer for performance tracking
            $start_time = microtime(true);

            // Build event based on type
            $event = $this->build_event($event_type, $test_data);

            // Add mandatory fields
            $event['cuft_tracked'] = true;
            $event['cuft_source'] = 'testing_dashboard';
            $event['test_mode'] = true;
            $event['session_id'] = $session_id;
            $event['timestamp'] = current_time('c'); // ISO 8601 format

            // Save to test events database
            $db_id = $this->save_test_event($session_id, $event_type, $event);

            // Calculate execution time
            $execution_time = (microtime(true) - $start_time) * 1000; // Convert to ms

            // Prepare response
            $response = array(
                'success' => true,
                'event' => $event,
                'db_id' => $db_id,
                'session_id' => $session_id,
                'execution_time_ms' => round($execution_time, 2)
            );

            // Performance check: Must be under 500ms
            if ($execution_time > 500) {
                error_log('CUFT Event Simulator: Performance warning - execution time ' . $execution_time . 'ms');
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('CUFT Event Simulator Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to simulate event.', 'choice-uft'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Build event object based on type
     *
     * @param string $event_type Event type
     * @param array $test_data Test data from generator
     * @return array
     */
    private function build_event($event_type, $test_data) {
        $event = array(
            'event' => $event_type
        );

        // Add common tracking parameters
        if (!empty($test_data['click_ids'])) {
            foreach ($test_data['click_ids'] as $key => $value) {
                if (!empty($value)) {
                    $event[$key] = $value;
                }
            }
        }

        if (!empty($test_data['utm_params'])) {
            foreach ($test_data['utm_params'] as $key => $value) {
                if (!empty($value)) {
                    $event[$key] = $value;
                }
            }
        }

        // Add event-specific fields matching actual plugin output
        switch ($event_type) {
            case 'phone_click':
                // Match the actual structure from cuft-links.js
                $phone_number = !empty($test_data['contact']['phone'])
                    ? $test_data['contact']['phone']
                    : '555-0123-4567';

                $event['clicked_phone'] = $phone_number;
                $event['href'] = 'tel:' . $phone_number;
                $event['clickedAt'] = date('c'); // ISO 8601 format
                $event['cuft_source'] = 'link_tracking';

                // Add GA4 standard parameters
                $event['page_location'] = admin_url('options-general.php?page=cuft-testing-dashboard');
                $event['page_referrer'] = '';
                $event['page_title'] = 'CUFT Testing Dashboard';
                $event['language'] = get_locale();
                $event['screen_resolution'] = '1920x1080'; // Mock value for testing
                $event['engagement_time_msec'] = rand(1000, 10000); // Random engagement time
                break;

            case 'email_click':
                // Match the actual structure from cuft-links.js
                $email = !empty($test_data['contact']['email'])
                    ? $test_data['contact']['email']
                    : 'test@example.com';

                $event['clicked_email'] = $email;
                $event['href'] = 'mailto:' . $email;
                $event['clickedAt'] = date('c');
                $event['cuft_source'] = 'link_tracking';

                // Add GA4 standard parameters
                $event['page_location'] = admin_url('options-general.php?page=cuft-testing-dashboard');
                $event['page_referrer'] = '';
                $event['page_title'] = 'CUFT Testing Dashboard';
                $event['language'] = get_locale();
                $event['screen_resolution'] = '1920x1080';
                $event['engagement_time_msec'] = rand(1000, 10000);
                break;

            case 'form_submit':
                // Match the actual structure from cuft-dataLayer-utils.js
                $event['form_type'] = 'elementor'; // Simulate Elementor form
                $event['form_id'] = 'test-form-' . substr(md5(uniqid()), 0, 8);
                $event['submitted_at'] = date('c');
                $event['cuft_source'] = 'elementor_pro';

                // Optional fields (only add if available)
                $event['form_name'] = 'Test Contact Form';

                if (!empty($test_data['contact']['email'])) {
                    $event['user_email'] = $test_data['contact']['email'];
                }

                if (!empty($test_data['contact']['phone'])) {
                    // Sanitize phone number like the actual plugin does
                    $event['user_phone'] = preg_replace('/[^0-9+]/', '', $test_data['contact']['phone']);
                }
                break;

            case 'generate_lead':
                // generate_lead includes all form_submit fields plus specific additions
                $event['form_type'] = 'elementor';
                $event['form_id'] = 'test-form-' . substr(md5(uniqid()), 0, 8);
                $event['submitted_at'] = date('c');
                $event['cuft_source'] = 'elementor_pro_lead';
                $event['form_name'] = 'Test Lead Form';

                // Required fields for generate_lead
                $event['user_email'] = !empty($test_data['contact']['email'])
                    ? $test_data['contact']['email']
                    : 'lead+' . uniqid() . '@example.com';
                $event['user_phone'] = !empty($test_data['contact']['phone'])
                    ? preg_replace('/[^0-9+]/', '', $test_data['contact']['phone'])
                    : '5550199999';

                // Lead-specific fields
                $event['currency'] = get_option('cuft_lead_currency', 'CAD');
                $event['value'] = floatval(get_option('cuft_lead_value', 100));

                // Ensure we have at least one click ID for lead generation (required)
                if (empty($event['click_id']) && empty($event['gclid']) && empty($event['fbclid']) && empty($event['msclkid'])) {
                    $event['click_id'] = 'test_lead_' . uniqid();
                }
                break;
        }

        return $event;
    }

    /**
     * Save test event to database
     *
     * @param string $session_id Session ID
     * @param string $event_type Event type
     * @param array $event_data Event data
     * @return int|bool Database ID or false on failure
     */
    private function save_test_event($session_id, $event_type, $event_data) {
        global $wpdb;

        // Ensure test events table exists
        if (!class_exists('CUFT_Test_Events_Table')) {
            require_once CUFT_PATH . 'includes/database/class-cuft-test-events-table.php';
        }

        $test_events_table = new CUFT_Test_Events_Table();
        $test_events_table->maybe_update();

        $table_name = $wpdb->prefix . 'cuft_test_events';

        // Insert event
        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'event_type' => $event_type,
                'event_data' => wp_json_encode($event_data),
                'test_mode' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            error_log('CUFT Event Simulator: Failed to save event to database - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }
}