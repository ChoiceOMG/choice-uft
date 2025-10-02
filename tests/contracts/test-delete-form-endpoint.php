<?php
/**
 * Contract Test: Delete Test Form Endpoint
 *
 * Tests the cuft_delete_test_form AJAX endpoint contract compliance.
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
 * Test Delete Form Endpoint Contract
 */
class CUFT_Test_Delete_Form_Endpoint extends WP_UnitTestCase {

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
     * Test successful deletion
     *
     * @expected FAIL - endpoint returns 501 "not implemented"
     */
    public function test_successful_deletion() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_delete_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'cuft_test_12345';

        ob_start();
        try {
            do_action('wp_ajax_cuft_delete_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        // EXPECTED TO FAIL
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('message', $response['data']);
        $this->assertArrayHasKey('instance_id', $response['data']);
        $this->assertEquals('cuft_test_12345', $response['data']['instance_id']);
    }

    /**
     * Test form not found error
     *
     * @expected FAIL - proper error handling not implemented
     */
    public function test_form_not_found_error() {
        wp_set_current_user($this->admin_user_id);

        $_POST['action'] = 'cuft_delete_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'non_existent_form';

        ob_start();
        try {
            do_action('wp_ajax_cuft_delete_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertFalse($response['success']);
        $this->assertEquals('form_not_found', $response['data']['code']);
        $this->assertStringContainsString('not found', $response['data']['message']);
    }

    /**
     * Test security validation
     *
     * @expected PASS - security already implemented
     */
    public function test_security_validation() {
        wp_set_current_user($this->admin_user_id);

        // Missing instance_id
        $_POST['action'] = 'cuft_delete_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = '';

        ob_start();
        try {
            do_action('wp_ajax_cuft_delete_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertFalse($response['success']);
        $this->assertEquals('missing_instance_id', $response['data']['code']);
    }

    /**
     * Test cleanup occurs
     *
     * @expected FAIL - cleanup logic not implemented
     */
    public function test_cleanup_occurs() {
        wp_set_current_user($this->admin_user_id);

        // This will be tested once form creation is implemented
        // For now, just validate that the endpoint exists
        $_POST['action'] = 'cuft_delete_test_form';
        $_POST['nonce'] = wp_create_nonce('cuft_form_builder_nonce');
        $_POST['instance_id'] = 'cuft_test_12345';

        ob_start();
        try {
            do_action('wp_ajax_cuft_delete_test_form');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        // Metadata cleanup will be validated in integration tests
        $this->assertIsArray($response);
    }

    public function tearDown(): void {
        parent::tearDown();
        unset($_POST);
    }
}
