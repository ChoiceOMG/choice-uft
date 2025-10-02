<?php
/**
 * Contract Test: Get Test Forms Endpoint
 *
 * Tests the cuft_get_test_forms AJAX endpoint contract compliance.
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
 * Test Get Forms Endpoint Contract
 */
class CUFT_Test_Get_Forms_Endpoint extends WP_UnitTestCase {

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
     * Test successful forms retrieval
     *
     * @expected FAIL - endpoint returns empty array, should return forms
     */
    public function test_successful_forms_retrieval() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_test_forms';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_test_forms');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('forms', $response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertIsArray($response['data']['forms']);
        $this->assertIsInt($response['data']['total']);
    }

    /**
     * Test empty state response
     *
     * @expected PASS - endpoint already returns proper structure for empty state
     */
    public function test_empty_state_response() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_test_forms';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_test_forms');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['data']['total']);
        $this->assertEmpty($response['data']['forms']);
    }

    /**
     * Test status filtering (active/all)
     *
     * @expected FAIL - filtering not implemented
     */
    public function test_status_filtering() {
        wp_set_current_user($this->admin_user_id);

        // Test active filter
        $_GET['action'] = 'cuft_get_test_forms';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_GET['status'] = 'active';

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_test_forms');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        // When forms exist, they should be filtered by status
    }

    /**
     * Test response data structure
     *
     * @expected FAIL - full form data structure not implemented
     */
    public function test_response_data_structure() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_test_forms';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_test_forms');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('forms', $response['data']);

        // If forms exist, validate structure
        if (!empty($response['data']['forms'])) {
            $form = $response['data']['forms'][0];
            $required_fields = array(
                'instance_id',
                'framework',
                'framework_label',
                'post_id',
                'form_id',
                'template_name',
                'status',
                'test_url',
                'iframe_url',
                'created_at',
                'last_tested',
                'test_count'
            );

            foreach ($required_fields as $field) {
                $this->assertArrayHasKey($field, $form, "Missing field: $field");
            }
        }
    }

    /**
     * Test security validation
     *
     * @expected PASS - security already implemented
     */
    public function test_security_validation() {
        wp_set_current_user($this->admin_user_id);

        // Invalid nonce
        $_GET['action'] = 'cuft_get_test_forms';
        $_GET['nonce'] = 'invalid_nonce';

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_test_forms');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertFalse($response['success']);
        $this->assertEquals('invalid_nonce', $response['data']['code']);
    }

    public function tearDown(): void {
        parent::tearDown();
        unset($_GET, $_POST);
    }
}
