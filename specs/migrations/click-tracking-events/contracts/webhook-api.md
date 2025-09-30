# Webhook API Contract: Backward Compatibility

**Endpoint**: `/cuft-webhook/`
**Method**: GET/POST
**Authentication**: API key
**Version**: 1.0 (Unchanged)

## Purpose
Maintain 100% backward compatibility with existing webhook API while adding event recording capability.

## Contract Guarantee
**CRITICAL**: This API contract MUST remain unchanged to ensure existing integrations continue to function.

## Request

### Request Format
```
GET /cuft-webhook/?key={api_key}&click_id={click_id}&qualified={0|1}&score={0-10}
```
OR
```
POST /cuft-webhook/
Content-Type: application/x-www-form-urlencoded

key={api_key}&click_id={click_id}&qualified={0|1}&score={0-10}
```

### Request Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| key | string | Yes | API key for authentication | Must match `cuft_webhook_key` option |
| click_id | string | Yes | Click identifier to update | Alphanumeric + hyphens, max 255 chars |
| qualified | int | No | Lead qualification status (0 or 1) | 0 or 1 |
| score | int | No | Lead quality score (0-10) | Integer 0-10 |

### Example Request
```bash
curl "https://example.com/cuft-webhook/?key=abc123&click_id=gclid_xyz789&qualified=1&score=8"
```

## Response

### Success Response (UNCHANGED)
**HTTP Status**: 200 OK
```json
{
  "success": true,
  "message": "Click tracking updated successfully",
  "click_id": "gclid_xyz789",
  "qualified": 1,
  "score": 8
}
```

### Error Responses (UNCHANGED)

#### Invalid API Key
**HTTP Status**: 403 Forbidden
```json
{
  "success": false,
  "message": "Invalid API key"
}
```

#### Missing Click ID
**HTTP Status**: 400 Bad Request
```json
{
  "success": false,
  "message": "Missing required parameter: click_id"
}
```

#### Invalid Score
**HTTP Status**: 400 Bad Request
```json
{
  "success": false,
  "message": "Score must be between 0 and 10"
}
```

## Behavior

### Current Behavior (Preserved)
1. Validate API key
2. Validate parameters
3. Update click tracking record:
   - Set `qualified` if provided
   - Set `score` if provided
   - Update `date_updated` to current timestamp
4. Create record if click_id doesn't exist
5. Return success/error response

### New Behavior (Added, Non-Breaking)
**After successful update, optionally record event**:
- If `qualified=1` is set: Record `status_qualified` event
- If `score` is increased: Record `score_updated` event
- This is internal only - does not affect API response

### Backward Compatibility Guarantees
✅ **Request format unchanged** - existing integrations work as-is
✅ **Response format unchanged** - existing parsers work as-is
✅ **Parameter validation unchanged** - existing behavior preserved
✅ **Error codes unchanged** - existing error handling works
✅ **Authentication unchanged** - existing API keys valid

## Event Recording Enhancement

### New Event Types (Internal Only)
These events are recorded internally but DO NOT affect the webhook API contract:

#### status_qualified
Recorded when `qualified=1` is set via webhook
```json
{
  "event": "status_qualified",
  "timestamp": "2025-01-01T12:00:00Z"
}
```

#### score_updated
Recorded when `score` is updated to a higher value via webhook
```json
{
  "event": "score_updated",
  "timestamp": "2025-01-01T12:00:00Z"
}
```

### Implementation (Non-Breaking)
```php
// After existing update logic
if ($qualified === 1) {
    CUFT_Click_Tracker::add_event($click_id, 'status_qualified');
}

if ($new_score > $old_score) {
    CUFT_Click_Tracker::add_event($click_id, 'score_updated');
}
```

## Migration Impact

### No Changes Required
✅ Existing webhook integrations continue to work without modification
✅ Third-party systems don't need updates
✅ API documentation remains valid
✅ Error handling remains consistent

### Internal Enhancements
- Event recording added transparently
- Admin interface can show webhook-triggered events
- User journey tracking includes qualification updates

## Testing

### Backward Compatibility Test Cases
1. ✅ GET request with valid key updates record
2. ✅ POST request with valid key updates record
3. ✅ Invalid key returns 403 (unchanged)
4. ✅ Missing click_id returns 400 (unchanged)
5. ✅ Invalid score returns 400 (unchanged)
6. ✅ Response format matches existing contract
7. ✅ Existing integrations continue to function

### Event Recording Test Cases (New, Internal)
1. ✅ qualified=1 triggers status_qualified event
2. ✅ Score increase triggers score_updated event
3. ✅ Event recording failure doesn't break webhook
4. ✅ Events appear in admin interface

## Security

### Authentication (Unchanged)
```php
$provided_key = sanitize_text_field($_GET['key'] ?? $_POST['key'] ?? '');
$stored_key = get_option('cuft_webhook_key', '');

if ($provided_key !== $stored_key) {
    wp_send_json_error('Invalid API key', 403);
    exit;
}
```

### Input Validation (Unchanged)
```php
$click_id = sanitize_text_field($_GET['click_id'] ?? $_POST['click_id'] ?? '');
$qualified = isset($_GET['qualified']) ? intval($_GET['qualified']) : null;
$score = isset($_GET['score']) ? intval($_GET['score']) : null;

if (empty($click_id)) {
    wp_send_json_error('Missing required parameter: click_id', 400);
    exit;
}

if ($score !== null && ($score < 0 || $score > 10)) {
    wp_send_json_error('Score must be between 0 and 10', 400);
    exit;
}
```

## Performance

### Target Metrics (Unchanged)
- Response time: <50ms (p95)
- Database update: <20ms
- Event recording: <10ms (non-blocking)

### Event Recording Impact
- Event recording happens after response sent (non-blocking)
- Event recording failure doesn't affect webhook success
- Total overhead: <10ms (acceptable)

## Dependencies

### WordPress Functions
- `sanitize_text_field()` - Input sanitization
- `get_option()` - API key retrieval
- `wp_send_json_error()` - Error responses
- `wp_send_json_success()` - Success responses

### Plugin Classes
- `CUFT_Click_Tracker::update_click()` - Update logic (existing)
- `CUFT_Click_Tracker::add_event()` - Event recording (new, non-breaking)

## Rollback Strategy

### If Event Recording Causes Issues
1. Disable event recording via feature flag
2. Webhook API continues to work normally
3. No impact on existing integrations

### Hybrid Rollback
If database migration needs rollback:
- Webhook updates to `qualified` and `score` are PRESERVED
- Event data is discarded (events column removed)
- Webhook API functionality remains intact

---

**Contract Version**: 1.0 (Unchanged)
**Last Updated**: 2025-09-29
**Breaking Changes**: None
**Migration Status**: Event recording added, API contract preserved