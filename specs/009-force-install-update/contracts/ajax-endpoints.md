# AJAX Endpoint Contracts: Force Install Update

**Feature**: 009-force-install-update
**Date**: 2025-10-12
**Status**: Complete

---

## Overview

This document defines the WordPress AJAX endpoint contracts for the Force Install Update feature. All endpoints require:
- Valid WordPress nonce (`cuft_force_update`)
- User capability: `update_plugins`
- DISALLOW_FILE_MODS check (for force_reinstall only)

**Base URL**: `/wp-admin/admin-ajax.php`
**Method**: POST
**Content-Type**: `application/x-www-form-urlencoded`

---

## Endpoint 1: Check for Updates

### Request

**Action**: `cuft_check_updates`

**Parameters**:
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | String | Yes | Must be `"cuft_check_updates"` |
| `nonce` | String | Yes | WordPress nonce generated with `wp_create_nonce('cuft_force_update')` |

**Example Request**:
```http
POST /wp-admin/admin-ajax.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

action=cuft_check_updates&nonce=a1b2c3d4e5
```

### Success Response

**HTTP Status**: 200 OK
**Content-Type**: `application/json`

**Response Body**:
```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "3.19.0",
    "update_available": true,
    "release_date": "2025-10-13",
    "changelog_summary": "Bug fixes and performance improvements",
    "download_url": "https://github.com/ChoiceOMG/choice-uft/releases/download/v3.19.0/choice-uft-v3.19.0.zip",
    "last_check": 1728729600,
    "message": "A new version (3.19.0) is available!"
  }
}
```

**Response when no update available**:
```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "3.18.0",
    "update_available": false,
    "last_check": 1728729600,
    "message": "Plugin is up to date (version 3.18.0)"
  }
}
```

### Error Responses

**Invalid Nonce** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Insufficient Permissions** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "insufficient_permissions",
    "message": "You do not have permission to check for updates."
  }
}
```

**GitHub API Timeout** (504 Gateway Timeout):
```json
{
  "success": false,
  "data": {
    "error_code": "github_timeout",
    "message": "GitHub API did not respond within 5 seconds. Please try again later.",
    "last_known_version": "3.19.0",
    "last_check": 1728729300
  }
}
```

**GitHub Rate Limit** (429 Too Many Requests):
```json
{
  "success": false,
  "data": {
    "error_code": "rate_limited",
    "message": "GitHub API rate limit exceeded. Showing cached result.",
    "installed_version": "3.18.0",
    "last_known_version": "3.19.0",
    "update_available": true,
    "last_check": 1728729300,
    "cached": true
  }
}
```

**Concurrent Operation** (409 Conflict):
```json
{
  "success": false,
  "data": {
    "error_code": "operation_in_progress",
    "message": "Another update operation is already in progress. Please wait."
  }
}
```

---

## Endpoint 2: Force Reinstall Latest Version

### Request

**Action**: `cuft_force_reinstall`

**Parameters**:
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | String | Yes | Must be `"cuft_force_reinstall"` |
| `nonce` | String | Yes | WordPress nonce generated with `wp_create_nonce('cuft_force_update')` |

**Example Request**:
```http
POST /wp-admin/admin-ajax.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

action=cuft_force_reinstall&nonce=a1b2c3d4e5
```

### Success Response

**HTTP Status**: 200 OK
**Content-Type**: `application/json`

**Response Body**:
```json
{
  "success": true,
  "data": {
    "message": "Plugin successfully reinstalled to version 3.19.0",
    "source_version": "3.18.0",
    "target_version": "3.19.0",
    "duration_seconds": 35,
    "backup_location": "/wp-content/uploads/cuft-backups/choice-uft-3.18.0-backup-1728729600.zip",
    "backup_deleted": true
  }
}
```

### Error Responses

**Invalid Nonce** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Insufficient Permissions** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "insufficient_permissions",
    "message": "You do not have permission to reinstall plugins."
  }
}
```

**File Modifications Disabled** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "file_mods_disabled",
    "message": "File modifications are disabled on this site (DISALLOW_FILE_MODS constant)."
  }
}
```

**Insufficient Disk Space** (507 Insufficient Storage):
```json
{
  "success": false,
  "data": {
    "error_code": "insufficient_disk_space",
    "message": "Insufficient disk space to create backup. Free at least 15 MB and try again.",
    "required_space_mb": 15,
    "available_space_mb": 10
  }
}
```

**Concurrent Operation** (409 Conflict):
```json
{
  "success": false,
  "data": {
    "error_code": "operation_in_progress",
    "message": "Another update operation is already in progress. Please wait."
  }
}
```

**Backup Failed** (500 Internal Server Error):
```json
{
  "success": false,
  "data": {
    "error_code": "backup_failed",
    "message": "Failed to create backup before reinstalling. Operation aborted.",
    "details": "Unable to write to /wp-content/uploads/cuft-backups/: Permission denied"
  }
}
```

**Download Failed** (502 Bad Gateway):
```json
{
  "success": false,
  "data": {
    "error_code": "download_failed",
    "message": "Failed to download latest version from GitHub.",
    "github_url": "https://github.com/ChoiceOMG/choice-uft/releases/download/v3.19.0/choice-uft-v3.19.0.zip",
    "http_status": 404
  }
}
```

**Validation Failed** (422 Unprocessable Entity):
```json
{
  "success": false,
  "data": {
    "error_code": "validation_failed",
    "message": "Downloaded file failed integrity validation.",
    "details": "Expected size: 5242880 bytes, actual size: 5100000 bytes"
  }
}
```

**Installation Failed with Rollback** (500 Internal Server Error):
```json
{
  "success": false,
  "data": {
    "error_code": "installation_failed",
    "message": "Installation failed. Restored previous version (3.18.0) from backup.",
    "source_version": "3.18.0",
    "target_version": "3.19.0",
    "rollback_successful": true,
    "error_details": "Unable to extract ZIP file: corrupted archive"
  }
}
```

**Timeout** (504 Gateway Timeout):
```json
{
  "success": false,
  "data": {
    "error_code": "operation_timeout",
    "message": "Operation exceeded 60 second timeout. Plugin remains at version 3.18.0.",
    "elapsed_seconds": 60,
    "last_stage": "downloading"
  }
}
```

---

## Endpoint 3: Get Update History

### Request

**Action**: `cuft_get_update_history`

**Parameters**:
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | String | Yes | Must be `"cuft_get_update_history"` |
| `nonce` | String | Yes | WordPress nonce generated with `wp_create_nonce('cuft_force_update')` |

**Example Request**:
```http
POST /wp-admin/admin-ajax.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

action=cuft_get_update_history&nonce=a1b2c3d4e5
```

### Success Response

**HTTP Status**: 200 OK
**Content-Type**: `application/json`

**Response Body**:
```json
{
  "success": true,
  "data": {
    "history": [
      {
        "operation_type": "force_reinstall",
        "user_display_name": "Admin",
        "timestamp": 1728729635,
        "timestamp_formatted": "2025-10-12 14:40:35",
        "status": "complete",
        "details": {
          "source_version": "3.18.0",
          "target_version": "3.19.0",
          "duration_seconds": 35
        }
      },
      {
        "operation_type": "manual_check",
        "user_display_name": "Admin",
        "timestamp": 1728729600,
        "timestamp_formatted": "2025-10-12 14:40:00",
        "status": "complete",
        "details": {
          "installed_version": "3.18.0",
          "latest_version": "3.19.0",
          "update_available": true
        }
      }
    ],
    "count": 2,
    "max_entries": 5
  }
}
```

**Response when no history**:
```json
{
  "success": true,
  "data": {
    "history": [],
    "count": 0,
    "max_entries": 5,
    "message": "No update operations in history yet."
  }
}
```

### Error Responses

**Invalid Nonce** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Insufficient Permissions** (403 Forbidden):
```json
{
  "success": false,
  "data": {
    "error_code": "insufficient_permissions",
    "message": "You do not have permission to view update history."
  }
}
```

---

## Response Field Definitions

### Common Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | Boolean | `true` for successful operations, `false` for errors |
| `data` | Object | Response payload (structure varies by endpoint) |
| `data.error_code` | String | Machine-readable error identifier (only in error responses) |
| `data.message` | String | Human-readable message for display in admin UI |

### Version Fields

| Field | Type | Format | Description |
|-------|------|--------|-------------|
| `installed_version` | String | Semver (e.g., "3.18.0") | Currently installed plugin version |
| `latest_version` | String | Semver (e.g., "3.19.0") | Latest version available on GitHub |
| `source_version` | String | Semver | Version before force reinstall |
| `target_version` | String | Semver | Version after force reinstall |

### Timestamp Fields

| Field | Type | Format | Description |
|-------|------|--------|-------------|
| `last_check` | Integer | Unix timestamp | When last update check was performed |
| `timestamp` | Integer | Unix timestamp | When operation completed |
| `timestamp_formatted` | String | "YYYY-MM-DD HH:MM:SS" | Human-readable timestamp |

---

## Security Validation Flow

### Nonce Validation

```php
// Generate nonce (in admin page render)
$nonce = wp_create_nonce( 'cuft_force_update' );

// Validate nonce (in AJAX handler)
if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' ) ) {
    wp_send_json_error( array(
        'error_code' => 'invalid_nonce',
        'message' => 'Security check failed. Please refresh the page and try again.'
    ), 403 );
}
```

### Capability Check

```php
if ( ! current_user_can( 'update_plugins' ) ) {
    wp_send_json_error( array(
        'error_code' => 'insufficient_permissions',
        'message' => 'You do not have permission to update plugins.'
    ), 403 );
}
```

### DISALLOW_FILE_MODS Check (force_reinstall only)

```php
if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
    wp_send_json_error( array(
        'error_code' => 'file_mods_disabled',
        'message' => 'File modifications are disabled on this site (DISALLOW_FILE_MODS constant).'
    ), 403 );
}
```

---

## Error Code Reference

| Error Code | HTTP Status | Description | User Action |
|------------|-------------|-------------|-------------|
| `invalid_nonce` | 403 | CSRF token invalid or expired | Refresh page, try again |
| `insufficient_permissions` | 403 | User lacks `update_plugins` capability | Contact site administrator |
| `file_mods_disabled` | 403 | DISALLOW_FILE_MODS constant set | Cannot force reinstall on this site |
| `insufficient_disk_space` | 507 | Not enough space for backup + download | Free up disk space |
| `operation_in_progress` | 409 | Another admin is running an update | Wait for operation to complete |
| `github_timeout` | 504 | GitHub API did not respond within 5s | Try again later |
| `rate_limited` | 429 | GitHub API rate limit exceeded | Wait 1 hour, or view cached result |
| `backup_failed` | 500 | Unable to create backup ZIP | Check filesystem permissions |
| `download_failed` | 502 | Unable to download from GitHub | Check network, try again |
| `validation_failed` | 422 | Downloaded ZIP failed integrity check | Try again (GitHub may have served partial file) |
| `installation_failed` | 500 | Installation failed, rollback succeeded | Check error logs, report issue |
| `operation_timeout` | 504 | Operation exceeded 60s timeout | Manual installation may be required |

---

## Rate Limiting

**GitHub API Rate Limit**: 60 requests/hour (unauthenticated)

**Mitigation Strategy**:
- Cache update check results for 5 minutes (transient)
- Return cached data when rate limited (with `cached: true` flag)
- Display "Last checked: X minutes ago" in UI

**No Internal Rate Limiting**: WordPress nonce validation provides sufficient protection against rapid repeated requests.

---

## Testing Contract Compliance

### Test Cases for `cuft_check_updates`

1. **Valid request with update available** ’ 200 OK, `update_available: true`
2. **Valid request with no update** ’ 200 OK, `update_available: false`
3. **Invalid nonce** ’ 403 Forbidden, `error_code: invalid_nonce`
4. **Non-admin user** ’ 403 Forbidden, `error_code: insufficient_permissions`
5. **GitHub API timeout** ’ 504 Gateway Timeout, `error_code: github_timeout`
6. **GitHub rate limited** ’ 429 Too Many Requests, `error_code: rate_limited`, `cached: true`

### Test Cases for `cuft_force_reinstall`

1. **Valid request with sufficient space** ’ 200 OK, `message: "Plugin successfully reinstalled"`
2. **Invalid nonce** ’ 403 Forbidden, `error_code: invalid_nonce`
3. **Non-admin user** ’ 403 Forbidden, `error_code: insufficient_permissions`
4. **DISALLOW_FILE_MODS enabled** ’ 403 Forbidden, `error_code: file_mods_disabled`
5. **Insufficient disk space** ’ 507 Insufficient Storage, `error_code: insufficient_disk_space`
6. **Concurrent operation** ’ 409 Conflict, `error_code: operation_in_progress`
7. **Backup creation fails** ’ 500 Internal Server Error, `error_code: backup_failed`
8. **Installation fails, rollback succeeds** ’ 500 Internal Server Error, `rollback_successful: true`
9. **Operation exceeds 60s timeout** ’ 504 Gateway Timeout, `error_code: operation_timeout`

### Test Cases for `cuft_get_update_history`

1. **Valid request with history** ’ 200 OK, `history: [...]`, `count > 0`
2. **Valid request with no history** ’ 200 OK, `history: []`, `count: 0`
3. **Invalid nonce** ’ 403 Forbidden, `error_code: invalid_nonce`
4. **Non-admin user** ’ 403 Forbidden, `error_code: insufficient_permissions`

---

## Implementation Checklist

- [ ] All endpoints validate nonce using `wp_verify_nonce()`
- [ ] All endpoints check `update_plugins` capability
- [ ] Force reinstall checks DISALLOW_FILE_MODS constant
- [ ] All success responses use `wp_send_json_success()`
- [ ] All error responses use `wp_send_json_error()` with appropriate HTTP status
- [ ] All error responses include `error_code` and `message` fields
- [ ] All version strings validated as semver format
- [ ] All timestamps are Unix timestamps (integer seconds)
- [ ] Transient lock acquired before force reinstall operations
- [ ] Transient lock released after operations complete (success or failure)
- [ ] Update history logged for all operations (success and failure)

---

**Contract Version**: 1.0
**Last Updated**: 2025-10-12
