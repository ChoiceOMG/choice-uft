<?php
/**
 * Contract Test: Create Test Form Endpoint
 *
 * Tests the cuft_create_test_form AJAX endpoint contract compliance.
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
 * Test Create Form Endpoint Contract
 */
class CUFT_Test_Create_Form_Endpoint extends WP_UnitTestCase {

    /**
     * Admin user ID
     *
     * @var int
     */
    protected $admin_user_id;

    /**
     * Form builder AJAX handler
     *
     * @var CUFT_Form_Builder_Ajax
     */
    protected $ajax_handler;

    /**
     * Setup test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Create admin user
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator',
        ));

        // Load required classes
        require_once CUFT_PATH . 'includes/ajax/class-cuft-form-builder-ajax.php';
        require_once CUFT_PATH . 'includes/admin/class-cuft-form-builder.php';

        $this->ajax_handler = CUFT_Form_Builder_Ajax::get_instance();
    }

    /**
     * Test successful form creation response structure
     *
     * @expected FAIL - endpoint returns 501 "not implemented"
     */
    public function test_successful_form_creation() {
        // Set current user as admin
        wp_set_current_user($this->admin_user_id);

        // Set up request
        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['framework'] = 'elementor';
        $_POST['template_id'] = 'basic_contact_form';

        // Capture output
        ob_start();
        try {
            do_action('wp_ajax_cuft_create_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        // EXPECTED TO FAIL: Assert successful response
        $this->assertTrue($response['success'], 'Expected success response');

        // Validate response data structure
        $this->assertArrayHasKey('data', $response);
        $data = $response['data'];

        $this->assertArrayHasKey('instance_id', $data);
        $this->assertStringStartsWith('cuft_test_', $data['instance_id']);

        $this->assertArrayHasKey('framework', $data);
        $this->assertEquals('elementor', $data['framework']);

        $this->assertArrayHasKey('post_id', $data);
        $this->assertIsInt($data['post_id']);

        $this->assertArrayHasKey('form_id', $data);
        $this->assertNotEmpty($data['form_id']);

        $this->assertArrayHasKey('test_url', $data);
        $this->assertStringContainsString('/cuft-test-form/', $data['test_url']);

        $this->assertArrayHasKey('iframe_url', $data);
        $this->assertStringContainsString('test_mode=1', $data['iframe_url']);

        $this->assertArrayHasKey('created_at', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['created_at']);
    }

    /**
     * Test invalid framework error
     *
     * @expected FAIL - endpoint returns 501 instead of 400 with proper error
     */
    public function test_invalid_framework_error() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['framework'] = 'invalid_framework';
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

        // Should return error
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('code', $response['data']);
        $this->assertEquals('framework_unavailable', $response['data']['code']);
    }

    /**
     * Test nonce verification failure
     *
     * @expected PASS - security checks are already implemented
     */
    public function test_nonce_verification_failure() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_create_test_form';
        $_POST['nonce'] = 'invalid_nonce';
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

        $this->assertFalse($response['success']);
        $this->assertEquals('invalid_nonce', $response['data']['code']);
    }

    /**
     * Test insufficient permissions error
     *
     * @expected PASS - security checks are already implemented
     */
    public function test_insufficient_permissions() {
        // Create subscriber user (no manage_options capability)
        $subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($subscriber_id);

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

        $this->assertFalse($response['success']);
        $this->assertEquals('insufficient_permissions', $response['data']['code']);
    }

    /**
     * Test response structure validation
     *
     * @expected FAIL - endpoint not fully implemented
     */
    public function test_response_structure_matches_contract() {
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

        // Validate complete contract structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);

        if ($response['success']) {
            $required_fields = array(
                'instance_id',
                'framework',
                'post_id',
                'form_id',
                'test_url',
                'iframe_url',
                'created_at'
            );

            foreach ($required_fields as $field) {
                $this->assertArrayHasKey($field, $response['data'], "Missing required field: $field");
            }
        }
    }

    /**
     * Cleanup
     */
    public function tearDown(): void {
        parent::tearDown();
        unset($_POST);
    }
}
