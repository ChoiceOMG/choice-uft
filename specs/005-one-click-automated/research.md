# Research Findings: One-Click Automated Update

**Date**: 2025-10-03
**Feature**: One-Click Automated Update for WordPress Plugin

## Executive Summary
This document consolidates research findings for implementing a one-click automated update system that integrates with WordPress's native update mechanism while fetching releases from GitHub.

## Research Areas

### 1. WordPress Nonce Validation in AJAX Handlers

**Decision**: Use WordPress native nonce generation with proper localization
**Rationale**:
- WordPress nonces provide CSRF protection
- Must be generated server-side and passed to JavaScript via wp_localize_script()
- Recent security issue likely due to nonce mismatch or incorrect validation

**Implementation Pattern**:
```php
// PHP: Generate and localize nonce
wp_localize_script('cuft-updater', 'cuftUpdater', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('cuft_updater_nonce')
));

// PHP: Validate in AJAX handler
if (!wp_verify_nonce($_POST['nonce'], 'cuft_updater_nonce')) {
    wp_die('Security check failed');
}

// JS: Include nonce in AJAX calls
fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'cuft_check_update',
        nonce: cuftUpdater.nonce
    })
});
```

**Alternatives Considered**:
- REST API with cookie authentication (more complex setup)
- Custom token system (unnecessary complexity)

### 2. WordPress Plugin Update API Integration

**Decision**: Hook into WordPress's update system via filters
**Rationale**:
- Seamless integration with WordPress Updates page
- Leverages existing UI and notifications
- Respects user preferences for auto-updates

**Key Hooks**:
- `pre_set_site_transient_update_plugins` - Inject our update info
- `plugins_api` - Provide plugin information
- `upgrader_source_selection` - Modify source during update

**Implementation Pattern**:
```php
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    $plugin_data = get_plugin_data(CUFT_PLUGIN_FILE);
    $github_release = $this->get_latest_github_release();

    if (version_compare($github_release->version, $plugin_data['Version'], '>')) {
        $transient->response['choice-uft/choice-uft.php'] = (object) array(
            'slug' => 'choice-uft',
            'new_version' => $github_release->version,
            'package' => $github_release->download_url,
            'url' => 'https://github.com/ChoiceOMG/choice-uft'
        );
    }
    return $transient;
});
```

**Alternatives Considered**:
- Custom update page (poor UX, doesn't integrate with WordPress)
- Direct file replacement (dangerous, no rollback)

### 3. GitHub API v3 Release Endpoints

**Decision**: Use unauthenticated API for public repository
**Rationale**:
- ChoiceOMG/choice-uft is public
- 60 requests/hour rate limit sufficient for twice-daily checks
- No authentication complexity

**API Endpoint**: `https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest`

**Response Caching**:
- Cache for 12 hours using WordPress transients
- Manual refresh option bypasses cache

**Error Handling**:
- Rate limit exceeded: Use cached data
- Network error: Fail gracefully with user notification
- Invalid response: Log error, show last known version

**Alternatives Considered**:
- GraphQL API (overkill for simple queries)
- Authenticated requests (unnecessary for public repo)
- Webhooks (requires server endpoint)

### 4. WordPress Filesystem API for Updates

**Decision**: Use WP_Filesystem with automatic method detection
**Rationale**:
- Handles different hosting configurations
- Manages file permissions correctly
- Provides rollback capability

**Implementation Pattern**:
```php
// Initialize filesystem
if (!function_exists('WP_Filesystem')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

WP_Filesystem();
global $wp_filesystem;

// Backup current version
$backup_dir = WP_CONTENT_DIR . '/upgrade/choice-uft-backup/';
$wp_filesystem->mkdir($backup_dir);
copy_dir($plugin_dir, $backup_dir);

// Extract update
$result = unzip_file($update_file, $plugin_dir);

// Rollback on failure
if (is_wp_error($result)) {
    copy_dir($backup_dir, $plugin_dir);
    $wp_filesystem->delete($backup_dir, true);
    return new WP_Error('update_failed', 'Update failed and was rolled back');
}
```

**Alternatives Considered**:
- Direct file operations (permission issues)
- FTP/SSH only (limits compatibility)

### 5. Update Check Scheduling and Caching

**Decision**: WordPress Cron with transient caching
**Rationale**:
- Native WordPress scheduling system
- Transients provide automatic expiration
- Reduces GitHub API calls

**Implementation**:
```php
// Schedule twice-daily checks
if (!wp_next_scheduled('cuft_check_updates')) {
    wp_schedule_event(time(), 'twicedaily', 'cuft_check_updates');
}

// Cache update data
set_transient('cuft_update_data', $update_data, 12 * HOUR_IN_SECONDS);

// Manual check bypasses cache
if ($force_check) {
    delete_transient('cuft_update_data');
}
```

**Alternatives Considered**:
- Real-time checks (excessive API usage)
- Database storage (transients are cleaner)
- External cron (unnecessary complexity)

## Security Considerations

### Nonce Security Fix
The reported "Security check failed" issue is likely caused by:
1. **Stale nonce**: Nonces expire after 24 hours
2. **Context mismatch**: Nonce created in wrong context
3. **Missing localization**: Nonce not passed to JavaScript

**Solution**:
- Generate fresh nonce on each page load
- Use consistent nonce action name
- Properly localize to JavaScript
- Add nonce refresh mechanism for long-lived pages

### Download Verification
- Verify HTTPS connection to GitHub
- Check file size matches release metadata
- Validate ZIP file structure before extraction
- Maintain backup until update confirmed successful

## Performance Optimizations

### Caching Strategy
- 12-hour transient for update checks
- 5-minute transient for in-progress updates
- Clear cache on manual check
- Preemptive cache warming via cron

### Background Processing
- Use WordPress background processing for downloads
- Show progress in admin bar
- Allow other admin operations during update

## Error Recovery

### Automatic Rollback Triggers
1. Download fails or corrupted
2. Extraction fails
3. File permission errors
4. Missing required files post-update
5. Version mismatch after update

### Rollback Mechanism
1. Create backup in upgrade directory
2. Attempt update
3. Verify update success
4. On failure: restore from backup
5. Clean up backup on success

## Testing Strategy

### Test Scenarios
1. **Update Available**: Mock newer GitHub release
2. **No Update**: Current version is latest
3. **Network Failure**: Simulate connection timeout
4. **Corrupt Download**: Provide invalid ZIP
5. **Permission Denied**: Read-only plugin directory
6. **Rollback**: Force update failure

### Test Implementation
- Create test mode that uses mock GitHub responses
- Implement dry-run mode for update process
- Log all operations for debugging

## Recommendations

1. **Fix Nonce Issue First**: Priority fix for security validation
2. **Implement Gradually**: Start with check functionality, then add update
3. **Add Logging**: Comprehensive logging for troubleshooting
4. **User Communication**: Clear status messages throughout process
5. **Fallback Options**: Multiple ways to trigger updates (cron, manual, WP-CLI)

## Conclusion

The implementation should leverage WordPress's native systems (updates, filesystem, cron, transients) while integrating with GitHub's release API. The critical nonce security issue requires immediate attention, likely resolved through proper nonce generation and localization. The automatic rollback mechanism ensures safety, while caching minimizes API usage.