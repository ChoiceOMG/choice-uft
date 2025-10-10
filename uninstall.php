<?php
/**
 * Uninstall script for Choice Universal Form Tracker
 *
 * This file is executed when the plugin is uninstalled.
 * It cleans up all plugin data from the database.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only allow uninstall for administrators
if ( ! current_user_can( 'activate_plugins' ) ) {
    exit;
}

// Clean up plugin options
$options_to_remove = array(
    'cuft_gtm_id',
    'cuft_debug_enabled',
    'cuft_generate_lead_enabled',
    'cuft_lead_currency',
    'cuft_lead_value',
    'cuft_console_logging',
    'cuft_github_updates_enabled',
    'cuft_version',
    'cuft_installed_at',
    'cuft_last_updated'
);

foreach ( $options_to_remove as $option ) {
    delete_option( $option );
}

// Clean up transients
$transients_to_remove = array(
    'cuft_update_status',
    'cuft_update_progress',
    'cuft_update_completed',
    'cuft_github_version',
    'cuft_github_api_cache'
);

foreach ( $transients_to_remove as $transient ) {
    delete_transient( $transient );
    delete_site_transient( $transient );
}

// Clean up user meta for dismissed notices
global $wpdb;

// Remove all dismissed update notice user meta
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} 
     WHERE meta_key LIKE 'cuft_dismissed_update_%'"
);

// Remove other plugin-related user meta
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} 
     WHERE meta_key LIKE 'cuft_%'"
);

// Clean up custom database tables
$tables_to_remove = array(
    $wpdb->prefix . 'cuft_update_log',
    $wpdb->prefix . 'cuft_click_tracking',
    $wpdb->prefix . 'cuft_form_events'
);

foreach ( $tables_to_remove as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Clean up scheduled events
wp_clear_scheduled_hook( 'cuft_check_updates' );
wp_clear_scheduled_hook( 'cuft_clear_update_progress' );
wp_clear_scheduled_hook( 'cuft_cleanup_old_logs' );

// Clean up any remaining plugin data
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE 'cuft_%'"
);

$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_cuft_%'"
);

$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_site_transient_cuft_%'"
);

// Log uninstall for debugging (if debug is enabled)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Choice Universal Form Tracker: Plugin uninstalled and data cleaned up.' );
}

