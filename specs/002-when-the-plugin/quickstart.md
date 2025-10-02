# Quickstart: Testing Dashboard Manual Validation

**Feature**: Admin Testing Dashboard
**Date**: 2025-09-30

## Purpose

This quickstart guide provides step-by-step manual testing procedures to validate that the Admin Testing Dashboard is functioning correctly.

---

## Prerequisites

- WordPress admin access with `manage_options` capability
- Choice Universal Form Tracker plugin activated
- Browser with developer tools (Chrome DevTools recommended)
- GTM container configured (or dataLayer monitoring enabled)

---

## Test Flow

### 1. Access Dashboard

**Steps**:
1. Log in to WordPress admin
2. Navigate to **Settings → Testing Dashboard**
3. Verify page loads without errors

**Expected Result**:
- ✅ Dashboard page displays
- ✅ No 403/404 errors
- ✅ Modern WordPress admin styling visible

**Failure**: If "Access Denied", check user has `manage_options` capability

---

### 2. Generate Test Data

**Steps**:
1. Open browser DevTools (F12) → Console tab
2. Click **"Generate Sample Data"** button
3. Observe generated data display

**Expected Result**:
- ✅ Request completes in <500ms
- ✅ Test data displayed in UI:
  - Click IDs (gclid, fbclid, etc.)
  - UTM parameters (source, medium, campaign)
  - Test email (format: `test+{id}@example.com`)
  - Test phone (format: `555-01XX`)
- ✅ Console shows: `Test data saved to localStorage`
- ✅ Verify localStorage: `localStorage.getItem('cuft_test_sessions')`

**Failure**: Check browser console for errors, verify AJAX endpoint registered

---

### 3. Simulate Phone Click Event

**Steps**:
1. Ensure test data generated (Step 2)
2. Open DevTools → Console tab
3. Type: `window.dataLayer` (verify array exists)
4. Click **"Simulate Phone Click"** button

**Expected Result**:
- ✅ Response time <500ms
- ✅ Console shows new dataLayer event:
  ```javascript
  {
    event: "phone_click",
    cuft_tracked: true,
    cuft_source: "testing_dashboard",
    test_mode: true,
    phone_number: "555-01XX",
    // ... tracking parameters
  }
  ```
- ✅ Event Viewer (if implemented) shows event in real-time
- ✅ All field names use snake_case (NOT camelCase)

**Validation**:
```javascript
// Run in console
const latestEvent = window.dataLayer[window.dataLayer.length - 1];
console.log('Event:', latestEvent.event);
console.log('CUFT Tracked:', latestEvent.cuft_tracked);
console.log('Test Mode:', latestEvent.test_mode);
```

---

### 4. Simulate Form Submission Event

**Steps**:
1. Click **"Simulate Form Submission"** button
2. Check dataLayer in console

**Expected Result**:
- ✅ Response time <500ms
- ✅ `form_submit` event in dataLayer with:
  - `cuft_tracked: true`
  - `cuft_source: "testing_dashboard"`
  - `test_mode: true`
  - `form_type`: framework name
  - `user_email`: test email
  - `user_phone`: test phone
  - All UTM parameters
  - All click IDs

---

### 5. Validate generate_lead Event

**Steps**:
1. Ensure previous form_submit had email + phone + click_id
2. Check if `generate_lead` event also fired

**Expected Result**:
- ✅ `generate_lead` event present ONLY if all three conditions met:
  1. Email present
  2. Phone present
  3. Click ID present (any type)
- ✅ Event includes `currency: "USD"` and `value: 0`
- ✅ All form_submit fields also included

**Failure**: If generate_lead fires without all three conditions, check FR-011 logic

---

### 6. Build Test Form

**Steps**:
1. Select framework from dropdown (e.g., "Gravity Forms")
2. Click **"Build Test Form"** button
3. Wait for form to render

**Expected Result**:
- ✅ Form renders below controls
- ✅ All fields pre-populated with test data:
  - Name: "Test User"
  - Email: from test session
  - Phone: from test session
  - Message: default text
- ✅ Form uses selected framework's styling

**Gravity Forms** (dynamic creation):
- ✅ New form ID returned
- ✅ Shortcode displayed

**Other Frameworks** (pre-built):
- ✅ Uses predefined form ID
- ✅ Form loads successfully

---

### 7. Submit Test Form

**Steps**:
1. With test form visible, submit it
2. Monitor dataLayer in console

**Expected Result**:
- ✅ Standard framework tracking fires (cuft-{framework}-forms.js)
- ✅ Events include test data from session
- ✅ `form_submit` event matches production format
- ✅ If email + phone + click_id present → `generate_lead` also fires
- ✅ All events have `test_mode: false` (production tracking path)

**Important**: Form submission uses PRODUCTION tracking code (FR-017), not separate test code

---

### 8. Event Viewer Filtering

**Steps**:
1. With multiple events in dataLayer, open Event Viewer
2. Toggle filter: **"Show Test Events Only"**
3. Toggle filter: **"Show All Events"**

**Expected Result**:
- ✅ Test-only mode shows events where `test_mode: true`
- ✅ All events mode shows everything
- ✅ Toggle happens instantly (<500ms)
- ✅ Event count updates correctly

---

### 9. Event Validation

**Steps**:
1. Select an event in Event Viewer
2. Click **"Validate Event"** button

**Expected Result**:
- ✅ Validation runs in <500ms
- ✅ Valid events show green checkmark
- ✅ Invalid events show red X with errors
- ✅ Errors highlight:
  - Missing `cuft_tracked`
  - Missing `cuft_source`
  - Missing `test_mode`
  - camelCase fields (constitutional violation)
  - Wrong data types

**Test Invalid Event**:
```javascript
// Push invalid event to test validator
window.dataLayer.push({
  event: "bad_event",
  formType: "test",  // Should be form_type!
  userEmail: "test@example.com"  // Should be user_email!
});
```

Expected errors:
- Missing cuft_tracked
- Missing cuft_source
- Missing test_mode
- camelCase violation: formType
- camelCase violation: userEmail

---

### 10. Database Test Events

**Steps**:
1. After simulating events, click **"View Test Events"** tab
2. Verify events table displays

**Expected Result**:
- ✅ Events from current session visible
- ✅ Columns: ID, Type, Timestamp, Actions
- ✅ Click ID links to session
- ✅ Event data displayed (JSON or formatted)

**Database Check** (optional):
```sql
SELECT * FROM wp_cuft_test_events ORDER BY created_at DESC LIMIT 10;
```

---

### 11. Clean Up Test Data

**Steps**:
1. Click **"Delete All Test Events"** button
2. Confirm deletion

**Expected Result**:
- ✅ Confirmation dialog appears
- ✅ After confirmation, events deleted from database
- ✅ localStorage cleared: `cuft_test_sessions`
- ✅ Event viewer cleared
- ✅ Success message displayed

---

## Performance Validation

Run these checks to validate NFR-001, NFR-002, NFR-003:

```javascript
// Measure AJAX response time
console.time('generate_data');
// Click "Generate Sample Data"
// Wait for response
console.timeEnd('generate_data');
// Should be < 500ms

console.time('simulate_event');
// Click "Simulate Phone Click"
console.timeEnd('simulate_event');
// Should be < 500ms

console.time('event_validation');
// Click "Validate Event"
console.timeEnd('event_validation');
// Should be < 500ms
```

---

## Troubleshooting

### Issue: "Access Denied"
**Cause**: User lacks `manage_options` capability
**Fix**: Log in as administrator or grant capability

### Issue: "Nonce verification failed"
**Cause**: Page cached or nonce expired
**Fix**: Hard refresh (Ctrl+F5) to reload page

### Issue: dataLayer events not appearing
**Cause**: GTM not loaded or dataLayer not initialized
**Fix**: Check `window.dataLayer` exists, verify GTM container ID

### Issue: Test data not persisting across reloads
**Cause**: localStorage disabled (private browsing)
**Fix**: Exit private mode, or data will be session-only

### Issue: Slow response (>500ms)
**Cause**: Server performance, large dataset, or network latency
**Fix**: Check server logs, database query performance

---

## Success Criteria

✅ All 11 test steps pass
✅ All performance targets met (<500ms)
✅ No JavaScript errors in console
✅ All events use snake_case naming
✅ All events include cuft_tracked, cuft_source, test_mode
✅ Event validation correctly identifies issues
✅ Database test events table populated
✅ Clean up successfully removes all test data

---

## Next Steps

After quickstart validation:
1. Run automated integration tests
2. Test cross-framework compatibility
3. Validate event isolation (test_mode filtering)
4. Performance benchmarking under load
5. Documentation review
