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
        
        $gtm_id = get_option( 'cuft_gtm_id', '' );
        $debug_enabled = get_option( 'cuft_debug_enabled', false );
        ?>
        <div class="wrap">
            <div style="display: flex; align-items: center; margin-bottom: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                    <span style="color: white; font-size: 24px; font-weight: bold;">📊</span>
                </div>
                <h1 style="margin: 0;">Choice Universal Form Tracker</h1>
            </div>
            
            <?php $this->render_settings_form( $gtm_id, $debug_enabled ); ?>
            <?php $this->render_framework_status(); ?>
            <?php $this->render_utm_status(); ?>
            <?php $this->render_debug_section(); ?>
        </div>
        <?php
    }
    
    /**
     * Render settings form
     */
    private function render_settings_form( $gtm_id, $debug_enabled ) {
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
                                   placeholder="GTM-XXXXXXX" class="regular-text" />
                            <p class="description">
                                Enter your GTM container ID (e.g., GTM-ABC123). Leave empty to disable GTM injection.
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
        
        ?>
        <div class="cuft-status-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h2 style="margin-top: 0;">Framework Detection Status</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php foreach ( $frameworks as $framework ): ?>
                    <div style="padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: <?php echo $framework['detected'] ? '#e8f5e8' : '#f8f8f8'; ?>;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <strong><?php echo esc_html( $framework['name'] ); ?></strong>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $framework['detected'] ? '#28a745' : '#6c757d'; ?>;">
                                <?php echo $framework['detected'] ? 'DETECTED' : 'NOT FOUND'; ?>
                            </span>
                        </div>
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
        
        // Validate GTM-ID format
        if ( empty( $gtm_id ) || $this->is_valid_gtm_id( $gtm_id ) ) {
            update_option( 'cuft_gtm_id', $gtm_id );
            update_option( 'cuft_debug_enabled', $debug_enabled );
            add_settings_error( 'cuft_messages', 'cuft_message', 'Settings saved!', 'updated' );
        } else {
            add_settings_error( 'cuft_messages', 'cuft_message', 'Invalid GTM-ID format. Use format: GTM-XXXXXXX', 'error' );
        }
        
        settings_errors( 'cuft_messages' );
    }
    
    /**
     * Validate GTM ID format
     */
    private function is_valid_gtm_id( $gtm_id ) {
        return preg_match( '/^GTM-[A-Z0-9]{7,}$/i', $gtm_id );
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
     * Display admin notices
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $gtm_id = get_option( 'cuft_gtm_id' );
        $detected_count = count( array_filter( CUFT_Form_Detector::get_detected_frameworks() ) );
        $gtm_status = $gtm_id && $this->is_valid_gtm_id( $gtm_id ) 
            ? " GTM container <code>$gtm_id</code> active." 
            : ' <a href="' . admin_url( 'options-general.php?page=choice-universal-form-tracker' ) . '">Configure GTM</a>';
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Choice Universal Form Tracker</strong> active with ' . $detected_count . ' form framework(s) detected.' . $gtm_status . '</p>';
        echo '</div>';
    }
}
