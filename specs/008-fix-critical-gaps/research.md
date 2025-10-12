# Research: WordPress Plugin_Upgrader Integration Patterns

## Document Overview

**Purpose**: Research WordPress standard patterns for implementing GitHub-based plugin update system
**Context**: Feature 007 update system gaps - implementing proper WordPress update integration
**Date**: 2025-10-11
**Scope**: WordPress 5.0+ compatibility, GitHub releases integration, security best practices

---

## 1. plugins_api Filter: Plugin Metadata for "View Details" Modal

### Overview

The `plugins_api` filter allows plugins to override the WordPress.org Plugin Installation API and provide custom plugin information for the "View Details" modal that appears when users click "View Details" during an update check.

### Standard Pattern

```php
/**
 * Filter plugin information for custom update server
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string $action The type of information being requested from the Plugin Installation API.
 * @param object $args Plugin API arguments.
 * @return object|false Plugin information object or false.
 */
add_filter('plugins_api', 'cuft_plugins_api_handler', 10, 3);

function cuft_plugins_api_handler($result, $action, $args) {
    // Only process our plugin
    if ($action !== 'plugin_information') {
        return $result;
    }

    // Check if this is our plugin slug
    if (!isset($args->slug) || $args->slug !== 'choice-uft') {
        return $result;
    }

    // Fetch data from GitHub or cache
    $plugin_info = get_transient('cuft_plugin_info');

    if (false === $plugin_info) {
        $plugin_info = cuft_fetch_github_release_info();
        set_transient('cuft_plugin_info', $plugin_info, 12 * HOUR_IN_SECONDS);
    }

    // Return required object structure
    return (object) array(
        'name'              => $plugin_info['name'],
        'slug'              => 'choice-uft',
        'version'           => $plugin_info['version'],
        'author'            => $plugin_info['author'],
        'author_profile'    => $plugin_info['author_url'],
        'requires'          => $plugin_info['requires_wp'],
        'tested'            => $plugin_info['tested_wp'],
        'requires_php'      => $plugin_info['requires_php'],
        'download_link'     => $plugin_info['download_url'],
        'trunk'             => $plugin_info['download_url'],
        'last_updated'      => $plugin_info['last_updated'],
        'homepage'          => $plugin_info['homepage'],
        'sections'          => array(
            'description'   => $plugin_info['description'],
            'installation'  => $plugin_info['installation'],
            'changelog'     => $plugin_info['changelog'],
        ),
        'banners'           => array(
            'high' => $plugin_info['banner_high'],
            'low'  => $plugin_info['banner_low'],
        ),
        'icons'             => array(
            '1x'   => $plugin_info['icon_1x'],
            '2x'   => $plugin_info['icon_2x'],
        ),
    );
}
```

### Required Object Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `name` | string | Yes | Plugin name displayed in modal |
| `slug` | string | Yes | Plugin slug (must match directory name) |
| `version` | string | Yes | Latest available version |
| `download_link` | string | Yes | Direct ZIP download URL |
| `sections` | array | Yes | HTML content for tabs (description, installation, changelog) |
| `author` | string | Recommended | Author name |
| `requires` | string | Recommended | Minimum WordPress version (e.g., "5.0") |
| `tested` | string | Recommended | Tested up to WordPress version (e.g., "6.4") |
| `requires_php` | string | Recommended | Minimum PHP version (e.g., "7.0") |
| `last_updated` | string | Recommended | ISO 8601 date format |
| `homepage` | string | Optional | Plugin homepage URL |
| `banners` | array | Optional | Banner images (high/low resolution) |
| `icons` | array | Optional | Plugin icons (1x/2x resolution) |

### Best Practices

1. **Early Exit Pattern**: Check `$action` and `$args->slug` immediately to avoid unnecessary processing
2. **Transient Caching**: Cache plugin info for 12+ hours to reduce API calls
3. **Conditional Requests**: Use ETags when fetching from GitHub to avoid rate limits
4. **Sanitization**: Sanitize all HTML content in `sections` array using `wp_kses_post()`
5. **Error Handling**: Return `false` (not `$result`) if data cannot be fetched
6. **HTML Formatting**: Use proper HTML in sections - WordPress will render it directly in modal

### Anti-Patterns to Avoid

- ❌ **Don't modify `$result`**: Return new object or false, never modify passed result
- ❌ **Don't process other plugins**: Always check slug before doing expensive operations
- ❌ **Don't use `wp_remote_get()` directly**: Cache responses to avoid rate limits
- ❌ **Don't return invalid HTML**: Malformed HTML breaks the modal display
- ❌ **Don't omit required fields**: Missing fields cause PHP notices and broken display

### Security Considerations

1. **Input Validation**: Validate `$args->slug` matches expected plugin slug exactly
2. **URL Validation**: Validate `download_link` is from trusted GitHub domain
3. **HTML Sanitization**: Use `wp_kses_post()` for all user-generated content
4. **Nonce Not Required**: This filter doesn't need nonce validation (read-only operation)
5. **Capability Check Not Required**: WordPress handles this in core

### Performance Best Practices

1. **Transient Duration**: Use 12-24 hour cache for plugin info
2. **Conditional Cache Refresh**: Use ETags to check for changes before downloading
3. **Background Refresh**: Use cron to refresh cache before expiry
4. **Minimal Processing**: Keep filter callback lightweight - defer heavy work to cron

---

## 2. upgrader_source_selection Filter: Directory Renaming

### Overview

The `upgrader_source_selection` filter allows modification of the extracted plugin directory path before WordPress validates the plugin structure. This is critical when the ZIP file's root directory name doesn't match WordPress's expected plugin slug.

### The Problem

GitHub release ZIPs have directories named like `choice-uft-v3.16.5/` but WordPress expects `choice-uft/`. Without renaming, WordPress will:
1. Extract to `wp-content/upgrades/choice-uft-v3.16.5/`
2. Try to copy to `wp-content/plugins/choice-uft-v3.16.5/`
3. Fail to find original plugin at `wp-content/plugins/choice-uft/`
4. Leave broken plugin with versioned directory name

### Standard Pattern

```php
/**
 * Rename extracted plugin directory to match expected slug
 *
 * @param string $source File source location.
 * @param string $remote_source Remote file source location.
 * @param WP_Upgrader $upgrader WP_Upgrader instance.
 * @param array $hook_extra Extra arguments passed to hooked filters.
 * @return string|WP_Error Modified source location or WP_Error on failure.
 */
add_filter('upgrader_source_selection', 'cuft_fix_plugin_directory_name', 10, 4);

function cuft_fix_plugin_directory_name($source, $remote_source, $upgrader, $hook_extra) {
    global $wp_filesystem;

    // Only process plugin updates (not themes or core)
    if (!isset($hook_extra['plugin']) || empty($hook_extra['plugin'])) {
        return $source;
    }

    // Only process our plugin
    $plugin_slug = dirname($hook_extra['plugin']);
    if ($plugin_slug !== 'choice-uft') {
        return $source;
    }

    // Check current directory name
    $source_basename = basename($source);

    // If directory already correct, no action needed
    if ($source_basename === $plugin_slug) {
        return $source;
    }

    // Check if extracted directory matches pattern (e.g., choice-uft-v3.16.5)
    if (strpos($source_basename, $plugin_slug) !== 0) {
        return new WP_Error(
            'incompatible_plugin_archive',
            __('Plugin archive does not contain expected directory structure.', 'choice-uft')
        );
    }

    // Build new directory path
    $corrected_source = dirname($source) . '/' . $plugin_slug . '/';

    // Rename directory using WordPress Filesystem API
    if (!$wp_filesystem->move($source, $corrected_source, true)) {
        return new WP_Error(
            'unable_to_rename_directory',
            sprintf(
                __('Unable to rename plugin directory from %1$s to %2$s.', 'choice-uft'),
                $source_basename,
                $plugin_slug
            )
        );
    }

    return $corrected_source;
}
```

### Critical Implementation Details

1. **Check Context**: Verify `$hook_extra['plugin']` exists before processing
2. **Validate Slug**: Extract plugin slug from basename of `$hook_extra['plugin']`
3. **Pattern Matching**: Ensure extracted directory starts with expected slug
4. **WP_Filesystem**: Always use `$wp_filesystem->move()`, never PHP's `rename()`
5. **Error Handling**: Return `WP_Error` objects with translatable messages
6. **Trailing Slashes**: Ensure new path has trailing slash for consistency

### Filter Parameters Explained

| Parameter | Type | Description |
|-----------|------|-------------|
| `$source` | string | Path to extracted plugin (e.g., `/tmp/choice-uft-v3.16.5/`) |
| `$remote_source` | string | Path to downloaded ZIP before extraction |
| `$upgrader` | WP_Upgrader | Instance of upgrader (Plugin_Upgrader, Theme_Upgrader, etc.) |
| `$hook_extra` | array | Contains `plugin`, `type`, `action` keys for context |

### hook_extra Array Structure

```php
array(
    'plugin' => 'choice-uft/choice-universal-form-tracker.php', // Plugin basename
    'type'   => 'plugin',                                        // Update type
    'action' => 'update',                                        // Action being performed
)
```

### Best Practices

1. **Early Exit**: Check context immediately to avoid processing other plugins/themes
2. **Pattern Validation**: Verify directory name matches expected pattern before renaming
3. **Use WP_Filesystem**: Never use direct PHP filesystem functions
4. **Descriptive Errors**: Return WP_Error with clear, translatable messages
5. **Test Both Paths**: Test with correct directory name and incorrect (should handle both)
6. **Preserve Overwrite Flag**: Pass `true` to `move()` to allow overwriting existing directories

### Anti-Patterns to Avoid

- ❌ **Don't use PHP rename()**: Always use `$wp_filesystem->move()`
- ❌ **Don't assume $wp_filesystem exists**: It's global, but verify before use
- ❌ **Don't modify other plugins**: Always check `$hook_extra['plugin']` slug
- ❌ **Don't return modified $source without renaming**: This causes broken plugins
- ❌ **Don't forget trailing slashes**: Directory paths should end with `/`
- ❌ **Don't skip error checking**: Always validate operations succeeded

### Security Considerations

1. **Path Validation**: Verify `$source` is within expected WordPress directories
2. **Directory Traversal**: Sanitize directory names to prevent `../` attacks
3. **Slug Validation**: Ensure plugin slug matches expected pattern exactly
4. **Permission Checks**: Verify `$wp_filesystem->move()` has permission before attempting
5. **No User Input**: Never use user-provided data for directory names

### Common Failure Scenarios

| Scenario | Symptom | Solution |
|----------|---------|----------|
| ZIP has no root directory | Files extracted to root of upgrades/ | Return WP_Error, require proper structure |
| Multiple directories in ZIP | Ambiguous source directory | Return WP_Error, require single root directory |
| Filesystem permissions | move() fails silently | Check return value, log errors |
| Wrong plugin slug | Directory renamed incorrectly | Validate slug pattern before renaming |
| Overwrite fails | Old directory remains | Use overwrite flag in move() |

### Testing Checklist

- [ ] Test with correct directory name (should pass through unchanged)
- [ ] Test with versioned directory name (should rename to slug)
- [ ] Test with completely wrong directory name (should return WP_Error)
- [ ] Test with multiple directories in ZIP (should return WP_Error)
- [ ] Test filesystem permission failures (should return WP_Error)
- [ ] Verify trailing slashes are preserved
- [ ] Verify other plugins are not affected

---

## 3. Plugin_Upgrader Class: Standard Hooks and Lifecycle

### Overview

The `Plugin_Upgrader` class extends `WP_Upgrader` and handles the complete lifecycle of plugin updates, from download to activation. Understanding this lifecycle is crucial for integrating custom update logic.

### Plugin Update Lifecycle

```
1. Check for updates (wp_update_plugins)
   ↓
2. User clicks "Update Now"
   ↓
3. Plugin_Upgrader::upgrade() called
   ↓
4. HOOK: upgrader_pre_install
   - Deactivates plugin (deactivate_plugin_before_upgrade)
   - Stores activation state (active_before)
   ↓
5. Download package (download_package)
   ↓
6. Extract package (unpack_package)
   ↓
7. HOOK: upgrader_source_selection
   - Rename directory if needed
   ↓
8. HOOK: upgrader_clear_destination
   - Delete old plugin (delete_old_plugin)
   ↓
9. Move to destination (copy_package)
   ↓
10. HOOK: upgrader_post_install
    - Reactivate plugin if was active (active_after)
    ↓
11. HOOK: upgrader_process_complete
    - Clear plugin cache (wp_clean_plugins_cache)
    - Fire completion action
    ↓
12. Delete temp files (cleanup)
```

### Key Hooks in Order

#### 1. upgrader_pre_install

**Purpose**: Runs before installing the update, typically to deactivate plugins

```php
add_filter('upgrader_pre_install', 'cuft_before_update', 10, 2);

function cuft_before_update($return, $plugin) {
    // $return is false by default
    // $plugin is array with plugin file path

    // Log update start
    if (isset($plugin['plugin']) && $plugin['plugin'] === 'choice-uft/choice-universal-form-tracker.php') {
        error_log('CUFT Update: Starting update process');

        // Store pre-update state
        $state = array(
            'version' => CUFT_VERSION,
            'timestamp' => time(),
            'active' => is_plugin_active($plugin['plugin']),
        );
        set_transient('cuft_pre_update_state', $state, HOUR_IN_SECONDS);
    }

    return $return;
}
```

**Parameters**:
- `$return` (bool|WP_Error): False by default, return WP_Error to abort
- `$plugin` (array): Contains `plugin` key with plugin basename

**Use Cases**:
- Log update start
- Store pre-update state
- Backup configuration
- Verify prerequisites

#### 2. upgrader_clear_destination

**Purpose**: Clears the destination directory before copying new files

```php
add_filter('upgrader_clear_destination', 'cuft_clear_destination', 10, 4);

function cuft_clear_destination($removed, $local_destination, $remote_destination, $plugin) {
    // WordPress will delete old plugin directory
    // This hook allows custom cleanup or validation

    if (isset($plugin['plugin']) && dirname($plugin['plugin']) === 'choice-uft') {
        // Verify old plugin can be safely removed
        $plugin_dir = WP_PLUGIN_DIR . '/choice-uft';

        if (!is_writable($plugin_dir)) {
            return new WP_Error(
                'plugin_not_writable',
                __('Plugin directory is not writable. Cannot perform update.', 'choice-uft')
            );
        }
    }

    return $removed;
}
```

**Parameters**:
- `$removed` (bool|WP_Error): True if cleared successfully
- `$local_destination` (string): Local destination path
- `$remote_destination` (string): Remote source path
- `$plugin` (array): Plugin information

#### 3. upgrader_post_install

**Purpose**: Runs after new plugin files are installed

```php
add_filter('upgrader_post_install', 'cuft_after_install', 10, 3);

function cuft_after_install($response, $hook_extra, $result) {
    // $response is true by default
    // $hook_extra contains plugin info
    // $result contains install details

    if (isset($hook_extra['plugin']) && dirname($hook_extra['plugin']) === 'choice-uft') {
        // Verify new plugin files exist
        $plugin_file = WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];

        if (!file_exists($plugin_file)) {
            return new WP_Error(
                'plugin_file_missing',
                __('Plugin update failed: main plugin file not found after installation.', 'choice-uft')
            );
        }

        // Run any migration scripts
        // NOTE: Plugin is NOT activated yet, so don't rely on activation hooks
    }

    return $response;
}
```

**Parameters**:
- `$response` (bool|WP_Error): True by default, return WP_Error to abort
- `$hook_extra` (array): Contains `plugin`, `type`, `action`
- `$result` (array): Contains `source`, `destination`, `clear_destination`, etc.

#### 4. upgrader_process_complete

**Purpose**: Fires after the entire update process completes successfully

```php
add_action('upgrader_process_complete', 'cuft_update_complete', 10, 2);

function cuft_update_complete($upgrader, $hook_extra) {
    // This runs AFTER plugin reactivation

    // Check if this is a plugin update for our plugin
    if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
        return;
    }

    if (!isset($hook_extra['action']) || $hook_extra['action'] !== 'update') {
        return;
    }

    // For bulk updates, plugins is an array
    $plugins = isset($hook_extra['plugins']) ? $hook_extra['plugins'] : array();

    // For single updates, plugin is a string
    if (isset($hook_extra['plugin'])) {
        $plugins = array($hook_extra['plugin']);
    }

    foreach ($plugins as $plugin) {
        if (dirname($plugin) === 'choice-uft') {
            // Update completed successfully

            // Get pre-update state
            $pre_state = get_transient('cuft_pre_update_state');

            // Log to update history
            CUFT_Update_Manager::log_update_event(
                'update_complete',
                array(
                    'from_version' => $pre_state['version'],
                    'to_version' => CUFT_VERSION,
                    'timestamp' => time(),
                )
            );

            // Clear transient
            delete_transient('cuft_pre_update_state');

            // Refresh cache
            delete_site_transient('update_plugins');
            wp_update_plugins();

            break;
        }
    }
}
```

**Parameters**:
- `$upgrader` (WP_Upgrader): Instance of upgrader class
- `$hook_extra` (array): Contains update context

**Critical Note**: When this hook fires for your plugin update, **it runs the NEW version's code** (not the old version). This is because the plugin has been reactivated by this point.

### Best Practices

1. **Hook Specificity**: Only process hooks when `$hook_extra['plugin']` matches your plugin
2. **Bulk vs Single**: Handle both bulk updates (`plugins` array) and single updates (`plugin` string)
3. **Error Objects**: Return `WP_Error` objects to abort update with user-friendly message
4. **State Preservation**: Use transients to pass state between pre/post hooks
5. **Cache Clearing**: Always clear relevant caches in `upgrader_process_complete`
6. **Version Comparison**: Store old version before update to detect what changed

### Anti-Patterns to Avoid

- ❌ **Don't block other plugins**: Always check plugin slug before processing
- ❌ **Don't assume activation**: Plugin may not be active during update
- ❌ **Don't rely on old code in upgrader_process_complete**: New version is already loaded
- ❌ **Don't forget bulk updates**: Handle both single and bulk update scenarios
- ❌ **Don't skip error handling**: Always validate operations before proceeding
- ❌ **Don't leave transients**: Clean up temporary data after use

### Security Considerations

1. **Capability Checks**: WordPress core handles this, but verify in admin contexts
2. **Nonce Validation**: Not required in these hooks (WordPress validates before calling)
3. **File Validation**: Verify new plugin files exist and are readable
4. **Path Sanitization**: Never trust paths from `$hook_extra` without validation
5. **State Protection**: Use transients (not options) for temporary update state

### Performance Best Practices

1. **Minimal Processing**: Keep hook callbacks lightweight - no HTTP requests
2. **Transient Duration**: Use short expiry (1 hour) for temporary update state
3. **Deferred Operations**: Use `wp_schedule_single_event()` for heavy post-update tasks
4. **Selective Cache Clearing**: Only clear caches that are actually affected

---

## 4. WordPress Filesystem API: Safe ZIP Operations

### Overview

The WordPress Filesystem API (`WP_Filesystem`) provides an abstraction layer for file operations that handles different hosting configurations (direct filesystem access, FTP, SSH) automatically.

### Initializing WP_Filesystem

```php
/**
 * Initialize WordPress Filesystem API
 *
 * @return bool True if initialized successfully, false otherwise.
 */
function cuft_initialize_filesystem() {
    global $wp_filesystem;

    // Check if already initialized
    if (!empty($wp_filesystem)) {
        return true;
    }

    // Load Filesystem API
    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Initialize with default credentials
    // WordPress will prompt for FTP/SSH credentials if needed
    $credentials = request_filesystem_credentials(
        site_url(), // URL to post credentials to
        '',         // Method (empty = auto-detect)
        false,      // Error flag
        false,      // Context (directory to write to)
        array()     // Extra fields
    );

    // Check if credentials provided (or not needed)
    if ($credentials === false) {
        return false;
    }

    // Initialize WP_Filesystem with credentials
    if (!WP_Filesystem($credentials)) {
        return false;
    }

    return true;
}
```

### ZIP File Creation

WordPress doesn't provide built-in ZIP creation, but you can use PHP's ZipArchive with WP_Filesystem for proper permissions:

```php
/**
 * Create ZIP file with proper permissions
 *
 * @param string $source_dir Directory to zip
 * @param string $zip_file Path to create ZIP file
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function cuft_create_zip($source_dir, $zip_file) {
    global $wp_filesystem;

    // Initialize filesystem
    if (!cuft_initialize_filesystem()) {
        return new WP_Error('filesystem_error', __('Could not initialize filesystem.', 'choice-uft'));
    }

    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_unavailable', __('ZipArchive class not available.', 'choice-uft'));
    }

    // Convert to WP_Filesystem path
    $source_dir = str_replace(ABSPATH, $wp_filesystem->abspath(), $source_dir);
    $zip_file = str_replace(ABSPATH, $wp_filesystem->abspath(), $zip_file);

    // Verify source directory exists
    if (!$wp_filesystem->is_dir($source_dir)) {
        return new WP_Error('source_not_found', __('Source directory does not exist.', 'choice-uft'));
    }

    // Create ZIP
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return new WP_Error('zip_open_failed', __('Could not create ZIP file.', 'choice-uft'));
    }

    // Add files recursively
    $files = $wp_filesystem->dirlist($source_dir, true);
    foreach ($files as $file => $file_info) {
        $file_path = trailingslashit($source_dir) . $file;

        if ($file_info['type'] === 'd') {
            $zip->addEmptyDir($file);
        } else {
            $contents = $wp_filesystem->get_contents($file_path);
            $zip->addFromString($file, $contents);
        }
    }

    $zip->close();

    // Set proper permissions
    $wp_filesystem->chmod($zip_file, FS_CHMOD_FILE);

    return true;
}
```

### ZIP File Extraction

WordPress provides `unzip_file()` which uses WP_Filesystem internally:

```php
/**
 * Extract ZIP file with proper permissions
 *
 * @param string $zip_file Path to ZIP file
 * @param string $destination Directory to extract to
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function cuft_extract_zip($zip_file, $destination) {
    global $wp_filesystem;

    // Initialize filesystem
    if (!cuft_initialize_filesystem()) {
        return new WP_Error('filesystem_error', __('Could not initialize filesystem.', 'choice-uft'));
    }

    // Convert to WP_Filesystem paths
    $zip_file = str_replace(ABSPATH, $wp_filesystem->abspath(), $zip_file);
    $destination = str_replace(ABSPATH, $wp_filesystem->abspath(), $destination);

    // Verify ZIP file exists
    if (!$wp_filesystem->exists($zip_file)) {
        return new WP_Error('zip_not_found', __('ZIP file does not exist.', 'choice-uft'));
    }

    // Create destination directory if needed
    if (!$wp_filesystem->is_dir($destination)) {
        if (!$wp_filesystem->mkdir($destination, FS_CHMOD_DIR)) {
            return new WP_Error('mkdir_failed', __('Could not create destination directory.', 'choice-uft'));
        }
    }

    // Extract using WordPress function
    $result = unzip_file($zip_file, $destination);

    if (is_wp_error($result)) {
        return $result;
    }

    // Verify extraction succeeded
    $files = $wp_filesystem->dirlist($destination);
    if (empty($files)) {
        return new WP_Error('extraction_empty', __('ZIP extraction produced no files.', 'choice-uft'));
    }

    return true;
}
```

### File Permission Constants

WordPress defines these constants for file permissions:

```php
// File permissions (default: 0644)
FS_CHMOD_FILE = 0644;

// Directory permissions (default: 0755)
FS_CHMOD_DIR = 0755;
```

### Common WP_Filesystem Methods

| Method | Description | Return |
|--------|-------------|--------|
| `exists($file)` | Check if file/directory exists | bool |
| `is_file($file)` | Check if path is a file | bool |
| `is_dir($path)` | Check if path is a directory | bool |
| `is_readable($file)` | Check if file is readable | bool |
| `is_writable($path)` | Check if path is writable | bool |
| `get_contents($file)` | Read file contents | string\|false |
| `put_contents($file, $contents)` | Write file contents | bool |
| `mkdir($path, $chmod)` | Create directory | bool |
| `rmdir($path, $recursive)` | Remove directory | bool |
| `dirlist($path, $recursive)` | List directory contents | array\|false |
| `move($source, $dest, $overwrite)` | Move file/directory | bool |
| `copy($source, $dest, $overwrite)` | Copy file | bool |
| `delete($file, $recursive)` | Delete file/directory | bool |
| `chmod($file, $mode)` | Change permissions | bool |

### Best Practices

1. **Always Initialize**: Call `WP_Filesystem()` before using `$wp_filesystem`
2. **Path Conversion**: Convert absolute paths using `str_replace(ABSPATH, $wp_filesystem->abspath(), $path)`
3. **Error Checking**: Always check return values and handle WP_Error objects
4. **Permission Constants**: Use `FS_CHMOD_FILE` and `FS_CHMOD_DIR` for consistency
5. **Cleanup**: Delete temporary files/directories when done
6. **Use WordPress Functions**: Prefer `unzip_file()` over direct ZipArchive for extraction

### Anti-Patterns to Avoid

- ❌ **Don't use PHP functions**: Never use `file_get_contents()`, `mkdir()`, `rename()`, etc.
- ❌ **Don't hardcode permissions**: Use WordPress permission constants
- ❌ **Don't skip path conversion**: Always convert ABSPATH to `$wp_filesystem->abspath()`
- ❌ **Don't assume direct access**: WP_Filesystem may use FTP/SSH internally
- ❌ **Don't skip error handling**: All operations can fail, handle errors gracefully

### Security Considerations

1. **Directory Traversal**: Validate all paths to prevent `../` attacks
2. **Path Validation**: Ensure paths are within WordPress directories
3. **Temporary Files**: Use `wp_tempnam()` or `get_temp_dir()` for temp files
4. **Cleanup**: Always delete temporary files, even on error
5. **Permission Verification**: Check `is_writable()` before attempting writes

### Permission Handling

Different hosting setups require different approaches:

| Method | When Used | Credentials |
|--------|-----------|-------------|
| `direct` | WordPress owns files | None |
| `ssh2` | SSH access available | SSH key/password |
| `ftpext` | FTP extension available | FTP credentials |
| `ftpsockets` | Pure PHP FTP | FTP credentials |

WordPress automatically detects and uses the appropriate method.

### Temporary Directory Usage

```php
/**
 * Get WordPress temporary directory
 *
 * @return string Temporary directory path with trailing slash
 */
function cuft_get_temp_dir() {
    $temp_dir = get_temp_dir();

    // Ensure directory exists and is writable
    if (!wp_mkdir_p($temp_dir)) {
        return false;
    }

    return trailingslashit($temp_dir);
}
```

---

## 5. GitHub API: Release Information with Rate Limit Handling

### Overview

The GitHub API provides endpoints for fetching release information, but has rate limits that must be handled properly. Authentication increases limits significantly.

### GitHub API Rate Limits (2024)

| Request Type | Unauthenticated | Authenticated |
|--------------|-----------------|---------------|
| REST API | 60 requests/hour | 5,000 requests/hour |
| GraphQL API | N/A | 5,000 points/hour |
| Search API | 10 requests/minute | 30 requests/minute |

### Fetching Latest Release

```php
/**
 * Fetch latest release information from GitHub
 *
 * @param string $repo Repository in format "owner/repo"
 * @param string $token Optional GitHub personal access token
 * @return array|WP_Error Release information or error
 */
function cuft_fetch_github_release($repo = 'ChoiceOMG/choice-uft', $token = null) {
    // Build API URL
    $api_url = sprintf(
        'https://api.github.com/repos/%s/releases/latest',
        $repo
    );

    // Prepare headers
    $headers = array(
        'Accept' => 'application/vnd.github.v3+json',
        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
    );

    // Add authentication if token provided
    if (!empty($token)) {
        $headers['Authorization'] = 'token ' . $token;
    }

    // Check cache first
    $cache_key = 'cuft_github_release_' . md5($repo);
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        // Use conditional request with ETag
        if (isset($cached['etag'])) {
            $headers['If-None-Match'] = $cached['etag'];
        }
    }

    // Make request
    $response = wp_remote_get($api_url, array(
        'headers' => $headers,
        'timeout' => 15,
    ));

    // Check for errors
    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);

    // Handle 304 Not Modified (cached version still valid)
    if ($response_code === 304) {
        return $cached['data'];
    }

    // Handle rate limit exceeded
    if ($response_code === 403) {
        $rate_limit_remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');

        if ($rate_limit_remaining === '0') {
            $reset_time = wp_remote_retrieve_header($response, 'x-ratelimit-reset');
            $wait_seconds = $reset_time - time();

            return new WP_Error(
                'github_rate_limit',
                sprintf(
                    __('GitHub API rate limit exceeded. Try again in %d minutes.', 'choice-uft'),
                    ceil($wait_seconds / 60)
                )
            );
        }
    }

    // Handle other error codes
    if ($response_code !== 200) {
        return new WP_Error(
            'github_api_error',
            sprintf(
                __('GitHub API returned error code %d', 'choice-uft'),
                $response_code
            )
        );
    }

    // Parse response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_parse_error', __('Could not parse GitHub API response.', 'choice-uft'));
    }

    // Extract release information
    $release_info = array(
        'version' => ltrim($data['tag_name'], 'v'),
        'name' => $data['name'],
        'download_url' => null,
        'changelog' => $data['body'],
        'published_at' => $data['published_at'],
        'html_url' => $data['html_url'],
    );

    // Find ZIP asset
    foreach ($data['assets'] as $asset) {
        if (substr($asset['name'], -4) === '.zip') {
            $release_info['download_url'] = $asset['browser_download_url'];
            break;
        }
    }

    // Fallback to zipball if no asset found
    if (empty($release_info['download_url'])) {
        $release_info['download_url'] = $data['zipball_url'];
    }

    // Cache the response with ETag
    $etag = wp_remote_retrieve_header($response, 'etag');
    set_transient($cache_key, array(
        'data' => $release_info,
        'etag' => $etag,
    ), 12 * HOUR_IN_SECONDS);

    return $release_info;
}
```

### Rate Limit Monitoring

```php
/**
 * Check GitHub API rate limit status
 *
 * @param string $token Optional GitHub personal access token
 * @return array|WP_Error Rate limit information or error
 */
function cuft_check_github_rate_limit($token = null) {
    $api_url = 'https://api.github.com/rate_limit';

    $headers = array(
        'Accept' => 'application/vnd.github.v3+json',
        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
    );

    if (!empty($token)) {
        $headers['Authorization'] = 'token ' . $token;
    }

    $response = wp_remote_get($api_url, array(
        'headers' => $headers,
        'timeout' => 10,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return array(
        'limit' => $data['rate']['limit'],
        'remaining' => $data['rate']['remaining'],
        'reset' => $data['rate']['reset'],
        'used' => $data['rate']['used'],
    );
}
```

### Best Practices

1. **Authentication**: Use GitHub Personal Access Token for higher limits
2. **ETag Caching**: Use `If-None-Match` header for conditional requests
3. **Transient Caching**: Cache responses for 12-24 hours
4. **User-Agent**: Always include WordPress version and site URL
5. **Timeout**: Use 15-second timeout for reliability
6. **Error Handling**: Handle rate limits, network errors, and invalid responses
7. **Exponential Backoff**: Wait progressively longer on repeated failures

### Exponential Backoff Implementation

```php
/**
 * Fetch GitHub release with exponential backoff retry
 *
 * @param string $repo Repository name
 * @param string $token GitHub token
 * @param int $max_attempts Maximum retry attempts
 * @return array|WP_Error Release information or error
 */
function cuft_fetch_github_release_with_retry($repo, $token = null, $max_attempts = 3) {
    $attempt = 0;
    $wait_seconds = 1;

    while ($attempt < $max_attempts) {
        $result = cuft_fetch_github_release($repo, $token);

        if (!is_wp_error($result)) {
            return $result;
        }

        // Don't retry rate limit errors
        if ($result->get_error_code() === 'github_rate_limit') {
            return $result;
        }

        $attempt++;

        if ($attempt < $max_attempts) {
            // Wait before retrying (exponential backoff)
            sleep($wait_seconds);
            $wait_seconds *= 2;
        }
    }

    return $result;
}
```

### GitHub Personal Access Token

For private repositories or higher rate limits, create a token:

1. Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Click "Generate new token (classic)"
3. Select scopes: `public_repo` (or `repo` for private repos)
4. Generate token
5. Store in WordPress options (encrypted)

```php
/**
 * Store GitHub token securely
 *
 * @param string $token GitHub personal access token
 * @return bool True on success
 */
function cuft_store_github_token($token) {
    // Validate token format
    if (!preg_match('/^ghp_[a-zA-Z0-9]{36}$/', $token)) {
        return false;
    }

    // Encrypt token before storing
    $encrypted = cuft_encrypt_token($token);

    return update_option('cuft_github_token', $encrypted, false);
}

/**
 * Retrieve GitHub token
 *
 * @return string|null Decrypted token or null
 */
function cuft_get_github_token() {
    $encrypted = get_option('cuft_github_token');

    if (empty($encrypted)) {
        return null;
    }

    return cuft_decrypt_token($encrypted);
}
```

### Anti-Patterns to Avoid

- ❌ **Don't ignore rate limits**: Always handle 403 responses with rate limit headers
- ❌ **Don't skip caching**: Every request counts against rate limit
- ❌ **Don't store tokens in plain text**: Encrypt tokens before storing
- ❌ **Don't use zipball_url**: Prefer release assets (controlled naming)
- ❌ **Don't skip User-Agent**: GitHub requires valid User-Agent header

### Security Considerations

1. **Token Storage**: Encrypt tokens using WordPress encryption functions
2. **Token Permissions**: Use minimum required scopes (public_repo for public repos)
3. **Token Validation**: Validate token format before storage
4. **HTTPS Only**: GitHub API requires HTTPS, never use HTTP
5. **URL Validation**: Verify download URLs are from github.com domain

### Performance Best Practices

1. **Background Checks**: Use WP-Cron to check for updates every 12 hours
2. **Transient Caching**: Cache release info for 12-24 hours
3. **Conditional Requests**: Use ETags to avoid downloading unchanged data
4. **Async Requests**: Don't block user requests with GitHub API calls
5. **Fallback Values**: Return cached data if API request fails

### Rate Limit Response Headers

GitHub includes rate limit information in response headers:

| Header | Description |
|--------|-------------|
| `x-ratelimit-limit` | Maximum requests per hour |
| `x-ratelimit-remaining` | Requests remaining in current window |
| `x-ratelimit-reset` | Unix timestamp when limit resets |
| `x-ratelimit-used` | Requests used in current window |
| `x-ratelimit-resource` | Resource type (core, search, etc.) |

### Example: Displaying Rate Limit in Admin

```php
/**
 * Display GitHub rate limit status in admin
 */
function cuft_display_rate_limit_status() {
    $token = cuft_get_github_token();
    $rate_limit = cuft_check_github_rate_limit($token);

    if (is_wp_error($rate_limit)) {
        return;
    }

    $reset_time = human_time_diff($rate_limit['reset'], time());

    printf(
        '<div class="notice notice-info"><p>%s</p></div>',
        sprintf(
            __('GitHub API: %d of %d requests remaining (resets in %s)', 'choice-uft'),
            $rate_limit['remaining'],
            $rate_limit['limit'],
            $reset_time
        )
    );
}
```

---

## Summary of Key Takeaways

### Critical Implementation Points

1. **plugins_api Filter**:
   - Return complete plugin information object for "View Details" modal
   - Cache response for 12+ hours using transients
   - Use ETags for conditional requests
   - Sanitize all HTML content in sections

2. **upgrader_source_selection Filter**:
   - Rename extracted directory from `plugin-v1.0.0/` to `plugin/`
   - Use `$wp_filesystem->move()`, never PHP `rename()`
   - Return `WP_Error` on failure with descriptive message
   - Validate plugin slug matches before processing

3. **Plugin_Upgrader Lifecycle**:
   - Use `upgrader_pre_install` to store pre-update state
   - Use `upgrader_post_install` to verify installation
   - Use `upgrader_process_complete` to log completion (runs new version)
   - Handle both single and bulk updates

4. **WordPress Filesystem API**:
   - Always initialize with `WP_Filesystem()` before use
   - Convert paths using `str_replace(ABSPATH, $wp_filesystem->abspath(), $path)`
   - Use WordPress permission constants (`FS_CHMOD_FILE`, `FS_CHMOD_DIR`)
   - Prefer `unzip_file()` for extraction

5. **GitHub API Integration**:
   - Authenticate with Personal Access Token for higher limits
   - Use ETags for conditional requests (`If-None-Match` header)
   - Cache responses for 12-24 hours
   - Handle rate limit errors gracefully (403 + headers)
   - Implement exponential backoff for transient errors

### WordPress Coding Standards

1. **Naming**: Use `cuft_` prefix for all functions, consistent with plugin namespace
2. **Sanitization**: Use `wp_kses_post()` for HTML, `sanitize_text_field()` for strings
3. **Validation**: Check capabilities (`current_user_can('manage_options')`) in admin contexts
4. **Escaping**: Use `esc_html()`, `esc_attr()`, `esc_url()` for output
5. **Internationalization**: Wrap all strings in `__()` or `_e()` with 'choice-uft' text domain
6. **Error Handling**: Return `WP_Error` objects, never throw exceptions
7. **Documentation**: Use PHPDoc blocks for all functions with param/return types

### Security Checklist

- [ ] Validate all input (slugs, versions, URLs)
- [ ] Sanitize HTML content in plugin information
- [ ] Verify download URLs are from github.com
- [ ] Encrypt GitHub tokens before storage
- [ ] Check file permissions before operations
- [ ] Prevent directory traversal attacks
- [ ] Use nonces for user-triggered actions (not needed in these filters)
- [ ] Validate plugin slug matches expected value

### Performance Optimization

- [ ] Cache plugin info for 12-24 hours
- [ ] Use ETags for conditional requests
- [ ] Check cache before API requests
- [ ] Use WP-Cron for background update checks
- [ ] Keep filter callbacks lightweight
- [ ] Schedule heavy operations for later
- [ ] Clean up transients and temporary files

### Testing Requirements

- [ ] Test with existing plugin installed
- [ ] Test with plugin not installed
- [ ] Test with versioned directory name
- [ ] Test with correct directory name
- [ ] Test filesystem permission failures
- [ ] Test GitHub API rate limit handling
- [ ] Test network error handling
- [ ] Test with and without authentication
- [ ] Test both single and bulk updates
- [ ] Verify "View Details" modal displays correctly

---

## References

### Official WordPress Documentation

- [Plugin API](https://developer.wordpress.org/plugins/hooks/)
- [Filesystem API](https://developer.wordpress.org/apis/filesystem/)
- [Plugin Update Process](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WP_Upgrader Class](https://developer.wordpress.org/reference/classes/wp_upgrader/)
- [Plugin_Upgrader Class](https://developer.wordpress.org/reference/classes/plugin_upgrader/)

### GitHub Documentation

- [REST API - Releases](https://docs.github.com/en/rest/releases/releases)
- [Rate Limits](https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api)
- [Best Practices](https://docs.github.com/en/rest/using-the-rest-api/best-practices-for-using-the-rest-api)
- [Authentication](https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api)

### Community Resources

- [Self-Hosted Plugin Updates Tutorial](https://rudrastyh.com/wordpress/self-hosted-plugin-update.html)
- [Plugin Update Checker Library](https://github.com/YahnisElsts/plugin-update-checker)
- [WordPress Filesystem API Guide](https://www.sitepoint.com/introduction-to-the-wordpress-filesystem-api/)

---

## Appendix: Complete Working Example

See implementation in `/home/r11/dev/choice-uft/includes/class-cuft-update-manager.php` for reference implementation following all patterns documented here.

**Version**: 1.0
**Last Updated**: 2025-10-11
**Maintainer**: Choice Universal Form Tracker Development Team
