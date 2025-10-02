<?php
/**
 * Contract Test: Populate Form Endpoint
 *
 * Tests the cuft_populate_form AJAX endpoint contract compliance.
 * These tests MUST FAIL until the endpoint is fully implemented.
 *
 * @package Choice_UTM_Form_Tracker
 * @subpackage Tests/Contracts
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Populate Form Endpoint Contract
 */
class CUFT_Test_Populate_Form_Endpoint extends WP_UnitTestCase {

    protected $admin_user_id;
    protected $ajax_handler;

    public function setUp(): void {
        parent::setUp();

        $this->admin_user_id = $this->factory->user->create(array('role' => 'administrator'));

        require_once CUFT_PATH . 'includes/ajax/class-cuft-form-builder-ajax.php';
        require_once CUFT_PATH . 'includes/admin/class-cuft-form-builder.php';

        $this->ajax_handler = CUFT_Form_Builder_Ajax::get_instance();
    }

    /**
     * Test postMessage sending
     *
     * @expected FAIL - endpoint returns 501 "not implemented"
     */
    public function test_postmessage_sending() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'cuft_test_12345';
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

        // EXPECTED TO FAIL
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('message_sent', $response['data']);
        $this->assertTrue($response['data']['message_sent']);
    }

    /**
     * Test data format validation
     *
     * @expected FAIL - data structure not implemented
     */
    public function test_data_format_validation() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'cuft_test_12345';
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

        if ($response['success']) {
            $this->assertArrayHasKey('test_data', $response['data']);
            $test_data = $response['data']['test_data'];

            // Validate test data structure
            $this->assertArrayHasKey('name', $test_data);
            $this->assertArrayHasKey('email', $test_data);
            $this->assertArrayHasKey('phone', $test_data);
            $this->assertArrayHasKey('message', $test_data);

            // Validate email format
            $this->assertMatchesRegularExpression('/^test-\d+@example\.com$/', $test_data['email']);

            // Validate phone format
            $this->assertMatchesRegularExpression('/^\d{3}-\d{4}$/', $test_data['phone']);
        }
    }

    /**
     * Test nonce verification
     *
     * @expected PASS - security already implemented
     */
    public function test_nonce_verification() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['instance_id'] = 'cuft_test_12345';

        ob_start();
        try {
            do_action('wp_ajax_cuft_populate_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertFalse($response['success']);
        $this->assertEquals('invalid_nonce', $response['data']['code']);
    }

    /**
     * Test response includes postMessage payload structure
     *
     * @expected FAIL - payload structure not implemented
     */
    public function test_postmessage_payload_structure() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_populate_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'cuft_test_12345';
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

        if ($response['success'] && isset($response['data']['test_data'])) {
            $test_data = $response['data']['test_data'];

            // Basic fields that should exist
            $required_fields = array('name', 'email', 'phone', 'message');
            foreach ($required_fields as $field) {
                $this->assertArrayHasKey($field, $test_data, "Missing required field: $field");
                $this->assertNotEmpty($test_data[$field], "Field $field should not be empty");
            }
        }
    }

    public function tearDown(): void {
        parent::tearDown();
        unset($_POST);
    }
}
