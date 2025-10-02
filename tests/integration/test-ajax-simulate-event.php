<?php
/**
 * Test AJAX Simulate Event Endpoint
 *
 * Tests for the event simulation AJAX endpoint.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

class Test_Ajax_Simulate_Event extends WP_Ajax_UnitTestCase {

    /**
     * Admin user ID
     *
     * @var int
     */
    private $admin_user_id;

    /**
     * Test events table
     *
     * @var CUFT_Test_Events_Table
     */
    private $events_table;

    /**
     * Sample test data
     *
     * @var array
     */
    private $test_data;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        // Create admin user
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));

        // Initialize components
        new CUFT_Event_Simulator();
        $this->events_table = new CUFT_Test_Events_Table();

        // Ensure test events table exists
        $this->events_table->maybe_update();

        // Create sample test data
        $this->test_data = array(
            'click_ids' => array(
                'click_id' => 'test_' . uniqid(),
                'gclid' => 'Cj0KCQiA' . substr(md5(uniqid()), 0, 20)
            ),
            'utm_params' => array(
                'utm_campaign' => 'test_campaign_' . date('Y')
            ),
            'contact' => array(
                'email' => 'test+' . uniqid() . '@example.com',
                'phone' => '555-0123-4567',
                'name' => 'Test User'
            )
        );
    }

    /**
     * Test successful form_submit event simulation
     *
     * @test
     */
    public function test_simulate_form_submit_event() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Set up request
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'form_submit';
        $_POST['session_id'] = 'test_session_' . uniqid();
        $_POST['test_data'] = json_encode($this->test_data);

        // Start timing
        $start_time = microtime(true);

        // Make AJAX call
        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        // Check execution time
        $execution_time = (microtime(true) - $start_time) * 1000;
        $this->assertLessThan(500, $execution_time, 'Response time should be under 500ms');

        // Get response
        $response = json_decode($this->_last_response, true);

        // Assert success
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);

        $data = $response['data'];

        // Check response structure
        $this->assertArrayHasKey('event', $data);
        $this->assertArrayHasKey('db_id', $data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertArrayHasKey('execution_time_ms', $data);

        // Validate event object
        $event = $data['event'];
        $this->assertEquals('form_submit', $event['event']);
        $this->assertTrue($event['cuft_tracked']);
        $this->assertEquals('elementor_pro', $event['cuft_source']);
        $this->assertTrue($event['test_mode']);
        $this->assertArrayHasKey('form_type', $event);
        $this->assertArrayHasKey('form_id', $event);
        $this->assertArrayHasKey('submitted_at', $event);

        // Check that test data was included
        $this->assertEquals($this->test_data['contact']['email'], $event['user_email']);
        $this->assertEquals('5550123456', $event['user_phone']); // Should be sanitized

        // Check that event was saved to database
        $saved_events = $this->events_table->get_events_by_session($_POST['session_id']);
        $this->assertCount(1, $saved_events);
        $this->assertEquals('form_submit', $saved_events[0]->event_type);
    }

    /**
     * Test phone_click event simulation
     *
     * @test
     */
    public function test_simulate_phone_click_event() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'phone_click';
        $_POST['session_id'] = 'test_session_' . uniqid();
        $_POST['test_data'] = json_encode($this->test_data);

        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);

        $event = $response['data']['event'];
        $this->assertEquals('phone_click', $event['event']);
        $this->assertEquals('link_tracking', $event['cuft_source']);
        $this->assertArrayHasKey('clicked_phone', $event);
        $this->assertArrayHasKey('href', $event);
        $this->assertStringStartsWith('tel:', $event['href']);
        $this->assertArrayHasKey('page_location', $event); // GA4 params
    }

    /**
     * Test email_click event simulation
     *
     * @test
     */
    public function test_simulate_email_click_event() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'email_click';
        $_POST['session_id'] = 'test_session_' . uniqid();
        $_POST['test_data'] = json_encode($this->test_data);

        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);

        $event = $response['data']['event'];
        $this->assertEquals('email_click', $event['event']);
        $this->assertEquals('link_tracking', $event['cuft_source']);
        $this->assertArrayHasKey('clicked_email', $event);
        $this->assertArrayHasKey('href', $event);
        $this->assertStringStartsWith('mailto:', $event['href']);
    }

    /**
     * Test generate_lead event simulation
     *
     * @test
     */
    public function test_simulate_generate_lead_event() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'generate_lead';
        $_POST['session_id'] = 'test_session_' . uniqid();
        $_POST['test_data'] = json_encode($this->test_data);

        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);

        $event = $response['data']['event'];
        $this->assertEquals('generate_lead', $event['event']);
        $this->assertEquals('elementor_pro_lead', $event['cuft_source']);
        $this->assertArrayHasKey('currency', $event);
        $this->assertArrayHasKey('value', $event);
        $this->assertArrayHasKey('user_email', $event);
        $this->assertArrayHasKey('user_phone', $event);

        // Must have at least one click ID for lead generation
        $has_click_id = isset($event['click_id']) ||
                        isset($event['gclid']) ||
                        isset($event['fbclid']) ||
                        isset($event['msclkid']);
        $this->assertTrue($has_click_id, 'Generate lead must have at least one click ID');
    }

    /**
     * Test invalid event type rejection
     *
     * @test
     */
    public function test_invalid_event_type_rejected() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'invalid_event';
        $_POST['session_id'] = 'test_session_' . uniqid();

        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success']);
        $this->assertContains('Invalid event type', $response['data']['message']);
    }

    /**
     * Test that events are saved to test events table
     *
     * @test
     */
    public function test_events_saved_to_database() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        $session_id = 'test_session_' . uniqid();

        // Simulate multiple events
        $event_types = array('phone_click', 'email_click', 'form_submit', 'generate_lead');

        foreach ($event_types as $event_type) {
            $_POST['event_type'] = $event_type;
            $_POST['session_id'] = $session_id;
            $_POST['test_data'] = json_encode($this->test_data);

            try {
                $this->_handleAjax('cuft_simulate_event');
            } catch (WPAjaxDieContinueException $e) {
                // Expected
            }

            $response = json_decode($this->_last_response, true);
            $this->assertTrue($response['success']);
            $this->assertNotEmpty($response['data']['db_id']);
        }

        // Check all events were saved
        $saved_events = $this->events_table->get_events_by_session($session_id);
        $this->assertCount(4, $saved_events);

        // Verify event types
        $saved_types = array_map(function($event) {
            return $event->event_type;
        }, $saved_events);

        $this->assertEquals($event_types, $saved_types);
    }

    /**
     * Test non-admin cannot simulate events
     *
     * @test
     */
    public function test_non_admin_cannot_simulate_events() {
        $editor_id = $this->factory->user->create(array('role' => 'editor'));
        wp_set_current_user($editor_id);

        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');
        $_POST['event_type'] = 'form_submit';

        try {
            $this->_handleAjax('cuft_simulate_event');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success']);
        $this->assertEquals(403, wp_remote_retrieve_response_code($response));
    }

    /**
     * Test performance requirement for all event types
     *
     * @test
     */
    public function test_all_events_performance_under_500ms() {
        wp_set_current_user($this->admin_user_id);
        $_REQUEST['nonce'] = wp_create_nonce('cuft-testing-dashboard');

        $event_types = array('phone_click', 'email_click', 'form_submit', 'generate_lead');

        foreach ($event_types as $event_type) {
            $_POST['event_type'] = $event_type;
            $_POST['session_id'] = 'test_' . uniqid();
            $_POST['test_data'] = json_encode($this->test_data);

            $start = microtime(true);

            try {
                $this->_handleAjax('cuft_simulate_event');
            } catch (WPAjaxDieContinueException $e) {
                // Expected
            }

            $time = (microtime(true) - $start) * 1000;
            $this->assertLessThan(500, $time,
                "Event type {$event_type} took {$time}ms, should be under 500ms");
        }
    }
}