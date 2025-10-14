<?php
/**
 * AJAX handlers for plugin updates
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT Updater AJAX Handler
 *
 * Handles all AJAX requests for the one-click automated update feature.
 * Implements proper nonce validation and capability checks for security.
 */
class CUFT_Updater_Ajax {

    /**
     * Nonce action name
     * IMPORTANT: Must match the nonce action used in JavaScript
     */
    const NONCE_ACTION = 'cuft_updater_nonce';

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers for both logged-in and non-logged-in users
     */
    private function register_ajax_handlers() {
        // Check for updates
        add_action( 'wp_ajax_cuft_check_update', array( $this, 'check_update' ) );

        // Get update status
        add_action( 'wp_ajax_cuft_update_status', array( $this, 'update_status' ) );

        // Get update history
        add_action( 'wp_ajax_cuft_update_history', array( $this, 'update_history' ) );

        // Update settings
        add_action( 'wp_ajax_cuft_update_settings', array( $this, 'update_settings' ) );

        // Dismiss update notice
        add_action( 'wp_ajax_cuft_dismiss_update_notice', array( $this, 'dismiss_update_notice' ) );

        // Force Update Feature (Feature 009)
        add_action( 'wp_ajax_cuft_check_updates', array( $this, 'handle_check_updates_ajax' ) );
        add_action( 'wp_ajax_cuft_force_reinstall', array( $this, 'handle_force_reinstall_ajax' ) );
        add_action( 'wp_ajax_cuft_get_update_history', array( $this, 'handle_get_update_history_ajax' ) );
    }

    /**
     * Verify nonce and capability for all AJAX requests
     *
     * @param string $capability The capability to check (default: update_plugins)
     * @return bool True if verification passes
     */
    private function verify_request( $capability = 'update_plugins' ) {
        // Check if nonce is provided
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) :
                ( isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '' );

        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => 'Security check failed',
                'code' => 'invalid_nonce'
            ), 403 );
            return false;
        }

        // Check user capability
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array(
                'message' => 'Insufficient permissions',
                'code' => 'insufficient_permissions'
            ), 403 );
            return false;
        }

        return true;
    }

    /**
     * Check for plugin updates
     *
     * AJAX Handler: cuft_check_update
     */
    public function check_update() {
        if ( ! $this->verify_request() ) {
            return;
        }

        try {
            // Force check if requested
            $force = isset( $_POST['force'] ) && filter_var( $_POST['force'], FILTER_VALIDATE_BOOLEAN );

            // Use Update Checker service
            $result = CUFT_Update_Checker::check( $force );

            if ( ! $result['success'] ) {
                wp_send_json_error( array(
                    'message' => $result['error'],
                    'code' => isset( $result['code'] ) ? $result['code'] : 'check_failed'
                ), 500 );
                return;
            }

            wp_send_json_success( $result );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Failed to check for updates',
                'code' => 'check_failed',
                'details' => $e->getMessage()
            ), 500 );
        }
    }


    /**
     * Get update status
     *
     * AJAX Handler: cuft_update_status
     */
    public function update_status() {
        if ( ! $this->verify_request() ) {
            return;
        }

        try {
            // Get update ID if provided
            $update_id = isset( $_GET['update_id'] ) ? sanitize_text_field( $_GET['update_id'] ) : '';

            // Get current update progress
            $status = CUFT_Update_Progress::get_display_progress();

            wp_send_json_success( $status );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Failed to get update status',
                'code' => 'status_failed',
                'details' => $e->getMessage()
            ), 500 );
        }
    }


    /**
     * Get update history
     *
     * AJAX Handler: cuft_update_history
     */
    public function update_history() {
        if ( ! $this->verify_request() ) {
            return;
        }

        try {
            $limit = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 10;
            $offset = isset( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;

            // Get update history from database
            $entries = CUFT_Update_Log::get_display_logs( array(
                'limit' => $limit,
                'offset' => $offset,
                'order' => 'DESC',
                'orderby' => 'timestamp'
            ) );

            $total = CUFT_Update_Log::get_count();

            wp_send_json_success( array(
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'entries' => $entries
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Failed to get update history',
                'code' => 'history_failed',
                'details' => $e->getMessage()
            ), 500 );
        }
    }

    /**
     * Update settings
     *
     * AJAX Handler: cuft_update_settings
     */
    public function update_settings() {
        // Requires manage_options capability
        if ( ! $this->verify_request( 'manage_options' ) ) {
            return;
        }

        try {
            // Get settings from POST
            $settings = array();

            if ( isset( $_POST['enabled'] ) ) {
                $settings['enabled'] = filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN );
            }

            if ( isset( $_POST['check_frequency'] ) ) {
                $settings['check_frequency'] = sanitize_text_field( $_POST['check_frequency'] );
            }

            if ( isset( $_POST['include_prereleases'] ) ) {
                $settings['include_prereleases'] = filter_var( $_POST['include_prereleases'], FILTER_VALIDATE_BOOLEAN );
            }

            if ( isset( $_POST['backup_before_update'] ) ) {
                $settings['backup_before_update'] = filter_var( $_POST['backup_before_update'], FILTER_VALIDATE_BOOLEAN );
            }

            if ( isset( $_POST['notification_email'] ) ) {
                $settings['notification_email'] = sanitize_email( $_POST['notification_email'] );
            }

            // Save settings using Configuration model
            $result = CUFT_Update_Configuration::update( $settings );

            if ( ! $result ) {
                wp_send_json_error( array(
                    'message' => 'Failed to save settings',
                    'code' => 'save_failed'
                ), 500 );
                return;
            }

            // Get updated configuration for display
            $config = CUFT_Update_Configuration::get_display_config();

            wp_send_json_success( array(
                'message' => 'Settings updated successfully',
                'settings' => $config
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Failed to update settings',
                'code' => 'settings_failed',
                'details' => $e->getMessage()
            ), 500 );
        }
    }

    /**
     * Helper: Get latest version from GitHub
     */
    private function get_latest_version() {
        $version = get_transient( 'cuft_github_version' );

        if ( false === $version ) {
            global $cuft_updater;

            if ( $cuft_updater && method_exists( $cuft_updater, 'get_remote_version' ) ) {
                // Use reflection to call private method (temporary workaround)
                $reflection = new ReflectionMethod( $cuft_updater, 'get_remote_version' );
                $reflection->setAccessible( true );
                $version = $reflection->invoke( $cuft_updater );
            } else {
                // Fallback: Direct API call
                $api_url = "https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest";
                $response = wp_remote_get( $api_url, array( 'timeout' => 30 ) );

                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $body = wp_remote_retrieve_body( $response );
                    $data = json_decode( $body, true );

                    if ( isset( $data['tag_name'] ) ) {
                        $version = ltrim( $data['tag_name'], 'v' );
                        set_transient( 'cuft_github_version', $version, HOUR_IN_SECONDS );
                    }
                }
            }
        }

        return $version ?: CUFT_VERSION;
    }

    /**
     * Helper: Get changelog
     */
    private function get_changelog() {
        $changelog = get_transient( 'cuft_github_changelog' );

        if ( false === $changelog ) {
            $api_url = "https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest";
            $response = wp_remote_get( $api_url, array( 'timeout' => 30 ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( isset( $data['body'] ) ) {
                    $changelog = wp_kses_post( $data['body'] );
                    set_transient( 'cuft_github_changelog', $changelog, HOUR_IN_SECONDS );
                }
            }
        }

        return $changelog ?: 'No changelog available';
    }

    /**
     * Helper: Get download size
     */
    private function get_download_size( $version ) {
        $api_url = "https://api.github.com/repos/ChoiceOMG/choice-uft/releases/tags/v{$version}";
        $response = wp_remote_get( $api_url, array( 'timeout' => 30 ) );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['assets'][0]['size'] ) ) {
                return size_format( $data['assets'][0]['size'] );
            }
        }

        return '2.5 MB'; // Default estimate
    }

    /**
     * Helper: Get published date
     */
    private function get_published_date( $version ) {
        $api_url = "https://api.github.com/repos/ChoiceOMG/choice-uft/releases/tags/v{$version}";
        $response = wp_remote_get( $api_url, array( 'timeout' => 30 ) );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['published_at'] ) ) {
                return $data['published_at'];
            }
        }

        return current_time( 'c' );
    }

    /**
     * Helper: Mock update process for testing
     */
    private function mock_update_process( $update_id, $mock_failure = '' ) {
        // Simulate update stages
        $stages = array(
            array( 'status' => 'checking', 'percentage' => 10, 'message' => 'Checking for updates...' ),
            array( 'status' => 'downloading', 'percentage' => 30, 'message' => 'Downloading update package...' ),
            array( 'status' => 'backing_up', 'percentage' => 50, 'message' => 'Creating backup...' ),
            array( 'status' => 'extracting', 'percentage' => 70, 'message' => 'Extracting update files...' ),
            array( 'status' => 'installing', 'percentage' => 90, 'message' => 'Installing update...' ),
            array( 'status' => 'verifying', 'percentage' => 95, 'message' => 'Verifying installation...' ),
            array( 'status' => 'complete', 'percentage' => 100, 'message' => 'Update completed successfully!' )
        );

        // Simulate failure if requested
        if ( $mock_failure ) {
            $failure_stage = array(
                'download' => 1,
                'extraction' => 3,
                'install' => 4
            );

            if ( isset( $failure_stage[ $mock_failure ] ) ) {
                $stages = array_slice( $stages, 0, $failure_stage[ $mock_failure ] + 1 );
                $stages[] = array(
                    'status' => 'failed',
                    'percentage' => 0,
                    'message' => 'Update failed: ' . ucfirst( $mock_failure ) . ' error',
                    'error' => 'Mock failure at ' . $mock_failure . ' stage',
                    'rollback_status' => 'complete'
                );
            }
        }

        // Store first stage
        set_transient( 'cuft_update_status', $stages[0], 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Helper: Start real update process
     */
    private function start_update_process( $update_id, $version, $backup ) {
        // This will be implemented when core services are created
        // For now, just set initial status
        $status = array(
            'status' => 'checking',
            'percentage' => 0,
            'message' => 'Checking for updates...',
            'started_at' => current_time( 'c' ),
            'elapsed_seconds' => 0
        );

        set_transient( 'cuft_update_status', $status, 5 * MINUTE_IN_SECONDS );

        // Schedule background update process
        wp_schedule_single_event( time() + 1, 'cuft_process_update', array( $update_id, $version, $backup ) );
    }

    /**
     * Helper: Perform rollback
     */
    private function perform_rollback( $update_id, $reason ) {
        // This will be implemented when backup manager is created
        // For now, just log the rollback
        $this->log_update_action( 'rollback', $reason );
    }

    /**
     * Helper: Get update history
     */
    private function get_update_history( $limit, $offset ) {
        // This will be implemented with database storage
        // For now, return mock data
        $entries = array();

        // Get from transients (temporary storage)
        $log = get_option( 'cuft_update_log', array() );

        $total = count( $log );
        $entries = array_slice( $log, $offset, $limit );

        return array(
            'total' => $total,
            'entries' => $entries
        );
    }

    /**
     * Helper: Update cron schedule
     */
    private function update_cron_schedule( $enabled, $frequency ) {
        $hook = 'cuft_check_updates';

        // Clear existing schedule
        wp_clear_scheduled_hook( $hook );

        if ( $enabled && $frequency !== 'manual' ) {
            // Schedule new event
            wp_schedule_event( time(), $frequency, $hook );
        }
    }

    /**
     * Helper: Get next scheduled check
     */
    private function get_next_scheduled_check() {
        $timestamp = wp_next_scheduled( 'cuft_check_updates' );

        if ( $timestamp ) {
            return date( 'c', $timestamp );
        }

        return null;
    }

    /**
     * Helper: Log update action
     */
    private function log_update_action( $action, $details ) {
        $log = get_option( 'cuft_update_log', array() );

        $entry = array(
            'id' => count( $log ) + 1,
            'timestamp' => current_time( 'c' ),
            'action' => $action,
            'status' => 'info',
            'user' => wp_get_current_user()->user_login,
            'details' => $details
        );

        array_unshift( $log, $entry );

        // Keep only last 100 entries
        $log = array_slice( $log, 0, 100 );

        update_option( 'cuft_update_log', $log );
    }

    /**
     * Dismiss update notice
     *
     * AJAX Handler: cuft_dismiss_update_notice
     */
    public function dismiss_update_notice() {
        if ( ! $this->verify_request( 'update_plugins' ) ) {
            return;
        }

        try {
            $version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';

            if ( empty( $version ) ) {
                wp_send_json_error( array(
                    'message' => 'Version parameter is required',
                    'code' => 'missing_version'
                ), 400 );
                return;
            }

            // Store dismissal in user meta
            $user_id = get_current_user_id();
            $meta_key = 'cuft_dismissed_update_' . $version;

            update_user_meta( $user_id, $meta_key, time() );

            wp_send_json_success( array(
                'message' => 'Notice dismissed successfully',
                'version' => $version
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Failed to dismiss notice',
                'code' => 'dismiss_failed',
                'details' => $e->getMessage()
            ), 500 );
        }
    }

    /**
     * Handle manual update check AJAX request
     *
     * AJAX Handler: cuft_check_updates (Feature 009)
     *
     * @since 3.19.0
     */
    public function handle_check_updates_ajax() {
        // Validate nonce with force update nonce action
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'invalid_nonce',
                    'message'    => __( 'Security check failed. Please refresh the page and try again.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Check capability
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'insufficient_permissions',
                    'message'    => __( 'You do not have permission to check for updates.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Call Force Update Handler
        $result = CUFT_Force_Update_Handler::handle_check_updates();

        // Send appropriate response
        if ( $result['success'] ) {
            wp_send_json_success( $result, 200 );
        } else {
            // Determine HTTP status code based on error
            $status_code = 500; // Default to server error

            if ( isset( $result['error_code'] ) ) {
                switch ( $result['error_code'] ) {
                    case 'invalid_nonce':
                    case 'insufficient_permissions':
                        $status_code = 403;
                        break;
                    case 'operation_in_progress':
                        $status_code = 409;
                        break;
                    case 'github_timeout':
                        $status_code = 504;
                        break;
                    case 'rate_limited':
                        $status_code = 429;
                        break;
                }
            }

            wp_send_json_error( $result, $status_code );
        }
    }

    /**
     * Handle force reinstall AJAX request
     *
     * AJAX Handler: cuft_force_reinstall (Feature 009)
     *
     * @since 3.19.0
     */
    public function handle_force_reinstall_ajax() {
        // Validate nonce with force update nonce action
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'invalid_nonce',
                    'message'    => __( 'Security check failed. Please refresh the page and try again.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Check capability
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'insufficient_permissions',
                    'message'    => __( 'You do not have permission to reinstall plugins.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Check DISALLOW_FILE_MODS constant
        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            wp_send_json_error(
                array(
                    'error_code' => 'file_mods_disabled',
                    'message'    => __( 'File modifications are disabled on this site (DISALLOW_FILE_MODS constant).', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Call Force Update Handler
        $result = CUFT_Force_Update_Handler::handle_force_reinstall();

        // Send appropriate response
        if ( $result['success'] ) {
            wp_send_json_success( $result, 200 );
        } else {
            // Determine HTTP status code based on error
            $status_code = 500; // Default to server error

            if ( isset( $result['error_code'] ) ) {
                switch ( $result['error_code'] ) {
                    case 'invalid_nonce':
                    case 'insufficient_permissions':
                    case 'file_mods_disabled':
                        $status_code = 403;
                        break;
                    case 'operation_in_progress':
                        $status_code = 409;
                        break;
                    case 'validation_failed':
                        $status_code = 422;
                        break;
                    case 'download_failed':
                        $status_code = 502;
                        break;
                    case 'operation_timeout':
                        $status_code = 504;
                        break;
                    case 'insufficient_disk_space':
                        $status_code = 507;
                        break;
                }
            }

            wp_send_json_error( $result, $status_code );
        }
    }

    /**
     * Handle get update history AJAX request
     *
     * AJAX Handler: cuft_get_update_history (Feature 009)
     *
     * @since 3.19.0
     */
    public function handle_get_update_history_ajax() {
        // Validate nonce with force update nonce action
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'invalid_nonce',
                    'message'    => __( 'Security check failed. Please refresh the page and try again.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        // Check capability
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error(
                array(
                    'error_code' => 'insufficient_permissions',
                    'message'    => __( 'You do not have permission to view update history.', 'choice-universal-form-tracker' ),
                ),
                403
            );
            return;
        }

        try {
            // Get history from model
            $history = CUFT_Update_History_Entry::get_history( 5 );

            // Format timestamps for display
            $formatted_history = array();
            foreach ( $history as $entry ) {
                $entry['timestamp_formatted'] = date_i18n( 'Y-m-d H:i:s', $entry['timestamp'] );
                $formatted_history[] = $entry;
            }

            $response = array(
                'history'     => $formatted_history,
                'count'       => count( $formatted_history ),
                'max_entries' => 5,
            );

            // Add message if no history
            if ( empty( $formatted_history ) ) {
                $response['message'] = __( 'No update operations in history yet.', 'choice-universal-form-tracker' );
            }

            wp_send_json_success( $response, 200 );

        } catch ( Exception $e ) {
            wp_send_json_error(
                array(
                    'error_code' => 'history_failed',
                    'message'    => __( 'Failed to retrieve update history.', 'choice-universal-form-tracker' ),
                ),
                500
            );
        }
    }
}

// Initialize AJAX handlers
if ( is_admin() ) {
    new CUFT_Updater_Ajax();
}