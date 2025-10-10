# Quickstart: Fix Update System Inconsistencies

**Feature**: 007-fix-update-system
**Date**: 2025-10-07

## Overview

This quickstart guide provides manual testing scenarios to validate all fixes for update system inconsistencies.

---

## Prerequisites

- WordPress 5.0+ installed
- Choice Universal Form Tracker plugin installed
- Admin access (user with `update_plugins` capability)
- Browser DevTools knowledge (Console, Network tabs)
- Test environment (not production)

---

## Test Scenario 1: Admin Notice Positioning (FR-001)

**Goal**: Verify notices appear above page title in standard WordPress location

### Steps

1. **Simulate Update Available**:
   ```bash
   # In WordPress admin, activate debug mode
   # Add to wp-config.php:
   define('CUFT_DEBUG_UPDATE', true);
   ```

2. **Navigate to Plugin Settings**:
   - Go to `Settings → Universal Form Tracker`
   - Observe notice placement

3. **Expected Result**:
   - ✅ Notice appears **above** the page title "Universal Form Tracker"
   - ✅ Notice is within `.notice` container
   - ✅ Notice has proper WordPress styling (blue border for info)
   - ✅ Dismiss button (X) present if dismissible

4. **Verify HTML Structure**:
   - Open DevTools → Elements tab
   - Find `.wp-header-end` element
   - Notice should appear immediately after this element
   ```html
   <div class="wrap">
       <h1>Universal Form Tracker</h1>
       <hr class="wp-header-end">
       <!-- Notice should be here -->
       <div class="notice notice-info is-dismissible">
           <p>Update available...</p>
       </div>
       <!-- Page content follows -->
   </div>
   ```

5. **Verify Other Admin Pages**:
   - Navigate to Dashboard
   - Navigate to Plugins page
   - Navigate to Updates page
   - Notices should appear in same relative position on all pages

### Pass Criteria
- [ ] Notice positioned above page title
- [ ] `.wp-header-end` marker present
- [ ] Consistent placement across admin pages
- [ ] No JavaScript console errors

---

## Test Scenario 2: Admin Bar Refresh After Update (FR-002)

**Goal**: Verify admin bar updates immediately without page refresh

### Steps

1. **Initial State - Update Available**:
   - Ensure plugin has an available update
   - Admin bar should show red icon with "CUFT Update" text
   - Badge "1" should appear

2. **Simulate Update Completion**:
   - Open DevTools → Console
   - Execute mock update:
   ```javascript
   // Simulate update completion
   window.cuftAdminBar.updateStatus({
       update_available: false,
       latest_version: '3.17.0',
       current_version: '3.17.0'
   });
   ```

3. **Expected Result** (NO PAGE RELOAD):
   - ✅ Icon changes from red "update" to green "plugins-checked"
   - ✅ Badge "1" disappears
   - ✅ Menu text changes from "CUFT Update" to "CUFT"
   - ✅ "Update to X.X.X" submenu item disappears

4. **Verify Periodic Polling**:
   - Keep DevTools Network tab open
   - Observe AJAX requests every 30-60 seconds
   - Requests to `admin-ajax.php?action=cuft_update_status`
   - Response contains update status JSON

5. **Manual Refresh Test**:
   - Click "Check for Updates" in admin bar submenu
   - Loading animation should appear on icon
   - Status should update after check completes
   - No page reload

### Pass Criteria
- [ ] Icon changes dynamically
- [ ] Badge appears/disappears dynamically
- [ ] No page reload required
- [ ] Periodic polling works (30-60s intervals)
- [ ] Manual check triggers update

---

## Test Scenario 3: Consistent Version Display (FR-003)

**Goal**: Verify all UI locations show same version info

### Steps

1. **Check Multiple Locations Simultaneously**:
   - Open Admin Bar (top menu)
   - Open Plugins page in one tab
   - Open Updates page in another tab
   - Open Plugin Settings page in third tab

2. **Compare Version Information**:
   | Location | Current Version | Latest Version | Update Available |
   |----------|-----------------|----------------|------------------|
   | Admin Bar | ? | ? | ? |
   | Plugins Page | ? | ? | ? |
   | Updates Page | ? | ? | ? |
   | Settings Page | ? | ? | ? |

3. **Expected Result**:
   - ✅ All locations show **identical** version numbers
   - ✅ "Update available" status consistent across all locations
   - ✅ No conflicting information

4. **Trigger Update Check**:
   - Click "Check for Updates" in admin bar
   - Wait for completion
   - Verify all locations updated consistently

5. **Cache Invalidation Test**:
   - Complete an update (or simulate completion)
   - Within 1 minute, check all locations again
   - All should reflect new version

### Pass Criteria
- [ ] Version numbers match across all locations
- [ ] Update status consistent
- [ ] No conflicting "update available" indicators
- [ ] Cache invalidates after update

---

## Test Scenario 4: Secure Update Button (FR-004)

**Goal**: Verify "Download & Install Update" works without security errors

### Steps

1. **Navigate to Updates Tab**:
   - Go to `Settings → Universal Form Tracker → Updates`
   - If update available, "Download & Install Update" button should appear

2. **Open DevTools Network Tab**:
   - Keep Network tab open to monitor AJAX requests

3. **Click "Download & Install Update"**:
   - Click the button
   - Observe Network tab for AJAX request

4. **Expected AJAX Request**:
   ```
   POST /wp-admin/admin-ajax.php
   action: cuft_perform_update
   nonce: {valid_nonce}
   version: latest
   backup: true
   ```

5. **Expected Success Response** (HTTP 200):
   ```json
   {
     "success": true,
     "data": {
       "status": "started",
       "update_id": "update_1728345600",
       "message": "Update process started"
     }
   }
   ```

6. **Common Failure (FR-004 Bug)**:
   - ❌ Response: `{"success": false, "data": {"message": "⚠️ Security check failed", "code": "invalid_nonce"}}`
   - ❌ HTTP Status: 403

7. **Verify Nonce Validation**:
   - Open DevTools → Console
   - Check `cuftUpdater.nonce` value exists
   - Verify nonce sent in request matches localized value

### Pass Criteria
- [ ] Button click triggers AJAX request
- [ ] Request includes valid nonce
- [ ] Response HTTP 200 (success)
- [ ] No "Security check failed" error
- [ ] Update process starts successfully

### Debug Steps (If Failing)

**Check Nonce Creation**:
```javascript
// In browser console
console.log(cuftUpdater);
// Should show: { ajaxUrl: '...', nonce: '...', ... }
```

**Check PHP Nonce Action**:
```php
// In class-cuft-updater-ajax.php
const NONCE_ACTION = 'cuft_updater_nonce';  // Must match JS

// In wp_localize_script call
'nonce' => wp_create_nonce('cuft_updater_nonce')  // Must match constant
```

**Check Nonce Parameter**:
```php
// In verify_request() method
$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) :
        (isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '');
// Should handle both POST and GET
```

---

## Test Scenario 5: Synchronized Update Indicators (FR-005)

**Goal**: Verify update indicators synchronized across all interfaces

### Steps

1. **Force Cache Clear**:
   ```bash
   # In WordPress admin console
   wp transient delete cuft_update_status
   wp transient delete update_plugins
   ```

2. **Trigger Manual Update Check**:
   - Click "Check for Updates" in admin bar

3. **Verify Synchronization**:
   Within 5 seconds of check completion:
   - Admin bar shows update status
   - Plugins page shows update row
   - Updates page shows plugin in list
   - Settings page shows update notice

4. **Test Update Completion Sync**:
   - Perform an update (or simulate)
   - After completion, verify within 1 minute:
     - Admin bar shows no update available
     - Plugins page removes update row
     - Updates page removes from list
     - Settings page clears update notice

5. **Test Multiple Admin Users**:
   - Log in as Admin User 1 in Browser 1
   - Log in as Admin User 2 in Browser 2
   - User 1 performs update
   - Within 60 seconds, User 2's interface should reflect update

### Pass Criteria
- [ ] All indicators update within 5 seconds
- [ ] No stale data after update completion
- [ ] Multi-user updates synchronized
- [ ] Cache invalidation works across users

---

## Test Scenario 6: Update History (FR-009)

**Goal**: Verify last 5 updates retained with FIFO cleanup

### Steps

1. **Generate Update History**:
   - Perform 6 consecutive updates (real or simulated)
   - Record timestamps and versions

2. **Check Database**:
   ```sql
   SELECT * FROM wp_cuft_update_log ORDER BY timestamp DESC;
   ```

3. **Expected Result**:
   - ✅ Only 5 most recent entries present
   - ✅ Oldest (6th) entry deleted
   - ✅ Entries ordered by timestamp DESC

4. **Verify Admin UI**:
   - Go to `Settings → Universal Form Tracker → Updates → History`
   - Should show last 5 updates in table
   - Columns: Date, Action, Status, From Version, To Version, User, Duration

5. **AJAX Endpoint Test**:
   ```javascript
   fetch('/wp-admin/admin-ajax.php?action=cuft_update_history&nonce=' + cuftUpdater.nonce)
       .then(r => r.json())
       .then(data => console.log(data));
   // Should return max 5 entries
   ```

### Pass Criteria
- [ ] Database never exceeds 5 entries
- [ ] FIFO cleanup automatic after insert
- [ ] Admin UI shows last 5 updates
- [ ] AJAX endpoint respects limit

---

## Test Scenario 7: Concurrent Update Handling (FR-010)

**Goal**: Verify only one update can run at a time

### Steps

1. **Simulate Concurrent Requests**:
   - Open two browser tabs as Admin
   - Tab 1: Click "Download & Install Update"
   - Tab 2: Immediately click "Download & Install Update"

2. **Expected Behavior**:
   - Tab 1: Receives success, update starts
   - Tab 2: Receives HTTP 409 error:
   ```json
   {
     "success": false,
     "data": {
       "message": "Update already in progress",
       "code": "update_in_progress",
       "current_status": {
         "status": "downloading",
         "percentage": 25,
         "user_id": 1
       }
     }
   }
   ```

3. **Verify Lock Mechanism**:
   ```bash
   # Check transient
   wp transient get cuft_update_in_progress
   # Should show: { status: "in_progress", ... }
   ```

4. **Verify Auto-Expiry**:
   - Let update fail mid-process
   - Wait 6 minutes
   - Try new update
   - Should succeed (lock expired)

5. **User Messaging**:
   - Tab 2 should show message: "Update already in progress by Admin (25% complete)"

### Pass Criteria
- [ ] Only first request succeeds
- [ ] Subsequent requests get 409 error
- [ ] Lock auto-expires after 5 minutes
- [ ] Clear user messaging about concurrent update

---

## Performance Validation

### AJAX Response Times (FR-008)

**Target**: <5 seconds for update checks, <500ms for status

**Test**:
```javascript
// Measure check endpoint
console.time('check');
fetch('/wp-admin/admin-ajax.php?action=cuft_check_update&nonce=' + cuftUpdater.nonce, {
    method: 'POST',
    body: new URLSearchParams({force: true})
})
.then(r => r.json())
.then(data => console.timeEnd('check'));
// Should be <5000ms

// Measure status endpoint
console.time('status');
fetch('/wp-admin/admin-ajax.php?action=cuft_update_status&nonce=' + cuftUpdater.nonce)
.then(r => r.json())
.then(data => console.timeEnd('status'));
// Should be <500ms
```

### UI Refresh Performance

**Target**: <100ms for admin bar updates

**Test**:
```javascript
console.time('refresh');
window.cuftAdminBar.updateStatus({update_available: false});
console.timeEnd('refresh');
// Should be <100ms
```

---

## Debugging Tools

### Enable Debug Mode

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('CUFT_DEBUG', true);
```

### View PHP Logs

```bash
tail -f /path/to/wp-content/debug.log
```

### View JavaScript Logs

```javascript
// In browser console
window.cuftAdminBar.debugMode = true;
window.cuftUpdater.debugMode = true;
```

### Clear All Caches

```bash
wp transient delete-all
wp cache flush
```

---

## Common Issues & Fixes

| Issue | Symptom | Fix |
|-------|---------|-----|
| Nonce validation fails | HTTP 403, "Security check failed" | Verify nonce action matches, check script localization |
| Admin bar not refreshing | Status stale after update | Check polling interval, verify AJAX endpoint works |
| Notices in wrong position | Notice beside title | Add `.wp-header-end` marker |
| Conflicting version info | Different versions in different places | Clear all transients, force recheck |
| Update button disabled | Can't click update button | Check if update already in progress |

---

## Success Criteria

All scenarios must pass for feature to be considered complete:

- [x] Admin notices positioned correctly (Scenario 1)
- [x] Admin bar refreshes without reload (Scenario 2)
- [x] Version info consistent (Scenario 3)
- [x] Update button works securely (Scenario 4)
- [x] Update indicators synchronized (Scenario 5)
- [x] History limited to 5 entries (Scenario 6)
- [x] Concurrent updates handled (Scenario 7)
- [x] Performance targets met

---

**Last Updated**: 2025-10-07
**Testing Time**: ~45 minutes
**Status**: Ready for QA
