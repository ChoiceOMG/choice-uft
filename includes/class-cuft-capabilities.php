<?php
/**
 * Choice Universal Form Tracker - Capability Manager
 *
 * Manages WordPress capability checks for all admin actions.
 * Ensures only authorized users can perform update operations.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Capability Manager Class
 *
 * Provides centralized capability checking
 */
class CUFT_Capabilities {
    /**
     * Capability requirements for different actions
     *
     * @var array
     */
    private static $action_capabilities = array(
        'update_check' => 'update_plugins',
        'update_perform' => 'update_plugins',
        'update_rollback' => 'update_plugins',
        'update_settings' => 'manage_options',
        'view_update_status' => 'update_plugins',
        'view_update_history' => 'manage_options',
        'test_connection' => 'manage_options',
        'clear_cache' => 'manage_options'
    );

    /**
     * Check if current user can perform action
     *
     * @param string $action Action to check
     * @return bool True if user has capability
     */
    public static function current_user_can($action) {
        try {
            // Get required capability
            $capability = self::get_required_capability($action);

            if (!$capability) {
                // Unknown action, require administrator
                return current_user_can('manage_options');
            }

            // Check capability
            return current_user_can($capability);

        } catch (Exception $e) {
            error_log('CUFT Capabilities Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get required capability for action
     *
     * @param string $action Action name
     * @return string|null Capability name or null if not found
     */
    public static function get_required_capability($action) {
        // Normalize action name
        $action = strtolower(trim($action));

        // Check for direct match
        if (isset(self::$action_capabilities[$action])) {
            return self::$action_capabilities[$action];
        }

        // Check for action prefix (e.g., cuft_check_update -> update_check)
        $normalized = str_replace('cuft_', '', $action);
        if (isset(self::$action_capabilities[$normalized])) {
            return self::$action_capabilities[$normalized];
        }

        return null;
    }

    /**
     * Check capability and send error if failed
     *
     * @param string $action Action being performed
     * @return bool True if check passed, sends JSON error and exits if failed
     */
    public static function check_and_enforce($action) {
        if (!self::current_user_can($action)) {
            wp_send_json_error(array(
                'message' => 'You do not have permission to perform this action.',
                'code' => 'insufficient_permissions',
                'required_capability' => self::get_required_capability($action)
            ), 403);
            exit;
        }

        return true;
    }

    /**
     * Check if user is logged in
     *
     * @return bool True if user is logged in
     */
    public static function is_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Check if user is administrator
     *
     * @return bool True if user is administrator
     */
    public static function is_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user can update plugins
     *
     * @return bool True if user can update plugins
     */
    public static function can_update_plugins() {
        return current_user_can('update_plugins');
    }

    /**
     * Get current user ID
     *
     * @return int User ID or 0 if not logged in
     */
    public static function get_current_user_id() {
        return get_current_user_id();
    }

    /**
     * Get current user display name
     *
     * @return string User display name
     */
    public static function get_current_user_name() {
        $user = wp_get_current_user();
        return $user ? $user->display_name : 'Guest';
    }

    /**
     * Log capability check for audit
     *
     * @param string $action Action being checked
     * @param bool $allowed Whether access was allowed
     * @return void
     */
    public static function log_capability_check($action, $allowed) {
        // Only log if in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $user_id = self::get_current_user_id();
        $user_name = self::get_current_user_name();

        $log_entry = sprintf(
            '[CUFT Capability] User: %s (ID: %d), Action: %s, Allowed: %s',
            $user_name,
            $user_id,
            $action,
            $allowed ? 'Yes' : 'No'
        );

        error_log($log_entry);
    }

    /**
     * Add custom capabilities to roles (for plugin activation)
     *
     * @return void
     */
    public static function add_custom_capabilities() {
        try {
            $roles = array('administrator', 'editor');

            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if (!$role) {
                    continue;
                }

                // Add custom capabilities
                $role->add_cap('cuft_manage_updates');
                $role->add_cap('cuft_view_settings');
            }

        } catch (Exception $e) {
            error_log('CUFT Capabilities: Failed to add custom capabilities - ' . $e->getMessage());
        }
    }

    /**
     * Remove custom capabilities (for plugin deactivation)
     *
     * @return void
     */
    public static function remove_custom_capabilities() {
        try {
            $roles = array('administrator', 'editor');

            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if (!$role) {
                    continue;
                }

                // Remove custom capabilities
                $role->remove_cap('cuft_manage_updates');
                $role->remove_cap('cuft_view_settings');
            }

        } catch (Exception $e) {
            error_log('CUFT Capabilities: Failed to remove custom capabilities - ' . $e->getMessage());
        }
    }

    /**
     * Verify nonce and capability together
     *
     * @param string $nonce Nonce value
     * @param string $action Action name
     * @param string $nonce_action Nonce action (defaults to cuft_updater_nonce)
     * @return bool True if both checks pass
     */
    public static function verify_nonce_and_capability($nonce, $action, $nonce_action = 'cuft_updater_nonce') {
        // Verify nonce
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            return false;
        }

        // Check capability
        return self::current_user_can($action);
    }

    /**
     * Get user's role
     *
     * @param int $user_id User ID (defaults to current user)
     * @return string|null User role or null
     */
    public static function get_user_role($user_id = null) {
        if ($user_id === null) {
            $user_id = self::get_current_user_id();
        }

        $user = get_userdata($user_id);
        if (!$user || empty($user->roles)) {
            return null;
        }

        return $user->roles[0];
    }

    /**
     * Check if user has specific role
     *
     * @param string $role Role name to check
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user has role
     */
    public static function user_has_role($role, $user_id = null) {
        if ($user_id === null) {
            $user_id = self::get_current_user_id();
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return in_array($role, (array) $user->roles);
    }

    /**
     * Get all users who can perform action
     *
     * @param string $action Action name
     * @return array Array of user IDs
     */
    public static function get_users_who_can($action) {
        $capability = self::get_required_capability($action);
        if (!$capability) {
            return array();
        }

        $args = array(
            'capability' => $capability,
            'fields' => 'ID'
        );

        return get_users($args);
    }

    /**
     * Send notification to users who can perform action
     *
     * @param string $action Action name
     * @param string $message Notification message
     * @param array $options Additional options
     * @return int Number of notifications sent
     */
    public static function notify_authorized_users($action, $message, $options = array()) {
        try {
            $user_ids = self::get_users_who_can($action);
            $sent = 0;

            foreach ($user_ids as $user_id) {
                $user = get_userdata($user_id);
                if (!$user || empty($user->user_email)) {
                    continue;
                }

                // Send email notification
                $subject = $options['subject'] ?? 'CUFT Update Notification';
                $headers = array('Content-Type: text/html; charset=UTF-8');

                if (wp_mail($user->user_email, $subject, $message, $headers)) {
                    $sent++;
                }
            }

            return $sent;

        } catch (Exception $e) {
            error_log('CUFT Capabilities: Failed to send notifications - ' . $e->getMessage());
            return 0;
        }
    }
}
