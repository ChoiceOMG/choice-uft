# Data Model: Force Install Update

**Feature**: 009-force-install-update
**Date**: 2025-10-12
**Status**: Complete

## Entities

### 1. Update Check Request

**Lifecycle**: Ephemeral (not persisted beyond request scope)

**Purpose**: Represents a manual update check triggered by an administrator via the "Check for Updates" button.

**Attributes**:
| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| `timestamp` | Unix timestamp (integer) | Yes | When the check was initiated |
| `user_id` | Integer | Yes | WordPress user ID who triggered check |
| `github_response` | Array | No | Response from GitHub API (null if error) |
| `status` | Enum | Yes | Current status: `pending`, `success`, `error`, `timeout` |
| `error_message` | String | No | Human-readable error message (if status=error) |

**State Transitions**:
```
pending → success (GitHub API returns version info)
pending → timeout (5 seconds elapsed without response)
pending → error (GitHub API returns error or rate limit)
```

**Storage**: Memory only (not persisted)

**Example**:
```php
array(
    'timestamp' => 1728729600,
    'user_id' => 1,
    'github_response' => array(
        'version' => '3.19.0',
        'release_date' => '2025-10-13',
        'changelog_summary' => 'Bug fixes and performance improvements',
        'download_url' => 'https://github.com/ChoiceOMG/choice-uft/releases/download/v3.19.0/choice-uft-v3.19.0.zip'
    ),
    'status' => 'success',
    'error_message' => null
)
```

---

### 2. Force Reinstall Operation

**Lifecycle**: Created at operation start, logged to update history on completion

**Purpose**: Represents a force reinstall attempt with full state tracking for recovery and auditing.

**Attributes**:
| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| `source_version` | String | Yes | Current installed version (e.g., "3.18.0") |
| `target_version` | String | Yes | GitHub latest version to install |
| `backup_location` | String | No | Filesystem path to backup ZIP (null if backup failed) |
| `status` | Enum | Yes | Current operation status (see below) |
| `error_details` | Array | No | Structured error information (code, message, context) |
| `started_at` | Unix timestamp | Yes | When operation began |
| `completed_at` | Unix timestamp | No | When operation finished (null if in progress) |

**Status Values**:
- `pending`: Operation queued, not started
- `backup_created`: Backup ZIP created successfully
- `downloading`: Downloading latest release from GitHub
- `validating`: Validating downloaded ZIP (size, integrity)
- `installing`: Extracting and installing new version
- `success`: Installation completed successfully
- `failed`: Operation failed (details in error_details)
- `rolled_back`: Failed installation, restored from backup

**State Transitions**:
```
pending → backup_created → downloading → validating → installing → success
              ↓                ↓              ↓            ↓
           failed          failed         failed      rolled_back
```

**Storage**: Logged to `cuft_update_log` WordPress option (FIFO, last 5 entries)

**Example**:
```php
array(
    'source_version' => '3.18.0',
    'target_version' => '3.19.0',
    'backup_location' => '/wp-content/uploads/cuft-backups/choice-uft-3.18.0-backup-1728729600.zip',
    'status' => 'success',
    'error_details' => null,
    'started_at' => 1728729600,
    'completed_at' => 1728729635
)
```

---

### 3. Plugin Installation State

**Lifecycle**: Cached in transient, refreshed on manual checks

**Purpose**: Represents the current state of the plugin installation and update availability.

**Attributes**:
| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| `installed_version` | String | Yes | Current installed version from CUFT_VERSION constant |
| `github_latest_version` | String | Yes | Latest version from GitHub Releases API |
| `last_check_timestamp` | Unix timestamp | Yes | When latest version was last fetched |
| `update_available` | Boolean | Yes | True if github_latest > installed_version |
| `cache_ttl` | Integer | Yes | Seconds until cache expires (300 = 5 minutes) |

**Validation Rules**:
- `github_latest_version` must be valid semver format (e.g., "3.19.0")
- `last_check_timestamp` must be within cache_ttl seconds for cache to be valid
- `update_available` = `version_compare(github_latest_version, installed_version) > 0`

**Storage**: WordPress transient `cuft_plugin_installation_state` (5-minute TTL)

**Example**:
```php
array(
    'installed_version' => '3.18.0',
    'github_latest_version' => '3.19.0',
    'last_check_timestamp' => 1728729600,
    'update_available' => true,
    'cache_ttl' => 300
)
```

---

### 4. Update History Entry

**Lifecycle**: Persistent (stored in `cuft_update_log` option, FIFO retention)

**Purpose**: Audit log of all manual update operations with full context for troubleshooting.

**Attributes**:
| Attribute | Type | Required | Description |
|-----------|------|----------|-------------|
| `operation_type` | Enum | Yes | Type of operation: `manual_check`, `force_reinstall` |
| `trigger_location` | String | Yes | Where operation was triggered (always 'force_update_button') |
| `user_id` | Integer | Yes | WordPress user ID who performed operation |
| `user_display_name` | String | Yes | Display name of user (for admin UI) |
| `timestamp` | Unix timestamp | Yes | When operation completed |
| `status` | Enum | Yes | Operation result: `complete`, `failed` |
| `details` | Array | Yes | Operation-specific details (versions, errors, etc.) |

**Details Structure (manual_check)**:
```php
array(
    'installed_version' => '3.18.0',
    'latest_version' => '3.19.0',
    'update_available' => true,
    'error_message' => null // or string if failed
)
```

**Details Structure (force_reinstall)**:
```php
array(
    'source_version' => '3.18.0',
    'target_version' => '3.19.0',
    'backup_location' => '/uploads/cuft-backups/...',
    'duration_seconds' => 35,
    'error_code' => null, // or string if failed
    'error_message' => null
)
```

**Storage**: WordPress option `cuft_update_log` (array, FIFO, max 5 entries)

**Example**:
```php
array(
    'operation_type' => 'force_reinstall',
    'trigger_location' => 'force_update_button',
    'user_id' => 1,
    'user_display_name' => 'Admin',
    'timestamp' => 1728729635,
    'status' => 'complete',
    'details' => array(
        'source_version' => '3.18.0',
        'target_version' => '3.19.0',
        'backup_location' => '/uploads/cuft-backups/choice-uft-3.18.0-backup-1728729600.zip',
        'duration_seconds' => 35,
        'error_code' => null,
        'error_message' => null
    )
)
```

---

## Entity Relationships

```
Update Check Request
    ↓ (creates)
Plugin Installation State (cached in transient)
    ↓ (logged to)
Update History Entry (persisted)

Force Reinstall Operation
    ↓ (creates)
Backup File (filesystem: /wp-content/uploads/cuft-backups/)
    ↓ (logged to)
Update History Entry (persisted)
```

## Data Flow

### Update Check Flow

1. User clicks "Check for Updates" button
2. `Update Check Request` created in memory (`status=pending`)
3. GitHub API queried (5s timeout)
4. `Update Check Request` status updated (`success`, `timeout`, or `error`)
5. `Plugin Installation State` cached in transient (5-minute TTL)
6. `Update History Entry` created with `operation_type=manual_check`
7. AJAX response sent to UI

### Force Reinstall Flow

1. User clicks "Force Reinstall Latest Version" button
2. `Force Reinstall Operation` created (`status=pending`)
3. Disk space validated (3x plugin size)
4. Transient lock acquired (`cuft_force_update_lock`)
5. Backup created → `status=backup_created`, `backup_location` set
6. GitHub release downloaded → `status=downloading`
7. ZIP validated (size, integrity) → `status=validating`
8. Installation performed → `status=installing`
9. Success: `status=success`, backup deleted, lock released
10. Failure: `status=failed` or `rolled_back`, backup restored if possible
11. `Update History Entry` created with `operation_type=force_reinstall`
12. AJAX response sent to UI

---

## Storage Locations

| Entity | Storage Mechanism | Location | TTL/Retention |
|--------|-------------------|----------|---------------|
| Update Check Request | Memory only | PHP variable | Request duration |
| Force Reinstall Operation | Memory + History | PHP variable → `cuft_update_log` option | Until completion → FIFO (5 entries) |
| Plugin Installation State | Transient | `cuft_plugin_installation_state` | 5 minutes |
| Update History Entry | WordPress Option | `cuft_update_log` option | FIFO (5 entries) |
| Backup File | Filesystem | `/wp-content/uploads/cuft-backups/` | Deleted after successful install |

---

## Data Validation

### Version Format Validation
- Must match semver pattern: `\d+\.\d+\.\d+` (e.g., "3.18.0")
- Comparison using PHP `version_compare()` function
- No prerelease suffixes allowed (-alpha, -beta, -rc)

### Path Validation
- Backup paths must be within `/wp-content/uploads/cuft-backups/`
- No directory traversal: reject paths containing `..`
- Filename pattern: `choice-uft-{VERSION}-backup-{TIMESTAMP}.zip`

### Timestamp Validation
- Unix timestamps (integer seconds since epoch)
- Must be positive integer
- Must be <= current time (no future timestamps)

### Status Enum Validation
- All status values predefined in class constants
- Invalid status values rejected with `WP_Error`
- State transitions validated before status changes

---

## Performance Considerations

**Transient Operations**: O(1) lookups via WordPress object cache
**History Log Writes**: O(n) where n=5 (FIFO array operations)
**Disk Space Check**: O(n) where n=number of files in plugin directory (~500 files = <100ms)
**Version Comparison**: O(1) string comparison

**Optimization Strategy**:
- Cache Plugin Installation State for 5 minutes (reduce GitHub API calls)
- Use transient-based locking (atomic, no race conditions)
- FIFO history limit (prevent unbounded growth)
- Immediate cleanup of backup files (prevent disk space issues)

---

## Migration Notes

**From Feature 007/008**: Reuses existing `cuft_update_log` option structure. No schema migration required.

**New Storage**:
- Transient: `cuft_plugin_installation_state` (auto-created)
- Transient: `cuft_force_update_lock` (auto-created, auto-expires)

**No Database Tables Required**: All data stored in WordPress options/transients.
