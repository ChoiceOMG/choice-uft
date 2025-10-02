<?php
/**
 * CUFT Test Mode Manager
 *
 * Manages test mode functionality to prevent real form submissions
 * during testing while allowing tracking validation.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Mode Manager Class
 */
class CUFT_Test_Mode {

    /**
     * Singleton instance
     *
     * @var CUFT_Test_Mode
     */
    private static $instance = null;

    /**
     * Test mode enabled flag
     *
     * @var bool
     */
    private $test_mode_enabled = false;

    /**
     * Get singleton instance
     *
     * @return CUFT_Test_Mode
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->detect_test_mode();

        if ($this->test_mode_enabled) {
            $this->init_test_mode();
        }
    }

    /**
     * Detect if test mode is enabled
     *
     * @return void
     */
    private function detect_test_mode() {
        // Check URL parameter
        if (isset($_GET['test_mode']) && $_GET['test_mode'] === '1') {
            $this->test_mode_enabled = true;
        }

        // Check if viewing a test form (has _cuft_test_form meta)
        global $post;
        if ($post && get_post_meta($post->ID, '_cuft_test_form', true) === '1') {
            $this->test_mode_enabled = true;
        }
    }

    /**
     * Initialize test mode hooks
     *
     * @return void
     */
    private function init_test_mode() {
        // Contact Form 7 - Skip mail
        add_filter('wpcf7_skip_mail', array($this, 'prevent_cf7_mail'), 10, 2);

        // Gravity Forms - Skip email
        add_filter('gform_pre_send_email', array($this, 'prevent_gravity_email'), 10, 4);

        // Ninja Forms - Skip actions
        add_filter('ninja_forms_submit_data', array($this, 'prevent_ninja_actions'), 10, 1);

        // Elementor Pro - Skip actions
        add_action('elementor_pro/forms/validation', array($this, 'prevent_elementor_actions'), 10, 2);

        // Add test mode indicator
        add_action('wp_footer', array($this, 'add_test_mode_indicator'));

        // Prevent real form actions
        add_action('init', array($this, 'register_prevention_hooks'));
    }

    /**
     * Check if test mode is enabled
     *
     * @return bool
     */
    public function is_test_mode() {
        return $this->test_mode_enabled;
    }

    /**
     * Prevent Contact Form 7 mail
     *
     * @param bool $skip_mail Skip mail flag
     * @param WPCF7_ContactForm $contact_form Contact form instance
     * @return bool
     */
    public function prevent_cf7_mail($skip_mail, $contact_form) {
        if ($this->test_mode_enabled) {
            return true; // Skip sending mail
        }
        return $skip_mail;
    }

    /**
     * Prevent Gravity Forms email
     *
     * @param array $email Email data
     * @param array $message_format Message format
     * @param array $notification Notification settings
     * @param array $entry Entry data
     * @return array|null
     */
    public function prevent_gravity_email($email, $message_format, $notification, $entry) {
        if ($this->test_mode_enabled) {
            return null; // Abort email
        }
        return $email;
    }

    /**
     * Prevent Ninja Forms actions
     *
     * @param array $form_data Form data
     * @return array
     */
    public function prevent_ninja_actions($form_data) {
        if ($this->test_mode_enabled) {
            // Mark as test submission
            $form_data['test_mode'] = true;

            // Disable email actions
            if (isset($form_data['actions'])) {
                foreach ($form_data['actions'] as $key => $action) {
                    if (isset($action['type']) && $action['type'] === 'email') {
                        unset($form_data['actions'][$key]);
                    }
                }
            }
        }
        return $form_data;
    }

    /**
     * Prevent Elementor Pro actions
     *
     * @param object $record Form record
     * @param object $ajax_handler AJAX handler
     * @return void
     */
    public function prevent_elementor_actions($record, $ajax_handler) {
        if ($this->test_mode_enabled) {
            // Remove email actions
            $record->remove_action('email');
            $record->remove_action('email2');

            // Remove webhook actions
            $record->remove_action('webhook');

            // Remove redirect actions
            $record->remove_action('redirect');
        }
    }

    /**
     * Register prevention hooks for all forms
     *
     * @return void
     */
    public function register_prevention_hooks() {
        if (!$this->test_mode_enabled) {
            return;
        }

        // Prevent mail() function calls in test mode
        if (!function_exists('wp_mail')) {
            /**
             * Override wp_mail in test mode
             *
             * @return bool
             */
            function wp_mail() {
                if (CUFT_Test_Mode::get_instance()->is_test_mode()) {
                    return true; // Fake success
                }
                return false;
            }
        }
    }

    /**
     * Add test mode visual indicator
     *
     * @return void
     */
    public function add_test_mode_indicator() {
        if (!$this->test_mode_enabled) {
            return;
        }

        ?>
        <style>
            .cuft-test-mode-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #ff9800;
                color: #fff;
                padding: 10px;
                text-align: center;
                font-weight: bold;
                z-index: 99999;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .cuft-test-mode-indicator::before {
                content: "🧪 ";
            }
        </style>
        <div class="cuft-test-mode-indicator">
            TEST MODE ACTIVE - No emails will be sent, no real actions will be performed
        </div>
        <?php
    }

    /**
     * Get test mode status for JavaScript
     *
     * @return array
     */
    public static function get_js_config() {
        $instance = self::get_instance();

        return array(
            'enabled' => $instance->is_test_mode(),
            'prevent_emails' => true,
            'prevent_webhooks' => true,
            'prevent_redirects' => true,
        );
    }
}

// Initialize test mode
CUFT_Test_Mode::get_instance();
