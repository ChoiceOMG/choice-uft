# Technical Research: Force Install Update

**Feature**: 009-force-install-update
**Date**: 2025-10-12
**Status**: Complete

## Research Questions & Decisions

### 1. WordPress Update Cache Mechanism

**Question**: How do we ensure WordPress immediately recognizes a new version after manual check?

**Decision**: Use `delete_site_transient('update_plugins')` + `wp_clean_plugins_cache(true)`

**Rationale**:
- `delete_site_transient('update_plugins')` clears the WordPress update check cache
- `wp_clean_plugins_cache(true)` forces WordPress to reload plugin metadata from disk
- Combined approach ensures all admin interfaces (Plugins page, admin bar, Updates page) show updated version immediately
- Standard WordPress pattern used by core update system

**Alternatives Considered**:
1. **Manual transient deletion only**: Insufficient - doesn't clear internal plugin metadata cache
2. **`wp_update_plugins()` alone**: Doesn't force immediate fresh check; relies on scheduled update check
3. **Custom cache invalidation**: Reinventing the wheel; WordPress provides native mechanisms

**Implementation Notes**:
```php
// Clear update plugins transient
delete_site_transient( 'update_plugins' );

// Force plugin cache rebuild
wp_clean_plugins_cache( true );

// Optionally trigger immediate update check
wp_update_plugins();
```

---

### 2. Concurrent Operation Prevention

**Question**: How do we prevent multiple administrators from triggering updates simultaneously?

**Decision**: WordPress transient-based locking with `set_transient('cuft_force_update_lock', true, 120)`

**Rationale**:
- Transients provide atomic set/get operations (database-level locking)
- Automatic expiry (120 seconds = 2 minutes) prevents indefinite locks if PHP crashes
- No additional database tables or file system dependencies required
- Compatible with WordPress multisite and caching layers
- Aligns with existing Feature 007/008 patterns

**Alternatives Considered**:
1. **Database row locking**: `SELECT ... FOR UPDATE` - Overkill for this use case; requires direct SQL queries
2. **File-based locks**: `flock()` - Problematic on shared hosting; filesystem permission issues
3. **wp_options autoload=no row**: Less efficient than transients; no automatic expiry

**Implementation Notes**:
```php
// Attempt to acquire lock
if ( get_transient( 'cuft_force_update_lock' ) ) {
    return new WP_Error( 'update_in_progress', 'Another update operation is already in progress. Please wait.' );
}

// Acquire lock for 120 seconds (max operation time + buffer)
set_transient( 'cuft_force_update_lock', array(
    'user_id' => get_current_user_id(),
    'started_at' => time(),
    'operation' => 'force_reinstall'
), 120 );

// ... perform update operation ...

// Release lock
delete_transient( 'cuft_force_update_lock' );
```

---

### 3. Timeout Implementation Strategy

**Question**: How do we enforce timeout limits (5s for check, 60s for reinstall) reliably?

**Decision**: PHP `time()` checks in loops with early exit + `set_time_limit()` extension

**Rationale**:
- PHP `time()` provides accurate elapsed time measurement independent of operation type
- Loop-based checks allow graceful early exit with partial cleanup
- `set_time_limit(0)` extension prevents PHP's max_execution_time from killing operation prematurely
- Works reliably across all hosting environments (shared, VPS, dedicated)
- Feature 008's CUFT_Backup_Manager already uses this pattern successfully

**Alternatives Considered**:
1. **JavaScript-side timeout only**: Unreliable - PHP continues running server-side even if client disconnects
2. **`pcntl_alarm()`**: Not available on most shared hosting; requires PCNTL extension
3. **`stream_set_timeout()`**: Only works for stream operations; doesn't cover all code paths

**Implementation Notes**:
```php
// Extend PHP execution time limit
set_time_limit( 0 );

// Track operation start
$start_time = time();
$timeout = 60; // seconds

// Check timeout in loop
while ( $operation_in_progress ) {
    // Check elapsed time
    if ( time() - $start_time > $timeout ) {
        // Timeout exceeded - abort operation
        return new WP_Error( 'operation_timeout', 'Operation exceeded 60 second timeout. Please try again or install manually.' );
    }

    // Continue operation...
}
```

---

### 4. AJAX Endpoint Security Pattern

**Question**: What security validation is required for AJAX endpoints?

**Decision**: WordPress nonce validation (`wp_verify_nonce`) + capability check (`current_user_can('update_plugins')`)

**Rationale**:
- WordPress nonces provide CSRF protection; tied to user session and action
- `update_plugins` capability ensures only administrators with update permissions can execute
- Integrates seamlessly with existing Feature 008 `CUFT_Update_Security` class
- Standard WordPress AJAX security pattern; well-documented and battle-tested
- Aligns with constitutional Security Principles (principle #8)

**Alternatives Considered**:
1. **Custom token system**: Reinventing the wheel; WordPress nonces already solve CSRF
2. **Session-based authentication**: Doesn't integrate with WordPress roles/capabilities
3. **IP-based restrictions**: Unreliable with proxies/load balancers; doesn't validate user identity

**Implementation Notes**:
```php
// In JavaScript (AJAX request)
const formData = new FormData();
formData.append( 'action', 'cuft_check_updates' );
formData.append( 'nonce', cuftForceUpdate.nonce );

// In PHP (AJAX handler)
public function handle_check_updates() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' ) ) {
        wp_send_json_error( array(
            'error_code' => 'invalid_nonce',
            'message' => 'Security check failed. Please refresh the page and try again.'
        ), 403 );
    }

    // Verify capability
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( array(
            'error_code' => 'insufficient_permissions',
            'message' => 'You do not have permission to update plugins.'
        ), 403 );
    }

    // Check DISALLOW_FILE_MODS
    if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
        wp_send_json_error( array(
            'error_code' => 'file_mods_disabled',
            'message': 'File modifications are disabled on this site (DISALLOW_FILE_MODS).'
        ), 403 );
    }

    // Proceed with operation...
}
```

---

### 5. Disk Space Calculation

**Question**: How do we accurately calculate required disk space (3x plugin size)?

**Decision**: PHP `disk_free_space()` + recursive directory size calculation for plugin folder

**Rationale**:
- `disk_free_space()` returns available bytes on filesystem; native PHP function, universally available
- Recursive directory size calculation provides accurate plugin size including all subdirectories
- Formula: `required = plugin_size * 3` (backup + download + extraction buffer)
- Native PHP functions; no external dependencies or shell commands
- Reliable across hosting environments (Linux, Windows, shared hosting)

**Alternatives Considered**:
1. **`WP_Filesystem::dirsize()`**: Available but less accurate; doesn't handle symlinks well
2. **External `du` command**: Not portable (Windows incompatibility); security risk (shell injection); not available in all environments
3. **Fixed space requirement**: Inflexible; doesn't scale with plugin size

**Implementation Notes**:
```php
/**
 * Get directory size recursively
 */
function get_directory_size( $path ) {
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS )
    );

    foreach ( $iterator as $file ) {
        if ( $file->isFile() ) {
            $size += $file->getSize();
        }
    }

    return $size;
}

/**
 * Check disk space before force reinstall
 */
function check_disk_space() {
    $plugin_dir = WP_PLUGIN_DIR . '/choice-uft';
    $plugin_size = $this->get_directory_size( $plugin_dir );
    $required_space = $plugin_size * 3; // Backup + download + extraction

    $uploads_dir = wp_upload_dir();
    $available_space = @disk_free_space( $uploads_dir['basedir'] );

    if ( $available_space === false || $available_space < $required_space ) {
        $required_mb = round( $required_space / 1048576 ); // bytes to MB
        return new WP_Error( 'insufficient_disk_space', sprintf(
            'Insufficient disk space to create backup. Free at least %d MB and try again.',
            $required_mb
        ) );
    }

    return true;
}
```

---

### 6. Admin UI Integration Point

**Question**: Where should the force update controls be placed in the WordPress admin?

**Decision**: Add new section to existing Settings page (`CUFT_Admin::render_settings_page()`)

**Rationale**:
- Consistent with Feature 007 update management patterns
- Users expect update controls in plugin Settings, not scattered across admin
- Avoids cluttering WordPress admin menu with additional top-level items
- Existing Settings page already has user-facing update information from Feature 007
- Single location for all plugin management (GTM config, tracking settings, updates)

**Alternatives Considered**:
1. **Separate admin menu item**: Clutters WordPress admin sidebar; users must navigate between two plugin pages
2. **WordPress Updates page**: Conflicts with native WordPress update interface; potential confusion with standard "Update Now" buttons
3. **Plugin list row action**: Not enough space for two buttons + status indicators; poor UX

**Implementation Notes**:
```php
// In CUFT_Admin::render_settings_page()
public function render_settings_page() {
    // ... existing GTM settings, testing dashboard, etc. ...

    // Add force update section
    ?>
    <div class="cuft-force-update-section">
        <h2><?php esc_html_e( 'Manual Update Control', 'choice-universal-form-tracker' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Manually check for plugin updates or force reinstall the latest version from GitHub.', 'choice-universal-form-tracker' ); ?>
        </p>

        <div class="cuft-update-controls">
            <button type="button" id="cuft-check-updates" class="button button-secondary">
                <?php esc_html_e( 'Check for Updates', 'choice-universal-form-tracker' ); ?>
            </button>

            <button type="button" id="cuft-force-reinstall" class="button button-primary">
                <?php esc_html_e( 'Force Reinstall Latest Version', 'choice-universal-form-tracker' ); ?>
            </button>
        </div>

        <div id="cuft-update-status" class="cuft-update-status" style="display:none;"></div>
    </div>
    <?php
}
```

---

## Dependencies & Integrations

### Existing Classes (Feature 007/008) to Reuse

1. **CUFT_GitHub_API** (`includes/class-cuft-github-api.php`)
   - `get_latest_release()`: Fetch latest version from GitHub Releases API
   - `get_download_url()`: Get ZIP download URL for latest release
   - **Integration Point**: Call from check_updates endpoint

2. **CUFT_Backup_Manager** (`includes/update/class-cuft-backup-manager.php`)
   - `create_backup()`: Create ZIP backup before reinstall
   - `restore_backup()`: Restore from backup if reinstall fails
   - `delete_backup()`: Clean up backup after successful install
   - **Integration Point**: Call from force_reinstall endpoint

3. **CUFT_Update_Validator** (`includes/update/class-cuft-update-validator.php`)
   - `validate_file_size()`: Validate downloaded ZIP size (±5% tolerance)
   - `validate_zip_format()`: Validate ZIP integrity
   - **Integration Point**: Call after download, before extraction

4. **CUFT_Update_Security** (`includes/update/class-cuft-update-security.php`)
   - `validate_nonce()`: Nonce validation wrapper
   - `check_capability()`: Capability check wrapper
   - `validate_download_url()`: GitHub URL validation
   - `check_file_mods()`: DISALLOW_FILE_MODS check
   - **Integration Point**: Call at start of both endpoints

5. **CUFT_Error_Messages** (`includes/update/class-cuft-error-messages.php`)
   - `get_error_message()`: Standardized error messages
   - `log_error()`: Error logging to update history
   - **Integration Point**: Use for all error responses

### New Dependencies

**None Required** - All functionality achievable with:
- WordPress core APIs (transients, options, filesystem)
- Existing Feature 007/008 classes
- Native PHP functions

---

## Performance Considerations

### Caching Strategy

**Update Check Results**:
- Cache GitHub API responses in transient: `cuft_force_check_result`
- TTL: 300 seconds (5 minutes)
- Avoids redundant API calls if user clicks repeatedly
- Cache key includes version comparison to detect changes

**Rate Limit Handling**:
- GitHub API rate limit: 60 requests/hour (unauthenticated)
- Cache last known result indefinitely if rate limited
- Display cached data with timestamp: "Last checked: 5 minutes ago"
- Existing CUFT_GitHub_API class already handles this

### Timeout Targets

**Update Check**: 5 seconds maximum
- GitHub API call: ~1-2 seconds typical
- Cache operations: <10ms
- Total overhead: <100ms
- Buffer: 2-3 seconds for slow networks

**Force Reinstall**: 60 seconds maximum
- Disk space check: <100ms
- Backup creation: 2-10 seconds (depends on plugin size)
- Download: 5-20 seconds (depends on network, ~5-10MB file)
- Validation: <500ms
- Extraction: 2-5 seconds
- Total: 10-35 seconds typical, 60s max with timeout

---

## Security Considerations

### CSRF Protection
- WordPress nonces generated per user session
- Nonce action: `cuft_force_update`
- Nonce name: `cuft_force_update_nonce`
- 24-hour nonce lifetime (WordPress default)

### Authorization
- Capability: `update_plugins` (admin or specific role)
- No bypass mechanisms; hard failure on missing capability
- Logged to update history with user information

### URL Validation
- GitHub-only downloads: `https://github.com/ChoiceOMG/choice-uft/*`
- Regex pattern matching from CUFT_Update_Security
- Reject query parameters and fragments

### File Operations
- All file operations through WP_Filesystem API
- No direct file_put_contents() or unlink() calls
- Respects DISALLOW_FILE_MODS constant

---

## Compatibility Notes

**WordPress Versions**: 5.0+ (minimum requirement)
**PHP Versions**: 7.0+ (minimum requirement)
**Multisite Compatibility**: Yes - operations are site-specific
**Caching Plugins**: Compatible - transients bypass object cache when needed
**Hosting Environments**: All supported (shared, VPS, dedicated, managed WordPress)

---

## Research Summary

All technical questions resolved. Key decisions:
1. Cache clearing: `delete_site_transient` + `wp_clean_plugins_cache`
2. Locking: Transient-based with automatic expiry
3. Timeouts: PHP `time()` checks with early exit
4. Security: WordPress nonces + capability checks
5. Disk space: Native `disk_free_space()` + recursive calculation
6. UI location: Existing Settings page section

**No Blockers** - Ready to proceed to Phase 1 (Design & Contracts)
