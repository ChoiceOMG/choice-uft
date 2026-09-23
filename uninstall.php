<?php
/**
 * Uninstall script for Choice Universal Form Tracker
 *
 * This file is executed when the plugin is uninstalled.
 * It cleans up all plugin data from the database.
 *
 * Single-site cleanup only: on a multisite network this removes data for the
 * site the uninstall runs against (WordPress core calls uninstall.php once per
 * site during a network-wide plugin deletion), not a manual loop over every
 * site in the network.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Belt-and-suspenders: WordPress core already checks 'delete_plugins' before
// calling uninstall.php, but keep a capability check here in case this file
// is ever reached another way.
if ( ! current_user_can( 'activate_plugins' ) ) {
    exit;
}

// Clean up plugin options
$cuft_options_to_remove = array(
    'cuft_gtm_id',
    'cuft_debug_enabled',
    'cuft_generate_lead_enabled',
    'cuft_phone_validation_enabled',
    'cuft_lead_currency',
    'cuft_lead_value',
    'cuft_console_logging',
    'cuft_github_updates_enabled',
    'cuft_version',
    'cuft_installed_at',
    'cuft_last_updated',
    'cuft_collector_host',
    'cuft_sgtm_enabled',
    'cuft_sgtm_url',
    'cuft_sgtm_validated',
    'cuft_sgtm_active_server',
    'cuft_sgtm_server_recovered',
    'cuft_sgtm_server_failed',
    'cuft_sgtm_health_last_check',
    'cuft_sgtm_health_last_result',
    'cuft_sgtm_health_last_message',
    'cuft_sgtm_health_response_time',
    'cuft_sgtm_health_consecutive_success',
    'cuft_sgtm_health_consecutive_failure',
    'cuft_register_secret',
    'cuft_measurement_id',
    'cuft_measurement_api_secret',
    'cuft_webhook_key',
    'cuft_update_log',
    'cuft_update_config',
    'cuft_update_history',
    'cuft_last_db_optimize',
    'cuft_feature_flags',
    'cuft_service_interest_map',
    'cuft_debug_logs'
);

foreach ( $cuft_options_to_remove as $cuft_option ) {
    delete_option( $cuft_option );
}

// Clean up transients
$cuft_transients_to_remove = array(
    'cuft_update_status',
    'cuft_update_progress',
    'cuft_update_completed',
    'cuft_update_in_progress',
    'cuft_backup_in_progress',
    'cuft_backup_path',
    'cuft_github_version',
    'cuft_github_api_cache',
    'cuft_github_changelog',
    'cuft_github_release_cache',
    'cuft_plugin_info',
    'cuft_test_multisite'
);

foreach ( $cuft_transients_to_remove as $cuft_transient ) {
    delete_transient( $cuft_transient );
    delete_site_transient( $cuft_transient );
}

// Clean up user meta for dismissed notices
global $wpdb;

// Remove all dismissed update notice user meta
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own user meta keys (cuft_dismissed_update_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key LIKE %s',
        $wpdb->usermeta,
        $wpdb->esc_like( 'cuft_dismissed_update_' ) . '%'
    )
);

// Remove other plugin-related user meta (e.g. cuft_notice_dismissed_*)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own user meta keys (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key LIKE %s',
        $wpdb->usermeta,
        $wpdb->esc_like( 'cuft_' ) . '%'
    )
);

// Clean up custom database tables
$cuft_tables_to_remove = array(
    $wpdb->prefix . 'cuft_update_log',
    $wpdb->prefix . 'cuft_click_tracking',
    $wpdb->prefix . 'cuft_form_events',
    $wpdb->prefix . 'cuft_test_events'
);

foreach ( $cuft_tables_to_remove as $cuft_table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin's own tables; schema migration (uninstall) for the plugin's own table.
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $cuft_table ) );
}

// Clean up scheduled events (hook names must match what CUFT_Cron_Manager,
// the GitHub update checker, and the update-log migration actually schedule)
wp_clear_scheduled_hook( 'cuft_check_updates' );
wp_clear_scheduled_hook( 'cuft_daily_cleanup' );
wp_clear_scheduled_hook( 'cuft_scheduled_health_check' );
wp_clear_scheduled_hook( 'cuft_cleanup_orphaned_downloads' );
wp_clear_scheduled_hook( 'cuft_cleanup_update_logs' );
wp_clear_scheduled_hook( 'cuft_clear_update_progress' );
wp_clear_scheduled_hook( 'cuft_process_update' );

// Clean up any remaining plugin data (options, and their transient timeout
// rows, that the explicit lists above might miss)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own options (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like( 'cuft_' ) . '%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own transients (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like( '_transient_cuft_' ) . '%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own transients (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like( '_transient_timeout_cuft_' ) . '%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own site transients (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like( '_site_transient_cuft_' ) . '%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own site transients (cuft_*); one-time uninstall cleanup, not cacheable.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like( '_site_transient_timeout_cuft_' ) . '%'
    )
);
