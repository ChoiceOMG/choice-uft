<?php
/**
 * Test Events AJAX Handler
 *
 * Handles retrieval and deletion of test events.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CUFT Test Events AJAX Class
 */
class CUFT_Test_Events_Ajax {

    /**
     * Test events table instance
     *
     * @var CUFT_Test_Events_Table
     */
    private $events_table;

    /**
     * Constructor
     */
    public function __construct() {
        $this->events_table = new CUFT_Test_Events_Table();

        // Register AJAX handlers
        add_action('wp_ajax_cuft_get_test_events', array($this, 'handle_get_test_events'));
        add_action('wp_ajax_cuft_delete_test_events', array($this, 'handle_delete_test_events'));
    }

    /**
     * Handle test events retrieval AJAX request (T009)
     *
     * @return void
     */
    public function handle_get_test_events() {
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
            // Get filter parameters
            $filters = array();

            if (isset($_POST['session_id'])) {
                $filters['session_id'] = sanitize_text_field($_POST['session_id']);
            }

            if (isset($_POST['event_type'])) {
                $filters['event_type'] = sanitize_text_field($_POST['event_type']);
            }

            if (isset($_POST['limit'])) {
                $filters['limit'] = absint($_POST['limit']);
            }

            if (isset($_POST['offset'])) {
                $filters['offset'] = absint($_POST['offset']);
            }

            // Default limit if not specified
            if (!isset($filters['limit'])) {
                $filters['limit'] = 50;
            }

            // Get events
            $events = $this->events_table->get_events($filters);

            // Get total count for pagination
            $total_count = $this->events_table->get_events_count(array(
                'session_id' => isset($filters['session_id']) ? $filters['session_id'] : null,
                'event_type' => isset($filters['event_type']) ? $filters['event_type'] : null
            ));

            // Format response
            $response = array(
                'events' => $this->format_events_for_display($events),
                'total' => $total_count,
                'limit' => $filters['limit'],
                'offset' => isset($filters['offset']) ? $filters['offset'] : 0,
                'has_more' => $total_count > (isset($filters['offset']) ? $filters['offset'] : 0) + count($events)
            );

            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('CUFT: Failed to get test events - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to retrieve test events.', 'choice-uft'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Handle test events deletion AJAX request (T010)
     *
     * @return void
     */
    public function handle_delete_test_events() {
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
            $delete_type = isset($_POST['delete_type']) ? sanitize_text_field($_POST['delete_type']) : '';
            $deleted_count = 0;

            switch ($delete_type) {
                case 'by_ids':
                    // Delete specific event IDs
                    if (isset($_POST['event_ids']) && is_array($_POST['event_ids'])) {
                        $ids = array_map('absint', $_POST['event_ids']);
                        $deleted_count = $this->events_table->delete_by_id($ids);
                    }
                    break;

                case 'by_session':
                    // Delete all events from a session
                    if (isset($_POST['session_id'])) {
                        $session_id = sanitize_text_field($_POST['session_id']);
                        $deleted_count = $this->events_table->delete_by_session($session_id);
                    }
                    break;

                case 'all':
                    // Delete all test events
                    $deleted_count = $this->events_table->delete_all();
                    break;

                case 'old':
                    // Delete old events (older than 30 days by default)
                    $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
                    $deleted_count = $this->events_table->cleanup_old_events($days);
                    break;

                default:
                    wp_send_json_error(array(
                        'message' => __('Invalid delete type specified.', 'choice-uft')
                    ), 400);
                    return;
            }

            // Success response
            $response = array(
                'deleted_count' => $deleted_count,
                'message' => sprintf(
                    _n(
                        '%d test event deleted successfully.',
                        '%d test events deleted successfully.',
                        $deleted_count,
                        'choice-uft'
                    ),
                    $deleted_count
                )
            );

            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('CUFT: Failed to delete test events - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Failed to delete test events.', 'choice-uft'),
                'error' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Format events for display
     *
     * @param array $events Raw events from database
     * @return array Formatted events
     */
    private function format_events_for_display($events) {
        $formatted = array();

        foreach ($events as $event) {
            $formatted[] = array(
                'id' => $event->id,
                'session_id' => $event->session_id,
                'event_type' => $event->event_type,
                'event_data' => $event->event_data, // Already decoded in get_events()
                'test_mode' => (bool) $event->test_mode,
                'created_at' => $event->created_at,
                'created_at_formatted' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->created_at),
                'time_ago' => human_time_diff(strtotime($event->created_at), current_time('timestamp')) . ' ' . __('ago', 'choice-uft')
            );
        }

        return $formatted;
    }
}