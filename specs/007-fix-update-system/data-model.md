# Data Model: Fix Update System Inconsistencies

**Feature**: 007-fix-update-system
**Date**: 2025-10-07

## Overview

This document defines the data models and state management for the update system fixes. All models use WordPress transients for caching and wp_options for persistence.

---

## 1. Update Status Model

**Class**: `CUFT_Update_Status`
**Storage**: Site transient (`_site_transient_cuft_update_status`)
**Expiration**: Context-aware (1 minute to 12 hours)

### Fields

| Field | Type | Description | Required | Default |
|-------|------|-------------|----------|---------|
| `current_version` | string | Currently installed plugin version | Yes | CUFT_VERSION |
| `latest_version` | string | Latest available version from GitHub | Yes | - |
| `update_available` | boolean | Whether update is available | Yes | false |
| `last_check` | datetime | Timestamp of last update check | Yes | null |
| `update_source` | string | Source of update (github/wordpress) | Yes | github |
| `release_url` | string | URL to release notes | No | - |
| `package_url` | string | Download URL for update package | No | - |
| `tested_wp_version` | string | Tested up to WordPress version | No | 6.4 |
| `requires_php` | string | Minimum PHP version required | No | 7.0 |

### Example JSON Structure

```json
{
  "current_version": "3.16.2",
  "latest_version": "3.17.0",
  "update_available": true,
  "last_check": "2025-10-07T14:30:00Z",
  "update_source": "github",
  "release_url": "https://github.com/ChoiceOMG/choice-uft/releases/tag/v3.17.0",
  "package_url": "https://github.com/ChoiceOMG/choice-uft/releases/download/v3.17.0/choice-uft-v3.17.0.zip",
  "tested_wp_version": "6.4",
  "requires_php": "7.4"
}
```

### State Transitions

```
Initial State → No update data
    ↓
First Check → Update status populated
    ↓
Update Available → update_available = true
    ↓
User Updates Plugin → current_version = latest_version
    ↓
No Update Available → update_available = false
    ↓
New Release → Update Available (cycle repeats)
```

### Validation Rules

1. **Version Format**: Must match semantic versioning (X.Y.Z)
2. **Update Available**: Only true if `version_compare(latest_version, current_version, '>')`
3. **Last Check**: Must be valid ISO 8601 datetime
4. **URLs**: Must be valid HTTP/HTTPS URLs
5. **Update Source**: Must be either 'github' or 'wordpress'

---

## 2. Update Progress Model

**Class**: `CUFT_Update_Progress`
**Storage**: Transient (`_transient_cuft_update_in_progress`)
**Expiration**: 5 minutes (safety timeout)

### Fields

| Field | Type | Description | Required | Default |
|-------|------|-------------|----------|---------|
| `update_id` | string | Unique identifier for update | Yes | update_{timestamp} |
| `status` | enum | Current status | Yes | pending |
| `percentage` | integer | Progress percentage (0-100) | Yes | 0 |
| `message` | string | Human-readable status message | Yes | - |
| `started_at` | timestamp | When update started | Yes | time() |
| `completed_at` | timestamp | When update completed | No | null |
| `user_id` | integer | User who initiated update | Yes | get_current_user_id() |
| `version_from` | string | Version updating from | Yes | CUFT_VERSION |
| `version_to` | string | Version updating to | Yes | - |
| `error_message` | string | Error message if failed | No | null |

### Status Enum Values

| Status | Description | Percentage Range |
|--------|-------------|------------------|
| `pending` | Update queued, not started | 0 |
| `checking` | Verifying update availability | 0-10 |
| `downloading` | Downloading update package | 10-40 |
| `backing_up` | Creating backup of current version | 40-60 |
| `extracting` | Extracting update files | 60-80 |
| `installing` | Installing update | 80-95 |
| `verifying` | Verifying installation | 95-99 |
| `complete` | Update completed successfully | 100 |
| `failed` | Update failed | - |
| `rolled_back` | Update rolled back after failure | - |

### Example JSON Structure

```json
{
  "update_id": "update_1728345600",
  "status": "downloading",
  "percentage": 25,
  "message": "Downloading update package... (1.2 MB / 2.5 MB)",
  "started_at": 1728345600,
  "completed_at": null,
  "user_id": 1,
  "version_from": "3.16.2",
  "version_to": "3.17.0",
  "error_message": null
}
```

### State Transitions

```
pending → checking → downloading → backing_up → extracting → installing → verifying → complete
                                                                              ↓
                                                                           failed
                                                                              ↓
                                                                        rolled_back
```

### Validation Rules

1. **Status**: Must be one of enum values
2. **Percentage**: Must be 0-100 integer
3. **Update ID**: Must be unique (timestamp-based)
4. **User ID**: Must exist in WordPress users table
5. **Versions**: Must be valid semantic versions
6. **Auto-Expiry**: If status = 'in_progress' for >5 minutes, consider stale

---

## 3. Update Log Model

**Class**: `CUFT_Update_Log`
**Storage**: Database table (`wp_cuft_update_log`)
**Retention**: Last 5 entries (FIFO)

### Fields

| Field | Type | Description | Required | Index |
|-------|------|-------------|----------|-------|
| `id` | bigint | Auto-increment primary key | Yes | PRIMARY |
| `timestamp` | datetime | When log entry created | Yes | DESC |
| `action` | varchar(20) | Type of action performed | Yes | - |
| `status` | varchar(20) | Result status | Yes | - |
| `version_from` | varchar(20) | Version before action | No | - |
| `version_to` | varchar(20) | Version after action | No | - |
| `user_id` | bigint | User who performed action | No | - |
| `error_message` | text | Error details if failed | No | - |
| `duration` | int | Action duration in seconds | No | - |

### Action Enum Values

| Action | Description |
|--------|-------------|
| `check` | Update availability check |
| `download` | Package download |
| `backup` | Backup creation |
| `install` | Update installation |
| `verify` | Installation verification |
| `rollback` | Rollback to previous version |
| `complete` | Successful update completion |

### Status Enum Values

| Status | Description |
|--------|-------------|
| `success` | Action completed successfully |
| `failure` | Action failed |
| `in_progress` | Action currently running |
| `skipped` | Action skipped |

### Example Table Rows

| id | timestamp | action | status | version_from | version_to | user_id | error_message | duration |
|----|-----------|--------|--------|--------------|------------|---------|---------------|----------|
| 5 | 2025-10-07 14:45:00 | complete | success | 3.16.2 | 3.17.0 | 1 | NULL | 45 |
| 4 | 2025-10-07 14:44:15 | install | success | 3.16.2 | 3.17.0 | 1 | NULL | 30 |
| 3 | 2025-10-06 10:30:00 | rollback | success | 3.16.1 | 3.16.0 | 1 | NULL | 20 |
| 2 | 2025-10-06 10:25:00 | install | failure | 3.16.0 | 3.16.1 | 1 | File permissions error | 15 |
| 1 | 2025-10-05 09:15:00 | check | success | 3.16.0 | 3.16.1 | 1 | NULL | 2 |

### FIFO Cleanup Logic

```php
// After inserting new entry
function cleanup_old_entries() {
    global $wpdb;
    $table = $wpdb->prefix . 'cuft_update_log';
    $max_entries = 5;

    // Get ID of 6th most recent entry
    $threshold_id = $wpdb->get_var(
        "SELECT id FROM $table
         ORDER BY timestamp DESC
         LIMIT 1 OFFSET $max_entries"
    );

    // Delete all older entries
    if ($threshold_id) {
        $wpdb->query("DELETE FROM $table WHERE id < $threshold_id");
    }
}
```

### Validation Rules

1. **Timestamp**: Must be valid MySQL datetime
2. **Action**: Must be one of enum values
3. **Status**: Must be one of enum values
4. **Entry Limit**: Never exceed 5 entries
5. **Auto-Cleanup**: Run after every insert

---

## 4. Admin Notice State

**Class**: `CUFT_Admin_Notices`
**Storage**: User meta for dismissals
**Display**: Hook into `admin_notices` action

### Fields

| Field | Type | Description | Required | Storage |
|-------|------|-------------|----------|---------|
| `notice_type` | enum | Type of notice | Yes | In-memory |
| `message` | string | Notice content | Yes | In-memory |
| `dismissible` | boolean | Can be dismissed | Yes | In-memory |
| `version` | string | Associated version | No | In-memory |
| `dismissed` | boolean | User dismissed state | No | User meta |
| `display_location` | string | Where to show | Yes | In-memory |

### Notice Type Enum

| Type | CSS Class | Color | Use Case |
|------|-----------|-------|----------|
| `info` | `notice-info` | Blue | Update available |
| `warning` | `notice-warning` | Yellow/Orange | Configuration issue |
| `error` | `notice-error` | Red | Update failed |
| `success` | `notice-success` | Green | Update completed |

### Display Location

| Location | Hook | Conditions |
|----------|------|------------|
| `above_title` | `admin_notices` | Critical system notices only |
| `below_title` | `admin_notices` | Standard notices (recommended) |
| `inline` | Custom hook | Within specific page sections |

### User Meta Schema

**Meta Key Format**: `cuft_dismissed_update_{version}`

| Meta Key | Meta Value | Description |
|----------|------------|-------------|
| `cuft_dismissed_update_3.17.0` | timestamp | User dismissed update notice for v3.17.0 |
| `cuft_dismissed_update_failed` | timestamp | User dismissed failure notice |
| `cuft_dismissed_update_in_progress` | timestamp | User dismissed progress notice |

### Example State

```php
array(
    'notice_type' => 'info',
    'message' => 'Version 3.17.0 is available. You are currently running version 3.16.2.',
    'dismissible' => true,
    'version' => '3.17.0',
    'dismissed' => false,  // Checked from user meta
    'display_location' => 'below_title'
)
```

### Validation Rules

1. **Notice Type**: Must be one of enum values
2. **Message**: Must be non-empty string
3. **Version**: Must be valid semantic version
4. **Display Location**: Must be 'above_title', 'below_title', or 'inline'
5. **Dismissal**: Only store if user explicitly dismissed

---

## 5. Admin Bar State

**Class**: `CUFT_Admin_Bar`
**Storage**: Rendered server-side, updated client-side
**Refresh**: AJAX polling (30-60 seconds)

### Fields

| Field | Type | Description | Required | Update Method |
|-------|------|-------------|----------|---------------|
| `update_available` | boolean | Update available flag | Yes | AJAX poll |
| `latest_version` | string | Latest version number | No | AJAX poll |
| `current_version` | string | Current version number | Yes | Static |
| `next_check_time` | timestamp | Next scheduled check | No | AJAX poll |
| `next_check_human` | string | Human-readable time | No | AJAX poll |
| `check_in_progress` | boolean | Check currently running | No | AJAX poll |
| `badge_count` | integer | Update badge number | No | AJAX poll |

### Admin Bar Node Structure

```
#wp-admin-bar-cuft-updates (parent)
    ├── #wp-admin-bar-cuft-check-updates (submenu - manual trigger)
    ├── #wp-admin-bar-cuft-view-updates (submenu - conditional)
    ├── #wp-admin-bar-cuft-update-settings (submenu - link)
    ├── #wp-admin-bar-cuft-update-history (submenu - link)
    └── #wp-admin-bar-cuft-next-check (submenu - info)
```

### Dynamic Elements

| Element | DOM Selector | Update Trigger | Update Method |
|---------|--------------|----------------|---------------|
| Icon | `.ab-icon` | Status change | Replace class, update color |
| Label | `.ab-label` | Status change | Update text content |
| Badge | `.ab-badge` | Update available | Create/remove element |
| Next check | `#wp-admin-bar-cuft-next-check a` | Time change | Update text content |

### Example JSON Response (AJAX)

```json
{
  "success": true,
  "data": {
    "update_available": true,
    "latest_version": "3.17.0",
    "current_version": "3.16.2",
    "next_check_time": 1728360000,
    "next_check_human": "in 2 hours",
    "check_in_progress": false,
    "badge_count": 1
  }
}
```

### Validation Rules

1. **Badge Count**: Must be 0 or 1 (not a counter)
2. **Version Format**: Must match semantic versioning
3. **Next Check Human**: Must be relative time string (e.g., "in 2 hours", "2 minutes ago")
4. **Polling Interval**: Must be 30-60 seconds
5. **Update Trigger**: Only update if status changed (avoid unnecessary DOM manipulation)

---

## 6. AJAX Response Models

### Success Response

```json
{
  "success": true,
  "data": {
    // Endpoint-specific data
  }
}
```

### Error Response

```json
{
  "success": false,
  "data": {
    "message": "Human-readable error message",
    "code": "machine_readable_error_code",
    "details": "Optional technical details"
  }
}
```

### Standard Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `invalid_nonce` | 403 | Security validation failed |
| `insufficient_permissions` | 403 | User lacks capability |
| `update_in_progress` | 409 | Concurrent update detected |
| `update_failed` | 500 | Update process failed |
| `check_failed` | 500 | Update check failed |
| `network_error` | 503 | GitHub API unreachable |
| `invalid_version` | 400 | Version format invalid |
| `missing_parameter` | 400 | Required parameter missing |

---

## 7. WordPress Update Transient Integration

### WordPress Core Transient Structure

**Transient**: `_site_transient_update_plugins`

```php
stdClass Object (
    [last_checked] => 1728259200,  // UNIX timestamp
    [checked] => Array (
        [choice-uft/choice-universal-form-tracker.php] => "3.16.2"
    ),
    [response] => Array (
        [choice-uft/choice-universal-form-tracker.php] => stdClass Object (
            [slug] => "choice-uft",
            [plugin] => "choice-uft/choice-universal-form-tracker.php",
            [new_version] => "3.17.0",
            [url] => "https://github.com/ChoiceOMG/choice-uft",
            [package] => "https://github.com/.../choice-uft-v3.17.0.zip",
            [tested] => "6.4",
            [requires_php] => "7.4"
        )
    ),
    [no_update] => Array ()
)
```

### Plugin Data Injection

```php
public function check_for_updates($transient) {
    // Early exit if WordPress hasn't checked
    if (empty($transient->checked)) {
        return $transient;
    }

    // Get plugin status
    $status = CUFT_Update_Status::get();

    // Inject update if available
    if ($status['update_available']) {
        $transient->response[$this->plugin_basename] = (object) array(
            'slug' => 'choice-uft',
            'plugin' => $this->plugin_basename,
            'new_version' => $status['latest_version'],
            'url' => $status['release_url'],
            'package' => $status['package_url'],
            'tested' => $status['tested_wp_version'],
            'requires_php' => $status['requires_php']
        );
    } else {
        // Mark as up-to-date
        $transient->no_update[$this->plugin_basename] = (object) array(
            'slug' => 'choice-uft',
            'plugin' => $this->plugin_basename,
            'new_version' => $status['current_version'],
            'url' => 'https://github.com/ChoiceOMG/choice-uft'
        );
    }

    return $transient;
}
```

---

## 8. Data Flow Diagram

```
User Triggers Update Check
    ↓
AJAX → cuft_check_update
    ↓
CUFT_Update_Checker::check()
    ↓
GitHub API Request
    ↓
CUFT_Update_Status::set()  (Site Transient)
    ↓
WordPress pre_set_site_transient_update_plugins Filter
    ↓
Inject Update Data into WordPress Transient
    ↓
AJAX Response → Admin Bar
    ↓
DOM Update (Icon, Badge, Submenu)

---

User Clicks "Download & Install Update"
    ↓
AJAX → cuft_perform_update
    ↓
Check CUFT_Update_Progress::is_in_progress()
    ↓
Set Progress Transient (Lock)
    ↓
Schedule Async Update (wp_cron)
    ↓
CUFT_Update_Installer::execute()
    ↓
Download → Backup → Extract → Install → Verify
    ↓
Update CUFT_Update_Progress (Percentage)
    ↓
On Success: Clear Progress, Log to CUFT_Update_Log
    ↓
On Failure: Set Failed Status, Trigger Rollback
    ↓
Clear WordPress Transient (force recheck)
    ↓
Admin Bar Polls → Shows New Status
```

---

## Summary

### Storage Locations

| Data | Storage Type | Key/Table | Expiration |
|------|--------------|-----------|------------|
| Update Status | Site Transient | `cuft_update_status` | Context-aware (1min-12h) |
| Update Progress | Transient | `cuft_update_in_progress` | 5 minutes |
| Update Log | Database Table | `wp_cuft_update_log` | FIFO (5 entries) |
| Notice Dismissals | User Meta | `cuft_dismissed_update_{version}` | Permanent |
| WordPress Updates | Site Transient | `update_plugins` | Context-aware |

### Key Relationships

1. **Update Status ↔ WordPress Transient**: Status injected into WordPress via filter
2. **Update Progress ↔ Admin Bar**: Progress polled by JavaScript, displayed in admin bar
3. **Update Log ← Update Progress**: Log entry created when progress completes
4. **Admin Notices ← Update Status**: Notices displayed based on status
5. **User Meta ← Admin Notices**: Dismissal state stored per-user

### Data Integrity Rules

1. **Atomicity**: Progress transient acts as lock (only one update at a time)
2. **Consistency**: Status, Progress, and WordPress transients synchronized
3. **Isolation**: User-specific dismissals don't affect other users
4. **Durability**: Log entries persisted to database, survive transient expiration

---

**Last Updated**: 2025-10-07
**Reviewed**: Pending
**Status**: Ready for implementation
