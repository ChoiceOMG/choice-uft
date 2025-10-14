# Contract Testing Guide: Force Install Update

**Feature**: 009-force-install-update
**Phase**: 5 - Contract Testing Layer
**Tasks**: T019, T020, T021
**Date**: 2025-10-14

---

## Overview

This guide provides step-by-step instructions for manually validating the three AJAX endpoints against their contract specifications. These tests ensure that the implementation matches the contracts defined in `contracts/ajax-endpoints.md`.

**Prerequisites**:
- Docker environment running (`docker-compose up -d`)
- WordPress accessible at http://localhost:8080
- Logged in as Administrator
- Browser DevTools available (Chrome/Firefox recommended)

**Test Environment**:
- Navigate to: http://localhost:8080/wp-admin/options-general.php?page=choice-universal-form-tracker
- Click on "Force Update" tab
- Open Browser DevTools (F12) → Network tab

---

## T019: Contract Test - cuft_check_updates Endpoint

### Test Case 1: Valid request with update available

**Goal**: Verify successful update check when newer version exists on GitHub

**Steps**:
1. Open Network tab in DevTools
2. Click "Check for Updates" button
3. Observe AJAX request to `admin-ajax.php?action=cuft_check_updates`
4. Examine response

**Expected Result**:
```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "<newer>",
    "update_available": true,
    "release_date": "<date>",
    "changelog_summary": "<text>",
    "download_url": "https://github.com/ChoiceOMG/choice-uft/releases/download/...",
    "last_check": <timestamp>,
    "message": "A new version (...) is available!"
  }
}
```

**Validation**:
- [ ] HTTP status is 200 OK
- [ ] `success` is `true`
- [ ] `update_available` is `true`
- [ ] `latest_version` is greater than `installed_version`
- [ ] `release_date` is present
- [ ] `message` is user-friendly

---

### Test Case 2: Valid request with no update

**Goal**: Verify response when plugin is already at latest version

**Steps**:
1. Ensure plugin is at latest version (run force reinstall if needed)
2. Click "Check for Updates" button
3. Examine response

**Expected Result**:
```json
{
  "success": true,
  "data": {
    "installed_version": "3.18.0",
    "latest_version": "3.18.0",
    "update_available": false,
    "last_check": <timestamp>,
    "message": "Plugin is up to date (version 3.18.0)"
  }
}
```

**Validation**:
- [ ] HTTP status is 200 OK
- [ ] `update_available` is `false`
- [ ] `installed_version` equals `latest_version`
- [ ] Message confirms "up to date"

---

### Test Case 3: Invalid nonce

**Goal**: Verify CSRF protection

**Steps**:
1. Open DevTools Console
2. Execute:
   ```javascript
   jQuery.post(ajaxurl, {
     action: 'cuft_check_updates',
     nonce: 'invalid_nonce_12345'
   }, function(response) {
     console.log(response);
   });
   ```
3. Examine response

**Expected Result**:
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Validation**:
- [ ] HTTP status is 403 Forbidden
- [ ] `success` is `false`
- [ ] `error_code` is `"invalid_nonce"`
- [ ] Error message is clear

---

### Test Case 4: Non-admin user

**Goal**: Verify capability check prevents unauthorized access

**Steps**:
1. Log out from WordPress admin
2. Create or log in as Editor or Subscriber user
3. Attempt to access Settings → Universal Form Tracker
4. Alternative test via WP-CLI:
   ```bash
   docker exec wp-pdev-cli wp user create testuser test@example.com --role=editor --user_pass=testpass
   ```

**Expected Result**:
- Page should not be accessible to non-admin users, OR
- Buttons should be non-functional, OR
- AJAX request returns 403 with `error_code: "insufficient_permissions"`

**Validation**:
- [ ] Non-admin users cannot execute update checks
- [ ] Capability check enforced
- [ ] Error message appropriate

**Cleanup**:
```bash
docker exec wp-pdev-cli wp user delete testuser --yes
```

---

### Test Case 5: GitHub API timeout

**Goal**: Verify graceful handling of slow/unavailable GitHub API

**Note**: Difficult to simulate without network manipulation

**Code Review Validation**:
1. Open `/home/r11/dev/choice-uft/includes/class-cuft-force-update-handler.php`
2. Verify timeout logic exists in `handle_check_updates()` method
3. Confirm 5-second timeout enforced
4. Verify timeout error response structure

**Expected Behavior** (from code):
- Operation should timeout after 5 seconds
- Error response with `error_code: "github_timeout"`
- Message: "GitHub API did not respond within 5 seconds..."
- Last known version cached and returned if available

**Validation**:
- [ ] Code implements 5-second timeout
- [ ] Timeout error handled gracefully
- [ ] User can retry after timeout

---

### Test Case 6: GitHub rate limited

**Goal**: Verify handling when GitHub API rate limit exceeded

**Note**: Requires 60+ requests within 1 hour to trigger

**Simulation** (optional):
- Make 60+ manual update checks rapidly
- Or review code for rate limit handling

**Expected Result**:
```json
{
  "success": false,
  "data": {
    "error_code": "rate_limited",
    "message": "GitHub API rate limit exceeded. Showing cached result.",
    "installed_version": "3.18.0",
    "last_known_version": "3.19.0",
    "update_available": true,
    "last_check": <timestamp>,
    "cached": true
  }
}
```

**Validation**:
- [ ] Cached results returned when rate limited
- [ ] `cached: true` flag present
- [ ] User informed of rate limit
- [ ] Last check timestamp shown

---

## T020: Contract Test - cuft_force_reinstall Endpoint

### Test Case 1: Valid request with sufficient space

**Goal**: Verify successful force reinstall operation

**Steps**:
1. Open Network tab in DevTools
2. Click "Force Reinstall Latest Version" button
3. Confirm dialog
4. Wait for completion (max 60 seconds)
5. Examine response

**Expected Result**:
```json
{
  "success": true,
  "data": {
    "message": "Plugin successfully reinstalled to version X.Y.Z",
    "source_version": "<old>",
    "target_version": "<new>",
    "duration_seconds": <number>,
    "backup_location": "<path>",
    "backup_deleted": true
  }
}
```

**Post-Operation Validation**:
```bash
# Verify backup was deleted
docker exec wp-pdev-wordpress ls -la /var/www/html/wp-content/uploads/cuft-backups/

# Verify plugin version updated
docker exec wp-pdev-cli wp plugin list --field=version --name=choice-uft

# Verify plugin still active
docker exec wp-pdev-cli wp plugin status choice-uft

# Test form tracking still works
# Visit: http://localhost:8080/cuft-test-forms/
# Submit form, check dataLayer in console
```

**Validation**:
- [ ] HTTP status is 200 OK
- [ ] Plugin version updated (if newer version available)
- [ ] Backup ZIP deleted from filesystem
- [ ] Plugin remains active
- [ ] Form tracking functionality works
- [ ] Settings preserved (GTM ID, etc.)

---

### Test Case 2: Invalid nonce

**Goal**: Verify CSRF protection on force reinstall

**Steps**:
1. Open DevTools Console
2. Execute:
   ```javascript
   jQuery.post(ajaxurl, {
     action: 'cuft_force_reinstall',
     nonce: 'invalid_nonce_12345'
   }, function(response) {
     console.log(response);
   });
   ```
3. Examine response

**Expected Result**:
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Validation**:
- [ ] HTTP status is 403 Forbidden
- [ ] `error_code` is `"invalid_nonce"`
- [ ] No reinstall occurred

---

### Test Case 3: Non-admin user

**Goal**: Verify capability check prevents unauthorized reinstalls

**Steps**:
1. Create test user with Editor role
2. Log in as that user
3. Attempt to access Force Update tab or execute AJAX directly

**Expected Result**:
- 403 Forbidden with `error_code: "insufficient_permissions"`

**Validation**:
- [ ] Non-admin users cannot force reinstall
- [ ] Appropriate error message displayed

---

### Test Case 4: DISALLOW_FILE_MODS enabled

**Goal**: Verify respect for WordPress file modification lockdown

**Steps**:
1. Edit Docker WordPress config:
   ```bash
   docker exec wp-pdev-wordpress bash -c "echo \"define( 'DISALLOW_FILE_MODS', true );\" >> /var/www/html/wp-config.php"
   ```
2. Reload Force Update tab in browser
3. Observe "Force Reinstall" button state
4. Attempt AJAX call via console

**Expected Result**:
- Button should be disabled in UI
- AJAX call returns: 403 Forbidden, `error_code: "file_mods_disabled"`

**Cleanup**:
```bash
docker exec wp-pdev-wordpress bash -c "sed -i '/DISALLOW_FILE_MODS/d' /var/www/html/wp-config.php"
```

**Validation**:
- [ ] Button disabled when DISALLOW_FILE_MODS is true
- [ ] AJAX request blocked with appropriate error
- [ ] Error message explains limitation

---

### Test Case 5: Insufficient disk space

**Goal**: Verify disk space validation prevents broken installations

**Note**: Difficult to simulate low disk space in Docker

**Code Review Validation**:
1. Open `/home/r11/dev/choice-uft/includes/class-cuft-disk-space-validator.php`
2. Verify `validate_space_for_reinstall()` checks for 3x plugin size
3. Confirm error response structure

**Expected Behavior**:
- Validation checks available space before backup attempt
- Requires 3x plugin size (backup + download + extraction)
- Returns 507 status with space requirements

**Validation**:
- [ ] Code implements 3x size validation
- [ ] Error includes required MB and available MB
- [ ] Operation aborts before creating backup

---

### Test Case 6: Concurrent operation

**Goal**: Verify locking prevents simultaneous updates

**Steps**:
1. Open Force Update tab in TWO browser windows (same user)
2. Click "Force Reinstall" in Window 1
3. **Immediately** click "Force Reinstall" in Window 2

**Expected Result**:
- Window 1: Proceeds normally
- Window 2: Returns 409 Conflict
  ```json
  {
    "success": false,
    "data": {
      "error_code": "operation_in_progress",
      "message": "Another update operation is already in progress. Please wait."
    }
  }
  ```

**Verification**:
```bash
# Check lock transient exists during operation
docker exec wp-pdev-cli wp transient get cuft_force_update_lock
```

**Validation**:
- [ ] Second request fails immediately with 409 status
- [ ] Lock transient created during operation
- [ ] Lock released after first operation completes
- [ ] Second request can retry successfully after completion

---

### Test Case 7: Backup creation fails

**Goal**: Verify graceful handling when backup cannot be created

**Note**: Difficult to simulate permission issues in Docker

**Code Review Validation**:
1. Review backup logic in `handle_force_reinstall()`
2. Verify backup failure is caught and handled
3. Confirm operation aborts if backup fails

**Expected Behavior**:
- 500 status, `error_code: "backup_failed"`
- Error details include failure reason
- No reinstall attempted without backup

**Validation**:
- [ ] Backup failure prevents reinstall
- [ ] Error message clear
- [ ] Plugin remains untouched

---

### Test Case 8: Installation fails, rollback succeeds

**Goal**: Verify automatic rollback on installation failure

**Note**: Very difficult to simulate installation failure

**Code Review Validation**:
1. Review rollback logic in `handle_force_reinstall()`
2. Verify restoration from backup on installation failure
3. Confirm previous version restored

**Expected Behavior**:
- 500 status, `error_code: "installation_failed"`
- `rollback_successful: true`
- Source version restored from backup
- Plugin remains functional

**Validation**:
- [ ] Rollback mechanism exists in code
- [ ] Backup restored on failure
- [ ] Error details logged to history

---

### Test Case 9: Operation exceeds 60s timeout

**Goal**: Verify timeout enforcement prevents hung operations

**Note**: Difficult to simulate 60s timeout

**Code Review Validation**:
1. Verify timeout logic in `handle_force_reinstall()`
2. Confirm 60-second maximum enforced
3. Check timeout error response structure

**Expected Behavior**:
- 504 status, `error_code: "operation_timeout"`
- Message: "Operation exceeded 60 second timeout..."
- Plugin remains at previous version

**Validation**:
- [ ] Code implements 60-second timeout
- [ ] Timeout error handled gracefully
- [ ] Plugin stability preserved

---

## T021: Contract Test - cuft_get_update_history Endpoint

### Test Case 1: Valid request with history

**Goal**: Verify history retrieval after operations

**Prerequisites**:
- Perform at least one update check
- Perform at least one force reinstall

**Steps**:
1. Load Force Update tab
2. Observe history table populated on page load
3. Open Network tab, find AJAX request `action=cuft_get_update_history`
4. Examine response

**Expected Result**:
```json
{
  "success": true,
  "data": {
    "history": [
      {
        "operation_type": "force_reinstall" | "manual_check",
        "user_display_name": "Admin",
        "timestamp": <unix_timestamp>,
        "timestamp_formatted": "2025-10-14 HH:MM:SS",
        "status": "complete" | "failed",
        "details": { ... }
      }
    ],
    "count": <number>,
    "max_entries": 5
  }
}
```

**Validation**:
- [ ] HTTP status is 200 OK
- [ ] Most recent entry appears first (descending order)
- [ ] Maximum 5 entries shown
- [ ] Timestamps formatted correctly
- [ ] Details appropriate for operation type
- [ ] User display name shown

---

### Test Case 2: Valid request with no history

**Goal**: Verify response when history is empty

**Steps**:
1. Clear history:
   ```bash
   docker exec wp-pdev-cli wp option delete cuft_update_log
   ```
2. Reload Force Update tab
3. Examine history AJAX response

**Expected Result**:
```json
{
  "success": true,
  "data": {
    "history": [],
    "count": 0,
    "max_entries": 5,
    "message": "No update operations in history yet."
  }
}
```

**Validation**:
- [ ] HTTP status is 200 OK
- [ ] `history` is empty array
- [ ] `count` is 0
- [ ] Message indicates no history

---

### Test Case 3: Invalid nonce

**Goal**: Verify CSRF protection on history endpoint

**Steps**:
1. Open DevTools Console
2. Execute:
   ```javascript
   jQuery.post(ajaxurl, {
     action: 'cuft_get_update_history',
     nonce: 'invalid_nonce_12345'
   }, function(response) {
     console.log(response);
   });
   ```
3. Examine response

**Expected Result**:
```json
{
  "success": false,
  "data": {
    "error_code": "invalid_nonce",
    "message": "Security check failed. Please refresh the page and try again."
  }
}
```

**Validation**:
- [ ] HTTP status is 403 Forbidden
- [ ] `error_code` is `"invalid_nonce"`

---

### Test Case 4: Non-admin user

**Goal**: Verify capability check prevents unauthorized history access

**Steps**:
1. Create test user with Editor role
2. Log in as that user
3. Attempt to access history

**Expected Result**:
- 403 Forbidden with `error_code: "insufficient_permissions"`

**Validation**:
- [ ] Non-admin users cannot view history
- [ ] Appropriate error message

---

## Test Results Summary

### T019: cuft_check_updates Endpoint

| Test Case | Status | Notes |
|-----------|--------|-------|
| TC1: Valid request (update available) | ☐ Pass ☐ Fail | |
| TC2: Valid request (no update) | ☐ Pass ☐ Fail | |
| TC3: Invalid nonce | ☐ Pass ☐ Fail | |
| TC4: Non-admin user | ☐ Pass ☐ Fail | |
| TC5: GitHub API timeout | ☐ Code Review | |
| TC6: GitHub rate limited | ☐ Code Review | |

### T020: cuft_force_reinstall Endpoint

| Test Case | Status | Notes |
|-----------|--------|-------|
| TC1: Valid request | ☐ Pass ☐ Fail | |
| TC2: Invalid nonce | ☐ Pass ☐ Fail | |
| TC3: Non-admin user | ☐ Pass ☐ Fail | |
| TC4: DISALLOW_FILE_MODS | ☐ Pass ☐ Fail | |
| TC5: Insufficient disk space | ☐ Code Review | |
| TC6: Concurrent operation | ☐ Pass ☐ Fail | |
| TC7: Backup creation fails | ☐ Code Review | |
| TC8: Installation fails, rollback | ☐ Code Review | |
| TC9: Operation timeout | ☐ Code Review | |

### T021: cuft_get_update_history Endpoint

| Test Case | Status | Notes |
|-----------|--------|-------|
| TC1: Valid request with history | ☐ Pass ☐ Fail | |
| TC2: Valid request no history | ☐ Pass ☐ Fail | |
| TC3: Invalid nonce | ☐ Pass ☐ Fail | |
| TC4: Non-admin user | ☐ Pass ☐ Fail | |

---

## Troubleshooting

### Issue: AJAX requests return 0 or empty response

**Solution**:
```bash
# Check PHP error logs
docker logs wp-pdev-wordpress | grep -i error

# Check WordPress debug log
docker exec wp-pdev-cli wp config get WP_DEBUG
docker exec wp-pdev-cli wp config set WP_DEBUG true
```

### Issue: Transient lock not releasing

**Solution**:
```bash
# Manually delete stuck lock
docker exec wp-pdev-cli wp transient delete cuft_force_update_lock
```

### Issue: History not appearing

**Solution**:
```bash
# Check if history option exists
docker exec wp-pdev-cli wp option get cuft_update_log --format=json

# Check if cron job scheduled
docker exec wp-pdev-cli wp cron event list | grep cuft
```

---

## Completion Criteria

**Phase 5 is complete when**:
- ✓ All executable test cases (TC1-4 for each endpoint) have been run
- ✓ All code review test cases (TC5-9) have been validated in source code
- ✓ All response structures match `contracts/ajax-endpoints.md` exactly
- ✓ All HTTP status codes correct (200, 403, 409, 422, 500, 502, 504, 507)
- ✓ All error codes match specification
- ✓ User feedback messages are clear and actionable

**Next Phase**: Phase 6 - Manual Validation (T022-T027) - Quickstart Scenarios

---

**Testing Notes**:
- Document any deviations from expected behavior
- Capture screenshots of UI for future reference
- Save Network tab HAR files for debugging
- Report any contract violations as implementation bugs

**Date Completed**: ____________
**Tester**: ____________
**Overall Result**: ☐ All Tests Pass ☐ Issues Found (see notes)
