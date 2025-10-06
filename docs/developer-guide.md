# Developer Guide: One-Click Automated Updates

**Version**: 3.15.0
**Last Updated**: 2025-10-03

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [API Reference](#api-reference)
3. [Hooks and Filters](#hooks-and-filters)
4. [Extending the Update System](#extending-the-update-system)
5. [Code Examples](#code-examples)
6. [Testing](#testing)

---

## Architecture Overview

### Component Structure

```
One-Click Update System
├── Models
│   ├── CUFT_GitHub_Release      - GitHub release data
│   ├── CUFT_Update_Status       - Current update state
│   ├── CUFT_Update_Progress     - Progress tracking
│   ├── CUFT_Update_Log          - Historical records
│   └── CUFT_Update_Configuration - Settings management
├── Services
│   ├── CUFT_GitHub_API          - GitHub API client
│   ├── CUFT_Update_Checker      - Update detection
│   ├── CUFT_Update_Installer    - Installation logic
│   ├── CUFT_Backup_Manager      - Backup/rollback
│   └── CUFT_Filesystem_Handler  - File operations
├── Integration
│   ├── CUFT_WordPress_Updater   - WP update hooks
│   ├── CUFT_Cron_Manager        - Scheduled checks
│   └── CUFT_Admin_Notices       - UI notifications
├── AJAX
│   └── CUFT_Updater_Ajax        - AJAX endpoints
├── Security
│   ├── CUFT_Rate_Limiter        - Request throttling
│   ├── CUFT_Download_Verifier   - Integrity checks
│   ├── CUFT_Capabilities        - Permission checks
│   └── CUFT_Input_Validator     - Input sanitization
└── Performance
    ├── CUFT_DB_Optimizer        - Query optimization
    ├── CUFT_Cache_Warmer        - Cache preloading
    └── cuft-lazy-loader.js      - Lazy loading
```

### Data Flow

```
1. Scheduled Cron / Manual Trigger
   ↓
2. CUFT_Update_Checker::check()
   ↓
3. CUFT_GitHub_API::get_latest_release()
   ↓
4. Version Comparison
   ↓
5. Update Available? → Yes
   ↓
6. CUFT_WordPress_Updater::inject_update()
   ↓
7. WordPress shows "Update Available"
   ↓
8. User clicks "Update Now"
   ↓
9. CUFT_Update_Installer::perform_update()
   ↓
10. CUFT_Backup_Manager::create_backup()
    ↓
11. Download → Verify → Extract → Install
    ↓
12. Success? → Yes: Cleanup
           → No: CUFT_Backup_Manager::rollback()
```

---

## API Reference

### Models

#### CUFT_GitHub_Release

Represents a GitHub release with download and version information.

**Static Methods**:

```php
/**
 * Fetch latest release from GitHub
 *
 * @param bool $include_prereleases Include pre-release versions
 * @param bool $force_refresh Bypass cache
 * @return CUFT_GitHub_Release|null Release object or null
 */
public static function fetch_latest($include_prereleases = false, $force_refresh = false);

/**
 * Fetch specific version
 *
 * @param string $version Version to fetch (e.g., "3.15.0")
 * @param bool $force_refresh Bypass cache
 * @return CUFT_GitHub_Release|null
 */
public static function fetch_version($version, $force_refresh = false);

/**
 * Create from GitHub API response
 *
 * @param object $api_response GitHub API release object
 * @return CUFT_GitHub_Release
 */
public static function from_github_api($api_response);
```

**Instance Methods**:

```php
public function get_version();        // "3.15.0"
public function get_tag_name();       // "v3.15.0"
public function get_download_url();   // ZIP download URL
public function get_changelog();      // Release notes markdown
public function get_published_at();   // ISO 8601 timestamp
public function get_assets();         // Array of release assets
public function is_prerelease();      // bool
public function to_array();           // Array representation
```

---

#### CUFT_Update_Status

Manages transient update status information.

**Static Methods**:

```php
/**
 * Get current update status
 *
 * @return array Status data
 */
public static function get();

/**
 * Set update status
 *
 * @param array $status Status data to store
 * @param int $expiration Expiration in seconds (default: 12 hours)
 * @return bool Success
 */
public static function set($status, $expiration = 43200);

/**
 * Clear status
 *
 * @return bool Success
 */
public static function clear();

/**
 * Check if update is in progress
 *
 * @return bool
 */
public static function is_update_in_progress();
```

**Status Structure**:

```php
array(
    'current_version' => '3.14.0',
    'latest_version' => '3.15.0',
    'update_available' => true,
    'last_check' => '2025-10-03T10:30:00Z',
    'download_url' => 'https://github.com/...',
    'changelog' => '### Version 3.15.0...',
    'in_progress' => false,
    'progress_percentage' => 0
)
```

---

#### CUFT_Update_Progress

Tracks real-time update progress.

**Static Methods**:

```php
/**
 * Update progress
 *
 * @param string $stage Stage name (checking, downloading, installing, etc.)
 * @param int $percentage Progress percentage (0-100)
 * @param string $message User-friendly message
 * @return bool Success
 */
public static function update($stage, $percentage, $message);

/**
 * Get current progress
 *
 * @return array Progress data
 */
public static function get();

/**
 * Mark as complete
 *
 * @param bool $success Success or failure
 * @param string $message Completion message
 * @return bool
 */
public static function complete($success, $message);
```

**Progress Stages**:

- `checking` - Verifying update availability
- `downloading` - Downloading update package
- `verifying` - Verifying download integrity
- `backing_up` - Creating backup
- `extracting` - Extracting files
- `installing` - Installing files
- `cleaning_up` - Removing temporary files
- `complete` - Update finished
- `failed` - Update failed
- `rolling_back` - Restoring previous version

---

#### CUFT_Update_Log

Database-backed update history logging.

**Static Methods**:

```php
/**
 * Add log entry
 *
 * @param string $action Action type
 * @param string $status Status (success, error, info, warning)
 * @param string $details Additional details
 * @param array $metadata Optional metadata
 * @return int|false Log entry ID or false
 */
public static function add($action, $status, $details = '', $metadata = array());

/**
 * Get logs with filters
 *
 * @param array $args Query arguments
 * @return array Log entries
 */
public static function get_logs($args = array());

/**
 * Delete old logs
 *
 * @param int $days_to_keep Retention period in days
 * @return int Number of deleted entries
 */
public static function cleanup($days_to_keep = 90);
```

**Action Types**:

- `check_started` - Update check initiated
- `check_completed` - Update check finished
- `update_started` - Update installation started
- `update_completed` - Update finished successfully
- `update_failed` - Update failed
- `rollback_started` - Rollback initiated
- `rollback_completed` - Rollback successful
- `settings_updated` - Configuration changed

---

### Services

#### CUFT_GitHub_API

GitHub API client for release information.

```php
/**
 * Get latest release
 *
 * @param bool $force_refresh Bypass cache
 * @return CUFT_GitHub_Release|null
 */
public static function get_latest_release($force_refresh = false);

/**
 * Get specific release by version
 *
 * @param string $version Version number
 * @param bool $force_refresh Bypass cache
 * @return CUFT_GitHub_Release|null
 */
public static function get_release($version, $force_refresh = false);

/**
 * Check if update is available
 *
 * @param bool $force_refresh Bypass cache
 * @return array Update check result
 */
public static function check_for_updates($force_refresh = false);
```

---

#### CUFT_Update_Checker

Update detection and version comparison.

```php
/**
 * Check for updates
 *
 * @param bool $force_refresh Force API call
 * @return array Update information
 */
public static function check($force_refresh = false);

/**
 * Compare versions
 *
 * @param string $version1 First version
 * @param string $version2 Second version
 * @return int -1 if v1 < v2, 0 if equal, 1 if v1 > v2
 */
public static function compare_versions($version1, $version2);

/**
 * Get current plugin version
 *
 * @return string Version number
 */
public static function get_current_version();
```

---

#### CUFT_Update_Installer

Core update installation logic.

```php
/**
 * Perform update
 *
 * @param string $version Target version
 * @param array $options Installation options
 * @return array Result with success status and message
 */
public static function perform_update($version, $options = array());

/**
 * Download update package
 *
 * @param string $download_url URL to ZIP file
 * @return string|false Path to downloaded file or false
 */
public static function download_package($download_url);

/**
 * Extract update package
 *
 * @param string $package_path Path to ZIP file
 * @param string $destination Extraction destination
 * @return bool Success
 */
public static function extract_package($package_path, $destination);

/**
 * Install extracted files
 *
 * @param string $source Source directory
 * @param string $destination Plugin directory
 * @return bool Success
 */
public static function install_files($source, $destination);
```

---

#### CUFT_Backup_Manager

Backup and rollback functionality.

```php
/**
 * Create backup before update
 *
 * @return string|false Backup directory path or false
 */
public static function create_backup();

/**
 * Restore from backup (rollback)
 *
 * @param string $backup_path Path to backup
 * @return bool Success
 */
public static function rollback($backup_path = null);

/**
 * List available backups
 *
 * @return array Backup paths with timestamps
 */
public static function list_backups();

/**
 * Delete old backups
 *
 * @param int $keep_count Number of recent backups to keep
 * @return int Number of deleted backups
 */
public static function cleanup_old_backups($keep_count = 3);
```

---

## Hooks and Filters

### Action Hooks

#### cuft_before_update_check
Fires before checking for updates.

```php
/**
 * Perform custom actions before update check
 */
add_action('cuft_before_update_check', function() {
    // Clear custom caches
    wp_cache_delete('my_custom_cache', 'my_group');

    // Log the check
    error_log('CUFT update check initiated');
});
```

---

#### cuft_after_update_check
Fires after update check completes.

```php
/**
 * @param array $result Update check result
 */
add_action('cuft_after_update_check', function($result) {
    if ($result['update_available']) {
        // Send notification to admin
        wp_mail(
            get_option('admin_email'),
            'Plugin Update Available',
            sprintf('Version %s is available', $result['latest_version'])
        );
    }
}, 10, 1);
```

---

#### cuft_before_update
Fires before starting update installation.

```php
/**
 * @param string $version Target version
 * @param array $release Release information
 */
add_action('cuft_before_update', function($version, $release) {
    // Create custom backup
    // Send notification
    // Log to external service
}, 10, 2);
```

---

#### cuft_after_update
Fires after update completes (success or failure).

```php
/**
 * @param bool $success Success status
 * @param string $version Installed version
 * @param array $result Update result
 */
add_action('cuft_after_update', function($success, $version, $result) {
    if ($success) {
        // Clear all caches
        wp_cache_flush();

        // Notify admin
        CUFT_Admin_Notices::add_notice(
            'success',
            sprintf('Successfully updated to version %s', $version)
        );
    }
}, 10, 3);
```

---

#### cuft_update_completed
Fires only on successful update.

```php
/**
 * @param string $old_version Previous version
 * @param string $new_version New version
 */
add_action('cuft_update_completed', function($old_version, $new_version) {
    // Run migrations if needed
    if (version_compare($new_version, '4.0.0', '>=')) {
        // Perform v4 migration
        CUFT_Migration::run_v4_migration();
    }
}, 10, 2);
```

---

#### cuft_update_failed
Fires when update fails.

```php
/**
 * @param string $error Error message
 * @param array $context Error context
 */
add_action('cuft_update_failed', function($error, $context) {
    // Log to external service
    error_log("CUFT Update Failed: {$error}");

    // Send alert
    wp_mail(
        get_option('admin_email'),
        'Plugin Update Failed',
        "Update failed: {$error}\n\nContext: " . print_r($context, true)
    );
}, 10, 2);
```

---

#### cuft_rollback_completed
Fires after successful rollback.

```php
/**
 * @param string $restored_version Version restored to
 */
add_action('cuft_rollback_completed', function($restored_version) {
    CUFT_Admin_Notices::add_notice(
        'warning',
        sprintf('Rolled back to version %s', $restored_version)
    );
}, 10, 1);
```

---

### Filter Hooks

#### cuft_github_api_url
Modify the GitHub API endpoint.

```php
/**
 * @param string $url GitHub API URL
 * @return string Modified URL
 */
add_filter('cuft_github_api_url', function($url) {
    // Use enterprise GitHub instance
    return str_replace('api.github.com', 'github.mycompany.com/api', $url);
});
```

---

#### cuft_update_check_frequency
Change automatic update check frequency.

```php
/**
 * @param string $frequency WordPress cron schedule
 * @return string Modified frequency
 */
add_filter('cuft_update_check_frequency', function($frequency) {
    // Check hourly instead of twice daily
    return 'hourly';
});
```

---

#### cuft_include_prereleases
Control pre-release version inclusion.

```php
/**
 * @param bool $include Include pre-releases
 * @return bool
 */
add_filter('cuft_include_prereleases', function($include) {
    // Enable for staging environment
    return wp_get_environment_type() === 'staging';
});
```

---

#### cuft_backup_before_update
Control automatic backup creation.

```php
/**
 * @param bool $create_backup Create backup
 * @return bool
 */
add_filter('cuft_backup_before_update', function($create_backup) {
    // Skip backup on development
    if (wp_get_environment_type() === 'local') {
        return false;
    }
    return $create_backup;
});
```

---

#### cuft_download_url
Modify download URL for custom hosting.

```php
/**
 * @param string $url Download URL
 * @param string $version Version being downloaded
 * @return string Modified URL
 */
add_filter('cuft_download_url', function($url, $version) {
    // Use private CDN
    return "https://cdn.mycompany.com/plugins/choice-uft-{$version}.zip";
}, 10, 2);
```

---

#### cuft_update_timeout
Modify update operation timeout.

```php
/**
 * @param int $timeout Timeout in seconds
 * @return int Modified timeout
 */
add_filter('cuft_update_timeout', function($timeout) {
    // Increase timeout for slow servers
    return 600; // 10 minutes
});
```

---

## Extending the Update System

### Custom Update Sources

Replace GitHub with a custom update source:

```php
/**
 * Custom update source integration
 */
class My_Custom_Update_Source {

    public function __construct() {
        // Override GitHub API
        add_filter('cuft_check_for_updates', array($this, 'check_custom_source'), 10, 2);
        add_filter('cuft_download_url', array($this, 'get_custom_download_url'), 10, 2);
    }

    /**
     * Check custom source for updates
     */
    public function check_custom_source($default_result, $force_refresh) {
        // Call your custom API
        $response = wp_remote_get('https://myapi.com/plugin/latest-version');

        if (is_wp_error($response)) {
            return $default_result;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'current_version' => CUFT_VERSION,
            'latest_version' => $data['version'],
            'update_available' => version_compare(CUFT_VERSION, $data['version'], '<'),
            'download_url' => $data['download_url'],
            'changelog' => $data['changelog'],
            'last_check' => current_time('c')
        );
    }

    /**
     * Get download URL from custom source
     */
    public function get_custom_download_url($default_url, $version) {
        return "https://mycdn.com/downloads/choice-uft-{$version}.zip";
    }
}

new My_Custom_Update_Source();
```

---

### Custom Update Notifications

Add custom notification channels:

```php
/**
 * Slack notifications for updates
 */
class CUFT_Slack_Notifications {

    private $webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';

    public function __construct() {
        add_action('cuft_update_completed', array($this, 'notify_success'), 10, 2);
        add_action('cuft_update_failed', array($this, 'notify_failure'), 10, 2);
    }

    public function notify_success($old_version, $new_version) {
        $this->send_slack_message(
            ":white_check_mark: Plugin updated successfully",
            sprintf('Updated from %s to %s', $old_version, $new_version),
            'good'
        );
    }

    public function notify_failure($error, $context) {
        $this->send_slack_message(
            ":x: Plugin update failed",
            $error,
            'danger'
        );
    }

    private function send_slack_message($title, $text, $color) {
        wp_remote_post($this->webhook_url, array(
            'body' => json_encode(array(
                'attachments' => array(
                    array(
                        'title' => $title,
                        'text' => $text,
                        'color' => $color
                    )
                )
            )),
            'headers' => array('Content-Type' => 'application/json')
        ));
    }
}

new CUFT_Slack_Notifications();
```

---

### Pre-Update Validation

Add custom validation before updates:

```php
/**
 * Validate environment before updating
 */
add_action('cuft_before_update', function($version, $release) {

    // Check PHP version requirement
    if (version_compare(PHP_VERSION, '7.2', '<')) {
        throw new Exception('PHP 7.2 or higher required for this update');
    }

    // Check disk space
    $free_space = disk_free_space(WP_CONTENT_DIR);
    if ($free_space < 50 * 1024 * 1024) { // 50 MB
        throw new Exception('Insufficient disk space. Need at least 50 MB free.');
    }

    // Check for required plugins
    if (!is_plugin_active('elementor/elementor.php')) {
        throw new Exception('Elementor is required for this update');
    }

}, 5, 2); // Priority 5 to run early
```

---

### Post-Update Actions

Run custom actions after successful updates:

```php
/**
 * Post-update maintenance tasks
 */
add_action('cuft_update_completed', function($old_version, $new_version) {

    // Clear all caches
    wp_cache_flush();

    // Regenerate .htaccess rules
    flush_rewrite_rules();

    // Update database if needed
    if (version_compare($old_version, '4.0.0', '<') && version_compare($new_version, '4.0.0', '>=')) {
        CUFT_DB_Migration::run_migrations();
    }

    // Warm cache with new version
    if (class_exists('CUFT_Cache_Warmer')) {
        CUFT_Cache_Warmer::force_refresh();
    }

    // Log to analytics
    wp_remote_post('https://analytics.example.com/track', array(
        'body' => json_encode(array(
            'event' => 'plugin_updated',
            'old_version' => $old_version,
            'new_version' => $new_version,
            'site' => home_url()
        ))
    ));

}, 10, 2);
```

---

## Code Examples

### Example 1: Programmatic Update Check

```php
/**
 * Check for updates programmatically
 */
function my_custom_update_check() {
    if (!class_exists('CUFT_Update_Checker')) {
        return;
    }

    // Force a fresh check
    $result = CUFT_Update_Checker::check(true);

    if ($result['update_available']) {
        echo sprintf(
            'Update available: %s → %s',
            $result['current_version'],
            $result['latest_version']
        );

        // Get download URL
        echo "Download: " . $result['download_url'];

        // Get changelog
        echo "Changes:\n" . $result['changelog'];

        return true;
    }

    echo 'No updates available';
    return false;
}
```

---

### Example 2: Trigger Update Programmatically

```php
/**
 * Programmatically trigger an update
 * WARNING: Only use in controlled environments
 */
function my_trigger_plugin_update() {
    if (!current_user_can('update_plugins')) {
        wp_die('Insufficient permissions');
    }

    // Check for updates first
    $check = CUFT_Update_Checker::check(true);

    if (!$check['update_available']) {
        return new WP_Error('no_update', 'No update available');
    }

    // Perform update
    $result = CUFT_Update_Installer::perform_update($check['latest_version'], array(
        'backup' => true,
        'auto_rollback' => true
    ));

    if ($result['success']) {
        return sprintf('Updated to version %s', $check['latest_version']);
    } else {
        return new WP_Error('update_failed', $result['message']);
    }
}
```

---

### Example 3: Monitor Update Progress

```php
/**
 * Real-time update progress monitoring
 */
function monitor_update_progress() {
    // Check if update is in progress
    if (!CUFT_Update_Status::is_update_in_progress()) {
        return 'No update in progress';
    }

    // Get current progress
    $progress = CUFT_Update_Progress::get();

    printf(
        'Stage: %s | Progress: %d%% | Message: %s',
        $progress['stage'],
        $progress['percentage'],
        $progress['message']
    );

    return $progress;
}
```

---

### Example 4: Custom Update Validation

```php
/**
 * Add custom update validation
 */
add_filter('cuft_can_update', function($can_update, $version) {

    // Don't update during business hours
    $hour = (int) current_time('H');
    if ($hour >= 9 && $hour < 17) {
        return new WP_Error(
            'business_hours',
            'Updates are disabled during business hours (9 AM - 5 PM)'
        );
    }

    // Check maintenance window
    if (!is_maintenance_window()) {
        return new WP_Error(
            'outside_maintenance',
            'Updates only allowed during maintenance windows'
        );
    }

    return $can_update;

}, 10, 2);

function is_maintenance_window() {
    // Check if current time is in maintenance window
    // Example: Tuesday 2 AM - 4 AM
    $day = (int) current_time('N'); // 1 = Monday, 7 = Sunday
    $hour = (int) current_time('H');

    return ($day === 2 && $hour >= 2 && $hour < 4);
}
```

---

### Example 5: Update Logging Integration

```php
/**
 * Integrate with external logging service
 */
class CUFT_External_Logger {

    public function __construct() {
        add_action('cuft_after_update_check', array($this, 'log_check'), 10, 1);
        add_action('cuft_update_completed', array($this, 'log_success'), 10, 2);
        add_action('cuft_update_failed', array($this, 'log_failure'), 10, 2);
    }

    public function log_check($result) {
        $this->send_log('info', 'update_check', array(
            'current_version' => $result['current_version'],
            'latest_version' => $result['latest_version'],
            'update_available' => $result['update_available']
        ));
    }

    public function log_success($old_version, $new_version) {
        $this->send_log('success', 'update_completed', array(
            'old_version' => $old_version,
            'new_version' => $new_version,
            'duration' => $this->get_update_duration()
        ));
    }

    public function log_failure($error, $context) {
        $this->send_log('error', 'update_failed', array(
            'error' => $error,
            'context' => $context
        ));
    }

    private function send_log($level, $event, $data) {
        // Send to external logging service (e.g., Loggly, Papertrail)
        wp_remote_post('https://logs.example.com/api/events', array(
            'body' => json_encode(array(
                'timestamp' => current_time('c'),
                'level' => $level,
                'event' => $event,
                'data' => $data,
                'site' => home_url()
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer YOUR_API_TOKEN'
            )
        ));
    }

    private function get_update_duration() {
        $progress = CUFT_Update_Progress::get();
        if (isset($progress['started_at'], $progress['completed_at'])) {
            return strtotime($progress['completed_at']) - strtotime($progress['started_at']);
        }
        return 0;
    }
}

new CUFT_External_Logger();
```

---

## Testing

### Unit Testing

```php
/**
 * PHPUnit test example for update checker
 */
class CUFT_Update_Checker_Test extends WP_UnitTestCase {

    public function test_version_comparison() {
        $this->assertEquals(
            -1,
            CUFT_Update_Checker::compare_versions('3.14.0', '3.15.0')
        );

        $this->assertEquals(
            0,
            CUFT_Update_Checker::compare_versions('3.14.0', '3.14.0')
        );

        $this->assertEquals(
            1,
            CUFT_Update_Checker::compare_versions('3.15.0', '3.14.0')
        );
    }

    public function test_update_check_with_cache() {
        // Set cached result
        CUFT_Update_Status::set(array(
            'current_version' => '3.14.0',
            'latest_version' => '3.15.0',
            'update_available' => true
        ));

        // Check without force refresh (should use cache)
        $result = CUFT_Update_Checker::check(false);

        $this->assertTrue($result['update_available']);
        $this->assertEquals('3.15.0', $result['latest_version']);
    }
}
```

### Integration Testing

```php
/**
 * Integration test for complete update flow
 */
class CUFT_Update_Integration_Test extends WP_UnitTestCase {

    public function test_complete_update_flow() {
        // 1. Check for updates
        $check = CUFT_Update_Checker::check(true);
        $this->assertArrayHasKey('update_available', $check);

        if (!$check['update_available']) {
            $this->markTestSkipped('No update available');
        }

        // 2. Create backup
        $backup_path = CUFT_Backup_Manager::create_backup();
        $this->assertNotFalse($backup_path);
        $this->assertFileExists($backup_path);

        // 3. Perform update
        $result = CUFT_Update_Installer::perform_update(
            $check['latest_version'],
            array('backup' => false) // Already backed up
        );

        $this->assertTrue($result['success']);

        // 4. Verify new version
        $new_version = CUFT_Update_Checker::get_current_version();
        $this->assertEquals($check['latest_version'], $new_version);

        // 5. Test rollback (if needed)
        if ($this->shouldTestRollback()) {
            $rollback = CUFT_Backup_Manager::rollback($backup_path);
            $this->assertTrue($rollback);
        }
    }
}
```

---

## Best Practices

1. **Always use hooks**: Extend via hooks instead of modifying core files
2. **Handle errors gracefully**: Use try-catch and check return values
3. **Respect permissions**: Check `update_plugins` capability
4. **Cache API calls**: Don't bypass cache unnecessarily
5. **Test on staging**: Test custom extensions before production
6. **Log important events**: Use CUFT_Update_Log for audit trail
7. **Validate input**: Sanitize and validate all user input
8. **Use transients**: Respect existing cache mechanisms
9. **Monitor rate limits**: Don't exceed GitHub API limits
10. **Document customizations**: Comment your code thoroughly

---

**Last Updated**: 2025-10-03
**Plugin Version**: 3.15.0
