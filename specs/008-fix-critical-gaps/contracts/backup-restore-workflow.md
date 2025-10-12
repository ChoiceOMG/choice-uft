# Contract: Backup and Restore Workflow

**Feature**: 008-fix-critical-gaps (FR-402)
**Purpose**: Automatic backup before update and rollback on failure
**Operations**: `create_backup()`, `restore_backup()`, `delete_backup()`
**Performance**: Backup <10s, Restore <10s (hard timeout)

---

## Overview

This contract defines the workflow for backing up the current plugin before an update and restoring it if the update fails. The backup mechanism provides a safety net for the update system, ensuring users can always return to a working version.

---

## Operation 1: create_backup()

### Purpose
Create a ZIP archive of the current plugin directory before starting an update.

### Signature

```php
/**
 * Create backup of current plugin files
 *
 * @param string $current_version Current plugin version (for filename)
 * @return string|WP_Error Path to backup file on success, WP_Error on failure
 */
function cuft_create_backup($current_version);
```

### Input Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `$current_version` | string | Yes | Current plugin version | Semver format (e.g., "3.16.5") |

### Output

**Success**: Returns absolute path to backup ZIP file
```php
'/var/www/html/wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip'
```

**Failure**: Returns `WP_Error` object
```php
new WP_Error('backup_failed', 'Reason for failure', $error_data);
```

### Backup File Naming

**Pattern**: `choice-uft-{VERSION}-backup.zip`

**Examples**:
- `choice-uft-3.16.5-backup.zip`
- `choice-uft-3.17.0-backup.zip`

**Rationale**: Version in filename allows identification and prevents conflicts

### Backup Location

**Base Directory**: `/wp-content/uploads/cuft-backups/`

**Directory Creation**:
```php
$backup_dir = WP_CONTENT_DIR . '/uploads/cuft-backups/';

if (!$wp_filesystem->is_dir($backup_dir)) {
    $created = $wp_filesystem->mkdir($backup_dir, FS_CHMOD_DIR);

    if (!$created) {
        return new WP_Error(
            'backup_dir_create_failed',
            __('Could not create backup directory.', 'choice-uft')
        );
    }
}
```

**Security**: Create `.htaccess` to deny direct access
```apache
# /wp-content/uploads/cuft-backups/.htaccess
Deny from all
```

### Pre-Backup Validation

| Check | Validation | Error Code |
|-------|------------|------------|
| Source directory exists | `$wp_filesystem->is_dir($plugin_dir)` | `source_not_found` |
| Source directory readable | `$wp_filesystem->is_readable($plugin_dir)` | `source_not_readable` |
| Backup directory writable | `$wp_filesystem->is_writable($backup_dir)` | `backup_dir_not_writable` |
| Sufficient disk space | `disk_free_space() > 2 * $source_size` | `disk_full` |
| ZipArchive available | `class_exists('ZipArchive')` | `zip_unavailable` |

### Backup Creation Process

```php
// 1. Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// 2. Validate pre-conditions
$plugin_dir = WP_PLUGIN_DIR . '/choice-uft/';
$backup_dir = WP_CONTENT_DIR . '/uploads/cuft-backups/';

// 3. Calculate source directory size
$source_size = cuft_get_directory_size($plugin_dir);

// 4. Check disk space (need 2x source size for safety)
$free_space = disk_free_space($backup_dir);
if ($free_space < ($source_size * 2)) {
    return new WP_Error(
        'disk_full',
        sprintf(
            __('Insufficient disk space. Need %s MB, have %s MB.', 'choice-uft'),
            round($source_size * 2 / 1024 / 1024, 2),
            round($free_space / 1024 / 1024, 2)
        )
    );
}

// 5. Create backup ZIP
$backup_file = $backup_dir . 'choice-uft-' . $current_version . '-backup.zip';

$zip = new ZipArchive();
if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    return new WP_Error(
        'zip_open_failed',
        __('Could not create backup ZIP file.', 'choice-uft')
    );
}

// 6. Add files recursively
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen($plugin_dir));

        $zip->addFile($file_path, $relative_path);
    }
}

$zip->close();

// 7. Verify backup created successfully
if (!$wp_filesystem->exists($backup_file)) {
    return new WP_Error(
        'backup_verification_failed',
        __('Backup file was not created.', 'choice-uft')
    );
}

$backup_size = $wp_filesystem->size($backup_file);
if ($backup_size === 0) {
    return new WP_Error(
        'backup_empty',
        __('Backup file is empty.', 'choice-uft')
    );
}

// 8. Set proper permissions
$wp_filesystem->chmod($backup_file, FS_CHMOD_FILE);

return $backup_file;
```

### Post-Backup Validation

| Check | Validation | Error Code |
|-------|------------|------------|
| Backup file exists | `$wp_filesystem->exists($backup_file)` | `backup_verification_failed` |
| Backup file size > 0 | `$wp_filesystem->size($backup_file) > 0` | `backup_empty` |
| ZIP file valid | `ZipArchive::open($backup_file)` succeeds | `backup_invalid` |

### Error Handling

| Error Scenario | Error Code | User Message | Action |
|----------------|------------|--------------|--------|
| Source directory not found | `source_not_found` | "Plugin directory not found. Cannot create backup." | Abort update |
| Source directory not readable | `source_not_readable` | "Cannot read plugin files. Check file permissions." | Abort update |
| Backup directory not writable | `backup_dir_not_writable` | "Cannot write to backup directory. Ensure /wp-content/uploads/ is writable." | Abort update |
| Insufficient disk space | `disk_full` | "Insufficient disk space. Need X MB, have Y MB." | Abort update |
| ZipArchive unavailable | `zip_unavailable` | "ZipArchive PHP extension not available. Cannot create backup." | Abort update |
| ZIP creation failed | `zip_open_failed` | "Could not create backup ZIP file. Try again or contact support." | Abort update |
| Backup verification failed | `backup_verification_failed` | "Backup verification failed. Update aborted for safety." | Abort update |

### Performance Target

**Target Time**: <10 seconds for typical plugin size (5 MB)

**Optimization**:
- Use `RecursiveIteratorIterator` for efficient directory traversal
- Stream files directly to ZIP (don't load into memory)
- Skip temporary files (`.DS_Store`, `Thumbs.db`, etc.)

---

## Operation 2: restore_backup()

### Purpose
Extract backup ZIP to plugin directory when update fails.

### Signature

```php
/**
 * Restore plugin from backup ZIP file
 *
 * @param string $backup_file Absolute path to backup ZIP
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function cuft_restore_backup($backup_file);
```

### Input Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `$backup_file` | string | Yes | Path to backup ZIP | Must exist, must be readable |

### Output

**Success**: Returns `true`

**Failure**: Returns `WP_Error` object

**Timeout**: Returns `WP_Error` with code `restoration_timeout`

### Hard Timeout Enforcement

**Requirement**: Restoration MUST complete within 10 seconds or abort.

```php
// Set hard timeout
set_time_limit(10);

$start_time = microtime(true);

// Perform restoration
$result = cuft_extract_backup($backup_file, $plugin_dir);

$elapsed = microtime(true) - $start_time;

if ($elapsed >= 10) {
    // Timeout reached, abort
    return new WP_Error(
        'restoration_timeout',
        sprintf(
            __('Restoration exceeded 10-second timeout (took %.2f seconds). Please reinstall manually from GitHub: %s', 'choice-uft'),
            $elapsed,
            'https://github.com/ChoiceOMG/choice-uft/releases/latest'
        )
    );
}

return $result;
```

### Pre-Restore Validation

| Check | Validation | Error Code |
|-------|------------|------------|
| Backup file exists | `$wp_filesystem->exists($backup_file)` | `backup_not_found` |
| Backup file readable | `$wp_filesystem->is_readable($backup_file)` | `backup_not_readable` |
| Plugin directory writable | `$wp_filesystem->is_writable($plugin_dir)` | `plugin_dir_not_writable` |
| ZIP file valid | `ZipArchive::open($backup_file)` succeeds | `backup_corrupted` |

### Restoration Process

```php
// 1. Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// 2. Set hard timeout
set_time_limit(10);
$start_time = microtime(true);

// 3. Validate backup file
if (!$wp_filesystem->exists($backup_file)) {
    return new WP_Error(
        'backup_not_found',
        __('Backup file not found. Cannot restore.', 'choice-uft')
    );
}

// 4. Clear plugin directory (except wp-content/uploads/cuft-backups/)
$plugin_dir = WP_PLUGIN_DIR . '/choice-uft/';
$cleared = $wp_filesystem->delete($plugin_dir, true, 'd');

if (!$cleared) {
    return new WP_Error(
        'plugin_dir_clear_failed',
        __('Could not clear plugin directory for restoration.', 'choice-uft')
    );
}

// 5. Recreate plugin directory
$wp_filesystem->mkdir($plugin_dir, FS_CHMOD_DIR);

// 6. Extract backup using WordPress function
$result = unzip_file($backup_file, $plugin_dir);

// 7. Check timeout
$elapsed = microtime(true) - $start_time;
if ($elapsed >= 10) {
    return new WP_Error(
        'restoration_timeout',
        sprintf(
            __('Restoration exceeded 10-second timeout. Please reinstall manually: %s', 'choice-uft'),
            'https://github.com/ChoiceOMG/choice-uft/releases/latest'
        )
    );
}

// 8. Check for errors
if (is_wp_error($result)) {
    return $result;
}

// 9. Verify restoration succeeded
$plugin_file = $plugin_dir . 'choice-universal-form-tracker.php';
if (!$wp_filesystem->exists($plugin_file)) {
    return new WP_Error(
        'restoration_verification_failed',
        __('Plugin file not found after restoration.', 'choice-uft')
    );
}

return true;
```

### Post-Restore Validation

| Check | Validation | Error Code |
|-------|------------|------------|
| Plugin file exists | `$wp_filesystem->exists($plugin_file)` | `restoration_verification_failed` |
| Elapsed time < 10s | `$elapsed < 10` | `restoration_timeout` |
| Directory not empty | `$wp_filesystem->dirlist($plugin_dir)` not empty | `restoration_empty` |

### Error Handling

| Error Scenario | Error Code | User Message | Action |
|----------------|------------|--------------|--------|
| Backup file not found | `backup_not_found` | "Backup file not found. Please reinstall manually from GitHub: [URL]" | Show manual reinstall message |
| Backup file corrupted | `backup_corrupted` | "Backup file is corrupted. Please reinstall manually from GitHub: [URL]" | Show manual reinstall message |
| Plugin directory not writable | `plugin_dir_not_writable` | "Plugin directory not writable. Check file permissions." | Show manual reinstall message |
| Restoration timeout (>10s) | `restoration_timeout` | "Restoration exceeded 10-second timeout. Please reinstall manually: [URL]" | Show manual reinstall message |
| Restoration failed (other) | `restoration_failed` | "Restoration failed: [reason]. Please reinstall manually: [URL]" | Show manual reinstall message |
| Verification failed | `restoration_verification_failed` | "Plugin files missing after restoration. Please reinstall manually: [URL]" | Show manual reinstall message |

**CRITICAL**: All restoration errors MUST be logged to PHP error_log with full details:

```php
error_log('CUFT CRITICAL: Backup restoration failed - ' . $error_code . ': ' . $error_message);
error_log('CUFT CRITICAL: Backup file: ' . $backup_file);
error_log('CUFT CRITICAL: Plugin directory: ' . $plugin_dir);
error_log('CUFT CRITICAL: Elapsed time: ' . $elapsed . 's');
```

### Manual Reinstall Message

When restoration fails, display this message:

```
Update failed and automatic restoration also failed.

Please reinstall the plugin manually:
1. Download the latest version from GitHub: https://github.com/ChoiceOMG/choice-uft/releases/latest
2. Deactivate and delete the current plugin
3. Upload and activate the downloaded ZIP file

If you need assistance, please contact support with the error details from your PHP error log.
```

### Performance Target

**Target Time**: <10 seconds (hard timeout)

**Typical Time**: 3-5 seconds for 5 MB plugin

**Optimization**:
- Use WordPress `unzip_file()` (optimized for speed)
- Don't validate every file (only main plugin file)
- Clear directory in one operation, not file-by-file

---

## Operation 3: delete_backup()

### Purpose
Delete backup ZIP file after successful update.

### Signature

```php
/**
 * Delete backup file after successful update
 *
 * @param string $backup_file Absolute path to backup ZIP
 * @return bool True on success, false on failure (non-critical)
 */
function cuft_delete_backup($backup_file);
```

### Input Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `$backup_file` | string | Yes | Path to backup ZIP | Should exist, but failure non-critical |

### Output

**Success**: Returns `true`

**Failure**: Returns `false` (logged, but not critical)

### Deletion Process

```php
// 1. Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// 2. Check if file exists
if (!$wp_filesystem->exists($backup_file)) {
    // Already deleted, consider success
    return true;
}

// 3. Delete file
$deleted = $wp_filesystem->delete($backup_file, false, 'f');

if (!$deleted) {
    // Log warning, but don't fail update
    error_log('CUFT WARNING: Could not delete backup file: ' . $backup_file);
    return false;
}

return true;
```

### Error Handling

| Error Scenario | Action |
|----------------|--------|
| File doesn't exist | Consider success (already deleted) |
| Delete operation fails | Log warning, return false, but DON'T fail update |
| Permission denied | Log warning, return false, file remains (cleaned up later by cron) |

**Rationale**: Backup deletion failure is non-critical. The update succeeded, so we don't want to fail the entire operation. Orphaned backups will be cleaned up by scheduled cron job.

### Performance Target

**Target Time**: <1 second

---

## Workflow Integration

### Update Lifecycle with Backup/Restore

```
1. Update triggered by user
   ↓
2. create_backup($current_version)
   ↓ (if WP_Error)
   ABORT: Show error, don't proceed
   ↓ (if success)
3. Store backup path in transient
   ↓
4. Download new version
   ↓ (if download fails)
   ROLLBACK: restore_backup($backup_file)
   ↓
5. Validate downloaded ZIP
   ↓ (if validation fails)
   ROLLBACK: restore_backup($backup_file)
   ↓
6. Extract new version
   ↓ (if extraction fails)
   ROLLBACK: restore_backup($backup_file)
   ↓
7. Rename directory
   ↓ (if rename fails)
   ROLLBACK: restore_backup($backup_file)
   ↓
8. Copy to plugin directory
   ↓ (if copy fails)
   ROLLBACK: restore_backup($backup_file)
   ↓
9. Update SUCCESS
   ↓
10. delete_backup($backup_file)
    ↓
11. Clear transient
```

### Hook Integration

**When to Create Backup**: `upgrader_pre_install` filter (before update starts)

```php
add_filter('upgrader_pre_install', 'cuft_create_backup_before_update', 10, 2);

function cuft_create_backup_before_update($return, $plugin) {
    if (!isset($plugin['plugin']) || dirname($plugin['plugin']) !== 'choice-uft') {
        return $return;
    }

    $backup_file = cuft_create_backup(CUFT_VERSION);

    if (is_wp_error($backup_file)) {
        return $backup_file; // Abort update
    }

    // Store backup path for potential restoration
    set_transient('cuft_backup_path', $backup_file, HOUR_IN_SECONDS);

    return $return;
}
```

**When to Delete Backup**: `upgrader_process_complete` action (after update succeeds)

```php
add_action('upgrader_process_complete', 'cuft_delete_backup_after_update', 10, 2);

function cuft_delete_backup_after_update($upgrader, $hook_extra) {
    if (!isset($hook_extra['plugin']) || dirname($hook_extra['plugin']) !== 'choice-uft') {
        return;
    }

    $backup_file = get_transient('cuft_backup_path');

    if ($backup_file) {
        cuft_delete_backup($backup_file);
        delete_transient('cuft_backup_path');
    }
}
```

**When to Restore Backup**: Any upgrade error

```php
add_filter('upgrader_post_install', 'cuft_restore_on_failure', 10, 3);

function cuft_restore_on_failure($response, $hook_extra, $result) {
    if (!isset($hook_extra['plugin']) || dirname($hook_extra['plugin']) !== 'choice-uft') {
        return $response;
    }

    // If update failed
    if (is_wp_error($response)) {
        $backup_file = get_transient('cuft_backup_path');

        if ($backup_file) {
            $restored = cuft_restore_backup($backup_file);

            if (is_wp_error($restored)) {
                // Restoration failed, show manual reinstall message
                return new WP_Error(
                    'restoration_failed',
                    sprintf(
                        __('Update failed and automatic restoration also failed. Please reinstall manually from GitHub: %s', 'choice-uft'),
                        'https://github.com/ChoiceOMG/choice-uft/releases/latest'
                    )
                );
            }

            // Restoration succeeded, update error message
            return new WP_Error(
                'update_failed_restored',
                sprintf(
                    __('Update failed: %s. Previous version has been restored automatically.', 'choice-uft'),
                    $response->get_error_message()
                )
            );
        }
    }

    return $response;
}
```

---

## Cleanup Strategy

### Immediate Cleanup (Success)
- Delete backup immediately after successful update
- Standard WordPress pattern (no retention)

### Orphaned Backup Cleanup (Scheduled)
- Daily WP-Cron job: `cuft_cleanup_old_backups`
- Delete backups older than 7 days
- Purpose: Catch backups from failed/crashed updates

```php
add_action('cuft_cleanup_old_backups', 'cuft_scheduled_backup_cleanup');

function cuft_scheduled_backup_cleanup() {
    $backup_dir = WP_CONTENT_DIR . '/uploads/cuft-backups/';
    $files = glob($backup_dir . '*.zip');

    foreach ($files as $file) {
        $age = time() - filemtime($file);

        // Delete if older than 7 days
        if ($age > 7 * DAY_IN_SECONDS) {
            wp_delete_file($file);
        }
    }
}

// Register scheduled event
if (!wp_next_scheduled('cuft_cleanup_old_backups')) {
    wp_schedule_event(time(), 'daily', 'cuft_cleanup_old_backups');
}
```

---

## Security Considerations

### Backup File Permissions
- Directory: 0755 (readable/executable by web server)
- Files: 0644 (readable by web server)
- `.htaccess`: Deny direct access via web browser

### Path Validation
- Validate all paths are within WordPress directories
- Prevent directory traversal (`../` attacks)
- Sanitize filenames

### Sensitive Data
- Backups may contain configuration files
- Ensure backups are not web-accessible
- Delete backups after use (don't accumulate)

---

## Testing Requirements

### Test Cases

1. **TC-001: Backup Creation Success**
   - Input: Current version 3.16.5, sufficient disk space
   - Expected: Backup created at expected path, size > 0

2. **TC-002: Backup Creation - Insufficient Disk Space**
   - Input: Disk 90% full
   - Expected: Return `WP_Error` with `disk_full` code

3. **TC-003: Backup Creation - Directory Not Writable**
   - Input: Backup directory permissions 0555
   - Expected: Return `WP_Error` with `backup_dir_not_writable` code

4. **TC-004: Restoration Success**
   - Input: Valid backup file, update fails
   - Expected: Plugin restored to previous version, main file exists

5. **TC-005: Restoration Timeout**
   - Input: Large backup file, slow filesystem
   - Expected: Return `WP_Error` with `restoration_timeout` code after 10s

6. **TC-006: Restoration - Backup File Missing**
   - Input: Backup file deleted or moved
   - Expected: Return `WP_Error` with `backup_not_found` code

7. **TC-007: Restoration - Backup File Corrupted**
   - Input: Truncated or malformed ZIP file
   - Expected: Return `WP_Error` with `backup_corrupted` code

8. **TC-008: Backup Deletion Success**
   - Input: Valid backup file after successful update
   - Expected: Backup file deleted, returns true

9. **TC-009: Backup Deletion - File Already Deleted**
   - Input: Backup file doesn't exist
   - Expected: Returns true (considered success)

10. **TC-010: Full Workflow - Update Success**
    - Input: Update from 3.16.5 to 3.17.0
    - Expected: Backup created, update succeeds, backup deleted

11. **TC-011: Full Workflow - Update Failure with Rollback**
    - Input: Update fails during extraction
    - Expected: Backup created, restoration succeeds, error message shows "restored"

12. **TC-012: Full Workflow - Update and Rollback Both Fail**
    - Input: Update fails, restoration times out
    - Expected: Error message shows manual reinstall instructions

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Implementation
