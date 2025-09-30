# Quickstart: Click Tracking Events Migration

**Feature**: Click Tracking Events Migration
**Version**: 3.12.0
**Estimated Time**: 15 minutes

## Purpose
This quickstart guide validates that the click tracking events migration works correctly in a development environment.

## Prerequisites

### Environment Setup
- ✅ WordPress development environment running (wp-pdev containers)
- ✅ Choice UFT plugin installed and activated
- ✅ MySQL 5.7+ (check via `docker exec wp-pdev-cli wp db version`)
- ✅ Plugin version 3.12.0+ installed

### Test Data Required
- At least one test form (Elementor, CF7, Ninja, Gravity, or Avada)
- Browser with DevTools Console access
- Test click_id (can be generated via URL parameter)

## Step 1: Verify Database Migration

### Check Table Schema
```bash
docker exec wp-pdev-cli wp db query "DESCRIBE wp_cuft_click_tracking" --skip-column-names
```

**Expected Output**:
```
id	bigint(20) unsigned	NO	PRI	NULL	auto_increment
click_id	varchar(255)	NO	UNI	NULL
campaign	varchar(255)	YES		NULL
utm_medium	varchar(255)	YES		NULL
utm_campaign	varchar(255)	YES		NULL
utm_term	varchar(255)	YES		NULL
utm_content	varchar(255)	YES		NULL
events	json	YES		NULL
qualified	tinyint(1)	YES		0
score	int(11)	YES		0
date_created	datetime	NO		CURRENT_TIMESTAMP
date_updated	datetime	NO		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP
ip_address	varchar(45)	YES		NULL
user_agent	text	YES		NULL
additional_data	longtext	YES		NULL
```

**✅ Pass Criteria**: `events` column exists with type `json`

### Check Indexes
```bash
docker exec wp-pdev-cli wp db query "SHOW INDEXES FROM wp_cuft_click_tracking WHERE Key_name='idx_date_updated'" --skip-column-names
```

**Expected Output**:
```
wp_cuft_click_tracking	0	idx_date_updated	1	date_updated	A	...
```

**✅ Pass Criteria**: Index `idx_date_updated` exists on `date_updated` column

### Verify Deprecated Columns Removed
```bash
docker exec wp-pdev-cli wp db query "DESCRIBE wp_cuft_click_tracking" --skip-column-names | grep -E "platform|utm_source"
```

**Expected Output**: Empty (no results)

**✅ Pass Criteria**: `platform` and `utm_source` columns do not exist

## Step 2: Test AJAX Endpoint

### Enable Debug Mode
```bash
docker exec wp-pdev-cli wp option update cuft_debug_mode 1
```

### Test Event Recording via Browser Console

1. Open WordPress site: http://localhost:8080/
2. Open DevTools Console (F12)
3. Execute test script:

```javascript
// Test event recording
const testClickId = 'test_' + Date.now();

fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
        action: 'cuft_record_event',
        nonce: cuftConfig.nonce,
        click_id: testClickId,
        event_type: 'phone_click'
    })
})
.then(response => response.json())
.then(data => {
    console.log('Event recording response:', data);
    if (data.success) {
        console.log('✅ Event recorded successfully');
    } else {
        console.error('❌ Event recording failed:', data.data.message);
    }
});
```

**Expected Console Output**:
```
Event recording response: {success: true, data: {message: "Event recorded successfully", click_id: "test_1234567890", event_type: "phone_click", event_count: 1}}
✅ Event recorded successfully
```

**✅ Pass Criteria**:
- Response `success: true`
- Message indicates event recorded
- Event count returned

### Verify Event in Database
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, events FROM wp_cuft_click_tracking WHERE click_id LIKE 'test_%' ORDER BY date_created DESC LIMIT 1" --skip-column-names
```

**Expected Output**:
```
test_1234567890	[{"event": "phone_click", "timestamp": "2025-09-29T12:00:00Z"}]
```

**✅ Pass Criteria**: Events column contains JSON array with recorded event

## Step 3: Test Phone/Email Link Tracking

### Setup Test Page
```bash
docker exec wp-pdev-cli wp post create \
  --post_type=page \
  --post_title="Event Tracking Test" \
  --post_content='<a href="tel:+1234567890" class="cuft-track-phone">Call Us</a> | <a href="mailto:test@example.com" class="cuft-track-email">Email Us</a>' \
  --post_status=publish
```

### Test Phone Click Event

1. Navigate to test page
2. Add click_id to URL: `http://localhost:8080/event-tracking-test/?click_id=phone_test_123`
3. Open DevTools Console
4. Click "Call Us" link
5. Check console for event logging

**Expected Console Output** (if debug mode enabled):
```
[CUFT] Phone click detected: +1234567890
[CUFT] Recording phone_click event for click_id: phone_test_123
```

**✅ Pass Criteria**: Console shows event recording initiated

### Verify Phone Click in Database
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, events FROM wp_cuft_click_tracking WHERE click_id='phone_test_123'" --skip-column-names
```

**Expected Output**:
```
phone_test_123	[{"event": "phone_click", "timestamp": "2025-09-29T12:05:00Z"}]
```

**✅ Pass Criteria**: phone_click event recorded with timestamp

## Step 4: Test Form Submission Events

### Test with Existing Test Form

1. Navigate to test form page: http://localhost:8080/cuft-test-forms/
2. Add click_id to URL: `http://localhost:8080/cuft-test-forms/?click_id=form_test_456`
3. Fill out and submit any test form
4. Open DevTools Console
5. Check for form_submit event in dataLayer

**Expected Console Output**:
```
dataLayer push: {
  event: "form_submit",
  form_type: "elementor",
  form_id: "...",
  cuft_tracked: true,
  cuft_source: "elementor_pro",
  ...
}
```

### Verify Form Submit in Database
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, JSON_EXTRACT(events, '$[*].event') as event_types FROM wp_cuft_click_tracking WHERE click_id='form_test_456'" --skip-column-names
```

**Expected Output**:
```
form_test_456	["form_submit"]
```

**✅ Pass Criteria**: form_submit event recorded

## Step 5: Test Event Deduplication

### Record Duplicate Event
```javascript
// In browser console
const clickId = 'dedup_test_789';

// Record first phone_click
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'cuft_record_event',
        nonce: cuftConfig.nonce,
        click_id: clickId,
        event_type: 'phone_click'
    })
}).then(() => {
    console.log('First phone_click recorded');

    // Wait 2 seconds, then record duplicate
    setTimeout(() => {
        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'cuft_record_event',
                nonce: cuftConfig.nonce,
                click_id: clickId,
                event_type: 'phone_click'
            })
        }).then(() => console.log('Duplicate phone_click recorded'));
    }, 2000);
});
```

### Verify Deduplication
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, events FROM wp_cuft_click_tracking WHERE click_id='dedup_test_789'" --skip-column-names
```

**Expected Output**:
```
dedup_test_789	[{"event": "phone_click", "timestamp": "2025-09-29T12:10:05Z"}]
```

**✅ Pass Criteria**:
- Only ONE phone_click event exists
- Timestamp is the LATEST occurrence (not first)

## Step 6: Test Admin Interface

### View Events in Admin

1. Navigate to admin: http://localhost:8080/wp-admin/
2. Go to: Settings → Universal Form Tracker → Click Tracking
3. Find test records in table

**Expected Display**:
- Events column shows timeline of events
- Each event shows type and timestamp
- Events are sorted chronologically

**✅ Pass Criteria**: Events displayed correctly in admin table

### Test Event Filtering
1. Use event type filter dropdown
2. Select "Phone Clicks" filter
3. Verify only records with phone_click events shown

**✅ Pass Criteria**: Filtering works correctly

## Step 7: Test Webhook API Compatibility

### Test Webhook Update
```bash
# Get webhook key
WEBHOOK_KEY=$(docker exec wp-pdev-cli wp option get cuft_webhook_key --format=json | tr -d '"')

# Update via webhook
curl "http://localhost:8080/cuft-webhook/?key=$WEBHOOK_KEY&click_id=webhook_test_999&qualified=1&score=8"
```

**Expected Output**:
```json
{
  "success": true,
  "message": "Click tracking updated successfully",
  "click_id": "webhook_test_999",
  "qualified": 1,
  "score": 8
}
```

**✅ Pass Criteria**: Webhook API returns success (unchanged behavior)

### Verify Webhook Event Recorded
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, qualified, score, events FROM wp_cuft_click_tracking WHERE click_id='webhook_test_999'" --skip-column-names
```

**Expected Output**:
```
webhook_test_999	1	8	[{"event": "status_qualified", "timestamp": "2025-09-29T12:15:00Z"}]
```

**✅ Pass Criteria**:
- qualified and score updated correctly
- status_qualified event recorded (new behavior)

## Step 8: Test FIFO Event Cleanup (Optional)

### Generate 100+ Events
```javascript
// In browser console
const clickId = 'fifo_test_100';

async function recordMultipleEvents(count) {
    for (let i = 0; i < count; i++) {
        await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'cuft_record_event',
                nonce: cuftConfig.nonce,
                click_id: clickId,
                event_type: i % 2 === 0 ? 'phone_click' : 'email_click'
            })
        });

        if (i % 10 === 0) {
            console.log(`Recorded ${i} events`);
        }
    }
    console.log('✅ All events recorded');
}

recordMultipleEvents(105);
```

### Verify Event Limit
```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, JSON_LENGTH(events) as event_count FROM wp_cuft_click_tracking WHERE click_id='fifo_test_100'" --skip-column-names
```

**Expected Output**:
```
fifo_test_100	100
```

**✅ Pass Criteria**: Event count capped at 100 (FIFO cleanup triggered)

## Rollback Testing (If Needed)

### Test Hybrid Rollback
```bash
# Trigger rollback (if migration class implements down() method)
docker exec wp-pdev-cli wp eval "CUFT_Migration_3_12_0::down();"
```

### Verify Rollback
```bash
# Check events column removed
docker exec wp-pdev-cli wp db query "DESCRIBE wp_cuft_click_tracking" --skip-column-names | grep events
```

**Expected Output**: Empty (events column removed)

### Verify Data Preserved
```bash
# Check qualified/score values preserved
docker exec wp-pdev-cli wp db query "SELECT click_id, qualified, score FROM wp_cuft_click_tracking WHERE click_id='webhook_test_999'" --skip-column-names
```

**Expected Output**:
```
webhook_test_999	1	8
```

**✅ Pass Criteria**: Business-critical data preserved during rollback

## Success Criteria Summary

All tests must pass:
- [x] Database schema updated correctly
- [x] AJAX endpoint records events
- [x] Phone/email link tracking works
- [x] Form submission events recorded
- [x] Event deduplication works
- [x] Admin interface displays events
- [x] Webhook API remains compatible
- [x] FIFO cleanup enforces 100-event limit

## Troubleshooting

### Events Not Recording
- Check debug mode enabled: `wp option get cuft_debug_mode`
- Verify nonce available in JavaScript: `console.log(cuftConfig.nonce)`
- Check browser console for JavaScript errors
- Verify AJAX endpoint registered: `wp option get cuft_click_event_tracking_enabled`

### Admin Interface Not Showing Events
- Check feature flag: `wp option get cuft_click_event_tracking_display`
- Clear WordPress cache
- Check for JavaScript errors in admin
- Verify events column has data in database

### Performance Issues
- Check MySQL query times: Enable slow query log
- Verify indexes exist: `SHOW INDEXES FROM wp_cuft_click_tracking`
- Monitor JSON_LENGTH operations
- Check for records exceeding 100 events

---

**Quickstart Version**: 1.0
**Last Updated**: 2025-09-29
**Estimated Completion Time**: 15 minutes