# AJAX Endpoint Contract: Event Recording

**Endpoint**: `/wp-admin/admin-ajax.php?action=cuft_record_event`
**Method**: POST
**Authentication**: WordPress nonce
**Version**: 1.0

## Purpose
Record click tracking events from client-side JavaScript (phone clicks, email clicks, form submissions, lead generation).

## Request

### Request Format
```
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded
```

### Request Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| action | string | Yes | Must be "cuft_record_event" | Exact match |
| nonce | string | Yes | WordPress nonce for security | `wp_verify_nonce()` |
| click_id | string | Yes | Click identifier to associate event with | Alphanumeric + hyphens, max 255 chars |
| event_type | string | Yes | Type of event being recorded | Must be one of: `phone_click`, `email_click`, `form_submit`, `generate_lead` |
| timestamp | string | No | ISO 8601 timestamp (optional, defaults to NOW()) | Valid ISO 8601 format |

### Example Request
```javascript
const formData = new FormData();
formData.append('action', 'cuft_record_event');
formData.append('nonce', cuftConfig.nonce);
formData.append('click_id', 'gclid_abc123xyz');
formData.append('event_type', 'phone_click');

fetch(cuftConfig.ajaxUrl, {
    method: 'POST',
    body: formData
});
```

## Response

### Success Response
**HTTP Status**: 200 OK
```json
{
  "success": true,
  "data": {
    "message": "Event recorded successfully",
    "click_id": "gclid_abc123xyz",
    "event_type": "phone_click",
    "event_count": 3
  }
}
```

### Error Responses

#### Invalid Nonce
**HTTP Status**: 403 Forbidden
```json
{
  "success": false,
  "data": {
    "message": "Security check failed"
  }
}
```

#### Invalid Event Type
**HTTP Status**: 400 Bad Request
```json
{
  "success": false,
  "data": {
    "message": "Invalid event type",
    "allowed_types": ["phone_click", "email_click", "form_submit", "generate_lead"]
  }
}
```

#### Missing Click ID
**HTTP Status**: 400 Bad Request
```json
{
  "success": false,
  "data": {
    "message": "Missing required parameter: click_id"
  }
}
```

#### Database Error
**HTTP Status**: 500 Internal Server Error
```json
{
  "success": false,
  "data": {
    "message": "Failed to record event"
  }
}
```

## Behavior

### Event Recording Logic
1. Validate nonce and inputs
2. Check if click_id exists in database
   - If not exists: Create new record with event array
   - If exists: Append or update event in array
3. For duplicate event types: Update timestamp to latest occurrence
4. For new event types: Append to events array
5. Update `date_updated` to timestamp of most recent event
6. Enforce 100-event limit with FIFO cleanup if exceeded

### Event Deduplication
When an event type already exists in the events array:
- **Update timestamp** to the latest occurrence time
- **Do not append** a duplicate entry
- This ensures each event type appears once with its most recent timestamp

Example:
```json
// Before: events = [{"event": "phone_click", "timestamp": "2025-01-01T12:00:00Z"}]
// User clicks phone link again at 2025-01-01T14:00:00Z
// After: events = [{"event": "phone_click", "timestamp": "2025-01-01T14:00:00Z"}]
```

### FIFO Event Cleanup
When events array exceeds 100 items:
1. Sort events by timestamp (oldest first)
2. Remove oldest events until count = 99
3. Append new event
4. Log cleanup action (debug mode only)

## Security

### Nonce Validation
```php
check_ajax_referer('cuft-event-recorder', 'nonce');
```

### Input Sanitization
```php
$click_id = sanitize_text_field($_POST['click_id'] ?? '');
$event_type = sanitize_text_field($_POST['event_type'] ?? '');
```

### Event Type Whitelist
```php
$valid_events = ['phone_click', 'email_click', 'form_submit', 'generate_lead'];
if (!in_array($event_type, $valid_events)) {
    wp_send_json_error('Invalid event type');
}
```

### Click ID Pattern Validation
```php
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $click_id)) {
    wp_send_json_error('Invalid click_id format');
}
```

## Performance

### Target Metrics
- Response time: <100ms (p95)
- Database query time: <50ms
- JSON operation time: <20ms

### Optimization Strategies
- Use prepared statements for SQL
- Batch event updates when possible
- Cache event count to avoid JSON_LENGTH on every call
- Use MySQL JSON functions (JSON_ARRAY_APPEND, JSON_SET)

## Error Handling

### Client-Side Error Handling
```javascript
fetch(cuftConfig.ajaxUrl, {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (!data.success && cuftConfig.debug) {
        console.warn('Event recording failed:', data.data.message);
    }
})
.catch(error => {
    // Silent fail - never break user functionality
    if (cuftConfig.debug) {
        console.error('Event recording exception:', error);
    }
});
```

### Server-Side Error Handling
```php
try {
    $result = CUFT_Click_Tracker::add_event($click_id, $event_type);
    if ($result) {
        wp_send_json_success(['message' => 'Event recorded']);
    } else {
        wp_send_json_error('Failed to record event');
    }
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('CUFT Event Recording Error: ' . $e->getMessage());
    }
    wp_send_json_error('Internal error');
}
```

## Testing

### Contract Test Cases
1. ✅ Valid event recording succeeds
2. ✅ Invalid nonce returns 403
3. ✅ Invalid event type returns 400
4. ✅ Missing click_id returns 400
5. ✅ Duplicate event updates timestamp
6. ✅ 100+ events triggers FIFO cleanup
7. ✅ Database error returns 500
8. ✅ Event count returned in response

### Integration Test Scenarios
1. Phone link click → phone_click event recorded
2. Email link click → email_click event recorded
3. Form submission → form_submit event recorded
4. Qualified lead → generate_lead event recorded
5. Multiple clicks → single event with latest timestamp
6. 100+ events → oldest events removed

## Dependencies

### WordPress Functions
- `check_ajax_referer()` - Nonce validation
- `sanitize_text_field()` - Input sanitization
- `wp_send_json_success()` - Success response
- `wp_send_json_error()` - Error response

### Plugin Classes
- `CUFT_Click_Tracker::add_event()` - Event recording logic
- `CUFT_Click_Tracker::get_event_count()` - Event count retrieval

### Database Requirements
- MySQL 5.7+ with JSON support
- cuft_click_tracking table with events column

---

**Contract Version**: 1.0
**Last Updated**: 2025-09-29
**Breaking Changes**: None (new endpoint)