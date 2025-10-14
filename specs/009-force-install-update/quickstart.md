# Quickstart: Force Install Update

**Feature**: 009-force-install-update
**Date**: 2025-10-12
**Purpose**: Manual validation of force update functionality

## Prerequisites

1. WordPress 5.0+ with PHP 7.0+
2. Choice Universal Form Tracker plugin installed and active
3. Administrator account with `update_plugins` capability
4. Internet connection (GitHub API access)
5. At least 3x plugin size free disk space (~150MB minimum)

## Setup

1. Navigate to **Settings → Universal Form Tracker** in WordPress admin
2. Scroll to "Manual Update Control" section
3. Verify two buttons visible: "Check for Updates" and "Force Reinstall Latest Version"

## Scenario 1: Manual Update Check (QS-1)

**Objective**: Verify manual update check queries GitHub and displays result

### Steps

1. Click **"Check for Updates"** button
2. Observe button changes to "Checking..." with loading indicator
3. Wait for operation to complete (max 5 seconds)
4. Verify result message displays

### Expected Results

**If Update Available**:
- ✅ Message displays: "Update available: Version X.Y.Z"
- ✅ Release date shown
- ✅ Changelog summary displayed
- ✅ "Update Now" link appears (or WordPress's native update button becomes available)

**If Up-to-Date**:
- ✅ Message displays: "Plugin is up to date (Version X.Y.Z)"
- ✅ Current version number shown
- ✅ Last checked timestamp displayed

**If Error (Timeout/Rate Limit)**:
- ✅ Error message displays clearly
- ✅ Button re-enabled after error
- ✅ Retry possible

### Validation

- [ ] Check browser console for AJAX call to `cuft_check_updates`
- [ ] Verify no JavaScript errors
- [ ] Check WordPress admin bar - update badge should appear if update available
- [ ] Navigate to Plugins page - update notice should be visible immediately (cache cleared)

---

## Scenario 2: Force Reinstall (Update Available)

**Objective**: Verify force reinstall downloads and installs latest version

### Steps

1. Ensure an update is available (if not, wait for next release or create test release)
2. Click **"Force Reinstall Latest Version"** button
3. Confirm action in confirmation dialog (if present)
4. Observe progress indicator (may show stages: backing up, downloading, installing)
5. Wait for operation to complete (max 60 seconds)

### Expected Results

- ✅ Button changes to "Installing..." with loading indicator
- ✅ Operation completes within 60 seconds
- ✅ Success message displays: "Plugin successfully reinstalled to version X.Y.Z"
- ✅ Previous version number shown
- ✅ Plugin remains active after reinstall
- ✅ All plugin functionality works normally

### Validation

- [ ] Check `/wp-content/uploads/cuft-backups/` - backup ZIP should be deleted after success
- [ ] Verify `CUFT_VERSION` constant updated: `grep "CUFT_VERSION" choice-universal-form-tracker.php`
- [ ] Check update history (if visible in UI)
- [ ] Test form tracking still works (submit test form, verify dataLayer event)

---

## Scenario 3: Force Reinstall (Already Up-to-Date)

**Objective**: Verify force reinstall works even when WordPress thinks plugin is current

### Steps

1. Ensure plugin is at latest version (run Scenario 1 first)
2. Click **"Force Reinstall Latest Version"** button
3. Confirm action
4. Wait for operation to complete

### Expected Results

- ✅ Operation proceeds (doesn't block with "already up-to-date" message)
- ✅ Downloads and reinstalls same version
- ✅ Success message confirms reinstallation
- ✅ Plugin remains active and functional

### Validation

- [ ] Verify reinstall actually occurred (check file timestamps)
- [ ] Confirm no version number change
- [ ] All settings preserved (GTM ID, debug flags, etc.)

---

## Edge Case 1: Insufficient Disk Space

**Objective**: Verify disk space validation prevents broken installations

### Setup

Simulate low disk space (if possible via test environment) or verify logic in code.

### Expected Results

- ✅ Error message displays: "Insufficient disk space... Free at least X MB"
- ✅ Exact space requirements shown
- ✅ Operation aborts before backup attempt
- ✅ Plugin functionality preserved

---

## Edge Case 2: GitHub API Timeout

**Objective**: Verify graceful handling of slow/unavailable GitHub API

### Setup

Simulate timeout (if possible) or verify 5-second timeout in code.

### Expected Results

- ✅ Operation times out after 5 seconds
- ✅ Error message: "Unable to check for updates. Please try again later."
- ✅ Details include timeout information
- ✅ Button re-enabled, retry possible

---

## Edge Case 3: Concurrent Operations

**Objective**: Verify locking prevents simultaneous updates

### Setup

1. Open Settings page in two browser windows (same WordPress user)
2. Click "Force Reinstall" in first window
3. Immediately click "Force Reinstall" in second window

### Expected Results

- ✅ First request proceeds normally
- ✅ Second request fails immediately with: "Another update operation is already in progress"
- ✅ Second window shows who started first operation
- ✅ After first completes, second can retry successfully

---

## Troubleshooting

### Button Doesn't Respond

- Check browser console for JavaScript errors
- Verify nonce generation: `view-source:` and search for `cuftForceUpdate.nonce`
- Check WordPress user has `update_plugins` capability: `wp user list --field=caps`

### AJAX Returns 403 Error

- Nonce validation failed - refresh page and try again
- User lacks permissions - verify `update_plugins` capability
- `DISALLOW_FILE_MODS` enabled - check `wp-config.php`

### Timeout Errors

- GitHub API slow/unavailable - wait and retry
- Firewall blocking GitHub - check server outbound connections
- PHP `max_execution_time` too low - increase or use `set_time_limit(0)` in code

### Installation Fails

- Check `/wp-content/uploads/cuft-backups/` for backup file
- Review PHP error log for details
- Verify filesystem permissions: `chmod 755 wp-content/uploads`
- Check available disk space: `df -h`

---

## Success Criteria

All scenarios pass with expected results:
- [ ] Scenario 1: Manual update check works
- [ ] Scenario 2: Force reinstall (update available) works
- [ ] Scenario 3: Force reinstall (already current) works
- [ ] Edge Case 1: Disk space validation works
- [ ] Edge Case 2: Timeout handling works
- [ ] Edge Case 3: Concurrent prevention works

**Feature Ready**: All checkboxes marked ✅
