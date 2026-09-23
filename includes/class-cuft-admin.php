<?php
/**
 * Admin settings functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'admin_init', array( $this, 'handle_export_actions' ) );
        add_action( 'wp_ajax_cuft_test_sgtm', array( $this, 'ajax_test_sgtm' ) );
        add_action( 'wp_ajax_cuft_save_sgtm_config', array( $this, 'ajax_save_sgtm_config' ) );
        add_action( 'wp_ajax_cuft_manual_health_check', array( $this, 'ajax_manual_health_check' ) );
        add_action( 'wp_ajax_cuft_get_sgtm_status', array( $this, 'ajax_get_sgtm_status' ) );
        add_action( 'wp_ajax_cuft_download_gtm_template', array( $this, 'ajax_download_gtm_template' ) );
        add_action( 'wp_ajax_cuft_dismiss_update_notice', array( $this, 'ajax_dismiss_update_notice' ) );
        
        // Cron job for scheduled health checks
        add_action( 'cuft_scheduled_health_check', array( $this, 'scheduled_health_check' ) );
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
        // cuft_record_event is handled by CUFT_Event_Recorder.
        add_action( 'wp_ajax_cuft_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }
    
    /**
     * Handle export actions early (before any output)
     */
    public function handle_export_actions() {
        // Run on our admin pages
        $allowed_pages = array( 'choice-universal-form-tracker', 'cuft-click-tracking' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only: the page slug selects which nonce-checked action below may run; nothing is changed on this read.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! in_array( $page, $allowed_pages, true ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only: each action below verifies its own nonce before doing anything.
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        if ( '' === $action ) {
            return;
        }

        // Every action below changes state or returns private data.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle CSV export
        if ( 'export_csv' === $action && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'cuft_export_csv' ) ) {
            $this->handle_csv_export();
            // handle_csv_export() will exit, so this won't be reached
        }

        // Handle Google Ads OCI export
        if ( 'export_google_ads_oci' === $action && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'cuft_export_google_ads_oci' ) ) {
            $this->handle_google_ads_oci_export();
            // handle_google_ads_oci_export() will exit, so this won't be reached
        }

        // Handle webhook key regeneration
        if ( 'regenerate_webhook_key' === $action && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'regenerate_webhook_key' ) ) {
            $this->regenerate_webhook_key();
            // This redirects, so won't be reached
        }
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            'Choice Universal Form Tracker',
            'Universal Form Tracker',
            'manage_options',
            'choice-universal-form-tracker',
            array( $this, 'admin_page' )
        );

        add_menu_page(
            'Click Tracking',
            'Click Tracking',
            'manage_options',
            'cuft-click-tracking',
            array( $this, 'click_tracking_page' ),
            'dashicons-chart-line',
            30
        );
    }
    
    /**
     * Render admin page
     */
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'choice-universal-form-tracker' ) );
        }

        // Handle form submission
        if ( isset( $_POST['cuft_save'], $_POST['cuft_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cuft_nonce'] ) ), 'cuft_settings' ) ) {
            $this->save_settings();
        }

        // Note: Export actions and webhook regeneration are now handled in handle_export_actions() via admin_init hook

        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $debug_enabled = get_option( 'cuft_debug_enabled', false );
        $generate_lead_enabled = get_option( 'cuft_generate_lead_enabled', false );
        $lead_currency = get_option( 'cuft_lead_currency', 'CAD' );
        $lead_value = get_option( 'cuft_lead_value', 100 );
        $console_logging = get_option( 'cuft_console_logging', 'no' );
        $phone_validation_enabled = get_option( 'cuft_phone_validation_enabled', false );

        // Get current tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector on a screen gated by manage_options above; nothing is changed.
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
        ?>
        <div class="wrap cuft-admin-container">
            <div class="cuft-admin-header">
                <div class="cuft-logo">
                    <span style="color: white; font-size: 24px; font-weight: bold;">📊</span>
                </div>
                <h1>Choice Universal Form Tracker</h1>
            </div>
            <hr class="wp-header-end">

            <?php $this->render_setup_progress(); ?>
            <?php $this->render_admin_tabs( $current_tab ); ?>

            <?php if ( $current_tab === 'settings' ): ?>
                <?php $this->render_settings_form( $gtm_id, $debug_enabled, $generate_lead_enabled, $lead_currency, $lead_value, $console_logging, $phone_validation_enabled ); ?>
                <?php $this->render_framework_status(); ?>
                <?php // render_github_status() removed in Feature 008 - using WordPress native updates ?>
                <?php $this->render_utm_status(); ?>
                <?php $this->render_debug_section(); ?>
            <?php elseif ( $current_tab === 'force-update' && CUFT_Plugin::has_updater() ): ?>
                <?php include CUFT_PATH . 'includes/admin/views/force-update-tab.php'; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings form
     */
    private function render_settings_form( $gtm_id, $debug_enabled, $generate_lead_enabled, $lead_currency, $lead_value, $console_logging, $phone_validation_enabled = false ) {
        ?>
        <div class="cuft-settings-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">Settings</h2>
            <form method="post">
                <?php wp_nonce_field( 'cuft_settings', 'cuft_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            Google Tag Manager ID
                            <?php // The WordPress.org package ships without the GTM container exports, so the button only appears where the file exists. ?>
                            <?php if ( file_exists( CUFT_PATH . 'gtm-web-client/CUFT - Web Defaults.json' ) ) : ?>
                            <br><br>
                            <button type="button" class="button button-secondary cuft-download-template" data-template="web" style="font-size: 12px;">
                                <span class="dashicons dashicons-download" style="font-size: 14px; vertical-align: middle;"></span>
                                Download Web GTM Template
                            </button>
                            <?php endif; ?>
                        </th>
                        <td>
                            <input type="text" name="gtm_id" value="<?php echo esc_attr( $gtm_id ); ?>" 
                                   placeholder="GTM-XXXX or GTM-XXXXXXX" class="regular-text" />
                            <p class="description">
                                Enter your GTM container ID (e.g., GTM-ABC123). Leave empty to disable GTM injection.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2" style="padding: 20px 0 10px 0; border-top: 1px solid #ddd;">
                            <h3 style="margin: 0; color: #23282d;">Server-Side GTM (sGTM)</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Enable Server-Side GTM</th>
                        <td>
                            <?php $sgtm_enabled = get_option( 'cuft_sgtm_enabled', false ); ?>
                            <label>
                                <input type="checkbox" name="sgtm_enabled" value="1" <?php checked( $sgtm_enabled ); ?> id="cuft-sgtm-enabled" />
                                Use custom server for GTM scripts (first-party tracking)
                            </label>
                            <p class="description">
                                Enable this if you have a server-side GTM setup to proxy Google's scripts through your own domain.
                            </p>
                        </td>
                    </tr>
                    <tr id="cuft-sgtm-url-row" style="<?php echo $sgtm_enabled ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            Server GTM URL
                            <?php // The WordPress.org package ships without the GTM container exports, so the button only appears where the file exists. ?>
                            <?php if ( file_exists( CUFT_PATH . 'gtm-server/CUFT - Server Defaults.json' ) ) : ?>
                            <br><br>
                            <button type="button" class="button button-secondary cuft-download-template" data-template="server" style="font-size: 12px;">
                                <span class="dashicons dashicons-download" style="font-size: 14px; vertical-align: middle;"></span>
                                Download Server GTM Template
                            </button>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php
                            $sgtm_url = get_option( 'cuft_sgtm_url', '' );
                            $sgtm_validated = get_option( 'cuft_sgtm_validated', false );
                            ?>
                            <input type="text" name="sgtm_url" value="<?php echo esc_attr( $sgtm_url ); ?>"
                                   placeholder="https://gtm.yourdomain.com" class="regular-text" id="cuft-sgtm-url" />
                            <button type="button" class="button button-secondary" id="cuft-test-sgtm">Test Connection</button>
                            <div id="cuft-sgtm-status" style="margin-top: 10px;">
                                <?php if ( $sgtm_url && $sgtm_validated ): ?>
                                    <span style="color: #28a745;">✓ Server GTM endpoint validated</span>
                                <?php elseif ( $sgtm_url && ! $sgtm_validated ): ?>
                                    <span style="color: #dc3545;">✗ Server GTM endpoint not validated - please test connection</span>
                                <?php endif; ?>
                            </div>
                            <p class="description">
                                Enter your server-side GTM URL (without trailing slash). This will replace googletagmanager.com in script sources.<br>
                                Example: <code>https://gtm.yourdomain.com</code> or <code>https://yourdomain.com/gtm</code><br>
                                <strong>Local Development:</strong> <code>.localnet</code> domains are supported with automatic SSL verification bypass (e.g., <code>https://tagging-server.localnet</code>)
                            </p>
                        </td>
                    </tr>
                    <tr id="cuft-health-check-row" style="<?php echo $sgtm_enabled ? '' : 'display:none;'; ?>">
                        <th scope="row">Health Check Status</th>
                        <td>
                            <div id="cuft-health-status">
                                <div style="margin-bottom: 10px;">
                                    <strong>Active Server:</strong> <span id="cuft-active-server">Loading...</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Last Check:</strong> <span id="cuft-last-check">Loading...</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Status:</strong> <span id="cuft-health-status-text">Loading...</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Consecutive Success:</strong> <span id="cuft-consecutive-success">Loading...</span> | 
                                    <strong>Consecutive Failure:</strong> <span id="cuft-consecutive-failure">Loading...</span>
                                </div>
                                <div style="margin-bottom: 10px;">
                                    <strong>Next Check:</strong> <span id="cuft-next-check">Loading...</span>
                                </div>
                                <button type="button" class="button button-secondary" id="cuft-manual-health-check">Run Health Check Now</button>
                                <div id="cuft-health-check-result" style="margin-top: 10px;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Debug Logging</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_enabled" value="1" <?php checked( $debug_enabled ); ?> />
                                Enable debug logging for troubleshooting
                            </label>
                            <p class="description">
                                When enabled, form tracking events will be logged for debugging purposes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Generate Lead Events</th>
                        <td>
                            <label>
                                <input type="checkbox" name="generate_lead_enabled" value="1" <?php checked( $generate_lead_enabled ); ?> id="cuft-generate-lead-enabled" />
                                Fire generate_lead events for qualified form submissions
                            </label>
                            <p class="description">
                                Automatically creates generate_lead events when forms are submitted with email, phone, and click ID data. Ideal for conversion tracking in GA4.
                            </p>

                            <div id="cuft-lead-settings" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; <?php echo $generate_lead_enabled ? '' : 'display:none;'; ?>">
                                <table class="form-table" style="margin: 0;">
                                    <tr>
                                        <th scope="row" style="padding-left: 0; width: 150px;">
                                            <label for="cuft-lead-currency">Lead Currency</label>
                                        </th>
                                        <td style="padding-left: 0;">
                                            <select name="lead_currency" id="cuft-lead-currency">
                                                <option value="CAD" <?php selected( $lead_currency, 'CAD' ); ?>>CAD - Canadian Dollar</option>
                                                <option value="USD" <?php selected( $lead_currency, 'USD' ); ?>>USD - US Dollar</option>
                                                <option value="EUR" <?php selected( $lead_currency, 'EUR' ); ?>>EUR - Euro</option>
                                                <option value="GBP" <?php selected( $lead_currency, 'GBP' ); ?>>GBP - British Pound</option>
                                                <option value="AUD" <?php selected( $lead_currency, 'AUD' ); ?>>AUD - Australian Dollar</option>
                                                <option value="JPY" <?php selected( $lead_currency, 'JPY' ); ?>>JPY - Japanese Yen</option>
                                                <option value="CHF" <?php selected( $lead_currency, 'CHF' ); ?>>CHF - Swiss Franc</option>
                                                <option value="SEK" <?php selected( $lead_currency, 'SEK' ); ?>>SEK - Swedish Krona</option>
                                                <option value="NOK" <?php selected( $lead_currency, 'NOK' ); ?>>NOK - Norwegian Krone</option>
                                                <option value="DKK" <?php selected( $lead_currency, 'DKK' ); ?>>DKK - Danish Krone</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="padding-left: 0;">
                                            <label for="cuft-lead-value">Lead Value</label>
                                        </th>
                                        <td style="padding-left: 0;">
                                            <input type="number" name="lead_value" id="cuft-lead-value"
                                                   value="<?php echo esc_attr( $lead_value ); ?>"
                                                   min="0" step="0.01" class="regular-text" />
                                            <p class="description">
                                                Monetary value for each lead in your chosen currency. Used for conversion tracking in Google Analytics 4.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Phone Validation</th>
                        <td>
                            <label>
                                <input type="checkbox" name="phone_validation_enabled" value="1" <?php checked( $phone_validation_enabled ); ?> />
                                Validate phone numbers via Choice validator service
                            </label>
                            <p class="description">
                                Validates submitted phone numbers using Twilio + Abstract API. Scores leads 0–10 by line type (mobile=9, landline=7, VoIP=4) and writes the result to the click tracking record. Requires site registration below.
                            </p>

                            <?php
                            $is_registered = class_exists( 'CUFT_Token_Manager' ) && CUFT_Token_Manager::is_registered();
                            $registered_at = get_option( CUFT_Token_Manager::OPTION_REGISTERED );
                            $registered_domain = get_option( CUFT_Token_Manager::OPTION_DOMAIN );
                            ?>
                            <div style="margin-top: 12px; padding: 12px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
                                <?php if ( $is_registered ): ?>
                                    <p style="margin: 0 0 8px; color: #3a7c3a;">
                                        &#10003; Registered: <strong><?php echo esc_html( $registered_domain ); ?></strong>
                                        <?php if ( $registered_at ): ?>
                                            (<?php echo esc_html( date_i18n( 'Y-m-d', $registered_at ) ); ?>)
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <p style="margin: 0 0 8px; color: #a00;">&#9679; Not registered: set the Registration Secret below (or define <code>CUFT_REGISTER_SECRET</code> in wp-config.php) then click Register.</p>
                                <?php endif; ?>
                                <button type="button" id="cuft-register-site" class="button button-secondary">
                                    <?php echo esc_html( $is_registered ? 'Re-register Site' : 'Register Site' ); ?>
                                </button>
                                <span id="cuft-register-status" style="margin-left: 10px; display: none;"></span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Browser Console Logging</th>
                        <td>
                            <select name="console_logging">
                                <option value="no" <?php selected( $console_logging, 'no' ); ?>>No - Disable console logging</option>
                                <option value="yes" <?php selected( $console_logging, 'yes' ); ?>>Yes - Enable for all visitors</option>
                                <option value="admin_only" <?php selected( $console_logging, 'admin_only' ); ?>>Admin Only - Enable only for logged-in administrators</option>
                            </select>
                            <p class="description">
                                Controls browser console logging for debugging. "Admin Only" is recommended for production sites.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cuft_collector_host">Click Collector Host</label></th>
                        <td>
                            <input type="text" id="cuft_collector_host" name="cuft_collector_host"
                                   value="<?php echo esc_attr( get_option( 'cuft_collector_host', '' ) ); ?>"
                                   class="regular-text" placeholder="track.example.com" />
                            <p class="description">
                                Optional. If set, CUFT posts click data to <code>https://&lt;host&gt;/p</code> on page load via <code>navigator.sendBeacon()</code>. Leave blank to disable.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- API Credentials Section -->
                <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px; color: #23282d;">API Credentials</h3>
                    <?php $register_secret_override = defined( 'CUFT_REGISTER_SECRET' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cuft_register_secret">Registration Secret</label></th>
                            <td>
                                <?php if ( $register_secret_override ) : ?>
                                    <input type="text" value="••••••••" disabled class="regular-text" />
                                    <p class="description">Overridden by <code>CUFT_REGISTER_SECRET</code> in wp-config.php</p>
                                <?php else : ?>
                                    <input type="password" id="cuft_register_secret" name="cuft_register_secret"
                                        value="" class="regular-text" autocomplete="off"
                                        placeholder="<?php echo get_option( 'cuft_register_secret', '' ) ? '••••••••' : ''; ?>" />
                                    <p class="description">Authenticates with the validator service. Leave blank to keep current value.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cuft_measurement_id">GA4 Measurement ID</label></th>
                            <td>
                                <input type="text" id="cuft_measurement_id" name="cuft_measurement_id"
                                    value="<?php echo esc_attr( get_option( 'cuft_measurement_id', '' ) ); ?>"
                                    class="regular-text" placeholder="G-XXXXXXXXXX" />
                                <p class="description">Required for server-side Measurement Protocol events.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cuft_measurement_api_secret">GA4 API Secret</label></th>
                            <td>
                                <input type="password" id="cuft_measurement_api_secret" name="cuft_measurement_api_secret"
                                    value="" class="regular-text" autocomplete="off"
                                    placeholder="<?php echo get_option( 'cuft_measurement_api_secret', '' ) ? '••••••••' : ''; ?>" />
                                <p class="description">Found in GA4 Admin > Data Streams > Measurement Protocol API secrets. Leave blank to keep current value.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( 'Save Settings', 'primary', 'cuft_save' ); ?>
            </form>
        </div>

        <?php // Register Site and template download handlers live in assets/cuft-admin.js. ?>
        <?php
    }
    
    /**
     * Render framework status section
     */
    private function render_framework_status() {
        $frameworks = CUFT_Form_Detector::get_framework_status();
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $admin_email = get_option( 'admin_email' );

        ?>
        <div class="cuft-status-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">Framework Detection Status</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php foreach ( $frameworks as $framework ): ?>
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: <?php echo $framework['detected'] ? '#e8f5e8' : '#f8f8f8'; ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <strong><?php echo esc_html( $framework['name'] ); ?></strong>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $framework['detected'] ? '#28a745' : '#6c757d'; ?>;">
                                <?php echo esc_html( $framework['detected'] ? 'DETECTED' : 'NOT FOUND' ); ?>
                            </span>
                        </div>
                        <?php if ( $framework['detected'] ): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                <div style="font-size: 12px; color: #666;">
                                    Form tracking enabled and active
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h3>Core Features</h3>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong>Form Tracking</strong></td>
                        <td><span style="color: #28a745;">✓</span> Active - pushes <code>form_submit</code> events with <code>user_email</code> and <code>user_phone</code></td>
                    </tr>
                    <tr>
                        <td><strong>Link Tracking</strong></td>
                        <td><span style="color: #28a745;">✓</span> Active - tracks <code>phone_click</code> on tel: links</td>
                    </tr>
                    <tr>
                        <td><strong>GTM Integration</strong></td>
                        <td>
                            <?php if ( $gtm_id && $this->is_valid_gtm_id( $gtm_id ) ): ?>
                                <span style="color: #28a745;">✓</span> Active - Container <?php echo esc_html( $gtm_id ); ?>
                            <?php else: ?>
                                <span style="color: #dc3545;">✗</span> Disabled - No valid container ID configured
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Server-Side GTM</strong></td>
                        <td>
                            <?php
                            $sgtm_enabled = get_option( 'cuft_sgtm_enabled', false );
                            $sgtm_url = get_option( 'cuft_sgtm_url', '' );
                            $sgtm_validated = get_option( 'cuft_sgtm_validated', false );

                            if ( $sgtm_enabled && $sgtm_url && $sgtm_validated ): ?>
                                <span style="color: #28a745;">✓</span> Active - <?php echo esc_html( $sgtm_url ); ?>
                            <?php elseif ( $sgtm_enabled && $sgtm_url && ! $sgtm_validated ): ?>
                                <span style="color: #ffc107;">⚠</span> Configured but not validated - <?php echo esc_html( $sgtm_url ); ?>
                            <?php elseif ( $sgtm_enabled && ! $sgtm_url ): ?>
                                <span style="color: #dc3545;">✗</span> Enabled but no URL configured
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span> Disabled (using standard GTM)
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // The caller already verified this nonce. Verifying again here keeps the
        // check next to the code that trusts $_POST, so a future caller cannot
        // reach these writes without it.
        check_admin_referer( 'cuft_settings', 'cuft_nonce' );

        $gtm_id = isset( $_POST['gtm_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gtm_id'] ) ) : '';
        $debug_enabled = ! empty( $_POST['debug_enabled'] );
        $generate_lead_enabled = ! empty( $_POST['generate_lead_enabled'] );
        $phone_validation_enabled = ! empty( $_POST['phone_validation_enabled'] );
        $lead_currency = isset( $_POST['lead_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_currency'] ) ) : 'CAD';
        $lead_value = isset( $_POST['lead_value'] ) ? floatval( sanitize_text_field( wp_unslash( $_POST['lead_value'] ) ) ) : 100;

        // Validate currency (ensure it's one of the allowed values)
        $allowed_currencies = array( 'CAD', 'USD', 'EUR', 'GBP', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK' );
        if ( ! in_array( $lead_currency, $allowed_currencies, true ) ) {
            $lead_currency = 'CAD';
        }

        // Ensure lead value is not negative
        if ( $lead_value < 0 ) {
            $lead_value = 0;
        }
        $console_logging_raw = isset( $_POST['console_logging'] ) ? sanitize_text_field( wp_unslash( $_POST['console_logging'] ) ) : 'no';
        $console_logging = in_array( $console_logging_raw, array( 'no', 'yes', 'admin_only' ), true ) ? $console_logging_raw : 'no';
        $collector_host = isset( $_POST['cuft_collector_host'] ) ? sanitize_text_field( wp_unslash( $_POST['cuft_collector_host'] ) ) : '';
        // Strip protocol/path if a user pastes a full URL - we only want the host.
        $collector_host = preg_replace( '#^https?://#i', '', $collector_host );
        $collector_host = trim( $collector_host, "/ \t\n\r\0\x0B" );
        $sgtm_enabled = ! empty( $_POST['sgtm_enabled'] );
        $sgtm_url = isset( $_POST['sgtm_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sgtm_url'] ) ) : '';

        // Remove trailing slash from sGTM URL
        $sgtm_url = rtrim( $sgtm_url, '/' );

        // Validate GTM-ID format
        if ( empty( $gtm_id ) || $this->is_valid_gtm_id( $gtm_id ) ) {
            update_option( 'cuft_gtm_id', $gtm_id );
            update_option( 'cuft_debug_enabled', $debug_enabled );
            update_option( 'cuft_generate_lead_enabled', $generate_lead_enabled );
            update_option( 'cuft_phone_validation_enabled', $phone_validation_enabled );
            update_option( 'cuft_lead_currency', $lead_currency );
            update_option( 'cuft_lead_value', $lead_value );
            update_option( 'cuft_console_logging', $console_logging );
            update_option( 'cuft_collector_host', $collector_host );
            update_option( 'cuft_sgtm_enabled', $sgtm_enabled );

            // Only save sGTM URL if sGTM is enabled
            if ( $sgtm_enabled ) {
                if ( $this->is_valid_sgtm_url( $sgtm_url ) ) {
                    // Get old URL BEFORE updating it
                    $old_url = get_option( 'cuft_sgtm_url', '' );

                    update_option( 'cuft_sgtm_url', $sgtm_url );

                    // Reset validation only if URL actually changed
                    if ( $old_url !== $sgtm_url && ! empty( $old_url ) ) {
                        update_option( 'cuft_sgtm_validated', false );
                        add_settings_error( 'cuft_messages', 'cuft_message', 'Server GTM URL changed. Please test the connection again to validate.', 'info' );
                    }
                    // Otherwise preserve the current validation status - don't change it!
                } else {
                    add_settings_error( 'cuft_messages', 'cuft_message', 'Invalid Server GTM URL format. Please enter a valid HTTPS URL.', 'error' );
                    return;
                }
            } else {
                // If sGTM is disabled, clear the validation
                update_option( 'cuft_sgtm_validated', false );
            }

            // API Credentials
            if ( isset( $_POST['cuft_register_secret'] ) && ! defined( 'CUFT_REGISTER_SECRET' ) ) {
                $secret = sanitize_text_field( wp_unslash( $_POST['cuft_register_secret'] ) );
                if ( ! empty( $secret ) ) {
                    update_option( 'cuft_register_secret', CUFT_Utils::encrypt_secret( $secret ) );
                }
            }
            if ( isset( $_POST['cuft_measurement_id'] ) ) {
                $measurement_id = sanitize_text_field( wp_unslash( $_POST['cuft_measurement_id'] ) );
                if ( empty( $measurement_id ) || preg_match( '/^G-[A-Z0-9]+$/', $measurement_id ) ) {
                    update_option( 'cuft_measurement_id', $measurement_id );
                }
            }
            if ( isset( $_POST['cuft_measurement_api_secret'] ) ) {
                $secret = sanitize_text_field( wp_unslash( $_POST['cuft_measurement_api_secret'] ) );
                if ( ! empty( $secret ) ) {
                    update_option( 'cuft_measurement_api_secret', CUFT_Utils::encrypt_secret( $secret ) );
                }
            }

            add_settings_error( 'cuft_messages', 'cuft_message', 'Settings saved!', 'updated' );
        } else {
            add_settings_error( 'cuft_messages', 'cuft_message', 'Invalid GTM-ID format. Use format: GTM-XXXX or GTM-XXXXXXX', 'error' );
        }

        settings_errors( 'cuft_messages' );
    }
    
    /**
     * Validate GTM ID format
     */
    private function is_valid_gtm_id( $gtm_id ) {
        return preg_match( '/^GTM-[A-Z0-9]{4,}$/i', $gtm_id );
    }

    /**
     * Validate sGTM URL format
     */
    private function is_valid_sgtm_url( $sgtm_url ) {
        if ( empty( $sgtm_url ) ) {
            return false;
        }

        // Check if it's a valid URL starting with https
        if ( ! filter_var( $sgtm_url, FILTER_VALIDATE_URL ) || strpos( $sgtm_url, 'https://' ) !== 0 ) {
            return false;
        }

        // Additional validation for localnet domains
        if ( $this->is_localnet_url( $sgtm_url ) ) {
            // For localnet URLs, ensure they follow the expected pattern
            if ( ! preg_match( '/^https:\/\/[a-zA-Z0-9\-\.]+\.localnet(\/.*)?$/', $sgtm_url ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if URL is a localnet domain
     */
    private function is_localnet_url( $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || ! isset( $parsed['host'] ) ) {
            return false;
        }

        return strpos( $parsed['host'], '.localnet' ) !== false;
    }
    
    /**
     * Render UTM tracking status
     */
    private function render_utm_status() {
        $utm_data = CUFT_UTM_Tracker::get_utm_data();
        
        ?>
        <div class="cuft-utm-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">
                <span style="margin-right: 8px;">🎯</span>
                UTM Campaign Tracking
            </h2>
            
            <?php if ( ! empty( $utm_data ) ): ?>
                <div style="padding: 15px; background: #e8f5e8; border-radius: 6px; border-left: 4px solid #28a745;">
                    <h4 style="margin-top: 0; color: #155724;">Active Campaign Detected</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <?php foreach ( $utm_data as $key => $value ): ?>
                            <div style="background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #d4edda;">
                                <strong style="color: #495057; font-size: 12px; text-transform: uppercase;"><?php echo esc_html( str_replace( 'utm_', '', $key ) ); ?></strong><br>
                                <span style="color: #155724;"><?php echo esc_html( $value ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-bottom: 0; color: #155724; font-size: 14px; margin-top: 10px;">
                        <strong>✓</strong> All form submissions will include this campaign data for attribution tracking.
                    </p>
                </div>
            <?php else: ?>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #6c757d;">
                    <h4 style="margin-top: 0; color: #495057;">No Active Campaign</h4>
                    <p style="margin-bottom: 0; color: #6c757d;">
                        When users visit your site with UTM parameters (e.g., <code>?utm_campaign=summer_sale</code>), 
                        the campaign data will be stored and included with all form submissions for proper attribution.
                    </p>
                </div>
            <?php endif; ?>
            
           
        </div>
        <?php
    }
    
    /**
     * Render debug section
     */
    private function render_debug_section() {
        if ( ! get_option( 'cuft_debug_enabled', false ) ) {
            return;
        }
        
        $logs = CUFT_Logger::get_logs( 50 );
        
        ?>
        <div class="cuft-debug-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h2 style="margin: 0;">Debug Logs</h2>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field( 'cuft_clear_logs', 'cuft_clear_nonce' ); ?>
                    <input type="submit" name="cuft_clear_logs" value="Clear Logs" class="button" />
                </form>
            </div>
            
            <?php if ( empty( $logs ) ): ?>
                <p style="color: #666; font-style: italic;">No debug logs yet. Logs will appear here when forms are submitted.</p>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="widefat" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Level</th>
                                <th>Message</th>
                                <th>Context</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo esc_html( $log['timestamp'] ); ?></td>
                                    <td><span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; background: <?php echo esc_attr( $this->get_log_level_color( $log['level'] ) ); ?>; color: white;"><?php echo esc_html( strtoupper( $log['level'] ) ); ?></span></td>
                                    <td><?php echo esc_html( $log['message'] ); ?></td>
                                    <td><?php echo ! empty( $log['context'] ) ? '<pre style="font-size: 11px; margin: 0;">' . esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) ) . '</pre>' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        // Handle log clearing
        if ( isset( $_POST['cuft_clear_logs'], $_POST['cuft_clear_nonce'] )
            && current_user_can( 'manage_options' )
            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cuft_clear_nonce'] ) ), 'cuft_clear_logs' ) ) {
            CUFT_Logger::clear_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Debug logs cleared.', 'choice-universal-form-tracker' ) . '</p></div>';
        }
    }
    
    /**
     * Get color for log level
     */
    private function get_log_level_color( $level ) {
        switch ( $level ) {
            case 'error': return '#dc3545';
            case 'warning': return '#ffc107';
            case 'info': return '#17a2b8';
            case 'debug': return '#6c757d';
            default: return '#6c757d';
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        $settings_hook = 'settings_page_choice-universal-form-tracker';
        $click_hook    = 'toplevel_page_cuft-click-tracking';

        // Plugin screens use the admin stylesheet. The GitHub build also styles
        // its updater notices with it on other screens, so it keeps loading it
        // everywhere; the WordPress.org build (no updater) loads it only here.
        if ( in_array( $hook, array( $settings_hook, $click_hook ), true ) || CUFT_Plugin::has_updater() ) {
            wp_enqueue_style(
                'cuft-admin',
                CUFT_URL . '/assets/cuft-admin.css',
                array(),
                CUFT_VERSION
            );
        }

        // Dismiss handler for the informational notice rendered by admin_notices().
        if ( $this->should_show_active_notice() ) {
            wp_register_script( 'cuft-admin-notice', false, array( 'jquery' ), CUFT_VERSION, true );
            wp_enqueue_script( 'cuft-admin-notice' );
            wp_add_inline_script(
                'cuft-admin-notice',
                'jQuery(function($){$(document).on("click",".notice[data-dismiss-action=\"cuft-dismiss-notice\"] .notice-dismiss",function(){$.post(ajaxurl,{action:"cuft_dismiss_notice",nonce:' . wp_json_encode( wp_create_nonce( 'cuft_dismiss_notice' ) ) . '});});});'
            );
        }

        // The admin script serves the settings page and the Click Tracking page.
        if ( $hook !== $settings_hook && $hook !== $click_hook ) {
            return;
        }

        wp_enqueue_script(
            'cuft-admin',
            CUFT_URL . '/assets/cuft-admin.js',
            array( 'jquery' ),
            CUFT_VERSION,
            true
        );

        wp_localize_script( 'cuft-admin', 'cuftAdmin', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'cuft_admin' ),
            'register_nonce'  => wp_create_nonce( 'cuft_token_register' ),
            'current_version' => CUFT_VERSION,
            'plugin_url'      => CUFT_URL,
            'admin_url'       => admin_url( 'options-general.php?page=choice-universal-form-tracker' ),
        ) );

        if ( $hook !== $settings_hook ) {
            return;
        }

        // Enqueue Force Update assets (Feature 009 - v3.19.0). Skipped in the
        // WordPress.org build, which ships neither the tab nor its handlers.
        if ( CUFT_Plugin::has_updater() ) {
            wp_enqueue_script(
                'cuft-force-update',
                CUFT_URL . '/assets/admin/cuft-force-update.js',
                array( 'jquery' ),
                CUFT_VERSION,
                true
            );

            wp_localize_script( 'cuft-force-update', 'cuftForceUpdate', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'cuft_force_update' ),
            ) );

            wp_enqueue_style(
                'cuft-force-update',
                CUFT_URL . '/assets/admin/cuft-force-update.css',
                array(),
                CUFT_VERSION
            );
        }


    }

    /**
     * AJAX handler for testing sGTM endpoint
     */
    public function ajax_test_sgtm() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $sgtm_url = isset( $_POST['sgtm_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sgtm_url'] ) ) : '';
        $gtm_id = get_option( 'cuft_gtm_id', '' );

        if ( empty( $sgtm_url ) ) {
            wp_send_json_error( array( 'message' => 'Please enter a Server GTM URL' ) );
        }

        if ( empty( $gtm_id ) ) {
            wp_send_json_error( array( 'message' => 'Please configure a GTM ID first' ) );
        }

        // Remove trailing slash
        $sgtm_url = rtrim( $sgtm_url, '/' );

        // Validate URL format
        if ( ! $this->is_valid_sgtm_url( $sgtm_url ) ) {
            wp_send_json_error( array( 'message' => 'Invalid URL format. Please use HTTPS.' ) );
        }

        // Test the endpoints
        $test_results = $this->test_sgtm_endpoints( $sgtm_url, $gtm_id );

        if ( $test_results['success'] ) {
            // Save validation status
            update_option( 'cuft_sgtm_validated', true );
            wp_send_json_success( array(
                'valid' => true,
                'message' => 'Custom server validated successfully',
                'response_time' => 200.0, // Mock value - in real implementation, measure actual time
                'endpoints_tested' => array(
                    'gtm_js' => isset( $test_results['details']['gtm_js'] ) && strpos( $test_results['details']['gtm_js'], '✓' ) !== false,
                    'ns_html' => isset( $test_results['details']['ns_html'] ) && strpos( $test_results['details']['ns_html'], '✓' ) !== false
                )
            ) );
        } else {
            update_option( 'cuft_sgtm_validated', false );
            wp_send_json_success( array(
                'valid' => false,
                'message' => $test_results['message'],
                'response_time' => 5000.0, // Mock value for timeout
                'endpoints_tested' => array(
                    'gtm_js' => false,
                    'ns_html' => false
                )
            ) );
        }
    }

    /**
     * AJAX handler for saving custom GTM server configuration
     */
    public function ajax_save_sgtm_config() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $enabled = ! empty( $_POST['enabled'] );
        $sgtm_url = isset( $_POST['sgtm_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sgtm_url'] ) ) : '';

        // Validate URL if provided
        if ( $enabled && ! empty( $sgtm_url ) ) {
            if ( ! filter_var( $sgtm_url, FILTER_VALIDATE_URL ) ) {
                wp_send_json_error( array( 'message' => 'Invalid URL format' ) );
            }
        }

        // Save configuration
        update_option( 'cuft_sgtm_enabled', $enabled );
        if ( ! empty( $sgtm_url ) ) {
            update_option( 'cuft_sgtm_url', $sgtm_url );
        }

        // Trigger initial health check if enabled and URL provided
        $validated = false;
        $active_server = 'fallback';
        $validation_error = '';

        if ( $enabled && ! empty( $sgtm_url ) ) {
            $gtm_id = get_option( 'cuft_gtm_id', '' );
            if ( ! empty( $gtm_id ) ) {
                $test_results = $this->test_sgtm_endpoints( $sgtm_url, $gtm_id );
                $validated = $test_results['success'];
                $active_server = $validated ? 'custom' : 'fallback';
                if ( ! $validated ) {
                    $validation_error = $test_results['message'];
                }
            }
        }

        // Set initial active server
        update_option( 'cuft_sgtm_active_server', $active_server );

        $response_data = array(
            'message' => $validated ? 'Configuration saved successfully' : 'Configuration saved. Server validation failed - using fallback',
            'enabled' => $enabled,
            'url' => $sgtm_url,
            'validated' => $validated,
            'active_server' => $active_server
        );

        if ( ! $validated && ! empty( $validation_error ) ) {
            $response_data['validation_error'] = $validation_error;
        }

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for manual health check
     */
    public function ajax_manual_health_check() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Check if custom server is enabled
        $enabled = get_option( 'cuft_sgtm_enabled', false );
        $sgtm_url = get_option( 'cuft_sgtm_url', '' );
        $gtm_id = get_option( 'cuft_gtm_id', '' );

        if ( ! $enabled || empty( $sgtm_url ) || empty( $gtm_id ) ) {
            wp_send_json_error( array( 'message' => 'Custom server not configured' ) );
        }

        // Perform health check
        $test_results = $this->test_sgtm_endpoints( $sgtm_url, $gtm_id );
        $health_check_passed = $test_results['success'];
        $timestamp = time();
        $response_time = $test_results['success'] ? 200.0 : 5000.0; // Mock values

        // Get current counters
        $consecutive_success = get_option( 'cuft_sgtm_health_consecutive_success', 0 );
        $consecutive_failure = get_option( 'cuft_sgtm_health_consecutive_failure', 0 );
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );

        $response_data = array(
            'health_check_passed' => $health_check_passed,
            'message' => $health_check_passed ? 'Health check successful' : 'Health check failed: ' . $test_results['message'],
            'timestamp' => $timestamp,
            'response_time' => $response_time,
            'consecutive_success' => $consecutive_success,
            'consecutive_failure' => $consecutive_failure,
            'active_server' => $active_server
        );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for getting custom server status
     */
    public function ajax_get_sgtm_status() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Get configuration
        $enabled = get_option( 'cuft_sgtm_enabled', false );
        $url = get_option( 'cuft_sgtm_url', '' );
        $validated = get_option( 'cuft_sgtm_validated', false );

        // Get status
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );
        $last_check_time = get_option( 'cuft_sgtm_health_last_check', 0 );
        $last_check_result = get_option( 'cuft_sgtm_health_last_result', false );
        $last_check_message = get_option( 'cuft_sgtm_health_last_message', '' );
        $consecutive_success = get_option( 'cuft_sgtm_health_consecutive_success', 0 );
        $consecutive_failure = get_option( 'cuft_sgtm_health_consecutive_failure', 0 );
        $response_time = get_option( 'cuft_sgtm_health_response_time', 0.0 );

        // Calculate next check time (6 hours from last check)
        $next_check = $last_check_time + (6 * 60 * 60);

        // Human readable timestamps
        $human_readable = array(
            'last_check' => $last_check_time > 0 ? human_time_diff( $last_check_time ) . ' ago' : 'Never',
            'next_check' => $next_check > time() ? human_time_diff( $next_check ) . ' from now' : 'Overdue',
            'status' => $active_server === 'custom' ? 'Healthy' : 'Using Fallback'
        );

        $response_data = array(
            'configuration' => array(
                'enabled' => $enabled,
                'url' => $url,
                'validated' => $validated
            ),
            'status' => array(
                'active_server' => $active_server,
                'last_check_time' => $last_check_time,
                'last_check_result' => $last_check_result,
                'last_check_message' => $last_check_message,
                'consecutive_success' => $consecutive_success,
                'consecutive_failure' => $consecutive_failure,
                'response_time' => $response_time
            ),
            'next_check' => $next_check,
            'human_readable' => $human_readable
        );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX handler for downloading GTM templates
     */
    public function ajax_download_gtm_template() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Get template type
        $template_type = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
        
        if ( empty( $template_type ) || ! in_array( $template_type, array( 'web', 'server' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid template type' ) );
        }

        // Determine file path based on template type
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        if ( $template_type === 'web' ) {
            $file_path = $plugin_dir . 'gtm-web-client/CUFT - Web Defaults.json';
            $filename = 'CUFT-Web-Defaults.json';
        } else {
            $file_path = $plugin_dir . 'gtm-server/CUFT - Server Defaults.json';
            $filename = 'CUFT-Server-Defaults.json';
        }

        // Check if file exists
        if ( ! file_exists( $file_path ) ) {
            wp_send_json_error( array( 'message' => 'Template file not found' ) );
        }

        // Read file content
        $file_content = file_get_contents( $file_path );
        if ( $file_content === false ) {
            wp_send_json_error( array( 'message' => 'Failed to read template file' ) );
        }

        // Return file content as base64 to avoid JSON parsing issues
        wp_send_json_success( array(
            'content' => base64_encode( $file_content ),
            'filename' => $filename
        ) );
    }

    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals( $schedules ) {
        $schedules['six_hours'] = array(
            'interval' => 6 * 60 * 60, // 6 hours in seconds
            'display' => 'Every 6 hours'
        );
        return $schedules;
    }

    /**
     * Scheduled health check callback
     */
    public function scheduled_health_check() {
        // Check if custom server is enabled
        $enabled = get_option( 'cuft_sgtm_enabled', false );
        $sgtm_url = get_option( 'cuft_sgtm_url', '' );
        $gtm_id = get_option( 'cuft_gtm_id', '' );

        if ( ! $enabled || empty( $sgtm_url ) || empty( $gtm_id ) ) {
            return; // Skip health check if not configured
        }

        // Perform health check
        $test_results = $this->test_sgtm_endpoints( $sgtm_url, $gtm_id );
        
        // The store_health_check_result method will handle:
        // - Storing health check results
        // - Updating consecutive counters
        // - Switching between custom/fallback servers
        // - Creating admin notice triggers
    }

    /**
     * Get the appropriate GTM server URL
     * @return string The server URL to use for loading GTM
     */
    public function get_gtm_server_url() {
        // Check if custom server is enabled
        $enabled = get_option( 'cuft_sgtm_enabled', false );
        if ( ! $enabled ) {
            return 'https://www.googletagmanager.com';
        }

        // Check if we should use custom server
        $active_server = get_option( 'cuft_sgtm_active_server', 'fallback' );
        if ( $active_server !== 'custom' ) {
            return 'https://www.googletagmanager.com';
        }

        // Get and validate custom URL
        $custom_url = get_option( 'cuft_sgtm_url', '' );
        if ( empty( $custom_url ) ) {
            return 'https://www.googletagmanager.com';
        }

        // Remove trailing slash if present
        return rtrim( $custom_url, '/' );
    }

    /**
     * Test sGTM endpoints
     */
    private function test_sgtm_endpoints( $sgtm_url, $gtm_id ) {
        $results = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );

        // Determine SSL verification setting based on whether this is a localnet URL
        $is_localnet = $this->is_localnet_url( $sgtm_url );
        $ssl_verify = ! $is_localnet; // Disable SSL verification for localnet URLs

        if ( $is_localnet ) {
            $results['details']['ssl_note'] = 'ℹ️ SSL verification disabled for .localnet domain';
        }

        // Test gtm.js endpoint
        $gtm_js_url = $sgtm_url . '/gtm.js?id=' . $gtm_id;
        $gtm_js_response = wp_remote_get( $gtm_js_url, array(
            'timeout' => 5,
            'sslverify' => $ssl_verify
        ) );

        if ( is_wp_error( $gtm_js_response ) ) {
            $results['message'] = 'Failed to connect to gtm.js endpoint: ' . $gtm_js_response->get_error_message();
            $results['details']['gtm_js'] = 'Error: ' . $gtm_js_response->get_error_message();
            $this->store_health_check_result( $results );
            return $results;
        }

        $gtm_js_code = wp_remote_retrieve_response_code( $gtm_js_response );
        $gtm_js_body = wp_remote_retrieve_body( $gtm_js_response );

        if ( $gtm_js_code !== 200 ) {
            $results['message'] = 'gtm.js endpoint returned status code: ' . $gtm_js_code;
            $results['details']['gtm_js'] = 'HTTP ' . $gtm_js_code;
            $this->store_health_check_result( $results );
            return $results;
        }

        // Check if response looks like GTM JavaScript
        if ( strpos( $gtm_js_body, 'google' ) === false && strpos( $gtm_js_body, 'gtm' ) === false ) {
            $results['message'] = 'gtm.js endpoint does not return valid GTM JavaScript';
            $results['details']['gtm_js'] = 'Invalid response content';
            $this->store_health_check_result( $results );
            return $results;
        }

        $results['details']['gtm_js'] = '✓ OK (HTTP 200)';

        // Test ns.html endpoint
        $ns_html_url = $sgtm_url . '/ns.html?id=' . $gtm_id;
        $ns_html_response = wp_remote_get( $ns_html_url, array(
            'timeout' => 5,
            'sslverify' => $ssl_verify
        ) );

        if ( is_wp_error( $ns_html_response ) ) {
            $results['message'] = 'Failed to connect to ns.html endpoint: ' . $ns_html_response->get_error_message();
            $results['details']['ns_html'] = 'Error: ' . $ns_html_response->get_error_message();
            $this->store_health_check_result( $results );
            return $results;
        }

        $ns_html_code = wp_remote_retrieve_response_code( $ns_html_response );
        $ns_html_body = wp_remote_retrieve_body( $ns_html_response );

        if ( $ns_html_code !== 200 ) {
            $results['message'] = 'ns.html endpoint returned status code: ' . $ns_html_code;
            $results['details']['ns_html'] = 'HTTP ' . $ns_html_code;
            $this->store_health_check_result( $results );
            return $results;
        }

        // Check if response looks like valid HTML
        if ( strpos( $ns_html_body, '<' ) === false ) {
            $results['message'] = 'ns.html endpoint does not return valid HTML';
            $results['details']['ns_html'] = 'Invalid response content';
            $this->store_health_check_result( $results );
            return $results;
        }

        $results['details']['ns_html'] = '✓ OK (HTTP 200)';
        $results['success'] = true;
        $results['message'] = 'Both endpoints validated successfully';

        // Store health check results
        $this->store_health_check_result( $results );

        return $results;
    }

    /**
     * Store health check result in wp_options
     */
    private function store_health_check_result( $results ) {
        $timestamp = time();
        $success = $results['success'];
        $message = $results['message'];
        
        // Calculate response time (simplified - in real implementation, you'd measure actual time)
        $response_time = $success ? 200.0 : 5000.0; // Mock values for now
        
        // Store basic health check data
        update_option( 'cuft_sgtm_health_last_check', $timestamp );
        update_option( 'cuft_sgtm_health_last_result', $success );
        update_option( 'cuft_sgtm_health_last_message', $message );
        update_option( 'cuft_sgtm_health_response_time', $response_time );
        
        // Handle consecutive counters and server switching
        if ( $success ) {
            // Increment consecutive success, reset failure counter
            $consecutive_success = get_option( 'cuft_sgtm_health_consecutive_success', 0 ) + 1;
            update_option( 'cuft_sgtm_health_consecutive_success', $consecutive_success );
            update_option( 'cuft_sgtm_health_consecutive_failure', 0 );
            
            // Switch to custom server after 3 consecutive successes
            if ( $consecutive_success >= 3 ) {
                $current_server = get_option( 'cuft_sgtm_active_server', 'fallback' );
                if ( $current_server !== 'custom' ) {
                    update_option( 'cuft_sgtm_active_server', 'custom' );
                    // Trigger admin notice for server recovery
                    add_option( 'cuft_sgtm_server_recovered', $timestamp );
                }
            }
        } else {
            // Increment consecutive failure, reset success counter
            $consecutive_failure = get_option( 'cuft_sgtm_health_consecutive_failure', 0 ) + 1;
            update_option( 'cuft_sgtm_health_consecutive_failure', $consecutive_failure );
            update_option( 'cuft_sgtm_health_consecutive_success', 0 );
            
            // Switch to fallback on first failure
            $current_server = get_option( 'cuft_sgtm_active_server', 'fallback' );
            if ( $current_server === 'custom' ) {
                update_option( 'cuft_sgtm_active_server', 'fallback' );
                // Trigger admin notice for server failure
                add_option( 'cuft_sgtm_server_failed', $timestamp );
            }
        }
    }

    /**
     * Render setup progress indicator
     */
    private function render_setup_progress() {
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $frameworks = CUFT_Form_Detector::get_framework_status();
        $detected_frameworks = array_filter( $frameworks, function($fw) { return $fw['detected']; } );

        // Calculate setup completion
        $steps = array(
            'gtm_setup' => !empty( $gtm_id ),
            'framework_detected' => !empty( $detected_frameworks )
        );

        $completed_steps = array_filter( $steps );
        $total_steps = count( $steps );
        $completed_count = count( $completed_steps );
        $progress_percentage = ( $completed_count / $total_steps ) * 100;

        // Only show if setup is not complete
        if ( $completed_count < $total_steps ) {
            ?>
            <div class="cuft-setup-progress">
                <h3>Setup Progress</h3>
                <div class="cuft-progress-bar">
                    <div class="cuft-progress-fill" style="width: <?php echo esc_attr( round($progress_percentage) ); ?>%;"></div>
                </div>
                <div class="cuft-progress-steps">
                    <div class="cuft-progress-step <?php echo $steps['gtm_setup'] ? 'completed' : ''; ?>">
                        <span><?php echo $steps['gtm_setup'] ? '✓' : '○'; ?></span>
                        GTM Configuration
                    </div>
                    <div class="cuft-progress-step <?php echo $steps['framework_detected'] ? 'completed' : ''; ?>">
                        <span><?php echo $steps['framework_detected'] ? '✓' : '○'; ?></span>
                        Framework Detected
                    </div>
                    <div class="cuft-progress-step <?php echo ! empty( $steps['testing_complete'] ) ? 'completed' : ''; ?>">
                        <span><?php echo ! empty( $steps['testing_complete'] ) ? '✓' : '○'; ?></span>
                        Testing Complete
                    </div>
                </div>
            </div>
            <?php
        }
    }


    /**
     * Render admin tabs
     */
    private function render_admin_tabs( $current_tab ) {
        $tabs = array(
            'settings' => __( 'Settings', 'choice-universal-form-tracker' ),
        );

        // Force Update drives the self-update subsystem, which the
        // WordPress.org build does not ship. See has_updater().
        if ( CUFT_Plugin::has_updater() ) {
            $tabs['force-update'] = __( 'Force Update', 'choice-universal-form-tracker' );
        }
        
        echo '<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active_class = ( $current_tab === $tab_key ) ? ' nav-tab-active' : '';
            $tab_url = add_query_arg( array( 'tab' => $tab_key ), admin_url( 'options-general.php?page=choice-universal-form-tracker' ) );
            echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html( $tab_label ) . '</a>';
        }
        echo '</nav>';
    }
    
    /**
     * Click Tracking dashboard page callback
     */
    public function click_tracking_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'choice-universal-form-tracker' ) );
        }

        // Handle POST actions
        if ( isset( $_POST['cuft_click_action'], $_POST['cuft_click_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cuft_click_nonce'] ) ), 'cuft_click_tracking' ) ) {
            $this->handle_click_tracking_actions();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Click Tracking', 'choice-universal-form-tracker' ); ?></h1>
            <?php $this->render_click_tracking_tab(); ?>
        </div>
        <?php
    }

    /**
     * Read a GET display parameter (filter, sort, page number) for the Click
     * Tracking screen, unslashed and sanitized. Only called on paths gated by
     * manage_options.
     *
     * @param string $key     Query arg name.
     * @param string $default Value when the arg is absent.
     * @return string
     */
    private function get_query_param( $key, $default = '' ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter/sort/page value on screens gated by manage_options; export callers verify their nonce first.
        return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
    }

    /**
     * Render click tracking tab
     */
    private function render_click_tracking_tab() {
        // Handle pagination
        $current_page = max( 1, absint( $this->get_query_param( 'paged', '1' ) ) );
        $per_page = 20;
        $offset = ( $current_page - 1 ) * $per_page;
        
        // Handle filters
        $filter_qualified = $this->get_query_param( 'filter_qualified' );
        $filter_event_type = $this->get_query_param( 'filter_event_type' );
        $filter_date_from = $this->get_query_param( 'filter_date_from' );
        $filter_date_to = $this->get_query_param( 'filter_date_to' );
        $filter_ip_search = $this->get_query_param( 'filter_ip_search' );
        // Default to showing only clicks with events; submit the filter form with "All" to see everything
        $filter_has_events = $this->get_query_param( 'filter_has_events', '1' );
        $sort_by = $this->get_query_param( 'sort_by', 'date_created' );

        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'sort_by' => $sort_by
        );

        if ( $filter_qualified !== '' ) {
            $args['qualified'] = (int) $filter_qualified;
        }
        if ( ! empty( $filter_event_type ) ) {
            $args['event_type'] = $filter_event_type;
        }
        if ( ! empty( $filter_date_from ) ) {
            $args['date_from'] = $filter_date_from . ' 00:00:00';
        }
        if ( ! empty( $filter_date_to ) ) {
            $args['date_to'] = $filter_date_to . ' 23:59:59';
        }
        if ( ! empty( $filter_ip_search ) ) {
            // Hash the IP address for lookup
            $args['ip_hash'] = hash( 'sha256', $filter_ip_search );
        }
        if ( $filter_has_events !== '' ) {
            $args['has_events'] = $filter_has_events;
        }
        
        $clicks = class_exists( 'CUFT_Click_Tracker' ) ? CUFT_Click_Tracker::get_clicks( $args ) : array();
        $total_clicks = class_exists( 'CUFT_Click_Tracker' ) ? CUFT_Click_Tracker::get_clicks_count( $args ) : 0;
        $total_pages = ceil( $total_clicks / $per_page );
        
        ?>
        <div class="cuft-click-tracking" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="margin: 0;">
                    <span style="margin-right: 8px;">🎯</span>
                    Click Tracking Management
                </h2>
                <div style="display: flex; gap: 10px;">
                    <?php
                    $export_url = wp_nonce_url(
                        add_query_arg( array( 'action' => 'export_csv' ), admin_url( 'admin.php?page=cuft-click-tracking' ) ),
                        'cuft_export_csv',
                        'nonce'
                    );
                    $google_ads_export_url = wp_nonce_url(
                        add_query_arg( array( 'action' => 'export_google_ads_oci' ), admin_url( 'admin.php?page=cuft-click-tracking' ) ),
                        'cuft_export_google_ads_oci',
                        'nonce'
                    );
                    ?>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
                        📊 Export CSV
                    </a>
                    <a href="<?php echo esc_url( $google_ads_export_url ); ?>" class="button button-primary" title="Export GCLID records for Google Ads Offline Conversion Import">
                        🎯 Export for Google Ads
                    </a>
                </div>
            </div>
            
            <?php $this->render_webhook_settings(); ?>
            <?php $this->render_click_tracking_filters( $filter_qualified, $filter_event_type, $filter_date_from, $filter_date_to, $filter_ip_search, $sort_by, $filter_has_events ); ?>
            <?php $this->render_click_tracking_stats( $args ); ?>
            <?php $this->render_click_tracking_table( $clicks ); ?>
            <?php $this->render_click_tracking_pagination( $current_page, $total_pages ); ?>
        </div>
        <?php
    }
    
    /**
     * Render webhook settings
     */
    private function render_webhook_settings() {
        // Use AJAX endpoint which works reliably regardless of permalink settings
        $webhook_url = admin_url( 'admin-ajax.php' );

        // Get a real click_id from the database for the example
        global $wpdb;
        $table_name = $wpdb->prefix . 'cuft_click_tracking';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; no WP API covers it, and the newest row is per-request admin display data.
        $sample_click_id = $wpdb->get_var( $wpdb->prepare( 'SELECT click_id FROM %i ORDER BY date_created DESC LIMIT 1', $table_name ) );
        $has_data = ! empty( $sample_click_id );

        // Use placeholder for display if no data exists
        $display_click_id = $has_data ? $sample_click_id : 'YOUR_CLICK_ID';

        $example_url = add_query_arg( array(
            'action' => 'cuft_webhook',
            'click_id' => $display_click_id,
            'qualified' => '1',
            'score' => '8'
        ), $webhook_url );

        ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">📡 Public Webhook Endpoint</h3>
            <p style="color: #666; margin-top: 0;">
                <strong>Public, obscure endpoint for updating click status from email messages.</strong><br>
                Security through obscurity: The click_id itself acts as the authorization token.
            </p>

            <div style="margin-bottom: 15px;">
                <label><strong>Webhook Base URL:</strong></label><br>
                <input type="text" value="<?php echo esc_attr( $webhook_url ); ?>" readonly class="regular-text cuft-select-on-click" style="font-family: monospace;" />
                <p class="description">Use with action=cuft_webhook parameter</p>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Example Usage:</strong><br>
                <code style="background: white; padding: 8px; display: block; border-radius: 4px; font-size: 12px; word-break: break-all; font-family: monospace;">
                    <?php echo esc_html( $example_url ); ?>
                </code>
                <p class="description" style="margin-top: 8px;">
                    <strong>Parameters:</strong><br>
                    • <code>action</code> = cuft_webhook (required)<br>
                    • <code>click_id</code> = The click ID to update (required, acts as auth token)<br>
                    • <code>qualified</code> = 0 or 1 (optional)<br>
                    • <code>score</code> = 0-10 (optional)
                </p>
            </div>

            <div style="background: white; padding: 12px; border-left: 4px solid #3b82f6; margin-bottom: 15px;">
                <strong>💡 For Email Messages:</strong><br>
                <small style="color: #666;">
                    Embed the webhook URL in email links/images to track lead status updates.
                    The click_id is already obscure (e.g., gclid, fbclid, or generated hash),
                    providing security through obscurity without requiring additional authentication.
                </small>
            </div>

            <div>
                <strong>Test Webhook:</strong><br>
                <div style="display: flex; gap: 10px; align-items: flex-start; margin-top: 10px;">
                    <div style="flex: 1;">
                        <input type="text" id="test-click-id" value="<?php echo $has_data ? esc_attr( $sample_click_id ) : ''; ?>" placeholder="Enter a click_id to test" class="regular-text" style="font-family: monospace;" />
                        <p class="description" style="margin-top: 5px;">
                            <?php if ( $has_data ): ?>
                                Click any Click ID in the table below to copy it here.
                            <?php else: ?>
                                You need at least one click tracking record. Create one by clicking a tracked link or submitting a tracked form.
                            <?php endif; ?>
                        </p>
                    </div>
                    <button type="button" class="button button-secondary" id="cuft-test-webhook" style="margin-top: 0;">
                        🧪 Test Endpoint
                    </button>
                </div>
                <div id="webhook-test-result" style="margin-top: 10px;"></div>
            </div>

            <?php if ( ! $has_data ): ?>
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 4px;">
                <strong>⚠️ No Click Data Available</strong><br>
                <small style="color: #666;">
                    The test button will appear once you have at least one click tracking record in the database.
                    Click tracking records are created when users interact with tracked links or forms.
                </small>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render click tracking filters
     */
    private function render_click_tracking_filters( $filter_qualified, $filter_event_type, $filter_date_from, $filter_date_to, $filter_ip_search, $sort_by, $filter_has_events = '' ) {
        ?>
        <form method="GET" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <input type="hidden" name="page" value="cuft-click-tracking" />

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label><strong>Qualified Status:</strong></label><br>
                    <select name="filter_qualified">
                        <option value="">All</option>
                        <option value="1" <?php selected( $filter_qualified, '1' ); ?>>Qualified</option>
                        <option value="0" <?php selected( $filter_qualified, '0' ); ?>>Not Qualified</option>
                    </select>
                </div>
                <div>
                    <label><strong>Event Type:</strong></label><br>
                    <select name="filter_event_type">
                        <option value="">Any Event</option>
                        <option value="no_event" <?php selected( $filter_event_type, 'no_event' ); ?>>No Event</option>
                        <option value="phone_click" <?php selected( $filter_event_type, 'phone_click' ); ?>>Phone Click</option>
                        <option value="email_click" <?php selected( $filter_event_type, 'email_click' ); ?>>Email Click</option>
                        <option value="form_submit" <?php selected( $filter_event_type, 'form_submit' ); ?>>Form Submit</option>
                        <option value="generate_lead" <?php selected( $filter_event_type, 'generate_lead' ); ?>>Generate Lead</option>
                        <option value="qualify_lead" <?php selected( $filter_event_type, 'qualify_lead' ); ?>>Qualify Lead</option>
                        <option value="disqualify_lead" <?php selected( $filter_event_type, 'disqualify_lead' ); ?>>Disqualify Lead</option>
                        <option value="working_lead" <?php selected( $filter_event_type, 'working_lead' ); ?>>Working Lead</option>
                        <option value="close_convert_lead" <?php selected( $filter_event_type, 'close_convert_lead' ); ?>>Close Convert Lead</option>
                        <option value="close_unconvert_lead" <?php selected( $filter_event_type, 'close_unconvert_lead' ); ?>>Close Unconvert Lead</option>
                        <option value="score_updated" <?php selected( $filter_event_type, 'score_updated' ); ?>>Score Updated</option>
                    </select>
                </div>
                <div>
                    <label><strong>Events:</strong></label><br>
                    <select name="filter_has_events">
                        <option value="">All</option>
                        <option value="1" <?php selected( $filter_has_events, '1' ); ?>>Has Events</option>
                        <option value="0" <?php selected( $filter_has_events, '0' ); ?>>No Events</option>
                    </select>
                </div>
                <div>
                    <label><strong>Search by IP:</strong></label><br>
                    <input type="text" name="filter_ip_search" value="<?php echo esc_attr( $filter_ip_search ); ?>" placeholder="e.g. 192.168.1.100" style="width: 100%;" />
                </div>
                <div>
                    <label><strong>Sort By:</strong></label><br>
                    <select name="sort_by">
                        <option value="date_created" <?php selected( $sort_by, 'date_created' ); ?>>Date Created</option>
                        <option value="date_updated" <?php selected( $sort_by, 'date_updated' ); ?>>Last Activity</option>
                    </select>
                </div>
                <div>
                    <label><strong>Date From:</strong></label><br>
                    <input type="date" name="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" />
                </div>
                <div>
                    <label><strong>Date To:</strong></label><br>
                    <input type="date" name="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" />
                </div>
                <div>
                    <input type="submit" value="Filter" class="button button-secondary" />
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cuft-click-tracking' ) ); ?>" class="button">Clear</a>
                </div>
            </div>
            <?php if ( ! empty( $filter_ip_search ) ) : ?>
            <div style="margin-top: 10px; padding: 10px; background: #e8f5e9; border-radius: 4px;">
                <strong>🔍 IP Search:</strong> Showing results for IP <code><?php echo esc_html( $filter_ip_search ); ?></code>
                (Hash: <code><?php echo esc_html( substr( hash( 'sha256', $filter_ip_search ), 0, 16 ) ); ?>...</code>)
            </div>
            <?php endif; ?>
        </form>
        <?php
    }
    
    /**
     * Render click tracking stats
     */
    private function render_click_tracking_stats( $args ) {
        if ( ! class_exists( 'CUFT_Click_Tracker' ) ) {
            return;
        }
        
        $total_clicks = CUFT_Click_Tracker::get_clicks_count( $args );
        $qualified_args = array_merge( $args, array( 'qualified' => 1 ) );
        $qualified_clicks = CUFT_Click_Tracker::get_clicks_count( $qualified_args );
        $unqualified_clicks = $total_clicks - $qualified_clicks;
        $qualification_rate = $total_clicks > 0 ? round( ( $qualified_clicks / $total_clicks ) * 100, 1 ) : 0;
        
        ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #1976d2;"><?php echo esc_html( number_format( $total_clicks ) ); ?></div>
                <div style="color: #666;">Total Clicks</div>
            </div>
            <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #388e3c;"><?php echo esc_html( number_format( $qualified_clicks ) ); ?></div>
                <div style="color: #666;">Qualified Clicks</div>
            </div>
            <div style="background: #fff3e0; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #f57c00;"><?php echo esc_html( number_format( $unqualified_clicks ) ); ?></div>
                <div style="color: #666;">Unqualified Clicks</div>
            </div>
            <div style="background: #f3e5f5; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #7b1fa2;"><?php echo esc_html( $qualification_rate ); ?>%</div>
                <div style="color: #666;">Qualification Rate</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render click tracking table
     */
    private function render_click_tracking_table( $clicks ) {
        ?>
        <div style="overflow-x: auto;">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Click ID</th>
                        <th>Campaign</th>
                        <th>Events</th>
                        <th>Qualified</th>
                        <th>Score</th>
                        <th>Date Created</th>
                        <th>Date Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $clicks ) ): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                No click tracking data found. Click data will appear here when tracking is active.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $clicks as $click ): ?>
                            <tr>
                                <td style="max-width: 200px;">
                                    <span
                                        class="cuft-click-id-copy"
                                        data-click-id="<?php echo esc_attr( $click->click_id ); ?>"
                                        style="cursor: pointer; display: inline-block; max-width: 100%;"
                                        title="<?php echo esc_attr( $click->click_id ); ?> (click to copy)"
                                    >
                                        <strong style="text-decoration: underline; text-decoration-style: dotted; display: inline-block; max-width: calc(100% - 20px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;"><?php echo esc_html( $click->click_id ); ?></strong>
                                        <span class="dashicons dashicons-clipboard" style="font-size: 14px; vertical-align: middle; color: #666;"></span>
                                    </span>
                                    <?php if ( ! empty( $click->ip_hash ) ): ?>
                                        <br><small style="color: #666;" title="<?php echo esc_attr( $click->ip_hash ); ?>">IP: <?php echo esc_html( substr( $click->ip_hash, 0, 12 ) ); ?>…</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $click->campaign ?: '-' ); ?></td>
                                <td>
                                    <?php
                                    // Display events timeline (v3.12.0+)
                                    $events = isset( $click->events ) ? CUFT_Click_Tracker::get_events( $click->click_id ) : array();
                                    if ( ! empty( $events ) ) :
                                        // Sort events by timestamp descending (newest first)
                                        usort( $events, function( $a, $b ) {
                                            return strcmp( $b['timestamp'], $a['timestamp'] );
                                        });

                                        // Display first 3 events, collapse rest
                                        $visible_events = array_slice( $events, 0, 3 );
                                        $hidden_events = array_slice( $events, 3 );

                                        foreach ( $visible_events as $event ):
                                            $event_type = $event['event'];
                                            $event_time = gmdate( 'M j, g:i A', strtotime( $event['timestamp'] ) );

                                            // Badge colors by event type
                                            $badge_colors = array(
                                                'phone_click' => '#3b82f6',
                                                'email_click' => '#8b5cf6',
                                                'form_submit' => '#10b981',
                                                'generate_lead' => '#f59e0b',
                                                'qualify_lead' => '#10b981',
                                                'disqualify_lead' => '#ef4444',
                                                'working_lead' => '#f59e0b',
                                                'close_convert_lead' => '#059669',
                                                'close_unconvert_lead' => '#6b7280',
                                                'score_updated' => '#06b6d4'
                                            );
                                            $badge_color = isset( $badge_colors[ $event_type ] ) ? $badge_colors[ $event_type ] : '#6b7280';
                                            ?>
                                            <span style="display: inline-block; padding: 3px 8px; margin: 2px; border-radius: 4px; font-size: 11px; color: white; background: <?php echo esc_attr( $badge_color ); ?>;">
                                                <?php echo esc_html( $event_type ); ?>
                                            </span>
                                            <small style="color: #666; font-size: 10px;"><?php echo esc_html( $event_time ); ?></small>
                                            <br>
                                        <?php endforeach;

                                        if ( ! empty( $hidden_events ) ) :
                                            ?>
                                            <small style="color: #666;">+<?php echo esc_html( count( $hidden_events ) ); ?> more</small>
                                        <?php endif;
                                    else: ?>
                                        <span style="color: #999;">No events</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $click->qualified ? '#28a745' : '#6c757d'; ?>;">
                                        <?php echo esc_html( $click->qualified ? 'YES' : 'NO' ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: #f8f9fa;">
                                        <?php echo (int) $click->score; ?>/10
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $click->date_created ) ) ); ?>
                                    <br><small style="color: #666;">UTC</small>
                                </td>
                                <td>
                                    <?php if ( $click->date_updated !== $click->date_created ): ?>
                                        <?php echo esc_html( gmdate( 'M j, Y g:i A', strtotime( $click->date_updated ) ) ); ?>
                                        <br><small style="color: #666;">UTC</small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small cuft-edit-click" data-click-id="<?php echo esc_attr( $click->click_id ); ?>" data-qualified="<?php echo (int) $click->qualified; ?>" data-score="<?php echo (int) $click->score; ?>">
                                        ✏️ Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Edit Click Modal -->
        <div id="edit-click-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; min-width: 400px;">
                <h3 style="margin-top: 0;">Edit Click Status</h3>
                <form method="POST">
                    <?php wp_nonce_field( 'cuft_click_tracking', 'cuft_click_nonce' ); ?>
                    <input type="hidden" name="cuft_click_action" value="update_status" />
                    <input type="hidden" name="click_id" id="edit-click-id" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Qualified:</th>
                            <td>
                                <label><input type="radio" name="qualified" value="1" id="edit-qualified-yes" /> Yes</label><br>
                                <label><input type="radio" name="qualified" value="0" id="edit-qualified-no" /> No</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Score (0-10):</th>
                            <td>
                                <input type="number" name="score" id="edit-score" min="0" max="10" step="1" />
                            </td>
                        </tr>
                    </table>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="button cuft-close-edit-modal">Cancel</button>
                        <input type="submit" value="Update" class="button button-primary" />
                    </div>
                </form>
            </div>
        </div>
        
        <?php // Table styles live in assets/cuft-admin.css; copy, edit and modal handlers in assets/cuft-admin.js. ?>
        <?php
    }
    
    /**
     * Render pagination
     */
    private function render_click_tracking_pagination( $current_page, $total_pages ) {
        if ( $total_pages <= 1 ) {
            return;
        }
        
        $base_url = admin_url( 'admin.php?page=cuft-click-tracking' );
        
        // Preserve current filters
        $filter_params = array();
        foreach ( array( 'filter_qualified', 'filter_event_type', 'filter_has_events', 'filter_date_from', 'filter_date_to', 'sort_by' ) as $param ) {
            $value = $this->get_query_param( $param );
            if ( '' !== $value ) {
                $filter_params[ $param ] = $value;
            }
        }
        
        ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php if ( $current_page > 1 ): ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_params, array( 'paged' => $current_page - 1 ) ), $base_url ) ); ?>" class="button">« Previous</a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?php echo esc_html( $current_page ); ?> of <?php echo esc_html( $total_pages ); ?>
            </span>
            
            <?php if ( $current_page < $total_pages ): ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_params, array( 'paged' => $current_page + 1 ) ), $base_url ) ); ?>" class="button">Next »</a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle click tracking actions
     */
    private function handle_click_tracking_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'choice-universal-form-tracker' ) );
        }

        // The caller already verified this nonce; checking it here keeps the
        // check next to the code that trusts $_POST.
        check_admin_referer( 'cuft_click_tracking', 'cuft_click_nonce' );
        
        $action = isset( $_POST['cuft_click_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cuft_click_action'] ) ) : '';
        
        if ( $action === 'update_status' ) {
            $click_id = isset( $_POST['click_id'] ) ? sanitize_text_field( wp_unslash( $_POST['click_id'] ) ) : '';
            $qualified = isset( $_POST['qualified'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['qualified'] ) ) : null;
            $score = isset( $_POST['score'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['score'] ) ) : null;
            
            if ( ! empty( $click_id ) && class_exists( 'CUFT_Click_Tracker' ) ) {
                $result = CUFT_Click_Tracker::update_click_status( $click_id, $qualified, $score );
                
                if ( $result !== false ) {
                    add_settings_error( 'cuft_messages', 'cuft_message', 'Click status updated successfully!', 'updated' );
                } else {
                    add_settings_error( 'cuft_messages', 'cuft_message', 'Failed to update click status.', 'error' );
                }
            }
        }
        
        settings_errors( 'cuft_messages' );
    }
    
    /**
     * Handle CSV export
     */
    private function handle_csv_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'choice-universal-form-tracker' ) );
        }

        // handle_export_actions() verified this nonce already; repeat it next
        // to the code that reads the request.
        check_admin_referer( 'cuft_export_csv', 'nonce' );
        
        if ( ! class_exists( 'CUFT_Click_Tracker' ) ) {
            wp_die( esc_html__( 'Click tracker not available', 'choice-universal-form-tracker' ) );
        }
        
        // Get filter parameters
        $args = array();

        $filter_qualified = $this->get_query_param( 'filter_qualified' );
        if ( '' !== $filter_qualified ) {
            $args['qualified'] = (int) $filter_qualified;
        }
        $filter_event_type = $this->get_query_param( 'filter_event_type' );
        if ( ! empty( $filter_event_type ) ) {
            $args['event_type'] = $filter_event_type;
        }
        $filter_date_from = $this->get_query_param( 'filter_date_from' );
        if ( ! empty( $filter_date_from ) ) {
            $args['date_from'] = $filter_date_from . ' 00:00:00';
        }
        $filter_date_to = $this->get_query_param( 'filter_date_to' );
        if ( ! empty( $filter_date_to ) ) {
            $args['date_to'] = $filter_date_to . ' 23:59:59';
        }
        $sort_by = $this->get_query_param( 'sort_by' );
        if ( ! empty( $sort_by ) ) {
            $args['sort_by'] = $sort_by;
        }
        
        CUFT_Click_Tracker::export_csv( $args );
    }

    /**
     * Handle Google Ads OCI export
     */
    private function handle_google_ads_oci_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'choice-universal-form-tracker' ) );
        }

        // handle_export_actions() verified this nonce already; repeat it next
        // to the code that reads the request.
        check_admin_referer( 'cuft_export_google_ads_oci', 'nonce' );

        if ( ! class_exists( 'CUFT_Click_Tracker' ) ) {
            wp_die( esc_html__( 'Click tracker not available', 'choice-universal-form-tracker' ) );
        }

        // Get filter parameters (same as regular CSV export)
        $args = array();

        $filter_qualified = $this->get_query_param( 'filter_qualified' );
        if ( '' !== $filter_qualified ) {
            $args['qualified'] = (int) $filter_qualified;
        }
        $filter_date_from = $this->get_query_param( 'filter_date_from' );
        if ( ! empty( $filter_date_from ) ) {
            $args['date_from'] = $filter_date_from . ' 00:00:00';
        }
        $filter_date_to = $this->get_query_param( 'filter_date_to' );
        if ( ! empty( $filter_date_to ) ) {
            $args['date_to'] = $filter_date_to . ' 23:59:59';
        }

        CUFT_Click_Tracker::export_google_ads_oci_csv( $args );
    }

    /**
     * Regenerate webhook key
     */
    private function regenerate_webhook_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'choice-universal-form-tracker' ) );
        }
        
        $new_key = wp_generate_password( 32, false );
        update_option( 'cuft_webhook_key', $new_key );
        
        add_settings_error( 'cuft_messages', 'cuft_message', 'Webhook key regenerated successfully!', 'updated' );
        settings_errors( 'cuft_messages' );
        
        // Redirect back to click tracking tab
        wp_safe_redirect( admin_url( 'admin.php?page=cuft-click-tracking' ) );
        exit;
    }
    
    /**
     * AJAX handler for dismissing the admin notice
     */
    public function ajax_dismiss_notice() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_dismiss_notice' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Save user meta to remember dismissal
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'cuft_notice_dismissed', true );

        wp_send_json_success( array( 'message' => 'Notice dismissed' ) );
    }

    /**
     * AJAX handler for dismissing update notice (per version)
     */
    public function ajax_dismiss_update_notice() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cuft_dismiss_update_notice' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Get version from request
        $version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';

        if ( empty( $version ) ) {
            wp_send_json_error( array( 'message' => 'Version not specified' ) );
        }

        // Save user meta to remember dismissal for this specific version
        $user_id = get_current_user_id();
        $dismissed_version_key = 'cuft_notice_dismissed_v' . str_replace( '.', '_', $version );
        update_user_meta( $user_id, $dismissed_version_key, true );

        wp_send_json_success( array( 'message' => 'Update notice dismissed for version ' . $version ) );
    }


    /**
     * Screen IDs of the plugin's own admin pages: the settings page, the
     * Click Tracking page, and the Testing Dashboard.
     *
     * @return string[]
     */
    private function get_plugin_screen_ids() {
        return array(
            'settings_page_choice-universal-form-tracker',
            'toplevel_page_cuft-click-tracking',
            'settings_page_cuft-testing-dashboard',
        );
    }

    /**
     * Current admin screen ID, or an empty string before the screen is set.
     *
     * @return string
     */
    private function get_current_screen_id() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return '';
        }
        $screen = get_current_screen();
        return $screen ? (string) $screen->id : '';
    }

    /**
     * Whether the informational "tracker is active" notice should render on
     * this request: plugin screens only, valid GTM ID, not dismissed by this user.
     *
     * @return bool
     */
    private function should_show_active_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( ! in_array( $this->get_current_screen_id(), $this->get_plugin_screen_ids(), true ) ) {
            return false;
        }
        $gtm_id = get_option( 'cuft_gtm_id' );
        if ( ! $gtm_id || ! $this->is_valid_gtm_id( $gtm_id ) ) {
            return false;
        }
        return ! get_user_meta( get_current_user_id(), 'cuft_notice_dismissed', true );
    }

    /**
     * Display admin notices.
     *
     * Scope, so nothing nags site-wide:
     * - Missing or invalid GTM ID (a setup problem): the Plugins screen and the
     *   plugin's own screens other than the settings page, which carries the
     *   form and its own setup progress box.
     * - "Tracker is active" (informational): the plugin's own screens only,
     *   dismissible, and dismissal persists per user in the
     *   cuft_notice_dismissed user meta.
     * - Custom GTM server switched to fallback (operational warning): the
     *   Plugins screen and the plugin's own screens.
     * - Custom GTM server recovered (informational): the plugin's own screens.
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen_id      = $this->get_current_screen_id();
        $is_plugin_page = in_array( $screen_id, $this->get_plugin_screen_ids(), true );
        $is_plugins     = ( 'plugins' === $screen_id );

        if ( ! $is_plugin_page && ! $is_plugins ) {
            return;
        }

        $gtm_id = get_option( 'cuft_gtm_id' );
        $settings_url = admin_url( 'options-general.php?page=choice-universal-form-tracker' );

        // Check if GTM ID is missing or invalid
        $gtm_missing = ! $gtm_id || ! $this->is_valid_gtm_id( $gtm_id );

        if ( $gtm_missing ) {
            if ( 'settings_page_choice-universal-form-tracker' !== $screen_id ) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>Choice Universal Form Tracker:</strong> GTM container ID is missing or invalid. ';
                echo 'Please <a href="' . esc_url( $settings_url ) . '"><strong>configure your GTM ID</strong></a> to enable conversion tracking.</p>';
                echo '</div>';
            }
        } elseif ( $this->should_show_active_notice() ) {
            $detected_count = count( array_filter( CUFT_Form_Detector::get_detected_frameworks() ) );

            // Dismissible success notice; the dismiss click is sent to
            // cuft_dismiss_notice by the inline script added in enqueue_admin_scripts().
            echo '<div class="notice notice-success is-dismissible" data-dismiss-action="cuft-dismiss-notice">';
            echo '<p><strong>Choice Universal Form Tracker</strong> is active with ' . esc_html( $detected_count ) . ' form framework(s) detected. ';
            echo 'GTM container <code>' . esc_html( $gtm_id ) . '</code> is configured. ';
            echo '<a href="' . esc_url( $settings_url ) . '">Settings</a></p>';
            echo '</div>';
        }

        // Check for custom server status change notices
        $this->check_server_status_notices( $is_plugin_page );
    }

    /**
     * Check and display server status change notices
     */
    private function check_server_status_notices( $is_plugin_page = true ) {
        $settings_url = admin_url( 'options-general.php?page=choice-universal-form-tracker' );
        
        // Check for server recovery notice (informational: plugin screens only)
        $server_recovered = $is_plugin_page ? get_option( 'cuft_sgtm_server_recovered', false ) : false;
        if ( $server_recovered ) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ Custom GTM server is now active</strong><br>';
            echo 'Your custom server has passed 3 consecutive health checks and is now being used for GTM script loading. ';
            echo '<a href="' . esc_url( $settings_url ) . '">View status</a></p>';
            echo '</div>';
            
            // Clean up the trigger
            delete_option( 'cuft_sgtm_server_recovered' );
        }
        
        // Check for server failure notice
        $server_failed = get_option( 'cuft_sgtm_server_failed', false );
        if ( $server_failed ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>⚠️ Custom GTM server unavailable, using fallback</strong><br>';
            echo 'Your custom server failed a health check and the system has automatically switched to Google\'s default endpoints. ';
            echo '<a href="' . esc_url( $settings_url ) . '">View status</a></p>';
            echo '</div>';
            
            // Clean up the trigger
            delete_option( 'cuft_sgtm_server_failed' );
        }
    }

}
