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
        add_action( 'wp_ajax_cuft_manual_update_check', array( $this, 'manual_update_check' ) );
        add_action( 'wp_ajax_cuft_test_sgtm', array( $this, 'ajax_test_sgtm' ) );
        add_action( 'wp_ajax_cuft_install_update', array( $this, 'ajax_install_update' ) );
        add_action( 'wp_ajax_cuft_test_form_submit', array( $this, 'ajax_test_form_submit' ) );
        add_action( 'wp_ajax_cuft_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
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
    }
    
    /**
     * Render admin page
     */
    public function admin_page() {
        // Handle form submission
        if ( isset( $_POST['cuft_save'] ) && wp_verify_nonce( $_POST['cuft_nonce'], 'cuft_settings' ) ) {
            $this->save_settings();
        }
        
        // Handle manual update check
        if ( isset( $_POST['cuft_manual_update'] ) && wp_verify_nonce( $_POST['cuft_manual_nonce'], 'cuft_manual_update' ) ) {
            $this->handle_manual_update_check();
        }
        
        // Handle click tracking actions
        if ( isset( $_POST['cuft_click_action'] ) && wp_verify_nonce( $_POST['cuft_click_nonce'], 'cuft_click_tracking' ) ) {
            $this->handle_click_tracking_actions();
        }
        
        // Handle CSV export
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' && wp_verify_nonce( $_GET['nonce'], 'cuft_export_csv' ) ) {
            $this->handle_csv_export();
        }
        
        // Handle webhook key regeneration
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'regenerate_webhook_key' && wp_verify_nonce( $_GET['_wpnonce'], 'regenerate_webhook_key' ) ) {
            $this->regenerate_webhook_key();
        }
        
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $debug_enabled = get_option( 'cuft_debug_enabled', false );
        $generate_lead_enabled = get_option( 'cuft_generate_lead_enabled', false );
        $console_logging = get_option( 'cuft_console_logging', 'no' );
        $github_updates_enabled = get_option( 'cuft_github_updates_enabled', true );
        
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
        ?>
        <div class="wrap">
            <div style="display: flex; align-items: center; margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                    <span style="color: white; font-size: 24px; font-weight: bold;">📊</span>
                </div>
                <h1 style="margin: 0;">Choice Universal Form Tracker</h1>
            </div>
            
            <?php $this->render_admin_tabs( $current_tab ); ?>
            
            <?php if ( $current_tab === 'settings' ): ?>
                <?php $this->render_settings_form( $gtm_id, $debug_enabled, $generate_lead_enabled, $console_logging, $github_updates_enabled ); ?>
                <?php $this->render_framework_status(); ?>
                <?php $this->render_github_status(); ?>
                <?php $this->render_utm_status(); ?>
                <?php $this->render_debug_section(); ?>
            <?php elseif ( $current_tab === 'click-tracking' ): ?>
                <?php $this->render_click_tracking_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings form
     */
    private function render_settings_form( $gtm_id, $debug_enabled, $generate_lead_enabled, $console_logging, $github_updates_enabled ) {
        ?>
        <div class="cuft-settings-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">Settings</h2>
            <form method="post">
                <?php wp_nonce_field( 'cuft_settings', 'cuft_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Google Tag Manager ID</th>
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
                        <th scope="row">Server GTM URL</th>
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
                                Example: <code>https://gtm.yourdomain.com</code> or <code>https://yourdomain.com/gtm</code>
                            </p>
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
                                <input type="checkbox" name="generate_lead_enabled" value="1" <?php checked( $generate_lead_enabled ); ?> />
                                Fire generate_lead events for qualified form submissions
                            </label>
                            <p class="description">
                                Automatically creates generate_lead events when forms are submitted with both user email and UTM campaign data. Ideal for conversion tracking in GA4.
                            </p>
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
                        <th scope="row" colspan="2" style="padding: 20px 0 10px 0; border-top: 1px solid #ddd;">
                            <h3 style="margin: 0; color: #23282d;">GitHub Auto-Updates</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Enable GitHub Updates</th>
                        <td>
                            <label>
                                <input type="checkbox" name="github_updates_enabled" value="1" <?php checked( $github_updates_enabled ); ?> />
                                Enable automatic updates from GitHub repository
                            </label>
                            <p class="description">
                                When enabled, the plugin will check for updates from the official GitHub repository instead of WordPress.org. 
                                Updates are checked twice daily and you'll be notified in the WordPress admin. No authentication required for public repositories.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings', 'primary', 'cuft_save' ); ?>
            </form>
        </div>
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
            <?php
            // Add link to test page
            $test_page_id = get_option( 'cuft_test_page_id' );
            if ( $test_page_id && get_post( $test_page_id ) ):
                $test_page_url = get_permalink( $test_page_id );
            ?>
                <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>🧪 Frontend Testing Available</strong><br>
                            <small>Test forms with full Tag Assistant visibility and real dataLayer events</small>
                        </div>
                        <a href="<?php echo esc_url( $test_page_url ); ?>" target="_blank" class="button button-primary">
                            Open Test Page →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php foreach ( $frameworks as $framework ): ?>
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: <?php echo $framework['detected'] ? '#e8f5e8' : '#f8f8f8'; ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <strong><?php echo esc_html( $framework['name'] ); ?></strong>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $framework['detected'] ? '#28a745' : '#6c757d'; ?>;">
                                <?php echo $framework['detected'] ? 'DETECTED' : 'NOT FOUND'; ?>
                            </span>
                        </div>
                        <?php if ( $framework['detected'] ): ?>
                            <div class="cuft-test-form-wrapper" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                                <div style="margin-bottom: 8px; font-size: 12px; color: #666;">
                                    Test submissions will be sent to: <strong><?php echo esc_html( $admin_email ); ?></strong>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <button type="button"
                                            class="button button-secondary cuft-test-form-submit"
                                            data-framework="<?php echo esc_attr( $framework['key'] ); ?>"
                                            data-email="<?php echo esc_attr( $admin_email ); ?>">
                                        📧 Submit Test Form
                                    </button>
                                </div>
                                <div class="cuft-test-result" id="test-result-<?php echo esc_attr( $framework['key'] ); ?>" style="margin-top: 10px; display: none;"></div>
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
                                <span style="color: #6c757d;">—</span> Disabled - Using standard GTM
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
        $gtm_id = sanitize_text_field( $_POST['gtm_id'] );
        $debug_enabled = isset( $_POST['debug_enabled'] ) && $_POST['debug_enabled'];
        $generate_lead_enabled = isset( $_POST['generate_lead_enabled'] ) && $_POST['generate_lead_enabled'];
        $console_logging = in_array( $_POST['console_logging'], array( 'no', 'yes', 'admin_only' ) ) ? $_POST['console_logging'] : 'no';
        $github_updates_enabled = isset( $_POST['github_updates_enabled'] ) && $_POST['github_updates_enabled'];
        $sgtm_enabled = isset( $_POST['sgtm_enabled'] ) && $_POST['sgtm_enabled'];
        $sgtm_url = isset( $_POST['sgtm_url'] ) ? sanitize_text_field( $_POST['sgtm_url'] ) : '';

        // Remove trailing slash from sGTM URL
        $sgtm_url = rtrim( $sgtm_url, '/' );

        // Validate GTM-ID format
        if ( empty( $gtm_id ) || $this->is_valid_gtm_id( $gtm_id ) ) {
            update_option( 'cuft_gtm_id', $gtm_id );
            update_option( 'cuft_debug_enabled', $debug_enabled );
            update_option( 'cuft_generate_lead_enabled', $generate_lead_enabled );
            update_option( 'cuft_console_logging', $console_logging );
            update_option( 'cuft_github_updates_enabled', $github_updates_enabled );
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

        return true;
    }
    
    /**
     * Render GitHub update status
     */
    private function render_github_status() {
        $github_updates_enabled = get_option( 'cuft_github_updates_enabled', true );
        $current_version = CUFT_VERSION;
        
        ?>
        <div class="cuft-github-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">
                <span style="margin-right: 8px;">🔄</span>
                GitHub Auto-Updates
            </h2>
            
            <?php if ( $github_updates_enabled ): ?>
                <div style="padding: 15px; background: #e8f5e8; border-radius: 6px; border-left: 4px solid #28a745;">
                    <h4 style="margin-top: 0; color: #155724;">GitHub Updates Enabled</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <div style="background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #d4edda;">
                            <strong style="color: #495057; font-size: 12px; text-transform: uppercase;">Current Version</strong><br>
                            <span style="color: #155724;"><?php echo esc_html( $current_version ); ?></span>
                        </div>
                        <div style="background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #d4edda;">
                            <strong style="color: #495057; font-size: 12px; text-transform: uppercase;">Repository</strong><br>
                            <span style="color: #155724;">ChoiceOMG/choice-uft</span>
                        </div>
                        <div style="background: white; padding: 8px 12px; border-radius: 4px; border: 1px solid #d4edda;">
                            <strong style="color: #495057; font-size: 12px; text-transform: uppercase;">Access Type</strong><br>
                            <span style="color: #155724;">Public Repository</span>
                        </div>
                    </div>
                    <p style="margin-bottom: 0; color: #155724; font-size: 14px; margin-top: 10px;">
                        <strong>✓</strong> Plugin will automatically check for updates from GitHub twice daily.
                    </p>
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #d4edda;">
                        <form method="post" style="display: inline-block; margin-right: 10px;">
                            <?php wp_nonce_field( 'cuft_manual_update', 'cuft_manual_nonce' ); ?>
                            <input type="submit" name="cuft_manual_update" value="Check for Updates Now" class="button button-secondary" />
                        </form>
                        <button type="button" id="cuft-ajax-update-check" class="button button-secondary" style="display: none; margin-right: 10px;">Check for Updates (AJAX)</button>
                        <button type="button" id="cuft-download-install" class="button button-primary" style="display: none;">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Download & Install Update
                        </button>
                        <div id="cuft-update-result" style="margin-top: 10px;"></div>
                        <div id="cuft-install-progress" style="margin-top: 10px; display: none;">
                            <div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">
                                <span class="spinner is-active" style="float: none; vertical-align: middle;"></span>
                                <span id="cuft-install-status">Preparing update...</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="padding: 15px; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">GitHub Updates Disabled</h4>
                    <p style="margin-bottom: 0; color: #856404;">
                        GitHub updates are currently disabled. The plugin will use WordPress.org for updates instead. 
                        Enable GitHub updates in the settings above to get the latest features directly from the public repository.
                    </p>
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ffc107;">
                        <form method="post" style="display: inline-block;">
                            <?php wp_nonce_field( 'cuft_manual_update', 'cuft_manual_nonce' ); ?>
                            <input type="submit" name="cuft_manual_update" value="Check for Updates Anyway" class="button button-secondary" disabled title="Enable GitHub updates first" />
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <h4 style="margin-top: 0;">How GitHub Updates Work</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>1. Check for Updates</strong><br>
                        <small>WordPress checks the GitHub repository for new releases twice daily</small>
                    </div>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>2. Download & Install</strong><br>
                        <small>Updates are downloaded directly from GitHub and installed automatically</small>
                    </div>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>3. Stay Current</strong><br>
                        <small>Get the latest features and fixes as soon as they're released</small>
                    </div>
                </div>
                <p style="margin-top: 10px; margin-bottom: 0; color: #6c757d; font-size: 14px;">
                    <strong>Repository:</strong> <a href="https://github.com/ChoiceOMG/choice-uft" target="_blank">https://github.com/ChoiceOMG/choice-uft</a>
                </p>
            </div>
        </div>
        <?php
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
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                <h4 style="margin-top: 0;">How UTM Tracking Works</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>1. Detection</strong><br>
                        <small>UTM parameters are captured when users first visit your site</small>
                    </div>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>2. Storage</strong><br>
                        <small>Campaign data is stored in the user's session for 30 days</small>
                    </div>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 4px;">
                        <strong>3. Attribution</strong><br>
                        <small>Form submissions include campaign data for proper tracking</small>
                    </div>
                </div>
            </div>
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
                                    <td><span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; background: <?php echo $this->get_log_level_color( $log['level'] ); ?>; color: white;"><?php echo esc_html( strtoupper( $log['level'] ) ); ?></span></td>
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
        if ( isset( $_POST['cuft_clear_logs'] ) && wp_verify_nonce( $_POST['cuft_clear_nonce'], 'cuft_clear_logs' ) ) {
            CUFT_Logger::clear_logs();
            echo '<div class="notice notice-success is-dismissible"><p>Debug logs cleared.</p></div>';
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
        // Always enqueue admin CSS for the notice styling
        if ( function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style( 
                'cuft-admin', 
                CUFT_URL . '/assets/cuft-admin.css', 
                array(), 
                CUFT_VERSION 
            );
        }
        
        // Only enqueue JS on the settings page
        if ( $hook !== 'settings_page_choice-universal-form-tracker' ) {
            return;
        }
        
        // Check if WordPress functions exist before enqueuing
        if ( function_exists( 'wp_enqueue_script' ) && function_exists( 'wp_localize_script' ) ) {
            wp_enqueue_script( 
                'cuft-admin', 
                CUFT_URL . '/assets/cuft-admin.js', 
                array( 'jquery' ), 
                CUFT_VERSION, 
                true 
            );
        } else {
            return; // Exit early if WordPress functions aren't available
        }
        
        wp_localize_script( 'cuft-admin', 'cuftAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cuft_ajax_nonce' )
        ));
    }
    
    /**
     * Handle manual update check (form submission)
     */
    private function handle_manual_update_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $github_updates_enabled = get_option( 'cuft_github_updates_enabled', true );
        
        if ( ! $github_updates_enabled ) {
            add_settings_error( 'cuft_messages', 'cuft_message', 'GitHub updates are disabled. Enable them in settings first.', 'error' );
            settings_errors( 'cuft_messages' );
            return;
        }
        
        // Get GitHub updater instance
        global $cuft_updater;
        if ( ! $cuft_updater || ! method_exists( $cuft_updater, 'force_check' ) ) {
            add_settings_error( 'cuft_messages', 'cuft_message', 'GitHub updater not available.', 'error' );
            settings_errors( 'cuft_messages' );
            return;
        }
        
        try {
            $remote_version = $cuft_updater->force_check();
            $current_version = CUFT_VERSION;
            
            if ( version_compare( $current_version, $remote_version, '<' ) ) {
                add_settings_error( 'cuft_messages', 'cuft_message', "Update available! Current: {$current_version}, Latest: {$remote_version}. You can update from the WordPress Plugins page.", 'updated' );
            } else {
                add_settings_error( 'cuft_messages', 'cuft_message', "Plugin is up to date. Current version: {$current_version}", 'updated' );
            }
        } catch ( Exception $e ) {
            add_settings_error( 'cuft_messages', 'cuft_message', 'Error checking for updates: ' . $e->getMessage(), 'error' );
        }
        
        settings_errors( 'cuft_messages' );
    }
    
    /**
     * Handle AJAX manual update check
     */
    public function manual_update_check() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $github_updates_enabled = get_option( 'cuft_github_updates_enabled', true );

        if ( ! $github_updates_enabled ) {
            wp_send_json_error( array(
                'message' => 'GitHub updates are disabled. Enable them in settings first.'
            ) );
        }

        // Get GitHub updater instance
        global $cuft_updater;
        if ( ! $cuft_updater || ! method_exists( $cuft_updater, 'force_check' ) ) {
            wp_send_json_error( array(
                'message' => 'GitHub updater not available.'
            ) );
        }

        try {
            $remote_version = $cuft_updater->force_check();
            $current_version = CUFT_VERSION;

            $response = array();

            if ( version_compare( $current_version, $remote_version, '<' ) ) {
                $response['message'] = "Update available! Current: {$current_version}, Latest: {$remote_version}";
                $response['update_available'] = true;
                $response['current_version'] = $current_version;
                $response['latest_version'] = $remote_version;
            } else {
                $response['message'] = "Plugin is up to date. Current version: {$current_version}";
                $response['update_available'] = false;
                $response['current_version'] = $current_version;
            }

            wp_send_json_success( $response );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Error checking for updates: ' . $e->getMessage()
            ) );
        }
    }

    /**
     * AJAX handler for installing plugin update
     */
    public function ajax_install_update() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $latest_version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
        if ( empty( $latest_version ) ) {
            wp_send_json_error( array( 'message' => 'No version specified' ) );
        }

        // Include necessary WordPress files for plugin updates
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/misc.php' );
        require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

        // Define custom upgrader skin class after WP_Upgrader_Skin is loaded
        if ( ! class_exists( 'CUFT_Ajax_Upgrader_Skin' ) ) {
            // Create an anonymous class that extends WP_Upgrader_Skin
            eval('
            class CUFT_Ajax_Upgrader_Skin extends WP_Upgrader_Skin {
                public $messages = array();

                public function feedback( $string, ...$args ) {
                    if ( ! empty( $args ) ) {
                        $string = vsprintf( $string, $args );
                    }
                    $this->messages[] = $string;
                }

                public function header() {}
                public function footer() {}

                public function error( $errors ) {
                    if ( is_string( $errors ) ) {
                        $this->messages[] = "Error: " . $errors;
                    } elseif ( is_wp_error( $errors ) ) {
                        $this->messages[] = "Error: " . $errors->get_error_message();
                    }
                }
            }
            ');
        }

        try {
            // Get download URL for the latest version
            $download_url = "https://github.com/ChoiceOMG/choice-uft/archive/refs/tags/v{$latest_version}.zip";

            // Initialize the upgrader
            $skin = new CUFT_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader( $skin );

            // Perform the upgrade
            $result = $upgrader->upgrade( CUFT_BASENAME, array(
                'package' => $download_url,
                'destination' => WP_PLUGIN_DIR,
                'clear_destination' => true,
                'clear_working' => true,
                'hook_extra' => array(
                    'plugin' => CUFT_BASENAME,
                    'type' => 'plugin',
                    'action' => 'update'
                )
            ) );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array(
                    'message' => 'Update failed: ' . $result->get_error_message(),
                    'details' => $skin->messages
                ) );
            } elseif ( $result === false ) {
                wp_send_json_error( array(
                    'message' => 'Update failed: Unknown error',
                    'details' => $skin->messages
                ) );
            } else {
                // Clear update transients
                delete_site_transient( 'update_plugins' );
                delete_transient( 'cuft_github_version' );

                wp_send_json_success( array(
                    'message' => "Successfully updated to version {$latest_version}",
                    'details' => $skin->messages,
                    'reload_needed' => true
                ) );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Update error: ' . $e->getMessage()
            ) );
        }
    }

    /**
     * AJAX handler for testing sGTM endpoint
     */
    public function ajax_test_sgtm() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $sgtm_url = isset( $_POST['sgtm_url'] ) ? sanitize_text_field( $_POST['sgtm_url'] ) : '';
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
                'message' => 'Server GTM endpoints validated successfully!',
                'details' => $test_results['details']
            ) );
        } else {
            update_option( 'cuft_sgtm_validated', false );
            wp_send_json_error( array(
                'message' => $test_results['message'],
                'details' => $test_results['details']
            ) );
        }
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

        // Test gtm.js endpoint
        $gtm_js_url = $sgtm_url . '/gtm.js?id=' . $gtm_id;
        $gtm_js_response = wp_remote_get( $gtm_js_url, array(
            'timeout' => 10,
            'sslverify' => true
        ) );

        if ( is_wp_error( $gtm_js_response ) ) {
            $results['message'] = 'Failed to connect to gtm.js endpoint: ' . $gtm_js_response->get_error_message();
            $results['details']['gtm_js'] = 'Error: ' . $gtm_js_response->get_error_message();
            return $results;
        }

        $gtm_js_code = wp_remote_retrieve_response_code( $gtm_js_response );
        $gtm_js_body = wp_remote_retrieve_body( $gtm_js_response );

        if ( $gtm_js_code !== 200 ) {
            $results['message'] = 'gtm.js endpoint returned status code: ' . $gtm_js_code;
            $results['details']['gtm_js'] = 'HTTP ' . $gtm_js_code;
            return $results;
        }

        // Check if response looks like GTM JavaScript
        if ( strpos( $gtm_js_body, 'google' ) === false && strpos( $gtm_js_body, 'gtm' ) === false ) {
            $results['message'] = 'gtm.js endpoint does not return valid GTM JavaScript';
            $results['details']['gtm_js'] = 'Invalid response content';
            return $results;
        }

        $results['details']['gtm_js'] = '✓ OK (HTTP 200)';

        // Test ns.html endpoint
        $ns_html_url = $sgtm_url . '/ns.html?id=' . $gtm_id;
        $ns_html_response = wp_remote_get( $ns_html_url, array(
            'timeout' => 10,
            'sslverify' => true
        ) );

        if ( is_wp_error( $ns_html_response ) ) {
            $results['message'] = 'Failed to connect to ns.html endpoint: ' . $ns_html_response->get_error_message();
            $results['details']['ns_html'] = 'Error: ' . $ns_html_response->get_error_message();
            return $results;
        }

        $ns_html_code = wp_remote_retrieve_response_code( $ns_html_response );
        $ns_html_body = wp_remote_retrieve_body( $ns_html_response );

        if ( $ns_html_code !== 200 ) {
            $results['message'] = 'ns.html endpoint returned status code: ' . $ns_html_code;
            $results['details']['ns_html'] = 'HTTP ' . $ns_html_code;
            return $results;
        }

        // Check if response looks like valid HTML
        if ( strpos( $ns_html_body, '<' ) === false ) {
            $results['message'] = 'ns.html endpoint does not return valid HTML';
            $results['details']['ns_html'] = 'Invalid response content';
            return $results;
        }

        $results['details']['ns_html'] = '✓ OK (HTTP 200)';
        $results['success'] = true;
        $results['message'] = 'Both endpoints validated successfully';

        return $results;
    }

    /**
     * AJAX handler for test form submission
     */
    public function ajax_test_form_submit() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $framework = isset( $_POST['framework'] ) ? sanitize_text_field( $_POST['framework'] ) : '';

        if ( empty( $framework ) ) {
            wp_send_json_error( array( 'message' => 'No framework specified' ) );
        }

        // Always use WordPress admin email
        $email = get_option( 'admin_email' );

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'WordPress admin email not configured properly' ) );
        }

        // Test phone number
        $phone = '1-555-555-5555';

        // Get framework display name
        $framework_names = CUFT_Form_Detector::get_framework_names();
        $framework_name = isset( $framework_names[ $framework ] ) ? $framework_names[ $framework ] : $framework;

        // Get GTM ID
        $gtm_id = get_option( 'cuft_gtm_id', '' );

        // Generate realistic form_id based on framework
        $form_id_map = array(
            'avada' => 'fusion_form_1',
            'elementor' => 'elementor-form-widget-7a2c4f9',
            'contact_form_7' => 'wpcf7-f123-p456-o1',
            'ninja_forms' => 'nf-form-3',
            'gravity_forms' => 'gform_1'
        );
        $form_id = isset( $form_id_map[ $framework ] ) ? $form_id_map[ $framework ] : 'test_form_1';

        // Prepare test data with all fields needed for generate_lead
        $test_data = array(
            'event' => 'form_submit',
            'user_email' => $email,
            'user_phone' => $phone,
            'form_framework' => $framework,
            'form_id' => $form_id,
            'form_name' => 'Test ' . $framework_name . ' Form',
            'test_submission' => true,
            'timestamp' => current_time( 'mysql' ),
            // Include multiple click ID types to ensure generate_lead fires
            'click_id' => 'test_click_' . uniqid(),
            'gclid' => 'test_gclid_' . uniqid(),
            // UTM parameters
            'utm_source' => 'cuft_test',
            'utm_medium' => 'test_form',
            'utm_campaign' => 'test_campaign_' . $framework,
            'utm_term' => 'test_term',
            'utm_content' => 'test_content',
            // Additional data for completeness
            'generate_lead_test' => true
        );

        // Add UTM data if available (but preserve our test values)
        $utm_data = CUFT_UTM_Tracker::get_utm_data();
        if ( ! empty( $utm_data ) ) {
            // Merge real UTM data but keep our test click_id and campaign
            $preserve_fields = array(
                'click_id' => $test_data['click_id'],
                'gclid' => $test_data['gclid'],
                'utm_campaign' => $test_data['utm_campaign'],
                'test_submission' => true,
                'generate_lead_test' => true
            );
            $test_data = array_merge( $test_data, $utm_data, $preserve_fields );
        }

        // Log the test submission
        CUFT_Logger::log( 'info', 'Test form submission triggered', $test_data );

        // Generate a tracking ID for this test
        $tracking_id = 'test_' . wp_generate_password( 8, false );

        // Send email notification to admin
        $email_sent = $this->send_test_form_email( $email, $framework_name, $tracking_id, $test_data );

        // If GTM is configured, we can't directly verify if it was pushed since it happens client-side
        // But we can verify our tracking script is loaded
        $tracking_active = ! empty( $gtm_id ) && $this->is_valid_gtm_id( $gtm_id );

        $response = array(
            'success' => true,
            'message' => 'Test form submission sent',
            'data' => $test_data,
            'gtm_active' => $tracking_active,
            'framework' => $framework,
            'tracking_id' => $tracking_id,
            'email_sent' => $email_sent
        );

        // Store test submission for verification
        set_transient( 'cuft_test_' . $tracking_id, $test_data, 300 ); // 5 minutes

        wp_send_json_success( $response );
    }

    /**
     * Send test form email notification
     */
    private function send_test_form_email( $to, $framework_name, $tracking_id, $test_data ) {
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();

        $subject = sprintf( '[%s] Test Form Submission - %s', $site_name, $framework_name );

        $message = "You have received a test form submission from the Choice Universal Form Tracker plugin.\n\n";
        $message .= "==================================================\n";
        $message .= "FRAMEWORK: {$framework_name}\n";
        $message .= "TRACKING ID: {$tracking_id}\n";
        $message .= "==================================================\n\n";

        $message .= "EVENTS TRIGGERED:\n";
        $message .= "--------------------------------------------------\n";
        $message .= "✓ form_submit (always fires)\n";
        $message .= "✓ generate_lead (email + phone + click_id present)\n\n";

        $message .= "FORM DATA:\n";
        $message .= "--------------------------------------------------\n";
        $message .= "Email: {$test_data['user_email']}\n";
        $message .= "Phone: {$test_data['user_phone']}\n";
        $message .= "Click ID: {$test_data['click_id']}\n";
        $message .= "GCLID: {$test_data['gclid']}\n";
        $message .= "Form ID: {$test_data['form_id']}\n";
        $message .= "Timestamp: {$test_data['timestamp']}\n";
        $message .= "\n";

        // Add UTM data if present
        if ( ! empty( $test_data['utm_source'] ) || ! empty( $test_data['utm_medium'] ) || ! empty( $test_data['utm_campaign'] ) ) {
            $message .= "UTM TRACKING DATA:\n";
            $message .= "--------------------------------------------------\n";
            if ( ! empty( $test_data['utm_source'] ) ) {
                $message .= "Source: {$test_data['utm_source']}\n";
            }
            if ( ! empty( $test_data['utm_medium'] ) ) {
                $message .= "Medium: {$test_data['utm_medium']}\n";
            }
            if ( ! empty( $test_data['utm_campaign'] ) ) {
                $message .= "Campaign: {$test_data['utm_campaign']}\n";
            }
            if ( ! empty( $test_data['utm_term'] ) ) {
                $message .= "Term: {$test_data['utm_term']}\n";
            }
            if ( ! empty( $test_data['utm_content'] ) ) {
                $message .= "Content: {$test_data['utm_content']}\n";
            }
            $message .= "\n";
        }

        // Add GTM status
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $gtm_status = ! empty( $gtm_id ) && $this->is_valid_gtm_id( $gtm_id ) ? 'Active (ID: ' . $gtm_id . ')' : 'Not configured';

        $message .= "TRACKING STATUS:\n";
        $message .= "--------------------------------------------------\n";
        $message .= "GTM Status: {$gtm_status}\n";
        $message .= "Debug Mode: " . ( get_option( 'cuft_debug_enabled', false ) ? 'Enabled' : 'Disabled' ) . "\n";
        $message .= "\n";

        $message .= "==================================================\n";
        $message .= "This is a test submission from the Choice Universal\n";
        $message .= "Form Tracker plugin to verify form tracking is\n";
        $message .= "working correctly.\n";
        $message .= "\n";
        $message .= "Site: {$site_url}\n";
        $message .= "Admin: {$site_url}/wp-admin/options-general.php?page=choice-universal-form-tracker\n";

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . $to . '>',
            'Reply-To: ' . $to
        );

        // Send the email
        $sent = wp_mail( $to, $subject, $message, $headers );

        // Log the email send attempt
        CUFT_Logger::log(
            $sent ? 'info' : 'error',
            $sent ? 'Test form email sent successfully' : 'Failed to send test form email',
            array(
                'to' => $to,
                'framework' => $framework_name,
                'tracking_id' => $tracking_id
            )
        );

        return $sent;
    }

    /**
     * Render admin tabs
     */
    private function render_admin_tabs( $current_tab ) {
        $tabs = array(
            'settings' => __( 'Settings', 'choice-universal-form-tracker' ),
            'click-tracking' => __( 'Click Tracking', 'choice-universal-form-tracker' )
        );
        
        echo '<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active_class = ( $current_tab === $tab_key ) ? ' nav-tab-active' : '';
            $tab_url = add_query_arg( array( 'tab' => $tab_key ), admin_url( 'options-general.php?page=choice-universal-form-tracker' ) );
            echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . $active_class . '">' . esc_html( $tab_label ) . '</a>';
        }
        echo '</nav>';
    }
    
    /**
     * Render click tracking tab
     */
    private function render_click_tracking_tab() {
        // Handle pagination
        $current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $per_page = 20;
        $offset = ( $current_page - 1 ) * $per_page;
        
        // Handle filters
        $filter_qualified = isset( $_GET['filter_qualified'] ) ? sanitize_text_field( $_GET['filter_qualified'] ) : '';
        $filter_platform = isset( $_GET['filter_platform'] ) ? sanitize_text_field( $_GET['filter_platform'] ) : '';
        $filter_date_from = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( $_GET['filter_date_from'] ) : '';
        $filter_date_to = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( $_GET['filter_date_to'] ) : '';
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset
        );
        
        if ( $filter_qualified !== '' ) {
            $args['qualified'] = (int) $filter_qualified;
        }
        if ( ! empty( $filter_platform ) ) {
            $args['platform'] = $filter_platform;
        }
        if ( ! empty( $filter_date_from ) ) {
            $args['date_from'] = $filter_date_from . ' 00:00:00';
        }
        if ( ! empty( $filter_date_to ) ) {
            $args['date_to'] = $filter_date_to . ' 23:59:59';
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
                <div>
                    <?php 
                    $export_url = wp_nonce_url( 
                        add_query_arg( array( 'action' => 'export_csv' ), admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=click-tracking' ) ), 
                        'cuft_export_csv', 
                        'nonce' 
                    );
                    ?>
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
                        📊 Export CSV
                    </a>
                </div>
            </div>
            
            <?php $this->render_webhook_settings(); ?>
            <?php $this->render_click_tracking_filters( $filter_qualified, $filter_platform, $filter_date_from, $filter_date_to ); ?>
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
        $webhook_key = get_option( 'cuft_webhook_key', '' );
        
        if ( empty( $webhook_key ) ) {
            $webhook_key = wp_generate_password( 32, false );
            update_option( 'cuft_webhook_key', $webhook_key );
        }
        
        $webhook_url = home_url( '/cuft-webhook/' );
        $example_url = add_query_arg( array(
            'key' => $webhook_key,
            'click_id' => 'example_click_123',
            'qualified' => '1',
            'score' => '8'
        ), $webhook_url );
        
        ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">Webhook Configuration</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label><strong>Webhook URL:</strong></label><br>
                    <input type="text" value="<?php echo esc_attr( $webhook_url ); ?>" readonly class="regular-text" onclick="this.select();" />
                </div>
                <div>
                    <label><strong>Webhook Key:</strong></label><br>
                    <input type="text" value="<?php echo esc_attr( $webhook_key ); ?>" readonly class="regular-text" onclick="this.select();" />
                    <button type="button" class="button button-small" onclick="if(confirm('Generate new webhook key? This will invalidate the current key.')) { location.href='<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'regenerate_webhook_key' ) ), 'regenerate_webhook_key' ) ); ?>'; }">
                        🔄 Regenerate
                    </button>
                </div>
            </div>
            <div>
                <strong>Example Usage:</strong><br>
                <code style="background: white; padding: 8px; display: block; border-radius: 4px; font-size: 12px; word-break: break-all;">
                    <?php echo esc_html( $example_url ); ?>
                </code>
                <small style="color: #666;">
                    Send GET requests to update click status. Parameters: key (required), click_id (required), qualified (0 or 1), score (0-10)
                </small>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render click tracking filters
     */
    private function render_click_tracking_filters( $filter_qualified, $filter_platform, $filter_date_from, $filter_date_to ) {
        ?>
        <form method="GET" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <input type="hidden" name="page" value="choice-universal-form-tracker" />
            <input type="hidden" name="tab" value="click-tracking" />
            
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
                    <label><strong>Platform:</strong></label><br>
                    <input type="text" name="filter_platform" value="<?php echo esc_attr( $filter_platform ); ?>" placeholder="e.g., facebook, google" />
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
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=click-tracking' ) ); ?>" class="button">Clear</a>
                </div>
            </div>
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
                <div style="font-size: 24px; font-weight: bold; color: #1976d2;"><?php echo number_format( $total_clicks ); ?></div>
                <div style="color: #666;">Total Clicks</div>
            </div>
            <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #388e3c;"><?php echo number_format( $qualified_clicks ); ?></div>
                <div style="color: #666;">Qualified Clicks</div>
            </div>
            <div style="background: #fff3e0; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #f57c00;"><?php echo number_format( $unqualified_clicks ); ?></div>
                <div style="color: #666;">Unqualified Clicks</div>
            </div>
            <div style="background: #f3e5f5; padding: 15px; border-radius: 6px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #7b1fa2;"><?php echo $qualification_rate; ?>%</div>
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
                        <th>Platform</th>
                        <th>Campaign</th>
                        <th>UTM Source</th>
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
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                No click tracking data found. Click data will appear here when tracking is active.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $clicks as $click ): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $click->click_id ); ?></strong>
                                    <?php if ( ! empty( $click->ip_address ) ): ?>
                                        <br><small style="color: #666;"><?php echo esc_html( $click->ip_address ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $click->platform ?: '—' ); ?></td>
                                <td><?php echo esc_html( $click->campaign ?: '—' ); ?></td>
                                <td><?php echo esc_html( $click->utm_source ?: '—' ); ?></td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $click->qualified ? '#28a745' : '#6c757d'; ?>;">
                                        <?php echo $click->qualified ? 'YES' : 'NO'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: #f8f9fa;">
                                        <?php echo (int) $click->score; ?>/10
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $click->date_created ) ) ); ?>
                                    <br><small style="color: #666;">UTC</small>
                                </td>
                                <td>
                                    <?php if ( $click->date_updated !== $click->date_created ): ?>
                                        <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $click->date_updated ) ) ); ?>
                                        <br><small style="color: #666;">UTC</small>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="editClick('<?php echo esc_js( $click->click_id ); ?>', <?php echo (int) $click->qualified; ?>, <?php echo (int) $click->score; ?>)">
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
                        <button type="button" class="button" onclick="closeEditModal()">Cancel</button>
                        <input type="submit" value="Update" class="button button-primary" />
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function editClick(clickId, qualified, score) {
            document.getElementById('edit-click-id').value = clickId;
            document.getElementById('edit-qualified-' + (qualified ? 'yes' : 'no')).checked = true;
            document.getElementById('edit-score').value = score;
            document.getElementById('edit-click-modal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('edit-click-modal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('edit-click-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render pagination
     */
    private function render_click_tracking_pagination( $current_page, $total_pages ) {
        if ( $total_pages <= 1 ) {
            return;
        }
        
        $base_url = admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=click-tracking' );
        
        // Preserve current filters
        $filter_params = array();
        foreach ( array( 'filter_qualified', 'filter_platform', 'filter_date_from', 'filter_date_to' ) as $param ) {
            if ( ! empty( $_GET[ $param ] ) ) {
                $filter_params[ $param ] = sanitize_text_field( $_GET[ $param ] );
            }
        }
        
        ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php if ( $current_page > 1 ): ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $filter_params, array( 'paged' => $current_page - 1 ) ), $base_url ) ); ?>" class="button">« Previous</a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
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
            wp_die( 'Insufficient permissions' );
        }
        
        $action = isset( $_POST['cuft_click_action'] ) ? sanitize_text_field( $_POST['cuft_click_action'] ) : '';
        
        if ( $action === 'update_status' ) {
            $click_id = isset( $_POST['click_id'] ) ? sanitize_text_field( $_POST['click_id'] ) : '';
            $qualified = isset( $_POST['qualified'] ) ? (int) $_POST['qualified'] : null;
            $score = isset( $_POST['score'] ) ? (int) $_POST['score'] : null;
            
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
            wp_die( 'Insufficient permissions' );
        }
        
        if ( ! class_exists( 'CUFT_Click_Tracker' ) ) {
            wp_die( 'Click tracker not available' );
        }
        
        // Get filter parameters
        $args = array();
        
        if ( isset( $_GET['filter_qualified'] ) && $_GET['filter_qualified'] !== '' ) {
            $args['qualified'] = (int) $_GET['filter_qualified'];
        }
        if ( ! empty( $_GET['filter_platform'] ) ) {
            $args['platform'] = sanitize_text_field( $_GET['filter_platform'] );
        }
        if ( ! empty( $_GET['filter_date_from'] ) ) {
            $args['date_from'] = sanitize_text_field( $_GET['filter_date_from'] ) . ' 00:00:00';
        }
        if ( ! empty( $_GET['filter_date_to'] ) ) {
            $args['date_to'] = sanitize_text_field( $_GET['filter_date_to'] ) . ' 23:59:59';
        }
        
        CUFT_Click_Tracker::export_csv( $args );
    }
    
    /**
     * Regenerate webhook key
     */
    private function regenerate_webhook_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $new_key = wp_generate_password( 32, false );
        update_option( 'cuft_webhook_key', $new_key );
        
        add_settings_error( 'cuft_messages', 'cuft_message', 'Webhook key regenerated successfully!', 'updated' );
        settings_errors( 'cuft_messages' );
        
        // Redirect back to click tracking tab
        wp_redirect( admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=click-tracking' ) );
        exit;
    }
    
    /**
     * AJAX handler for dismissing the admin notice
     */
    public function ajax_dismiss_notice() {
        // Verify nonce and permissions
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_dismiss_notice' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Save user meta to remember dismissal
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'cuft_notice_dismissed', true );

        wp_send_json_success( array( 'message' => 'Notice dismissed' ) );
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Don't show on the plugin's own settings page
        $current_screen = get_current_screen();
        if ( $current_screen && $current_screen->id === 'settings_page_choice-universal-form-tracker' ) {
            return;
        }

        // Check if notice has been dismissed by this user
        $user_id = get_current_user_id();
        $dismissed = get_user_meta( $user_id, 'cuft_notice_dismissed', true );
        if ( $dismissed ) {
            return;
        }

        $gtm_id = get_option( 'cuft_gtm_id' );
        $detected_count = count( array_filter( CUFT_Form_Detector::get_detected_frameworks() ) );
        $gtm_status = $gtm_id && $this->is_valid_gtm_id( $gtm_id )
            ? " GTM container <code>$gtm_id</code> active."
            : ' <a href="' . admin_url( 'options-general.php?page=choice-universal-form-tracker' ) . '">Configure GTM</a>';

        $settings_url = admin_url( 'options-general.php?page=choice-universal-form-tracker' );

        echo '<div class="notice notice-success is-dismissible" data-dismiss-action="cuft-dismiss-notice">';
        echo '<p><strong>Choice Universal Form Tracker</strong> active with ' . $detected_count . ' form framework(s) detected. ';
        echo $gtm_status . ' <a href="' . $settings_url . '">Settings</a></p>';
        echo '</div>';

        // Add inline script to handle dismiss
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.notice[data-dismiss-action="cuft-dismiss-notice"] .notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'cuft_dismiss_notice',
                    nonce: '<?php echo wp_create_nonce( 'cuft_dismiss_notice' ); ?>'
                });
            });
        });
        </script>
        <?php
    }
}
