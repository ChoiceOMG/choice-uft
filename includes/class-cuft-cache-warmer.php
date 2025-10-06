<?php
/**
 * Transient Cache Warmer
 *
 * Preloads transient cache data for admin pages to improve performance
 * by reducing database queries on page load.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class CUFT_Cache_Warmer
 *
 * Provides cache preloading functionality for update-related data.
 */
class CUFT_Cache_Warmer {

    /**
     * Cache keys to preload
     *
     * @var array
     */
    private static $cache_keys = array(
        'cuft_update_status',
        'cuft_update_progress',
        'cuft_github_release_cache',
        'cuft_update_config'
    );

    /**
     * Initialize cache warmer
     *
     * @since 3.15.0
     */
    public static function init() {
        // Warm cache when admin loads
        add_action('admin_init', array(__CLASS__, 'warm_admin_cache'), 5);

        // Warm cache before update check
        add_action('cuft_before_update_check', array(__CLASS__, 'warm_update_cache'));

        // Clear specific caches on update completion
        add_action('cuft_update_completed', array(__CLASS__, 'clear_update_caches'));
        add_action('cuft_update_failed', array(__CLASS__, 'clear_update_caches'));
    }

    /**
     * Warm admin page cache
     *
     * Preloads commonly accessed transients when admin dashboard loads.
     * Only runs once per admin session to avoid overhead.
     *
     * @since 3.15.0
     * @return bool True if cache was warmed
     */
    public static function warm_admin_cache() {
        // Skip if not on update-related admin page
        if (!self::is_update_admin_page()) {
            return false;
        }

        // Check if already warmed this session
        $session_key = 'cuft_cache_warmed_' . get_current_user_id();
        if (get_transient($session_key)) {
            return false;
        }

        try {
            // Preload update status
            self::preload_update_status();

            // Preload update configuration
            self::preload_update_config();

            // Preload recent update logs (first page only)
            self::preload_recent_logs();

            // Mark cache as warmed for this session (15 minutes)
            set_transient($session_key, true, 15 * MINUTE_IN_SECONDS);

            return true;

        } catch (Exception $e) {
            error_log('CUFT Cache Warmer: Failed to warm admin cache - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm update-specific cache
     *
     * Preloads cache needed for update checks and operations.
     *
     * @since 3.15.0
     * @return bool True if cache was warmed
     */
    public static function warm_update_cache() {
        try {
            // Ensure GitHub release data is cached
            self::preload_github_release();

            // Ensure update status is fresh
            self::preload_update_status();

            return true;

        } catch (Exception $e) {
            error_log('CUFT Cache Warmer: Failed to warm update cache - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Preload update status transient
     *
     * @since 3.15.0
     * @return mixed Update status data or false
     */
    private static function preload_update_status() {
        $status = get_transient('cuft_update_status');

        if (false === $status && class_exists('CUFT_Update_Checker')) {
            // Trigger a background check if status is missing
            // This will populate the transient for next request
            wp_schedule_single_event(time() + 10, 'cuft_check_updates');
        }

        return $status;
    }

    /**
     * Preload update configuration
     *
     * @since 3.15.0
     * @return mixed Update configuration or defaults
     */
    private static function preload_update_config() {
        $config = get_option('cuft_update_config');

        if (false === $config && class_exists('CUFT_Update_Configuration')) {
            // Get default configuration
            $config = CUFT_Update_Configuration::get_defaults();
            update_option('cuft_update_config', $config);
        }

        return $config;
    }

    /**
     * Preload GitHub release data
     *
     * @since 3.15.0
     * @return mixed GitHub release data or false
     */
    private static function preload_github_release() {
        $release = get_transient('cuft_github_release_cache');

        if (false === $release && class_exists('CUFT_GitHub_API')) {
            try {
                // Fetch latest release and cache it
                $github_api = new CUFT_GitHub_API();
                $release = $github_api->get_latest_release();

                if ($release) {
                    set_transient('cuft_github_release_cache', $release, 12 * HOUR_IN_SECONDS);
                }
            } catch (Exception $e) {
                // Silently fail - will retry on next warm
            }
        }

        return $release;
    }

    /**
     * Preload recent update logs
     *
     * Fetches and caches the first page of recent logs for quick display.
     *
     * @since 3.15.0
     * @return array Recent logs
     */
    private static function preload_recent_logs() {
        $cache_key = 'cuft_recent_logs';
        $logs = get_transient($cache_key);

        if (false === $logs && class_exists('CUFT_DB_Optimizer')) {
            $logs = CUFT_DB_Optimizer::get_optimized_logs(array(
                'limit' => 10,
                'offset' => 0
            ));

            if ($logs) {
                // Cache for 5 minutes
                set_transient($cache_key, $logs, 5 * MINUTE_IN_SECONDS);
            }
        }

        return $logs ?: array();
    }

    /**
     * Check if current admin page is update-related
     *
     * @since 3.15.0
     * @return bool True if on update admin page
     */
    private static function is_update_admin_page() {
        global $pagenow;

        // Check if on plugins page or updates page
        if (in_array($pagenow, array('plugins.php', 'update-core.php'))) {
            return true;
        }

        // Check if on plugin settings page
        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            if (strpos($_GET['page'], 'cuft') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear update-related caches
     *
     * Called after update completion or failure to ensure fresh data.
     *
     * @since 3.15.0
     * @return void
     */
    public static function clear_update_caches() {
        // Delete all update-related transients
        foreach (self::$cache_keys as $key) {
            delete_transient($key);
        }

        // Clear recent logs cache
        delete_transient('cuft_recent_logs');

        // Clear all user session warming flags
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cuft_cache_warmed_%'"
        );
    }

    /**
     * Force cache refresh
     *
     * Clears all caches and immediately warms them with fresh data.
     *
     * @since 3.15.0
     * @return bool True if refresh succeeded
     */
    public static function force_refresh() {
        try {
            // Clear existing caches
            self::clear_update_caches();

            // Warm with fresh data
            self::preload_update_status();
            self::preload_update_config();
            self::preload_github_release();
            self::preload_recent_logs();

            return true;

        } catch (Exception $e) {
            error_log('CUFT Cache Warmer: Force refresh failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache statistics
     *
     * Returns information about current cache state.
     *
     * @since 3.15.0
     * @return array Cache statistics
     */
    public static function get_cache_stats() {
        $stats = array(
            'cached' => array(),
            'missing' => array(),
            'hit_rate' => 0
        );

        foreach (self::$cache_keys as $key) {
            $value = get_transient($key);
            if (false !== $value) {
                $stats['cached'][] = $key;
            } else {
                $stats['missing'][] = $key;
            }
        }

        $total = count(self::$cache_keys);
        $cached = count($stats['cached']);
        $stats['hit_rate'] = $total > 0 ? round(($cached / $total) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Preload cache on plugin activation
     *
     * Ensures cache is ready immediately after activation.
     *
     * @since 3.15.0
     * @return void
     */
    public static function on_activation() {
        self::force_refresh();
    }
}

// Initialize cache warmer
CUFT_Cache_Warmer::init();
