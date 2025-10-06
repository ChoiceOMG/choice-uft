# AJAX API Contracts: One-Click Automated Update

**Base URL**: `/wp-admin/admin-ajax.php`
**Authentication**: WordPress nonce + capability check
**Format**: URL-encoded POST requests, JSON responses

## Endpoints

### 1. Check for Updates

**Endpoint**: `POST /wp-admin/admin-ajax.php?action=cuft_check_update`

**Purpose**: Check GitHub for latest release and compare with current version

**Request Parameters**:
```
action: cuft_check_update
nonce: {WordPress nonce}
force: true|false (optional, bypass cache)
```

**Capability Required**: `update_plugins`

**Success Response (200)**:
```json
{
    "success": true,
    "data": {
        "current_version": "3.14.0",
        "latest_version": "3.15.0",
        "update_available": true,
        "changelog": "### Version 3.15.0\n- New feature...",
        "download_size": "2.5 MB",
        "published_date": "2025-10-01T12:00:00Z",
        "last_check": "2025-10-03T10:30:00Z"
    }
}
```

**No Update Response (200)**:
```json
{
    "success": true,
    "data": {
        "current_version": "3.14.0",
        "latest_version": "3.14.0",
        "update_available": false,
        "message": "You have the latest version",
        "last_check": "2025-10-03T10:30:00Z"
    }
}
```

**Error Response (403)**:
```json
{
    "success": false,
    "data": {
        "message": "Security check failed",
        "code": "invalid_nonce"
    }
}
```

**Error Response (500)**:
```json
{
    "success": false,
    "data": {
        "message": "Failed to check for updates",
        "code": "github_api_error",
        "details": "Rate limit exceeded"
    }
}
```

### 2. Perform Update

**Endpoint**: `POST /wp-admin/admin-ajax.php?action=cuft_perform_update`

**Purpose**: Download and install the latest version with automatic rollback on failure

**Request Parameters**:
```
action: cuft_perform_update
nonce: {WordPress nonce}
version: {target version to install}
backup: true|false (optional, default true)
```

**Capability Required**: `update_plugins`

**Initial Response (200)**:
```json
{
    "success": true,
    "data": {
        "status": "started",
        "update_id": "update_1234567890",
        "message": "Update process started"
    }
}
```

**Error Response (409)**:
```json
{
    "success": false,
    "data": {
        "message": "Update already in progress",
        "code": "update_in_progress",
        "current_status": "downloading"
    }
}
```

### 3. Get Update Status

**Endpoint**: `GET /wp-admin/admin-ajax.php?action=cuft_update_status`

**Purpose**: Get current status of ongoing update operation

**Request Parameters**:
```
action: cuft_update_status
nonce: {WordPress nonce}
update_id: {update ID from perform_update} (optional)
```

**Capability Required**: `update_plugins`

**In-Progress Response (200)**:
```json
{
    "success": true,
    "data": {
        "status": "downloading",
        "percentage": 45,
        "message": "Downloading update package...",
        "started_at": "2025-10-03T10:35:00Z",
        "elapsed_seconds": 15
    }
}
```

**Complete Response (200)**:
```json
{
    "success": true,
    "data": {
        "status": "complete",
        "percentage": 100,
        "message": "Update completed successfully",
        "old_version": "3.14.0",
        "new_version": "3.15.0",
        "completed_at": "2025-10-03T10:36:30Z",
        "total_time_seconds": 90
    }
}
```

**Failed Response (200)**:
```json
{
    "success": true,
    "data": {
        "status": "failed",
        "message": "Update failed and was rolled back",
        "error": "File extraction failed",
        "rollback_status": "complete",
        "current_version": "3.14.0"
    }
}
```

### 4. Cancel/Rollback Update

**Endpoint**: `POST /wp-admin/admin-ajax.php?action=cuft_rollback_update`

**Purpose**: Cancel ongoing update or manually trigger rollback

**Request Parameters**:
```
action: cuft_rollback_update
nonce: {WordPress nonce}
update_id: {update ID to cancel}
reason: {optional reason for cancellation}
```

**Capability Required**: `update_plugins`

**Success Response (200)**:
```json
{
    "success": true,
    "data": {
        "status": "rolled_back",
        "message": "Update cancelled and previous version restored",
        "restored_version": "3.14.0"
    }
}
```

**Error Response (400)**:
```json
{
    "success": false,
    "data": {
        "message": "No update in progress to cancel",
        "code": "no_active_update"
    }
}
```

### 5. Get Update History

**Endpoint**: `GET /wp-admin/admin-ajax.php?action=cuft_update_history`

**Purpose**: Retrieve update attempt history and logs

**Request Parameters**:
```
action: cuft_update_history
nonce: {WordPress nonce}
limit: {number of entries, default 10}
offset: {pagination offset, default 0}
```

**Capability Required**: `update_plugins`

**Success Response (200)**:
```json
{
    "success": true,
    "data": {
        "total": 25,
        "limit": 10,
        "offset": 0,
        "entries": [
            {
                "id": 123,
                "timestamp": "2025-10-03T10:35:00Z",
                "action": "update_completed",
                "status": "success",
                "version_from": "3.13.0",
                "version_to": "3.14.0",
                "user": "admin",
                "details": "Update completed in 85 seconds"
            },
            {
                "id": 122,
                "timestamp": "2025-10-02T14:20:00Z",
                "action": "check_completed",
                "status": "info",
                "message": "No updates available"
            }
        ]
    }
}
```

### 6. Configure Update Settings

**Endpoint**: `POST /wp-admin/admin-ajax.php?action=cuft_update_settings`

**Purpose**: Update auto-update configuration

**Request Parameters**:
```
action: cuft_update_settings
nonce: {WordPress nonce}
enabled: true|false
check_frequency: manual|hourly|twicedaily|daily|weekly
include_prereleases: true|false
backup_before_update: true|false
notification_email: {email address, optional}
```

**Capability Required**: `manage_options`

**Success Response (200)**:
```json
{
    "success": true,
    "data": {
        "message": "Settings updated successfully",
        "settings": {
            "enabled": true,
            "check_frequency": "twicedaily",
            "include_prereleases": false,
            "backup_before_update": true,
            "notification_email": "admin@example.com",
            "next_scheduled_check": "2025-10-03T22:30:00Z"
        }
    }
}
```

## Common Error Codes

| Code | Description | HTTP Status |
|------|-------------|------------|
| `invalid_nonce` | Nonce verification failed | 403 |
| `insufficient_permissions` | User lacks required capability | 403 |
| `update_in_progress` | Another update is already running | 409 |
| `no_update_available` | No newer version exists | 400 |
| `github_api_error` | GitHub API request failed | 503 |
| `download_failed` | Failed to download update package | 500 |
| `extraction_failed` | Failed to extract update files | 500 |
| `rollback_failed` | Failed to restore previous version | 500 |
| `filesystem_error` | File permission or write error | 500 |

## Request Headers

All requests must include:
```
Content-Type: application/x-www-form-urlencoded
X-Requested-With: XMLHttpRequest
```

## Response Headers

All responses include:
```
Content-Type: application/json
Cache-Control: no-cache, no-store, must-revalidate
```

## Rate Limiting

- Check operations: Max 10 per hour per user
- Update operations: Max 3 per hour per user
- Status checks: No limit (read-only)
- Settings changes: Max 20 per hour per user

## Nonce Security

### Generation (PHP):
```php
wp_localize_script('cuft-updater', 'cuftUpdater', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('cuft_updater_nonce')
));
```

### Validation (PHP):
```php
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cuft_updater_nonce')) {
    wp_send_json_error(array(
        'message' => 'Security check failed',
        'code' => 'invalid_nonce'
    ), 403);
}
```

### Usage (JavaScript):
```javascript
const response = await fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
        action: 'cuft_check_update',
        nonce: cuftUpdater.nonce,
        force: 'true'
    })
});
```

## Testing Endpoints

For testing purposes, additional parameters can be used:

### Mock Mode:
```
mock: true - Use mock GitHub responses
mock_version: 3.16.0 - Specific version to mock
mock_delay: 2000 - Simulate network delay (ms)
mock_failure: download|extract|permissions - Simulate specific failure
```

### Dry Run Mode:
```
dry_run: true - Simulate update without actually modifying files
```