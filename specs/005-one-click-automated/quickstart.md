# Quick Start Guide: One-Click Automated Update

**Feature**: One-Click Automated Update System
**Version**: 1.0.0
**Last Updated**: 2025-10-03

## Overview

This guide helps you quickly test and validate the one-click automated update feature for the Choice Universal Form Tracker plugin.

## Prerequisites

- WordPress 5.0+ installation
- Administrator access (update_plugins capability)
- Choice Universal Form Tracker plugin installed
- Internet connection for GitHub API access

## Quick Test Scenarios

### Scenario 1: Fix Current Nonce Security Issue 🔧

**Current Issue**: AJAX endpoints returning "⚠️ Security check failed"

**Test Steps**:
1. Open WordPress admin dashboard
2. Navigate to Settings → Universal Form Tracker
3. Open browser DevTools Console
4. Click "Check for Updates" button
5. **Expected**: Version check completes successfully
6. **Current Bug**: Shows "⚠️ Security check failed"

**Debug Commands**:
```javascript
// Check if nonce is properly localized
console.log(typeof cuftUpdater !== 'undefined' ? cuftUpdater : 'Not loaded');

// Test nonce validation directly
fetch(ajaxurl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
        action: 'cuft_check_update',
        nonce: cuftUpdater?.nonce || 'missing',
        force: 'true'
    })
}).then(r => r.json()).then(console.log);
```

### Scenario 2: Check for Updates (Happy Path) ✅

**Test Steps**:
1. Navigate to WordPress admin → Updates page
2. Look for "Choice Universal Form Tracker" in plugins list
3. Click "Check Again" to force update check
4. **Expected**: Shows current version and available version
5. If update available, shows "Update Available" with version number

**Validation**:
- ✅ Current version displayed correctly
- ✅ Latest GitHub release detected
- ✅ Update button appears if newer version exists
- ✅ "You have the latest version" if up-to-date

### Scenario 3: Perform One-Click Update 🚀

**Test Steps**:
1. When update is available, click "Update Now" button
2. Watch progress indicator
3. **Expected Progress**:
   - "Checking for updates..." (2-3 seconds)
   - "Downloading update..." (5-10 seconds)
   - "Creating backup..." (2-3 seconds)
   - "Installing update..." (3-5 seconds)
   - "Verifying installation..." (1-2 seconds)
   - "Update complete!" ✅

**Validation**:
- ✅ Progress bar shows real-time status
- ✅ No page refresh required
- ✅ Plugin remains active after update
- ✅ Settings preserved
- ✅ New version number shown

### Scenario 4: Network Failure Handling 🌐

**Test Steps**:
1. Open DevTools → Network tab
2. Set throttling to "Offline"
3. Click "Check for Updates"
4. **Expected**: Shows friendly error message
5. Re-enable network
6. Click "Try Again"
7. **Expected**: Successfully checks for updates

**Validation**:
- ✅ Error message is user-friendly
- ✅ Suggests retry action
- ✅ Doesn't break the admin interface
- ✅ Cached data used if available

### Scenario 5: Automatic Rollback on Failure 🔄

**Test Mode Command**:
```javascript
// Trigger update with simulated failure
fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
        action: 'cuft_perform_update',
        nonce: cuftUpdater.nonce,
        version: 'latest',
        mock: 'true',
        mock_failure: 'extraction'
    })
}).then(r => r.json()).then(console.log);
```

**Expected Behavior**:
1. Update starts normally
2. Fails during extraction phase
3. Automatic rollback initiated
4. Previous version restored
5. Error message displayed
6. Plugin remains functional

### Scenario 6: Concurrent Update Prevention 🚫

**Test Steps**:
1. Open two admin tabs
2. Start update in first tab
3. Try to start update in second tab
4. **Expected**: Second tab shows "Update already in progress"
5. Second tab displays current progress
6. Both tabs show completion when done

### Scenario 7: Update Scheduling ⏰

**Test Steps**:
1. Navigate to Settings → Universal Form Tracker → Updates
2. Verify "Automatic Updates" is enabled
3. Check "Update Frequency" is set to "Twice Daily"
4. Note "Next scheduled check" timestamp
5. **Validation**:
   - Check runs at scheduled time
   - Transient cache updated
   - Log entry created

**Manual Trigger via WP-CLI**:
```bash
wp cron event run cuft_check_updates
```

## Configuration Testing

### Enable/Disable Auto-Updates
```javascript
// Enable auto-updates
fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'cuft_update_settings',
        nonce: cuftUpdater.nonce,
        enabled: 'true',
        check_frequency: 'twicedaily'
    })
}).then(r => r.json()).then(console.log);
```

### Check Update Status
```javascript
// Get current update status
fetch(cuftUpdater.ajaxUrl + '?action=cuft_update_status&nonce=' + cuftUpdater.nonce)
    .then(r => r.json())
    .then(console.log);
```

### View Update History
```javascript
// Get last 5 update attempts
fetch(cuftUpdater.ajaxUrl + '?action=cuft_update_history&nonce=' + cuftUpdater.nonce + '&limit=5')
    .then(r => r.json())
    .then(data => console.table(data.data.entries));
```

## Mock Testing Commands

### Simulate Different Versions
```javascript
// Mock newer version available
window.cuftMockVersion = '3.99.0';

// Mock current version is latest
window.cuftMockVersion = null;

// Mock specific GitHub response
window.cuftMockResponse = {
    tag_name: 'v3.16.0',
    published_at: '2025-10-03T12:00:00Z',
    body: '### New Features\n- One-click updates',
    assets: [{
        browser_download_url: 'https://github.com/ChoiceOMG/choice-uft/releases/download/v3.16.0/choice-uft.zip',
        size: 2500000
    }]
};
```

### Test Failure Scenarios
```javascript
// Test download failure
window.cuftMockFailure = 'download';

// Test extraction failure
window.cuftMockFailure = 'extract';

// Test permission failure
window.cuftMockFailure = 'permissions';

// Test rollback
window.cuftMockFailure = 'install';

// Clear mock settings
delete window.cuftMockFailure;
delete window.cuftMockVersion;
```

## Validation Checklist

### Security
- [ ] Nonce validation working
- [ ] Capability checks enforced
- [ ] HTTPS enforced for downloads
- [ ] No sensitive data in logs

### Functionality
- [ ] Version comparison accurate
- [ ] Download completes successfully
- [ ] Backup created before update
- [ ] Rollback works on failure
- [ ] Settings preserved after update

### User Experience
- [ ] Clear progress indicators
- [ ] Informative error messages
- [ ] No page refresh required
- [ ] Update history viewable
- [ ] Integration with WordPress Updates page

### Performance
- [ ] Update check < 2 seconds
- [ ] Download reasonable for file size
- [ ] No blocking operations
- [ ] Caching working correctly

### Edge Cases
- [ ] Network failure handled
- [ ] Concurrent updates blocked
- [ ] Invalid versions rejected
- [ ] Corrupted downloads detected
- [ ] Permission issues handled

## Troubleshooting

### "Security check failed" Error
1. Check nonce is being generated: `wp nonce verify`
2. Verify nonce is localized to JavaScript
3. Check nonce action name matches
4. Ensure user is logged in
5. Clear browser cache

### "No update available" When Update Exists
1. Clear transient cache: `wp transient delete cuft_update_status`
2. Force check: Add `&force=true` parameter
3. Check GitHub API rate limit
4. Verify version number format

### Update Stays at 0%
1. Check browser console for errors
2. Verify file permissions: `ls -la wp-content/plugins/choice-uft/`
3. Check PHP error log
4. Ensure sufficient disk space
5. Test with mock mode first

### Rollback Fails
1. Check backup exists: `ls -la wp-content/upgrade/`
2. Verify write permissions
3. Check PHP memory limit
4. Review error log for specifics

## Support Commands

### WP-CLI Commands
```bash
# Check current version
wp plugin get choice-uft --field=version

# Force update check
wp eval 'do_action("cuft_check_updates");'

# View update transients
wp transient get cuft_update_status

# Clear update cache
wp transient delete cuft_update_status
wp transient delete cuft_update_progress
```

### Database Queries
```sql
-- View update log
SELECT * FROM wp_cuft_update_log ORDER BY timestamp DESC LIMIT 10;

-- Check update configuration
SELECT option_value FROM wp_options WHERE option_name = 'cuft_update_config';

-- Clear update locks
DELETE FROM wp_options WHERE option_name LIKE '_transient_cuft_update%';
```

## Success Criteria

The update feature is working correctly when:
1. ✅ Nonce validation passes without security errors
2. ✅ Updates integrate with WordPress Updates page
3. ✅ One-click updates complete in < 30 seconds
4. ✅ Automatic rollback restores previous version on failure
5. ✅ Update checks run twice daily automatically
6. ✅ All test scenarios pass without errors

## Next Steps

After validating the quick start scenarios:
1. Review update logs for any warnings
2. Test with different WordPress configurations
3. Verify compatibility with other plugins
4. Document any custom configurations needed
5. Monitor GitHub API rate limits in production