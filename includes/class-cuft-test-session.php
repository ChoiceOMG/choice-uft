<?php
/**
 * CUFT Test Session Manager
 *
 * Manages ephemeral test sessions for form testing with transient storage.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Session Class
 */
class CUFT_Test_Session {

    /**
     * Session duration (1 hour)
     */
    const SESSION_DURATION = 3600;

    /**
     * Transient prefix
     */
    const TRANSIENT_PREFIX = 'cuft_test_session_';

    /**
     * Session ID
     *
     * @var string
     */
    private $session_id;

    /**
     * Session data
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     *
     * @param string $session_id Session ID (optional)
     */
    public function __construct($session_id = null) {
        if ($session_id) {
            $this->session_id = $session_id;
            $this->load();
        } else {
            $this->session_id = $this->generate_session_id();
            $this->init_data();
        }
    }

    /**
     * Generate unique session ID
     *
     * @return string
     */
    private function generate_session_id() {
        return 'cuft_session_' . time() . '_' . wp_rand(1000, 9999);
    }

    /**
     * Initialize session data
     *
     * @return void
     */
    private function init_data() {
        $this->data = array(
            'session_id' => $this->session_id,
            'created_at' => current_time('mysql'),
            'instance_id' => null,
            'framework' => null,
            'template_id' => null,
            'events' => array(),
            'validations' => array(),
            'form_data' => array(),
            'status' => 'active',
        );

        $this->save();
    }

    /**
     * Load session data from transient
     *
     * @return bool Success
     */
    private function load() {
        $transient_key = self::TRANSIENT_PREFIX . $this->session_id;
        $data = get_transient($transient_key);

        if ($data === false) {
            // Session expired or doesn't exist
            $this->init_data();
            return false;
        }

        $this->data = $data;
        return true;
    }

    /**
     * Save session data to transient
     *
     * @return bool Success
     */
    public function save() {
        $transient_key = self::TRANSIENT_PREFIX . $this->session_id;
        return set_transient($transient_key, $this->data, self::SESSION_DURATION);
    }

    /**
     * Get session ID
     *
     * @return string
     */
    public function get_session_id() {
        return $this->session_id;
    }

    /**
     * Set instance ID
     *
     * @param string $instance_id Instance ID
     * @return void
     */
    public function set_instance_id($instance_id) {
        $this->data['instance_id'] = $instance_id;
        $this->save();
    }

    /**
     * Set framework
     *
     * @param string $framework Framework identifier
     * @return void
     */
    public function set_framework($framework) {
        $this->data['framework'] = $framework;
        $this->save();
    }

    /**
     * Set template ID
     *
     * @param string $template_id Template ID
     * @return void
     */
    public function set_template_id($template_id) {
        $this->data['template_id'] = $template_id;
        $this->save();
    }

    /**
     * Add event to session
     *
     * @param array $event Event data
     * @return void
     */
    public function add_event($event) {
        if (!isset($this->data['events'])) {
            $this->data['events'] = array();
        }

        $this->data['events'][] = array_merge($event, array(
            'timestamp' => current_time('mysql'),
        ));

        $this->save();
    }

    /**
     * Add validation result
     *
     * @param array $validation Validation data
     * @return void
     */
    public function add_validation($validation) {
        if (!isset($this->data['validations'])) {
            $this->data['validations'] = array();
        }

        $this->data['validations'][] = array_merge($validation, array(
            'timestamp' => current_time('mysql'),
        ));

        $this->save();
    }

    /**
     * Set form data
     *
     * @param array $form_data Form data
     * @return void
     */
    public function set_form_data($form_data) {
        $this->data['form_data'] = $form_data;
        $this->save();
    }

    /**
     * Get all events
     *
     * @return array
     */
    public function get_events() {
        return isset($this->data['events']) ? $this->data['events'] : array();
    }

    /**
     * Get all validations
     *
     * @return array
     */
    public function get_validations() {
        return isset($this->data['validations']) ? $this->data['validations'] : array();
    }

    /**
     * Get form data
     *
     * @return array
     */
    public function get_form_data() {
        return isset($this->data['form_data']) ? $this->data['form_data'] : array();
    }

    /**
     * Get session data
     *
     * @return array
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Mark session as completed
     *
     * @return void
     */
    public function complete() {
        $this->data['status'] = 'completed';
        $this->data['completed_at'] = current_time('mysql');
        $this->save();
    }

    /**
     * Delete session
     *
     * @return bool Success
     */
    public function delete() {
        $transient_key = self::TRANSIENT_PREFIX . $this->session_id;
        return delete_transient($transient_key);
    }

    /**
     * Get session by ID (static)
     *
     * @param string $session_id Session ID
     * @return CUFT_Test_Session|null
     */
    public static function get_session($session_id) {
        $transient_key = self::TRANSIENT_PREFIX . $session_id;
        $data = get_transient($transient_key);

        if ($data === false) {
            return null;
        }

        return new self($session_id);
    }

    /**
     * Create new session (static)
     *
     * @param string $instance_id Instance ID
     * @param string $framework Framework identifier
     * @param string $template_id Template ID
     * @return CUFT_Test_Session
     */
    public static function create_session($instance_id, $framework, $template_id) {
        $session = new self();
        $session->set_instance_id($instance_id);
        $session->set_framework($framework);
        $session->set_template_id($template_id);

        return $session;
    }

    /**
     * Cleanup expired sessions
     *
     * Note: WordPress automatically deletes expired transients,
     * but this can be used for manual cleanup if needed.
     *
     * @return int Number of sessions cleaned up
     */
    public static function cleanup_expired_sessions() {
        global $wpdb;

        $count = 0;

        // Get all cuft_test_session transients
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                WHERE option_name LIKE %s",
                '_transient_' . self::TRANSIENT_PREFIX . '%'
            )
        );

        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);

            // Check if transient has expired
            if (get_transient($transient_name) === false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all active sessions
     *
     * @return array
     */
    public static function get_active_sessions() {
        global $wpdb;

        $sessions = array();

        // Get all cuft_test_session transients
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                WHERE option_name LIKE %s",
                '_transient_' . self::TRANSIENT_PREFIX . '%'
            )
        );

        foreach ($transients as $transient) {
            $data = maybe_unserialize($transient->option_value);
            if ($data && is_array($data)) {
                $sessions[] = $data;
            }
        }

        return $sessions;
    }
}
