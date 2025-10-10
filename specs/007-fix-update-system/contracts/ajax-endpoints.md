# AJAX Endpoints Contract

**Feature**: 007-fix-update-system
**Date**: 2025-10-07

## Overview

This contract defines all AJAX endpoints for the update system, including request/response formats, error codes, and security requirements.

---

## Security Requirements (All Endpoints)

### Nonce Validation
- **Action**: `cuft_updater_nonce`
- **Parameter**: `nonce` (POST or GET)
- **Validation**: `wp_verify_nonce($nonce, 'cuft_updater_nonce')`
- **Failure Response**: HTTP 403 with `invalid_nonce` code

### Capability Check
- **Default Capability**: `update_plugins`
- **Settings Capability**: `manage_options`
- **Validation**: `current_user_can($capability)`
- **Failure Response**: HTTP 403 with `insufficient_permissions` code

### Response Format
```json
{
  "success": boolean,
  "data": object | array
}
```

---

## Endpoint 1: Check for Updates

**Action**: `cuft_check_update`
**Method**: POST
**Hook**: `wp_ajax_cuft_check_update`
**Capability**: `update_plugins`

### Request

```javascript
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=cuft_check_update
&nonce={nonce_value}
&force=true
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_check_update` |
| `nonce` | string | Yes | Security nonce |
| `force` | boolean | No | Force fresh check (ignore cache) |

### Success Response (HTTP 200)

```json
{
  "success": true,
  "data": {
    "update_available": true,
    "current_version": "3.16.2",
    "latest_version": "3.17.0",
    "last_check": "2025-10-07T14:30:00Z",
    "release_url": "https://github.com/ChoiceOMG/choice-uft/releases/tag/v3.17.0",
    "changelog": "- Added feature X\n- Fixed bug Y",
    "download_size": "2.5 MB",
    "published_at": "2025-10-06T10:00:00Z"
  }
}
```

### Error Responses

**Invalid Nonce (HTTP 403)**:
```json
{
  "success": false,
  "data": {
    "message": "Security check failed",
    "code": "invalid_nonce"
  }
}
```

**Check Failed (HTTP 500)**:
```json
{
  "success": false,
  "data": {
    "message": "Failed to check for updates",
    "code": "check_failed",
    "details": "GitHub API returned 404"
  }
}
```

**Network Error (HTTP 503)**:
```json
{
  "success": false,
  "data": {
    "message": "Network error occurred",
    "code": "network_error",
    "details": "Connection timeout after 30 seconds"
  }
}
```

### Performance Requirements
- **Target Response Time**: <500ms P95
- **Timeout**: 5 seconds (as per FR-008)
- **Caching**: Use transient cache, respect context-aware timing

---

## Endpoint 2: Perform Update

**Action**: `cuft_perform_update`
**Method**: POST
**Hook**: `wp_ajax_cuft_perform_update`
**Capability**: `update_plugins`

### Request

```javascript
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=cuft_perform_update
&nonce={nonce_value}
&version=latest
&backup=true
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_perform_update` |
| `nonce` | string | Yes | Security nonce |
| `version` | string | No | Target version (default: 'latest') |
| `backup` | boolean | No | Create backup before update (default: true) |

### Success Response (HTTP 200)

```json
{
  "success": true,
  "data": {
    "status": "started",
    "update_id": "update_1728345600",
    "message": "Update process started"
  }
}
```

### Error Responses

**Update Already in Progress (HTTP 409)**:
```json
{
  "success": false,
  "data": {
    "message": "Update already in progress",
    "code": "update_in_progress",
    "current_status": {
      "status": "downloading",
      "percentage": 25,
      "message": "Downloading update package...",
      "user_id": 2,
      "started_at": 1728345500
    }
  }
}
```

**Update Failed (HTTP 500)**:
```json
{
  "success": false,
  "data": {
    "message": "Failed to start update",
    "code": "update_failed",
    "details": "Package download URL invalid"
  }
}
```

### Asynchronous Behavior
- Returns immediately after scheduling update
- Actual update runs via `wp_cron` hook: `cuft_process_update`
- Use `cuft_update_status` endpoint to poll progress

---

## Endpoint 3: Get Update Status

**Action**: `cuft_update_status`
**Method**: GET
**Hook**: `wp_ajax_cuft_update_status`
**Capability**: `update_plugins`

### Request

```javascript
GET /wp-admin/admin-ajax.php?action=cuft_update_status&nonce={nonce_value}&update_id={update_id}
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_update_status` |
| `nonce` | string | Yes | Security nonce |
| `update_id` | string | No | Specific update ID to check |

### Success Response (HTTP 200)

**In Progress**:
```json
{
  "success": true,
  "data": {
    "status": "downloading",
    "percentage": 35,
    "message": "Downloading update package... (1.5 MB / 2.5 MB)",
    "started_at": 1728345600,
    "elapsed_seconds": 15,
    "user_id": 1,
    "version_from": "3.16.2",
    "version_to": "3.17.0"
  }
}
```

**Completed**:
```json
{
  "success": true,
  "data": {
    "status": "complete",
    "percentage": 100,
    "message": "Update completed successfully!",
    "started_at": 1728345600,
    "completed_at": 1728345645,
    "elapsed_seconds": 45,
    "version_from": "3.16.2",
    "version_to": "3.17.0"
  }
}
```

**Failed**:
```json
{
  "success": true,
  "data": {
    "status": "failed",
    "percentage": 0,
    "message": "Update failed: File extraction error",
    "error_message": "ZIP file corrupt or incomplete",
    "rollback_status": "complete",
    "started_at": 1728345600,
    "failed_at": 1728345630
  }
}
```

**No Update in Progress**:
```json
{
  "success": true,
  "data": {
    "status": "idle",
    "message": "No update currently in progress",
    "update_available": true,
    "latest_version": "3.17.0"
  }
}
```

### Error Responses

**Status Check Failed (HTTP 500)**:
```json
{
  "success": false,
  "data": {
    "message": "Failed to get update status",
    "code": "status_failed",
    "details": "Transient data corrupted"
  }
}
```

### Performance Requirements
- **Target Response Time**: <100ms P95 (lightweight read-only)
- **Cache Hit**: Should serve from transient, not recompute
- **Polling Frequency**: Client should poll every 1-2 seconds during update

---

## Endpoint 4: Rollback Update

**Action**: `cuft_rollback_update`
**Method**: POST
**Hook**: `wp_ajax_cuft_rollback_update`
**Capability**: `update_plugins`

### Request

```javascript
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=cuft_rollback_update
&nonce={nonce_value}
&update_id={update_id}
&reason=User%20requested
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_rollback_update` |
| `nonce` | string | Yes | Security nonce |
| `update_id` | string | No | Update ID to rollback |
| `reason` | string | No | Reason for rollback |

### Success Response (HTTP 200)

```json
{
  "success": true,
  "data": {
    "status": "rolled_back",
    "message": "Previous version has been restored",
    "restored_version": "3.16.2",
    "backup_used": "backup_1728345500.zip",
    "elapsed_seconds": 20
  }
}
```

### Error Responses

**Rollback Failed (HTTP 500)**:
```json
{
  "success": false,
  "data": {
    "message": "Failed to rollback update",
    "code": "rollback_failed",
    "details": "No backup file found"
  }
}
```

---

## Endpoint 5: Get Update History

**Action**: `cuft_update_history`
**Method**: GET
**Hook**: `wp_ajax_cuft_update_history`
**Capability**: `update_plugins`

### Request

```javascript
GET /wp-admin/admin-ajax.php?action=cuft_update_history&nonce={nonce_value}&limit=5&offset=0
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_update_history` |
| `nonce` | string | Yes | Security nonce |
| `limit` | integer | No | Number of entries (default: 5, max: 10) |
| `offset` | integer | No | Pagination offset (default: 0) |

### Success Response (HTTP 200)

```json
{
  "success": true,
  "data": {
    "total": 5,
    "limit": 5,
    "offset": 0,
    "entries": [
      {
        "id": 5,
        "timestamp": "2025-10-07T14:45:00Z",
        "action": "complete",
        "status": "success",
        "version_from": "3.16.2",
        "version_to": "3.17.0",
        "user_id": 1,
        "user_display_name": "Admin",
        "duration": 45,
        "error_message": null
      },
      {
        "id": 4,
        "timestamp": "2025-10-06T10:30:00Z",
        "action": "rollback",
        "status": "success",
        "version_from": "3.16.1",
        "version_to": "3.16.0",
        "user_id": 1,
        "user_display_name": "Admin",
        "duration": 20,
        "error_message": null
      }
    ]
  }
}
```

### Error Responses

**History Failed (HTTP 500)**:
```json
{
  "success": false,
  "data": {
    "message": "Failed to get update history",
    "code": "history_failed",
    "details": "Database query error"
  }
}
```

---

## Endpoint 6: Dismiss Update Notice

**Action**: `cuft_dismiss_update_notice`
**Method**: POST
**Hook**: `wp_ajax_cuft_dismiss_update_notice`
**Capability**: `update_plugins`

### Request

```javascript
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=cuft_dismiss_update_notice
&nonce={nonce_value}
&version=3.17.0
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `cuft_dismiss_update_notice` |
| `nonce` | string | Yes | Security nonce |
| `version` | string | Yes | Version of notice to dismiss |

### Success Response (HTTP 200)

```json
{
  "success": true,
  "data": {
    "message": "Notice dismissed successfully",
    "version": "3.17.0"
  }
}
```

### Error Responses

**Missing Version (HTTP 400)**:
```json
{
  "success": false,
  "data": {
    "message": "Version parameter is required",
    "code": "missing_version"
  }
}
```

---

## Error Code Reference

| Code | HTTP Status | Description | Recovery Action |
|------|-------------|-------------|-----------------|
| `invalid_nonce` | 403 | Nonce validation failed | Refresh page, get new nonce |
| `insufficient_permissions` | 403 | User lacks capability | Request admin access |
| `update_in_progress` | 409 | Concurrent update detected | Wait for completion or cancel |
| `update_failed` | 500 | Update process failed | Check logs, retry update |
| `check_failed` | 500 | Update check failed | Retry, check network |
| `network_error` | 503 | API unreachable | Wait and retry |
| `status_failed` | 500 | Status retrieval failed | Refresh page |
| `rollback_failed` | 500 | Rollback failed | Manual restoration needed |
| `history_failed` | 500 | History query failed | Refresh page |
| `missing_version` | 400 | Required param missing | Include version parameter |

---

## Implementation Checklist

### Security
- [ ] All endpoints validate nonce with `cuft_updater_nonce` action
- [ ] All endpoints check user capability
- [ ] All input parameters sanitized
- [ ] All output escaped for XSS prevention
- [ ] Rate limiting implemented (prevent brute force)

### Error Handling
- [ ] All errors return consistent JSON format
- [ ] HTTP status codes match error severity
- [ ] Error messages are user-friendly
- [ ] Technical details only in debug mode
- [ ] Errors logged for debugging

### Performance
- [ ] Response times meet targets (<500ms check, <100ms status)
- [ ] Caching implemented for read-only endpoints
- [ ] Database queries optimized
- [ ] Timeout protection (5 second max)
- [ ] No N+1 query issues

### Testing
- [ ] Unit tests for all endpoints
- [ ] Integration tests for happy path
- [ ] Error case tests (invalid nonce, missing params)
- [ ] Concurrent request tests
- [ ] Load testing (100 concurrent requests)

---

**Last Updated**: 2025-10-07
**Status**: Ready for implementation
