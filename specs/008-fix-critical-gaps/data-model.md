# Data Model: Update System Implementation Gaps

**Feature**: 008-fix-critical-gaps
**Date**: 2025-10-11
**Purpose**: Define data entities for plugin metadata, download validation, backup/restore, and update execution tracking

---

## Entity Overview

This feature introduces four key entities to complete the WordPress update system:

1. **Plugin Metadata** - Information displayed in "View Details" modal
2. **Download Package** - Validation state for downloaded ZIP files
3. **Backup Archive** - Filesystem backup for rollback capability
4. **Update Execution Context** - Transient tracking of update progress

---

## 1. Plugin Metadata Entity

### Purpose
Provides complete plugin information for WordPress's "View Details" modal via the `plugins_api` filter.

### Source
- **Primary**: GitHub Releases API (`https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest`)
- **Fallback**: Hardcoded plugin info if GitHub API unavailable
- **Storage**: WordPress transient (`cuft_plugin_info`)
- **TTL**: 12 hours

### Fields

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `name` | string | Yes | Plugin display name | Non-empty, max 100 chars |
| `slug` | string | Yes | Plugin slug (directory name) | Must equal `choice-uft` |
| `version` | string | Yes | Latest available version | Semver format (e.g., `3.17.0`) |
| `author` | string | Yes | Author name | Non-empty, max 100 chars |
| `author_profile` | string | Yes | Author URL | Valid HTTPS URL |
| `homepage` | string | Yes | Plugin repository URL | Valid HTTPS URL |
| `requires` | string | Yes | Minimum WordPress version | Version format (e.g., `5.0`) |
| `tested` | string | Yes | Tested up to WordPress version | Version format (e.g., `6.7`) |
| `requires_php` | string | Yes | Minimum PHP version | Version format (e.g., `7.0`) |
| `download_link` | string | Yes | Direct ZIP download URL | Valid HTTPS GitHub URL |
| `last_updated` | string | Yes | Last release date | ISO 8601 format |
| `sections` | object | Yes | Modal content tabs | See Sections structure |
| `banners` | object | Optional | Banner images | See Banners structure |
| `icons` | object | Optional | Plugin icons | See Icons structure |

#### Sections Object Structure

```php
array(
    'description' => string, // HTML content (sanitized with wp_kses_post)
    'installation' => string, // HTML installation instructions
    'changelog' => string, // HTML changelog from GitHub release notes
)
```

**Validation**:
- All HTML sanitized via `wp_kses_post()`
- `changelog` may be omitted if GitHub API fails (graceful degradation)
- Other sections required (return hardcoded fallback if API fails)

#### Banners Object Structure

```php
array(
    'high' => string, // URL to 1544x500 banner image (optional)
    'low' => string,  // URL to 772x250 banner image (optional)
)
```

#### Icons Object Structure

```php
array(
    '1x' => string, // URL to 128x128 icon (optional)
    '2x' => string, // URL to 256x256 icon (optional)
)
```

### Lifecycle

```
1. Check transient cache (cuft_plugin_info)
   ↓ (if expired)
2. Fetch from GitHub API with ETag conditional request
   ↓ (if 304 Not Modified)
3. Return cached data unchanged
   ↓ (if 200 OK)
4. Parse response, validate fields
   ↓
5. Sanitize HTML sections
   ↓
6. Store in transient with 12-hour TTL
   ↓
7. Return to plugins_api filter
```

### State Transitions

**No state transitions** - This is a read-only DTO with no mutable state.

### Relationships

- **None** - Standalone entity, no foreign keys or relationships

### Validation Rules

1. **Version Format**: Must match regex `/^\d+\.\d+\.\d+$/` (semver)
2. **URL Format**: Must start with `https://`
3. **Date Format**: Must be parseable by `strtotime()`
4. **Slug Match**: Must exactly equal `choice-uft` (case-sensitive)
5. **GitHub URL**: `download_link` must match pattern `https://github.com/ChoiceOMG/choice-uft/releases/download/*`

### Error Handling

| Error Scenario | Behavior |
|----------------|----------|
| GitHub API unavailable | Omit `changelog` section, use hardcoded description/installation |
| Rate limit exceeded | Return cached data (even if expired), show admin notice |
| Invalid JSON response | Return `false` from filter, WordPress shows default modal |
| Missing required field | Log error, return `false` from filter |
| Malformed HTML in sections | Sanitize with `wp_kses_post()`, strip disallowed tags |

### Example Instance

```php
(object) array(
    'name'              => 'Choice Universal Form Tracker',
    'slug'              => 'choice-uft',
    'version'           => '3.17.0',
    'author'            => 'Choice Marketing',
    'author_profile'    => 'https://github.com/ChoiceOMG',
    'homepage'          => 'https://github.com/ChoiceOMG/choice-uft',
    'requires'          => '5.0',
    'tested'            => '6.7',
    'requires_php'      => '7.0',
    'download_link'     => 'https://github.com/ChoiceOMG/choice-uft/releases/download/v3.17.0/choice-uft-v3.17.0.zip',
    'last_updated'      => '2025-10-11T12:00:00Z',
    'sections'          => array(
        'description'   => '<p>Universal form tracking plugin...</p>',
        'installation'  => '<ol><li>Upload ZIP...</li></ol>',
        'changelog'     => '<h3>Version 3.17.0</h3><ul><li>Feature X</li></ul>',
    ),
    'banners'           => array(
        'high' => 'https://example.com/banner-1544x500.png',
        'low'  => 'https://example.com/banner-772x250.png',
    ),
    'icons'             => array(
        '1x'   => 'https://example.com/icon-128x128.png',
        '2x'   => 'https://example.com/icon-256x256.png',
    ),
)
```

---

## 2. Download Package Entity

### Purpose
Tracks validation state of downloaded update packages before extraction.

### Source
- **Download URL**: From Plugin Metadata entity (`download_link`)
- **Expected Size**: From GitHub API response (`assets[0].size`)
- **Storage**: Transient validation state during update process
- **Filesystem**: Downloaded to WordPress temp directory

### Fields

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `source_url` | string | Yes | GitHub release asset URL | Valid HTTPS GitHub CDN URL |
| `expected_size` | int | Yes | Expected file size in bytes | Positive integer > 0 |
| `actual_size` | int | No | Downloaded file size in bytes | Set after download |
| `validation_status` | bool | No | Pass/fail validation result | Set after validation |
| `validation_errors` | array | No | Array of error messages | Populated on failure |
| `local_path` | string | No | Temporary file path | Set after download |
| `file_hash` | string | No | MD5 or SHA256 hash | For future integrity checks |

### Lifecycle

```
1. CREATE: Initialize when update starts
   ↓
2. DOWNLOAD: Download ZIP to temp directory
   ↓
3. VALIDATE_SIZE: Check file size (±5% tolerance)
   ↓ (if pass)
4. VALIDATE_ZIP: Verify ZIP format
   ↓ (if pass)
5. VALIDATED: Mark as validated, proceed to extraction
   ↓ (after update completes)
6. DELETE: Remove temp file and transient
   ↓ (if validation fails at any stage)
7. DELETE_IMMEDIATE: Remove invalid file immediately
```

### State Transitions

```
created → downloading → validating_size → validating_zip → validated → deleted
                              ↓                   ↓
                         validation_failed ← validation_failed
                              ↓
                          deleted_immediate
```

### Relationships

- **None** - Transient entity, no persistent relationships

### Validation Rules

1. **Source URL Validation**:
   - Must start with `https://`
   - Must match pattern: `https://github.com/ChoiceOMG/choice-uft/releases/download/v*/choice-uft-*.zip`
   - Must not contain query parameters or fragments

2. **File Size Validation**:
   - Tolerance: ±5% of expected size
   - Formula: `$expected_size * 0.95 <= $actual_size <= $expected_size * 1.05`
   - Reason: ZIP compression variance across platforms

3. **ZIP Format Validation**:
   - Use WordPress `unzip_file()` with dry-run test
   - Verify ZIP contains at least one file
   - Verify root directory exists

### Error Handling

| Error Scenario | Validation Error | Action |
|----------------|------------------|--------|
| File size outside tolerance | `file_size_mismatch` | Delete file, abort update |
| Invalid ZIP format | `invalid_zip_format` | Delete file, abort update |
| Download incomplete | `download_incomplete` | Delete file, allow retry |
| Network error | `network_error` | Keep file, allow retry |
| Disk full | `disk_full` | Delete file, abort update |

### Cleanup Strategy

**Immediate Cleanup**:
- Triggered on validation failure
- Deletes local file immediately
- Deletes transient state

**Scheduled Cleanup**:
- Daily WP-Cron job (`cuft_cleanup_orphaned_downloads`)
- Scans WordPress temp directory for `choice-uft-*.zip` files
- Deletes files older than 24 hours
- Purpose: Catch files from crashed/interrupted updates

### Example Instance

```php
array(
    'source_url'         => 'https://github.com/ChoiceOMG/choice-uft/releases/download/v3.17.0/choice-uft-v3.17.0.zip',
    'expected_size'      => 2621440, // 2.5 MB
    'actual_size'        => 2618324, // Within 5% tolerance
    'validation_status'  => true,
    'validation_errors'  => array(),
    'local_path'         => '/tmp/choice-uft-update-abc123.zip',
    'file_hash'          => 'd41d8cd98f00b204e9800998ecf8427e', // MD5
)
```

---

## 3. Backup Archive Entity

### Purpose
Provides rollback capability by backing up current plugin files before update.

### Source
- **Source Directory**: `/wp-content/plugins/choice-uft/`
- **Storage Location**: `/wp-content/uploads/cuft-backups/`
- **Filename Pattern**: `choice-uft-{VERSION}-backup.zip`

### Fields

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `source_version` | string | Yes | Version being backed up | Semver format |
| `backup_path` | string | Yes | Full path to ZIP file | Must exist in cuft-backups/ |
| `created_date` | string | Yes | ISO 8601 timestamp | Valid datetime |
| `file_size` | int | Yes | Backup ZIP size in bytes | Positive integer > 0 |
| `backup_status` | string | Yes | Current status | See status values |

### Status Values

- `created` - Backup successfully created, ready for use
- `restored` - Backup has been restored (update failed)
- `deleted` - Backup deleted (update succeeded)

### Lifecycle

```
1. CREATE: Before update starts
   - Check disk space available (need 2x plugin size)
   - Create /wp-content/uploads/cuft-backups/ if not exists
   - ZIP current plugin directory
   ↓
2. VERIFY: After creation
   - Verify ZIP file exists
   - Verify ZIP file size > 0
   - Verify ZIP is readable
   ↓
3. STANDBY: During update process
   - Backup remains on disk, untouched
   ↓ (if update succeeds)
4. DELETE: Immediately after successful update
   - Delete backup ZIP file
   - Remove from tracking
   ↓ (if update fails)
5. RESTORE: When update failure detected
   - Extract backup ZIP to plugin directory (with timeout)
   - Mark status as 'restored'
   - Keep backup file for debugging
```

### State Transitions

```
created → standby → deleted (success path)
            ↓
         restored (failure path, kept for debugging)
```

### Relationships

- **Links to Update Execution Context** via `source_version` field
- No foreign key constraints (filesystem artifact)

### Validation Rules

1. **Pre-Backup Validation**:
   - Source directory exists and is readable
   - Backup directory is writable
   - Sufficient disk space (2x source directory size)

2. **Post-Backup Validation**:
   - Backup file exists at expected path
   - Backup file size > 0 bytes
   - ZIP file passes `ZipArchive::open()` test

3. **Pre-Restore Validation**:
   - Backup file still exists
   - Backup file is readable
   - Plugin directory is writable

### Restoration Process

**Timeout Enforcement**: Hard limit of 10 seconds

```php
set_time_limit(10); // Hard timeout

$start_time = time();

// Extract backup
$result = unzip_file($backup_path, $plugin_dir);

$elapsed = time() - $start_time;

if ($elapsed >= 10) {
    // Timeout reached
    return WP_Error('restoration_timeout', 'Restoration exceeded 10 seconds. Please reinstall manually.');
}

if (is_wp_error($result)) {
    return $result; // Restoration failed
}

return true; // Restoration succeeded
```

**Timeout Behavior**:
- If timeout reached, abort restoration
- Display manual reinstall message with GitHub download link
- Log critical error to PHP error_log
- Leave plugin in potentially broken state (admin must manually reinstall)

### Error Handling

| Error Scenario | Error Code | Action |
|----------------|------------|--------|
| Disk space insufficient | `disk_full` | Abort update, show error |
| Backup directory not writable | `backup_dir_not_writable` | Abort update, show error |
| ZIP creation failed | `zip_create_failed` | Abort update, show error |
| Restoration timeout (>10s) | `restoration_timeout` | Abort restoration, show manual reinstall message |
| Restoration failed (other) | `restoration_failed` | Log critical error, show manual reinstall message |
| Backup file missing | `backup_missing` | Log critical error, show manual reinstall message |

### Storage Management

**Directory Structure**:
```
/wp-content/uploads/cuft-backups/
├── choice-uft-3.16.5-backup.zip
├── choice-uft-3.16.4-backup.zip (old, may exist if prev update failed)
└── .htaccess (deny direct access)
```

**Cleanup Strategy**:
- Immediate deletion on successful update (standard WordPress pattern)
- No retention period (not versioned backups)
- Old backups only remain if update failed (for debugging)

### Example Instance

```php
array(
    'source_version'  => '3.16.5',
    'backup_path'     => '/var/www/html/wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip',
    'created_date'    => '2025-10-11T14:30:00Z',
    'file_size'       => 2621440, // 2.5 MB
    'backup_status'   => 'created',
)
```

---

## 4. Update Execution Context Entity

### Purpose
Tracks update progress and metadata for logging, error reporting, and concurrent update detection.

### Source
- **User Context**: WordPress `wp_get_current_user()`
- **Trigger Location**: Detected from execution context
- **Storage**: Site transient (`cuft_update_context_{user_id}`)
- **TTL**: 1 hour (cleared on completion or expiry)

### Fields

| Field | Type | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `trigger_location` | string | Yes | Where update was triggered | See trigger values |
| `user_id` | int | Yes | WordPress user ID | Valid user ID |
| `user_display_name` | string | Yes | User's display name | For conflict messages |
| `started_timestamp` | string | Yes | ISO 8601 start time | Valid datetime |
| `completed_timestamp` | string | No | ISO 8601 completion time | Set on completion |
| `duration` | int | No | Seconds elapsed | Calculated on completion |
| `previous_version` | string | Yes | Version before update | Semver format |
| `target_version` | string | Yes | Version being installed | Semver format |
| `status` | string | Yes | Current status | See status values |
| `error_message` | string | No | Error description | Set on failure |
| `progress_percentage` | int | No | 0-100 for UI display | Optional progress tracking |

### Trigger Location Values

- `plugins_page` - Update initiated from `/wp-admin/plugins.php`
- `updates_page` - Update initiated from `/wp-admin/update-core.php`
- `wp_cli` - Update initiated via WP-CLI command
- `bulk_update` - Update initiated from bulk update interface
- `auto_update` - Future: automatic background update

### Status Values

- `pending` - Update queued, not yet started
- `downloading` - Downloading ZIP file from GitHub
- `extracting` - Extracting ZIP file
- `installing` - Moving files to plugin directory
- `complete` - Update completed successfully
- `failed` - Update failed (reason in `error_message`)
- `rolled_back` - Update failed and rollback completed

### Lifecycle

```
1. CREATE: When update starts
   - Detect trigger location
   - Store user context
   - Set status = 'pending'
   ↓
2. UPDATE: As update progresses
   - Update status through: downloading → extracting → installing
   - Optionally update progress_percentage
   ↓
3. COMPLETE: When update finishes
   - Set completed_timestamp
   - Calculate duration
   - Set status = 'complete' | 'failed' | 'rolled_back'
   - Log to update history (FR-009 from Feature 007)
   ↓
4. DELETE: After logging
   - Clear transient
   - Data persisted in update history log
```

### State Transitions

```
pending → downloading → extracting → installing → complete
            ↓              ↓            ↓
         failed ←---------←-------------←
            ↓
         rolled_back
```

### Relationships

- **Links to WordPress User** via `user_id` field
- **Links to Update History** via logging on completion
- **Links to Backup Archive** via `previous_version` field

### Concurrent Update Detection

```php
// Check for existing update in progress
$existing_context = get_site_transient('cuft_update_context_*');

if ($existing_context && in_array($existing_context['status'], ['pending', 'downloading', 'extracting', 'installing'])) {
    return new WP_Error(
        'update_in_progress',
        sprintf(
            __('Update already in progress by %s. Please wait.', 'choice-uft'),
            $existing_context['user_display_name']
        )
    );
}
```

### Validation Rules

1. **User Validation**:
   - User must have `update_plugins` capability
   - User ID must exist in WordPress database

2. **Version Validation**:
   - `previous_version` must match current `CUFT_VERSION` constant
   - `target_version` must be valid semver format
   - `target_version` must be different from `previous_version`

3. **Status Transitions**:
   - Can only transition to next status in sequence
   - Cannot transition backwards (except to 'failed')
   - Cannot transition from 'complete' to any other status

### Progress Percentage Mapping

| Status | Progress % |
|--------|-----------|
| `pending` | 0% |
| `downloading` | 25% |
| `extracting` | 50% |
| `installing` | 75% |
| `complete` | 100% |
| `failed` | Last known % |
| `rolled_back` | Last known % |

### Error Handling

All errors stored in `error_message` field with user-friendly descriptions:

```php
array(
    'status' => 'failed',
    'error_message' => 'Download verification failed: File size mismatch. Expected 2.5 MB, got 1.2 MB. Please try again.',
)
```

### Example Instance

```php
array(
    'trigger_location'      => 'plugins_page',
    'user_id'               => 1,
    'user_display_name'     => 'Admin User',
    'started_timestamp'     => '2025-10-11T14:30:00Z',
    'completed_timestamp'   => '2025-10-11T14:30:45Z',
    'duration'              => 45,
    'previous_version'      => '3.16.5',
    'target_version'        => '3.17.0',
    'status'                => 'complete',
    'error_message'         => null,
    'progress_percentage'   => 100,
)
```

---

## Entity Relationships Diagram

```
┌─────────────────────┐
│ Plugin Metadata     │ (Read-only DTO)
│ - version           │
│ - download_link     │
└─────────────────────┘
         │
         │ provides download URL
         ↓
┌─────────────────────┐
│ Download Package    │ (Transient)
│ - source_url        │
│ - validation_status │
└─────────────────────┘
         │
         │ validated, proceeds to update
         ↓
┌─────────────────────┐         ┌─────────────────────┐
│ Update Execution    │←-------→│ Backup Archive      │
│ Context             │  links   │ - source_version    │
│ - previous_version  │  via     │ - backup_path       │
│ - target_version    │  version │ - backup_status     │
│ - status            │          └─────────────────────┘
└─────────────────────┘
         │
         │ logs to (on completion)
         ↓
┌─────────────────────┐
│ Update History      │ (Persistent, from Feature 007)
│ (FIFO, last 5)      │
└─────────────────────┘
```

---

## Database Storage Summary

| Entity | Storage Method | Location | TTL/Retention |
|--------|---------------|----------|---------------|
| Plugin Metadata | Transient | `cuft_plugin_info` | 12 hours |
| Download Package | Transient | `cuft_download_validation_{hash}` | Until update completes |
| Backup Archive | Filesystem | `/wp-content/uploads/cuft-backups/` | Deleted on success |
| Update Execution Context | Site Transient | `cuft_update_context_{user_id}` | 1 hour |

**Note**: No custom database tables required. All data uses WordPress core storage mechanisms.

---

## Performance Considerations

### Transient Caching Strategy

1. **Plugin Metadata**: 12-hour cache reduces GitHub API calls
2. **Download Package**: Short-lived transient, deleted after update
3. **Update Context**: 1-hour TTL catches abandoned updates

### Filesystem Operations

1. **Backup Creation**: Target <10 seconds for 5 MB plugin
2. **Backup Restoration**: Hard timeout of 10 seconds
3. **Cleanup**: Daily cron job, low priority

### Memory Usage

- Plugin Metadata: ~50 KB (JSON serialized)
- Download Package: ~2 KB (validation state only, not file contents)
- Backup Archive: ~5 MB (filesystem storage)
- Update Context: ~1 KB (tracking data)

**Total Memory Footprint**: <100 KB transient memory + 5 MB filesystem

---

## Security Considerations

### Input Validation

- All URLs validated as HTTPS GitHub URLs
- All versions validated as semver format
- All file paths validated within WordPress directories

### Permission Checks

- Backup directory: 0755 (writable by WordPress)
- Backup files: 0644 (readable by WordPress)
- `.htaccess` in backup directory denies direct access

### Sensitive Data

- No passwords or API tokens stored in entities
- GitHub token (if used) stored separately, encrypted
- User IDs logged for accountability, not exposed in errors

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Implementation
