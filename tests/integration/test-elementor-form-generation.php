<?php
/**
 * Integration Test: Elementor Form Generation
 *
 * End-to-end test for Elementor form creation workflow including
 * iframe loading, field population, and event capture.
 *
 * @package Choice_UTM_Form_Tracker
 * @subpackage Tests/Integration
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Elementor Form Generation Integration
 */
class CUFT_Test_Elementor_Form_Generation extends WP_UnitTestCase {

    protected $admin_user_id;
    protected $form_builder;
    protected $ajax_handler;
    protected $test_form_id;

    public function setUp(): void {
        parent::setUp();

        $this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));

        require_once CUFT_PATH . 'includes/ajax/class-cuft-form-builder-ajax.php';
        require_once CUFT_PATH . 'includes/admin/class-cuft-form-builder.php';

        $this->form_builder = CUFT_Form_Builder::get_instance();
        $this->ajax_handler = CUFT_Form_Builder_Ajax::get_instance();
    }

    /**
     * Test complete form creation workflow
     *
     * @expected FAIL - adapters not implemented yet
     */
    public function test_complete_form_creation_workflow() {
        wp_set_current_user($this->admin_user_id);

        // Step 1: Create form via AJAX
        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['framework'] = 'elementor';
        $_POST['template_id'] = 'basic_contact_form';

        ob_start();
        try {
            do_action('wp_ajax_cuft_create_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($output, true);
        }

        // EXPECTED TO FAIL until adapters are implemented
        $this->assertTrue($response['success'], 'Form creation should succeed');
        $this->assertArrayHasKey('instance_id', $response['data']);

        $this->test_form_id = $response['data']['instance_id'];

        // Step 2: Verify form post was created
        $query = new WP_Query(array(
            'meta_key' => '_cuft_instance_id',
            'meta_value' => $this->test_form_id,
            'post_type' => 'any',
            'posts_per_page' => 1
        ));

        $this->assertTrue($query->have_posts(), 'Form post should exist');

        // Step 3: Verify metadata
        if ($query->have_posts()) {
            $post_id = $query->posts[0]->ID;

            $is_test_form = get_post_meta($post_id, '_cuft_test_form', true);
            $framework = get_post_meta($post_id, '_cuft_framework', true);
            $template_id = get_post_meta($post_id, '_cuft_template_id', true);

            $this->assertEquals('1', $is_test_form);
            $this->assertEquals('elementor', $framework);
            $this->assertEquals('basic_contact_form', $template_id);
        }
    }

    /**
     * Test iframe loading with test mode
     *
     * @expected FAIL - test mode infrastructure not implemented
     */
    public function test_iframe_loading() {
        // This test validates that the iframe URL is properly formatted
        // and includes test_mode=1 parameter

        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['framework'] = 'elementor';
        $_POST['template_id'] = 'basic_contact_form';

        ob_start();
        try {
            do_action('wp_ajax_cuft_create_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        if ($response['success']) {
            $iframe_url = $response['data']['iframe_url'];

            // Validate iframe URL format
            $this->assertStringContainsString('test_mode=1', $iframe_url);
            $this->assertStringContainsString('form_id=', $iframe_url);

            // Parse URL and validate parameters
            $parsed = parse_url($iframe_url);
            parse_str($parsed['query'], $params);

            $this->assertEquals('1', $params['test_mode']);
            $this->assertNotEmpty($params['form_id']);
        }
    }

    /**
     * Test field population via postMessage
     *
     * @expected FAIL - populate endpoint not implemented
     */
    public function test_field_population() {
        wp_set_current_user($this->admin_user_id);

        // Assuming a form was created
        $instance_id = 'cuft_test_' . time();

        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = $instance_id;
        $_POST['use_test_data'] = true;

        ob_start();
        try {
            do_action('wp_ajax_cuft_populate_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        // EXPECTED TO FAIL until populate endpoint is implemented
        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['message_sent']);
        $this->assertArrayHasKey('test_data', $response['data']);

        // Validate test data structure
        $test_data = $response['data']['test_data'];
        $this->assertArrayHasKey('name', $test_data);
        $this->assertArrayHasKey('email', $test_data);
        $this->assertArrayHasKey('phone', $test_data);
        $this->assertArrayHasKey('message', $test_data);
    }

    /**
     * Test event capture from submission
     *
     * @expected FAIL - submission endpoint not implemented
     */
    public function test_event_capture() {
        wp_set_current_user($this->admin_user_id);

        $instance_id = 'cuft_test_' . time();

        $_POST['action'] = 'cuft_test_submit';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = $instance_id;
        $_POST['form_data'] = json_encode(array(
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '555-0123',
            'message' => 'Test message'
        ));
        $_POST['tracking_event'] = json_encode(array(
            'event' => 'form_submit',
            'form_type' => 'elementor',
            'form_id' => 'elementor-form-test',
            'form_name' => 'Test Form',
            'user_email' => 'test@example.com',
            'user_phone' => '555-0123',
            'submitted_at' => gmdate('c'),
            'cuft_tracked' => true,
            'cuft_source' => 'elementor_pro'
        ));

        ob_start();
        try {
            do_action('wp_ajax_cuft_test_submit');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        // EXPECTED TO FAIL until submission endpoint is implemented
        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['logged']);

        // Validate constitutional compliance
        $validation = $response['data']['validation'];
        $this->assertTrue($validation['has_cuft_tracked']);
        $this->assertTrue($validation['has_cuft_source']);
        $this->assertTrue($validation['uses_snake_case']);
    }

    /**
     * Test complete end-to-end flow
     *
     * @expected FAIL - full workflow not implemented
     */
    public function test_complete_flow() {
        wp_set_current_user($this->admin_user_id);

        // 1. Create form
        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['framework'] = 'elementor';
        $_POST['template_id'] = 'basic_contact_form';

        ob_start();
        try {
            do_action('wp_ajax_cuft_create_test_form');
            $output = ob_get_clean();
            $create_response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $create_response = json_decode($e->getMessage(), true);
        }

        if (!$create_response['success']) {
            $this->markTestSkipped('Form creation failed, skipping rest of flow');
            return;
        }

        $instance_id = $create_response['data']['instance_id'];

        // 2. Populate form
        unset($_POST);
        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = $instance_id;
        $_POST['use_test_data'] = true;

        ob_start();
        try {
            do_action('wp_ajax_cuft_populate_form');
            $output = ob_get_clean();
            $populate_response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $populate_response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($populate_response['success']);

        // 3. Submit form
        $test_data = $populate_response['data']['test_data'];

        unset($_POST);
        $_POST['action'] = 'cuft_test_submit';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = $instance_id;
        $_POST['form_data'] = json_encode($test_data);
        $_POST['tracking_event'] = json_encode(array(
            'event' => 'form_submit',
            'form_type' => 'elementor',
            'cuft_tracked' => true,
            'cuft_source' => 'elementor_pro'
        ));

        ob_start();
        try {
            do_action('wp_ajax_cuft_test_submit');
            $output = ob_get_clean();
            $submit_response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $submit_response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($submit_response['success']);

        // 4. Delete form
        unset($_POST);
        $_POST['action'] = 'cuft_delete_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = $instance_id;

        ob_start();
        try {
            do_action('wp_ajax_cuft_delete_test_form');
            $output = ob_get_clean();
            $delete_response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $delete_response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($delete_response['success']);
    }

    public function tearDown(): void {
        // Cleanup any test forms created
        if ($this->test_form_id) {
            $query = new WP_Query(array(
                'meta_key' => '_cuft_instance_id',
                'meta_value' => $this->test_form_id,
                'post_type' => 'any'
            ));

            if ($query->have_posts()) {
                wp_delete_post($query->posts[0]->ID, true);
            }
        }

        parent::tearDown();
        unset($_POST, $_GET);
    }
}
