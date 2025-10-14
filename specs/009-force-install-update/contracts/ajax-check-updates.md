# Contract: Check for Updates AJAX Endpoint

**Endpoint**: `/wp-admin/admin-ajax.php?action=cuft_check_updates`
**Method**: POST
**Feature**: 009-force-install-update

## Authentication

- **Nonce**: Required (`cuft_force_update` action)
- **Capability**: `update_plugins`
- **Constant Check**: Respects `DISALLOW_FILE_MODS` (though check operation allowed even if true)

## Request

```json
{
  "action": "cuft_check_updates",
  "nonce": "a1b2c3d4e5"
}
```

## Response: Success (Update Available)

**HTTP Status**: 200

```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "3.19.0",
    "update_available": true,
    "release_date": "2025-10-13",
    "changelog_summary": "Bug fixes and performance improvements",
    "checked_at": 1728729600
  }
}
```

## Response: Success (Up-to-Date)

**HTTP Status**: 200

```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "3.18.0",
    "update_available": false,
    "message": "Plugin is up to date",
    "checked_at": 1728729600
  }
}
```

## Response: Error (Timeout)

**HTTP Status**: 200 (WordPress AJAX convention)

```json
{
  "success": false,
  "data": {
    "error_code": "github_timeout",
    "message": "Unable to check for updates. Please try again later.",
    "details": "GitHub API did not respond within 5 seconds"
  }
}
```

## Response: Error (Rate Limit)

```json
{
  "success": false,
  "data": {
    "error_code": "github_rate_limit",
    "message": "GitHub API rate limit exceeded. Displaying cached information.",
    "cached_version": "3.19.0",
    "cached_at": 1728729300
  }
}
```

## Side Effects

1. Clears `update_plugins` site transient
2. Calls `wp_clean_plugins_cache(true)`
3. Creates Update History Entry with `operation_type=manual_check`
4. Caches result in `cuft_plugin_installation_state` transient (5min TTL)

## Performance

- **Target**: Complete within 5 seconds
- **Timeout**: Hard 5-second timeout on GitHub API call
- **Cache**: 5-minute transient reduces redundant API calls

## Test Cases

- TC-001: Valid nonce returns version info
- TC-002: Missing nonce returns 403
- TC-003: Insufficient capability returns 403
- TC-004: GitHub timeout (5s) returns error
- TC-005: Rate limit returns cached data
- TC-006: Up-to-date returns appropriate message
- TC-007: New version includes changelog
- TC-008: Cache cleared after check
