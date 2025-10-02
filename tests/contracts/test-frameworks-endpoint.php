<?php
/**
 * Contract Test: Framework Capabilities Endpoint
 *
 * Tests the cuft_get_frameworks AJAX endpoint contract compliance.
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
 * Test Frameworks Endpoint Contract
 */
class CUFT_Test_Frameworks_Endpoint extends WP_UnitTestCase {

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
     * Test framework detection accuracy
     *
     * @expected PASS - detection already implemented in CUFT_Form_Builder
     */
    public function test_framework_detection_accuracy() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('frameworks', $response['data']);
        $this->assertIsArray($response['data']['frameworks']);
    }

    /**
     * Test availability status
     *
     * @expected PASS - availability checking already implemented
     */
    public function test_availability_status() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        if ($response['success']) {
            $frameworks = $response['data']['frameworks'];

            foreach ($frameworks as $key => $framework) {
                $this->assertArrayHasKey('available', $framework);
                $this->assertIsBool($framework['available']);
            }
        }
    }

    /**
     * Test version reporting
     *
     * @expected PASS - version detection already implemented
     */
    public function test_version_reporting() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        if ($response['success']) {
            $frameworks = $response['data']['frameworks'];

            foreach ($frameworks as $key => $framework) {
                $this->assertArrayHasKey('version', $framework);
                // Version can be null if framework not installed
                if ($framework['available']) {
                    $this->assertNotNull($framework['version']);
                }
            }
        }
    }

    /**
     * Test response structure validation
     *
     * @expected PASS - structure already defined in CUFT_Form_Builder
     */
    public function test_response_structure_validation() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('frameworks', $response['data']);

        $frameworks = $response['data']['frameworks'];

        foreach ($frameworks as $key => $framework) {
            $required_fields = array(
                'name',
                'version',
                'available',
                'supports_generation',
                'icon'
            );

            foreach ($required_fields as $field) {
                $this->assertArrayHasKey($field, $framework, "Missing field: $field in framework $key");
            }

            // Validate data types
            $this->assertIsString($framework['name']);
            $this->assertIsBool($framework['available']);
            $this->assertIsBool($framework['supports_generation']);
            $this->assertIsString($framework['icon']);
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
        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = 'invalid_nonce';

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
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
     * Test framework support indicators
     *
     * @expected PASS - supports_generation already set in detect_frameworks
     */
    public function test_framework_support_indicators() {
        wp_set_current_user($this->admin_user_id);

        $_GET['action'] = 'cuft_get_frameworks';
        $_GET['nonce'] = wp_create_nonce('cuft_form_builder_nonce');

        ob_start();
        try {
            do_action('wp_ajax_cuft_get_frameworks');
            $output = ob_get_clean();
            $response = json_decode($output, true);
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($e->getMessage(), true);
        }

        if ($response['success']) {
            $frameworks = $response['data']['frameworks'];

            foreach ($frameworks as $key => $framework) {
                // All detected frameworks should support generation
                if ($framework['available']) {
                    $this->assertTrue(
                        $framework['supports_generation'],
                        "Framework $key should support generation when available"
                    );
                }
            }
        }
    }

    public function tearDown(): void {
        parent::tearDown();
        unset($_GET);
    }
}
