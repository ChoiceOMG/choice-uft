# AJAX API Contracts: Custom GTM Server Health Checks

## Overview
This document defines the AJAX endpoints for managing custom GTM server configuration and health checks.

## Base Configuration
- **Base URL**: `/wp-admin/admin-ajax.php`
- **Authentication**: WordPress nonce validation
- **Nonce Action**: `cuft_admin` (uses existing admin nonce)
- **Response Format**: JSON

## Endpoints

### 1. Test Custom Server
**Action**: `cuft_test_sgtm`

**Purpose**: Validates that a custom GTM server URL is accessible and serving valid GTM scripts

**Method**: POST

**Request**:
```javascript
{
    action: "cuft_test_sgtm",
    nonce: "{wordpress_nonce}",      // Action: cuft_admin
    sgtm_url: "https://gtm.example.com"  // Custom server URL to test
}
```

**Response Success** (200):
```json
{
    "success": true,
    "data": {
        "valid": true,
        "message": "Custom server validated successfully",
        "response_time": 234.5,
        "endpoints_tested": {
            "gtm_js": true,
            "ns_html": true
        }
    }
}
```

**Response Failure** (200):
```json
{
    "success": false,
    "data": {
        "valid": false,
        "message": "Server timeout: No response within 5 seconds",
        "response_time": 5000,
        "endpoints_tested": {
            "gtm_js": false,
            "ns_html": false
        }
    }
}
```

**Validation Rules**:
- URL must be valid format
- URL must use HTTPS (recommended) or HTTP
- Timeout after 5 seconds
- Response must be HTTP 200
- Response must contain 'google' or 'gtm' string

### 2. Save Custom Server Configuration
**Action**: `cuft_save_sgtm_config`

**Purpose**: Saves custom server configuration and triggers initial validation

**Method**: POST

**Request**:
```javascript
{
    action: "cuft_save_sgtm_config",
    nonce: "{wordpress_nonce}",      // Action: cuft_admin
    enabled: true,                    // Enable/disable custom server
    sgtm_url: "https://gtm.example.com"  // Custom server URL
}
```

**Response Success** (200):
```json
{
    "success": true,
    "data": {
        "message": "Configuration saved successfully",
        "enabled": true,
        "url": "https://gtm.example.com",
        "validated": true,
        "active_server": "custom"
    }
}
```

**Response Validation Failed** (200):
```json
{
    "success": true,
    "data": {
        "message": "Configuration saved. Server validation failed - using fallback",
        "enabled": true,
        "url": "https://gtm.example.com",
        "validated": false,
        "active_server": "fallback",
        "validation_error": "Server returned 404"
    }
}
```

### 3. Trigger Manual Health Check
**Action**: `cuft_manual_health_check`

**Purpose**: Manually triggers a health check of the configured custom server

**Method**: POST

**Request**:
```javascript
{
    action: "cuft_manual_health_check",
    nonce: "{wordpress_nonce}"      // Action: cuft_admin
}
```

**Response Success** (200):
```json
{
    "success": true,
    "data": {
        "health_check_passed": true,
        "message": "Health check successful",
        "timestamp": 1234567890,
        "response_time": 345.2,
        "consecutive_success": 1,
        "active_server": "custom"
    }
}
```

**Response Failure** (200):
```json
{
    "success": true,
    "data": {
        "health_check_passed": false,
        "message": "Health check failed: Connection timeout",
        "timestamp": 1234567890,
        "response_time": 5000,
        "consecutive_failure": 1,
        "active_server": "fallback"
    }
}
```

### 4. Get Server Status
**Action**: `cuft_get_sgtm_status`

**Purpose**: Retrieves current custom server configuration and health status

**Method**: POST

**Request**:
```javascript
{
    action: "cuft_get_sgtm_status",
    nonce: "{wordpress_nonce}"      // Action: cuft_admin
}
```

**Response** (200):
```json
{
    "success": true,
    "data": {
        "configuration": {
            "enabled": true,
            "url": "https://gtm.example.com",
            "validated": true
        },
        "status": {
            "active_server": "custom",
            "last_check_time": 1234567890,
            "last_check_result": true,
            "last_check_message": "Success",
            "consecutive_success": 5,
            "consecutive_failure": 0,
            "response_time": 234.5
        },
        "next_check": 1234571490,
        "human_readable": {
            "last_check": "5 minutes ago",
            "next_check": "in 55 minutes",
            "status": "Healthy"
        }
    }
}
```

### 5. Clear Health Check History
**Action**: `cuft_clear_health_history`

**Purpose**: Clears health check history and resets counters

**Method**: POST

**Request**:
```javascript
{
    action: "cuft_clear_health_history",
    nonce: "{wordpress_nonce}"      // Action: cuft_admin
}
```

**Response** (200):
```json
{
    "success": true,
    "data": {
        "message": "Health check history cleared",
        "counters_reset": true
    }
}
```

## Error Responses

### Security Check Failed (403):
```json
{
    "success": false,
    "data": "Security check failed"
}
```

### Missing Required Parameter (400):
```json
{
    "success": false,
    "data": "Missing required parameter: sgtm_url"
}
```

### Invalid URL Format (400):
```json
{
    "success": false,
    "data": "Invalid URL format"
}
```

### Insufficient Permissions (403):
```json
{
    "success": false,
    "data": "Insufficient permissions. Admin access required."
}
```

## JavaScript Implementation Examples

### Using jQuery (existing pattern):
```javascript
jQuery.ajax({
    url: cuftAdmin.ajax_url,
    type: 'POST',
    data: {
        action: 'cuft_test_sgtm',
        nonce: cuftAdmin.nonce,
        sgtm_url: jQuery('#cuft-sgtm-url').val()
    },
    dataType: 'json',
    timeout: 10000, // 10 second client timeout
    success: function(response) {
        if (response.success) {
            console.log('Server validated:', response.data);
        } else {
            console.error('Validation failed:', response.data);
        }
    },
    error: function(xhr, status, error) {
        console.error('AJAX error:', error);
    }
});
```

### Using Fetch API (modern pattern):
```javascript
fetch(cuftAdmin.ajax_url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
        action: 'cuft_manual_health_check',
        nonce: cuftAdmin.nonce
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Health check result:', data.data);
    } else {
        console.error('Health check failed:', data.data);
    }
})
.catch(error => {
    console.error('Network error:', error);
});
```

## Cron Job Contract

### Scheduled Health Check
**Hook**: `cuft_scheduled_health_check`

**Schedule**: Every 6 hours (uses `wp_schedule_event`)

**Function**: `CUFT_Admin::scheduled_health_check()`

**Behavior**:
1. Checks if custom server is enabled
2. Performs health check on configured URL
3. Updates health status in wp_options
4. Switches between custom/fallback based on results
5. Creates admin notice if status changes

**Registration**:
```php
// On plugin activation
if (!wp_next_scheduled('cuft_scheduled_health_check')) {
    wp_schedule_event(time(), 'six_hours', 'cuft_scheduled_health_check');
}

// Custom interval
add_filter('cron_schedules', function($schedules) {
    $schedules['six_hours'] = array(
        'interval' => 6 * 60 * 60,
        'display' => 'Every 6 hours'
    );
    return $schedules;
});
```

## Security Requirements

1. **Nonce Validation**: All endpoints must validate WordPress nonce with action `cuft_admin`
2. **Capability Check**: User must have `manage_options` capability (administrator)
3. **URL Sanitization**: All URLs must be sanitized with `sanitize_url()`
4. **Rate Limiting**: Manual health checks limited to once per minute
5. **Input Validation**: All inputs validated before processing
6. **XSS Prevention**: All output escaped appropriately