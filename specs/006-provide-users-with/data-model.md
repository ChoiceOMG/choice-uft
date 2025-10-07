# Data Model: Custom GTM Server Domain with Health Checks

## Overview
This document defines the data structures and storage patterns for managing custom GTM server configurations and their health check results.

## Entities

### 1. Custom Server Configuration
**Purpose**: Stores the administrator's custom GTM server settings

**Storage**: WordPress `wp_options` table

| Field | Type | Key | Description | Default |
|-------|------|-----|-------------|---------|
| enabled | boolean | `cuft_sgtm_enabled` | Whether custom server is enabled | false |
| url | string | `cuft_sgtm_url` | Custom server URL (validated) | '' |
| validated | boolean | `cuft_sgtm_validated` | Initial validation passed | false |
| active_server | string | `cuft_sgtm_active_server` | Current server: 'custom' or 'fallback' | 'fallback' |

**Validation Rules**:
- URL must include protocol (https:// or http://)
- URL must be valid format (filter_var with FILTER_VALIDATE_URL)
- URL must not contain query parameters or fragments
- URL must not be Google's default domains

**State Transitions**:
```
disabled → enabled (when URL provided and validation passes)
enabled → disabled (when admin disables or URL removed)
custom → fallback (when health check fails)
fallback → custom (after 3 consecutive successful health checks)
```

### 2. Health Check Result
**Purpose**: Records the outcome of health check attempts

**Storage**: WordPress `wp_options` table

| Field | Type | Key | Description | Default |
|-------|------|-----|-------------|---------|
| last_check_time | int | `cuft_sgtm_health_last_check` | Unix timestamp of last check | 0 |
| last_check_result | boolean | `cuft_sgtm_health_last_result` | Success/failure of last check | false |
| last_check_message | string | `cuft_sgtm_health_last_message` | Error message or 'Success' | '' |
| consecutive_success | int | `cuft_sgtm_health_consecutive_success` | Count for recovery logic | 0 |
| consecutive_failure | int | `cuft_sgtm_health_consecutive_failure` | Count for fallback logic | 0 |
| response_time | float | `cuft_sgtm_health_response_time` | Milliseconds for last check | 0.0 |

**Validation Rules**:
- last_check_time must be Unix timestamp (positive integer)
- consecutive_success resets to 0 on any failure
- consecutive_failure resets to 0 on any success
- response_time must be positive float or 0

**Health Check Success Criteria**:
1. HTTP response status = 200
2. Response received within 5 seconds
3. Response body contains 'google' or 'gtm' (case-insensitive)
4. Response headers indicate JavaScript content-type

### 3. Health Check History (Optional Enhancement)
**Purpose**: Maintains recent health check history for diagnostics

**Storage**: WordPress transient API (auto-expires after 7 days)

| Field | Type | Key | Description |
|-------|------|-----|-------------|
| history | array | `cuft_sgtm_health_history` | Last 10 health check results |

**Array Structure**:
```php
[
    [
        'timestamp' => 1234567890,
        'result' => true,
        'response_time' => 234.5,
        'message' => 'Success',
        'server_tested' => 'https://gtm.example.com'
    ],
    // ... up to 10 entries, newest first
]
```

## Relationships

```
Custom Server Configuration
    ↓ triggers
Health Check (via AJAX or Cron)
    ↓ produces
Health Check Result
    ↓ affects
Active Server Selection (custom/fallback)
    ↓ determines
GTM Script Source URL
```

## Database Operations

### Reading Configuration
```php
// Get all configuration values
$enabled = get_option('cuft_sgtm_enabled', false);
$url = get_option('cuft_sgtm_url', '');
$active = get_option('cuft_sgtm_active_server', 'fallback');

// Get health check status
$last_check = get_option('cuft_sgtm_health_last_check', 0);
$last_result = get_option('cuft_sgtm_health_last_result', false);
```

### Writing Configuration
```php
// Update configuration
update_option('cuft_sgtm_enabled', true);
update_option('cuft_sgtm_url', sanitize_url($url));

// Update health check result
update_option('cuft_sgtm_health_last_check', time());
update_option('cuft_sgtm_health_last_result', $success);
update_option('cuft_sgtm_health_consecutive_success', $count);
```

### Atomic Updates
```php
// Switch to fallback (atomic operation)
update_option('cuft_sgtm_active_server', 'fallback');
update_option('cuft_sgtm_health_consecutive_failure', 1);
update_option('cuft_sgtm_health_consecutive_success', 0);

// Switch to custom (after 3 successes)
if (get_option('cuft_sgtm_health_consecutive_success', 0) >= 3) {
    update_option('cuft_sgtm_active_server', 'custom');
    add_option('cuft_sgtm_server_recovered', time()); // For admin notice
}
```

## Migration Considerations

### Initial Setup
When feature is first deployed:
1. All options will be created with defaults on first access
2. No database migration required (uses existing wp_options table)
3. Backward compatible - missing options return defaults

### Rollback Strategy
If feature needs to be disabled:
1. Set `cuft_sgtm_enabled` to false
2. System automatically uses Google default endpoints
3. Options can be safely deleted without breaking functionality

### Data Cleanup
```php
// Complete removal of feature data
delete_option('cuft_sgtm_enabled');
delete_option('cuft_sgtm_url');
delete_option('cuft_sgtm_validated');
delete_option('cuft_sgtm_active_server');
delete_option('cuft_sgtm_health_last_check');
delete_option('cuft_sgtm_health_last_result');
delete_option('cuft_sgtm_health_last_message');
delete_option('cuft_sgtm_health_consecutive_success');
delete_option('cuft_sgtm_health_consecutive_failure');
delete_option('cuft_sgtm_health_response_time');
delete_transient('cuft_sgtm_health_history');
```

## Performance Considerations

1. **Option Caching**: WordPress automatically caches options in memory after first access
2. **Transient Storage**: History uses transients which can be stored in object cache if available
3. **Atomic Operations**: Each state change is a single database operation
4. **No Table Creation**: Uses existing WordPress infrastructure

## Security Considerations

1. **URL Sanitization**: All URLs sanitized with `sanitize_url()` before storage
2. **Capability Checks**: Only administrators can modify configuration
3. **Nonce Protection**: All AJAX endpoints require valid nonces
4. **No Direct SQL**: All operations use WordPress APIs
5. **Input Validation**: All inputs validated before storage