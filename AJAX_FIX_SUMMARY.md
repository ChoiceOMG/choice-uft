# CUFT AJAX Endpoint Fix Summary

## Problem Solved
The AJAX endpoint for recording click tracking events was failing with "Security check failed" even after commenting out the nonce validation code.

## Root Cause
There were **duplicate AJAX handlers** registered for the same action (`cuft_record_event`):
1. `CUFT_Admin::ajax_record_event()` - Registered first, using nonce `cuft_ajax_nonce`
2. `CUFT_Event_Recorder::record_event()` - Registered second, using nonce `cuft-event-recorder`

WordPress was calling the first handler (from CUFT_Admin), which was checking for a different nonce than what was being provided by the frontend JavaScript.

## Solution Applied
1. **Removed duplicate AJAX handlers** from `class-cuft-admin.php` (lines 21-22)
2. **Enabled feature flags** for `click_event_tracking` and `enhanced_admin_interface`
3. **Re-enabled nonce validation** in `class-cuft-event-recorder.php`

## Files Modified
- `/home/r11/dev/choice-uft/includes/class-cuft-admin.php` - Commented out duplicate AJAX action hooks
- `/home/r11/dev/choice-uft/includes/ajax/class-cuft-event-recorder.php` - Re-enabled nonce validation

## Database Status
✅ Migration completed successfully:
- `events` column exists (JSON type)
- `idx_date_updated` index exists
- Events are being recorded correctly

## Testing Instructions

### Browser Console Test
1. Visit http://localhost:8080/event-tracking-test/ (or any page where the plugin is active)
2. Open browser DevTools Console (F12)
3. Run the test script from `/home/r11/dev/choice-uft/test-ajax-endpoint.js`

Or run this quick test:
```javascript
// Quick test in browser console
const testClickId = 'browser_test_' + Date.now();
fetch(cuftConfig.ajaxUrl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'cuft_record_event',
        nonce: cuftConfig.nonce,
        click_id: testClickId,
        event_type: 'phone_click'
    })
})
.then(r => r.json())
.then(d => console.log('Result:', d));
```

### Verify in Database
```bash
# Check recent events
docker exec wp-pdev-db mysql -u wordpress -pwordpress wordpress -e \
"SELECT click_id, events, date_updated FROM wp_cuft_click_tracking \
WHERE events IS NOT NULL AND events != '[]' \
ORDER BY date_updated DESC LIMIT 5;" 2>&1 | grep -v "Using a password"
```

### Check Debug Logs
```bash
# View recent debug entries
docker exec wp-pdev-wordpress tail -20 /var/www/html/wp-content/debug.log
```

## Success Indicators
✅ AJAX endpoint returns: `{"success":true,"data":{"message":"Event recorded successfully"...}}`
✅ Events appear in database with proper JSON structure
✅ Debug logs show successful nonce verification
✅ Multiple event types work (phone_click, email_click, form_submit, generate_lead)

## Next Steps
Continue with the quickstart validation testing:
1. Test phone/email link tracking
2. Test form submission events
3. Test event deduplication
4. Test admin interface display
5. Test webhook API compatibility

## Notes
- Feature flags must be enabled for event tracking to work
- The nonce action is `cuft-event-recorder` (not `cuft_ajax_nonce`)
- Events are stored as JSON arrays in the `events` column
- Each event includes timestamp in ISO 8601 format