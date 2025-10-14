<?php
/**
 * Plugin Name:       Choice Universal Form Tracker
 * Description:       Universal form tracking for WordPress - supports Avada, Elementor Pro, Contact Form 7, Ninja Forms, Gravity Forms, and more. Tracks submissions and link clicks via Google Tag Manager's dataLayer.
 * Version:           3.19.1
 * Author:            Choice OMG
 * Author URI:        https://choice.marketing
 * Text Domain:       choice-universal-form-tracker
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CUFT_VERSION', '3.19.1' );
define( 'CUFT_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'CUFT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUFT_BASENAME', plugin_basename( __FILE__ ) );
define( 'CUFT_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class
 */
class Choice_Universal_Form_Tracker {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core includes with error handling
        $includes = array(
            'includes/class-cuft-feature-flags.php',  // Load feature flags first
            'includes/class-cuft-logger.php',         // Load logger early
            'includes/class-cuft-db-migration.php',   // Load migration handler
            'includes/class-cuft-admin.php',
            'includes/admin/class-cuft-testing-dashboard.php',  // Testing dashboard
            'includes/class-cuft-gtm.php',
            'includes/class-cuft-form-detector.php',
            'includes/class-cuft-form-tracker.php',
            'includes/class-cuft-link-tracking.php',
            'includes/class-cuft-utm-tracker.php',
            'includes/class-cuft-console-logger.php',
            'includes/class-cuft-github-updater.php',
            'includes/class-cuft-click-tracker.php',
            'includes/class-cuft-click-integration.php',
            'includes/class-cuft-utils.php',
            'includes/class-cuft-migration-events.php',
            'includes/class-cuft-cryptojs.php',
            // Form Builder Infrastructure
            'includes/admin/framework-adapters/abstract-cuft-adapter.php',  // Base adapter
            'includes/admin/framework-adapters/class-cuft-elementor-adapter.php',
            'includes/admin/framework-adapters/class-cuft-cf7-adapter.php',
            'includes/admin/framework-adapters/class-cuft-gravity-adapter.php',
            'includes/admin/framework-adapters/class-cuft-ninja-adapter.php',
            'includes/admin/framework-adapters/class-cuft-avada-adapter.php',
            'includes/admin/class-cuft-adapter-factory.php',  // Adapter factory
            'includes/admin/class-cuft-form-builder.php',  // Form builder core
            // Form Builder Supporting Classes
            'includes/class-cuft-test-mode.php',  // Test mode manager
            'includes/class-cuft-test-routing.php',  // Test form routing
            'includes/class-cuft-form-template.php',  // Form templates
            'includes/class-cuft-test-session.php',  // Test sessions
            'includes/class-cuft-form-builder-validator.php',  // Compliance validator
            // AJAX Handlers
            'includes/ajax/class-cuft-event-recorder.php',  // AJAX event recording handler
            'includes/ajax/class-cuft-test-data-generator.php',  // Test data generator AJAX
            'includes/ajax/class-cuft-event-simulator.php',  // Event simulator AJAX
            'includes/ajax/class-cuft-test-form-builder.php',  // Test form builder AJAX
            'includes/ajax/class-cuft-test-events-ajax.php',  // Test events retrieval/deletion AJAX
            'includes/ajax/class-cuft-form-builder-ajax.php',  // Form builder AJAX endpoints
            'includes/ajax/class-cuft-updater-ajax.php',  // Updater AJAX endpoints
            'includes/database/class-cuft-test-events-table.php',  // Test events database table
            // Updater Models
            'includes/models/class-cuft-update-status.php',  // Update status model
            'includes/models/class-cuft-update-progress.php',  // Update progress model
            'includes/models/class-cuft-github-release.php',  // GitHub release model
            'includes/models/class-cuft-update-log.php',  // Update log model
            'includes/models/class-cuft-update-configuration.php',  // Update configuration model
            // Force Update Infrastructure (Feature 009 - v3.19.0)
            'includes/class-cuft-update-lock-manager.php',  // Transient-based operation locking
            'includes/class-cuft-disk-space-validator.php',  // Disk space validation
            'includes/class-cuft-cache-clearer.php',  // WordPress cache clearing
            // Force Update Models (Feature 009 - v3.19.0)
            'includes/models/class-cuft-force-reinstall-operation.php',  // Force reinstall operation model
            'includes/models/class-cuft-plugin-installation-state.php',  // Plugin installation state model
            'includes/models/class-cuft-update-history-entry.php',  // Update history entry model
            'includes/models/class-cuft-update-check-request.php',  // Update check request model
            // Force Update Service (Feature 009 - v3.19.0)
            'includes/class-cuft-force-update-handler.php',  // Force update orchestrator
            // Updater Services
            'includes/class-cuft-github-api.php',  // GitHub API service
            'includes/class-cuft-update-checker.php',  // Update checker service
            'includes/class-cuft-filesystem-handler.php',  // Filesystem wrapper
            // 'includes/class-cuft-backup-manager.php',  // DEPRECATED: Replaced by includes/update/class-cuft-backup-manager.php in Feature 008
            // 'includes/class-cuft-update-installer.php',  // DEPRECATED: Removed in Feature 007/008 refactor
            'includes/class-cuft-wordpress-updater.php',  // WordPress update integration
            'includes/class-cuft-cron-manager.php',  // Cron scheduling manager
            // Updater Admin
            'includes/admin/class-cuft-admin-notices.php',  // Admin update notices
            // 'includes/admin/class-cuft-admin-bar.php',  // DEPRECATED: Removed in Feature 007 refactor
            // Updater Performance
            'includes/class-cuft-db-optimizer.php',  // Database query optimization
            'includes/class-cuft-cache-warmer.php',  // Cache preloading
            // Updater Security
            'includes/class-cuft-rate-limiter.php',  // Rate limiting
            'includes/class-cuft-download-verifier.php',  // Download verification
            'includes/class-cuft-capabilities.php',  // Capability checks
            'includes/class-cuft-input-validator.php',  // Input validation
            // Migrations
            'includes/migrations/class-cuft-migration-3-12-0.php',
            'includes/migrations/create-update-log-table.php',  // Update log table migration
            // Update System (Feature 008 - v3.17.0)
            'includes/update/class-cuft-plugin-info.php',  // plugins_api filter for update modal
            'includes/update/class-cuft-directory-fixer.php',  // upgrader_source_selection filter
            'includes/update/class-cuft-update-logger.php',  // upgrader_process_complete hook for history
            'includes/update/class-cuft-update-validator.php',  // Download validation (FR-401)
            'includes/update/class-cuft-backup-manager.php',  // Backup/restore (FR-402)
            // Form framework handlers
            'includes/forms/class-cuft-avada-forms.php',
            'includes/forms/class-cuft-elementor-forms.php',
            'includes/forms/class-cuft-cf7-forms.php',
            'includes/forms/class-cuft-ninja-forms.php',
            'includes/forms/class-cuft-gravity-forms.php'
        );

        foreach ( $includes as $file ) {
            $filepath = CUFT_PATH . $file;
            if ( file_exists( $filepath ) ) {
                require_once $filepath;
            } else {
                // Log missing file error but don't break the plugin
                error_log( "CUFT Warning: Missing file {$file}" );
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'wp_loaded', array( $this, 'ensure_updater_hooks' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Add plugin action links (Settings, GitHub)
        add_filter( 'plugin_action_links_' . CUFT_BASENAME, array( $this, 'add_action_links' ) );

        // Add plugin row meta (View on GitHub, Releases)
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

        // Customize update notification message
        add_action( 'in_plugin_update_message-' . CUFT_BASENAME, array( $this, 'update_message' ), 10, 2 );
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Check if plugin is enabled
        if ( ! apply_filters( 'cuft_enabled', true ) ) {
            return;
        }
        
        // Check PHP version compatibility
        if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            }
            return;
        }
        
        // Check WordPress version compatibility  
        if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
            }
            return;
        }
        
        // Run database migrations if needed (for plugin updates)
        if ( class_exists( 'CUFT_DB_Migration' ) && CUFT_DB_Migration::needs_migration() ) {
            CUFT_DB_Migration::run_migrations();
        }

        // Initialize core components with error handling
        try {
            // Initialize feature flags first (Phase 1 migration)
            if ( class_exists( 'CUFT_Feature_Flags' ) ) {
                new CUFT_Feature_Flags();
            }


            if ( is_admin() && class_exists( 'CUFT_Admin' ) ) {
                new CUFT_Admin();
            }

            if ( class_exists( 'CUFT_GTM' ) ) {
                new CUFT_GTM();
            }
            
            if ( class_exists( 'CUFT_Form_Detector' ) ) {
                new CUFT_Form_Detector();
            }
            
            if ( class_exists( 'CUFT_Form_Tracker' ) ) {
                new CUFT_Form_Tracker();
            }
            
            if ( class_exists( 'CUFT_Link_Tracking' ) ) {
                new CUFT_Link_Tracking();
            }
            
            if ( class_exists( 'CUFT_UTM_Tracker' ) ) {
                new CUFT_UTM_Tracker();
            }
            
            if ( class_exists( 'CUFT_Click_Tracker' ) ) {
                new CUFT_Click_Tracker();
            }
            
            if ( class_exists( 'CUFT_Click_Integration' ) ) {
                new CUFT_Click_Integration();
            }

            if ( class_exists( 'CUFT_CryptoJS' ) ) {
                new CUFT_CryptoJS();
            }

            // Initialize AJAX Event Recorder (v3.12.0)
            if ( class_exists( 'CUFT_Event_Recorder' ) ) {
                new CUFT_Event_Recorder();
            }

            // Initialize Testing Dashboard (v3.14.0)
            if ( is_admin() && class_exists( 'CUFT_Testing_Dashboard' ) ) {
                new CUFT_Testing_Dashboard();
            }

            // Initialize Testing Dashboard AJAX Handlers (v3.14.0)
            if ( class_exists( 'CUFT_Test_Data_Generator' ) ) {
                new CUFT_Test_Data_Generator();
            }
            if ( class_exists( 'CUFT_Event_Simulator' ) ) {
                new CUFT_Event_Simulator();
            }
            if ( class_exists( 'CUFT_Test_Form_Builder' ) ) {
                new CUFT_Test_Form_Builder();
            }
            if ( class_exists( 'CUFT_Test_Events_Ajax' ) ) {
                new CUFT_Test_Events_Ajax();
            }

            // Initialize Update System (Feature 008 - v3.17.0)
            if ( class_exists( 'CUFT_Update_Validator' ) ) {
                new CUFT_Update_Validator();
            }
            if ( class_exists( 'CUFT_Backup_Manager' ) ) {
                new CUFT_Backup_Manager();
            }

            // Enqueue cuftConfig JavaScript object with AJAX URL and nonce
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cuft_config' ) );

            // Legacy GitHub updater disabled in favor of CUFT_WordPress_Updater (v3.16.0+)
            // The new system uses CUFT_Update_Checker, CUFT_GitHub_API, and CUFT_WordPress_Updater
            // for unified update management with WordPress native integration.
            // See: includes/class-cuft-wordpress-updater.php (initialized automatically)

        } catch ( Exception $e ) {
            error_log( "CUFT Error during initialization: " . $e->getMessage() );
            if ( is_admin() ) {
                add_action( 'admin_notices', array( $this, 'init_error_notice' ) );
            }
        }
    }

    /**
     * Ensure updater hooks are properly registered
     *
     * @deprecated 3.16.2 No longer needed - CUFT_WordPress_Updater handles all hooks
     */
    public function ensure_updater_hooks() {
        // No-op: CUFT_WordPress_Updater (v3.16.0+) handles all update hooks automatically
        // See: includes/class-cuft-wordpress-updater.php:register_hooks()
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if they don't exist
        if ( false === get_option( 'cuft_gtm_id' ) ) {
            add_option( 'cuft_gtm_id', '' );
        }
        if ( false === get_option( 'cuft_debug_enabled' ) ) {
            add_option( 'cuft_debug_enabled', false );
        }
        if ( false === get_option( 'cuft_github_updates_enabled' ) ) {
            add_option( 'cuft_github_updates_enabled', true );
        }
        if ( false === get_option( 'cuft_lead_currency' ) ) {
            add_option( 'cuft_lead_currency', 'CAD' );
        }
        if ( false === get_option( 'cuft_lead_value' ) ) {
            add_option( 'cuft_lead_value', 100 );
        }

        // Create click tracking table
        if ( class_exists( 'CUFT_Click_Tracker' ) ) {
            CUFT_Click_Tracker::create_table();
        }

        // Create test events table
        if ( class_exists( 'CUFT_Test_Events_Table' ) ) {
            $test_events_table = new CUFT_Test_Events_Table();
            $test_events_table->maybe_update();
        }

        // Run database migrations
        if ( class_exists( 'CUFT_DB_Migration' ) ) {
            CUFT_DB_Migration::run_migrations();
        }

        // Clear update-related transients on activation (handles update completion)
        delete_transient( 'cuft_github_version' );
        delete_transient( 'cuft_github_changelog' );
        delete_site_transient( 'update_plugins' );
        wp_clean_plugins_cache();

        // Force a fresh update check
        wp_update_plugins();

        // Flush rewrite rules to enable webhook endpoints
        flush_rewrite_rules();

        // Schedule health check cron job (every 6 hours)
        if ( ! wp_next_scheduled( 'cuft_scheduled_health_check' ) ) {
            wp_schedule_event( time(), 'six_hours', 'cuft_scheduled_health_check' );
        }

        // Schedule force update history cleanup cron (Feature 009 - v3.19.0)
        if ( class_exists( 'CUFT_Cron_Manager' ) ) {
            CUFT_Cron_Manager::schedule_history_cleanup();
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup debug logs older than 30 days
        delete_option( 'cuft_debug_logs' );

        // Clear scheduled health check cron job
        $timestamp = wp_next_scheduled( 'cuft_scheduled_health_check' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cuft_scheduled_health_check' );
        }

        // Clear update validator orphaned downloads cleanup cron job (v3.17.0)
        if ( class_exists( 'CUFT_Update_Validator' ) ) {
            CUFT_Update_Validator::deactivate();
        }

        // Clear force update transients and cron job (Feature 009 - v3.19.0)
        delete_transient( 'cuft_force_update_lock' );
        delete_transient( 'cuft_plugin_installation_state' );

        // Unschedule force update history cleanup cron
        $cleanup_timestamp = wp_next_scheduled( 'cuft_daily_cleanup' );
        if ( $cleanup_timestamp ) {
            wp_unschedule_event( $cleanup_timestamp, 'cuft_daily_cleanup' );
        }

        // Note: cuft_update_history is intentionally preserved on deactivation

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Enqueue cuftConfig JavaScript object (v3.12.0)
     *
     * Provides AJAX URL and nonce for client-side event recording.
     * Also enqueues frontend health check script when custom server is enabled.
     */
    public function enqueue_cuft_config() {
        // Create nonce for event recording
        $nonce = wp_create_nonce( 'cuft-event-recorder' );

        // Check if custom server is enabled
        $custom_server_enabled = get_option( 'cuft_sgtm_enabled', false ) && 
                                get_option( 'cuft_sgtm_active_server', 'fallback' ) === 'custom';

        // Prepare config object
        $cuft_config = array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => $nonce,
            'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'custom_server_enabled' => $custom_server_enabled,
        );

        // Add inline script to make config globally available
        wp_add_inline_script(
            'jquery', // Attach to jQuery since it's always loaded
            'var cuftConfig = ' . wp_json_encode( $cuft_config ) . ';',
            'before'
        );

        // Enqueue frontend health check script when custom server is enabled
        if ( $custom_server_enabled ) {
            wp_enqueue_script(
                'cuft-health-check',
                CUFT_URL . '/assets/cuft-health-check.js',
                array(),
                CUFT_VERSION,
                true
            );
        }
    }

    /**
     * Add plugin action links
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=choice-universal-form-tracker' ) . '">Settings</a>';

        // Add settings link at the beginning
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Add plugin row meta links
     */
    public function add_row_meta( $plugin_meta, $plugin_file ) {
        if ( CUFT_BASENAME !== $plugin_file ) {
            return $plugin_meta;
        }

        $row_meta = array(
            'support' => '<a href="https://github.com/ChoiceOMG/choice-uft/issues" target="_blank">Support</a>'
        );

        return array_merge( $plugin_meta, $row_meta );
    }

    /**
     * Customize update notification message
     */
    public function update_message( $plugin_data, $response ) {
        if ( empty( $response->new_version ) ) {
            return;
        }

        $new_version = $response->new_version;

        echo ' ';
        echo 'Version ' . esc_html( $new_version ) . ' is available. ';
        echo '<a href="' . admin_url( 'options-general.php?page=choice-universal-form-tracker' ) . '">Go to Settings → Force Update</a> | ';
        echo '<a href="https://github.com/ChoiceOMG/choice-uft/releases/tag/v' . esc_attr( $new_version ) . '" target="_blank">View release notes</a>';
    }

    /**
     * PHP version compatibility notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> This plugin requires PHP 7.0 or higher. ';
        echo 'You are running PHP ' . PHP_VERSION . '. Please contact your hosting provider to upgrade PHP.';
        echo '</p></div>';
    }
    
    /**
     * WordPress version compatibility notice
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> This plugin requires WordPress 5.0 or higher. ';
        echo 'You are running WordPress ' . get_bloginfo( 'version' ) . '. Please update WordPress.';
        echo '</p></div>';
    }
    
    /**
     * Initialization error notice
     */
    public function init_error_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Choice Universal Form Tracker:</strong> Plugin initialization failed. ';
        echo 'Please check error logs for details or contact support.';
        echo '</p></div>';
    }
}

// Initialize the plugin
Choice_Universal_Form_Tracker::get_instance();
