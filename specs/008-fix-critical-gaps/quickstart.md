# Quickstart: Testing Update System Implementation Gaps

**Feature**: 008-fix-critical-gaps
**Date**: 2025-10-11
**Purpose**: Manual testing guide for verifying plugin information modal, directory naming fix, update execution, download validation, and backup/restore functionality

---

## Prerequisites

### Environment Setup

1. **WordPress Installation**:
   - WordPress 5.0 or higher
   - PHP 7.0 or higher
   - MySQL 5.6 or higher

2. **Plugin Installation**:
   - Choice Universal Form Tracker installed from GitHub
   - Plugin active and functioning
   - Current version: 3.16.5 (or earlier)

3. **Test Update Available**:
   - New version available on GitHub (e.g., 3.17.0)
   - Release includes ZIP asset
   - Release notes available

4. **Required Permissions**:
   - Administrator account with `update_plugins` capability
   - Write permissions on `/wp-content/plugins/`
   - Write permissions on `/wp-content/uploads/`

5. **Testing Tools**:
   - Browser with DevTools (for network inspection)
   - SSH/terminal access (for WP-CLI tests)
   - Text editor (for viewing logs)

6. **Optional Tools**:
   - Git (for checking repository)
   - GitHub personal access token (for rate limit testing)

### Initial State Verification

Before starting tests, verify:

```bash
# Check current plugin version
wp plugin list --name=choice-uft --fields=name,status,version

# Check file permissions
ls -la /wp-content/plugins/choice-uft/
ls -la /wp-content/uploads/

# Check available updates
wp plugin update choice-uft --dry-run

# Check PHP error log location
php -i | grep error_log
```

**Expected Results**:
- Plugin is active
- Version shows as 3.16.5 (or earlier than target)
- Update available to 3.17.0 (or target version)
- Directories writable by web server

---

## Quick Start Scenarios (QS)

### QS-1: Plugin Information Modal

**Test**: Verify "View Details" modal displays complete plugin information

**Steps**:
1. Navigate to `/wp-admin/plugins.php`
2. Locate Choice Universal Form Tracker plugin
3. Verify update notice shows: "There is a new version of Choice Universal Form Tracker available."
4. Click "View version X.X.X details" link
5. Wait for modal to load

**Expected Results**:
- Modal opens with plugin information
- **Name**: "Choice Universal Form Tracker"
- **Version**: Latest version number (e.g., "3.17.0")
- **Author**: "Choice Marketing" with link to GitHub
- **Compatibility**:
  - "Requires WordPress Version: 5.0 or higher"
  - "Compatible up to: 6.7"
  - "Requires PHP Version: 7.0 or higher"
- **Last Updated**: Recent date (ISO 8601 format)
- **Tabs Present**:
  - Description tab (default)
  - Installation tab
  - Changelog tab
- **Description Tab**: Plugin overview and features
- **Installation Tab**: Installation instructions
- **Changelog Tab**: Release notes from GitHub
- **"Update Now" Button**: Present and clickable

**Verification**:
```bash
# Check transient cache
wp transient get cuft_plugin_info | jq .

# Verify ETag stored
wp transient get cuft_plugin_info | jq .etag
```

**Troubleshooting**:
- If modal shows "information not available": Check GitHub API connectivity
- If changelog missing: Check GitHub API rate limit
- If modal broken: Check browser console for JavaScript errors

---

### QS-2: Update from Plugins Page

**Test**: Execute update using WordPress native "Update Now" button

**Steps**:
1. Navigate to `/wp-admin/plugins.php`
2. Locate Choice Universal Form Tracker plugin
3. Note current version displayed
4. Click "Update Now" link
5. Watch progress messages
6. Wait for completion

**Expected Results**:
- **Progress Messages**:
  - "Downloading update from https://github.com/..."
  - "Unpacking the update..."
  - "Installing the latest version..."
  - "Successfully updated to version X.X.X"
- **Success Notice**: Green admin notice: "Plugin updated successfully."
- **Version Updated**: Version number changes to new version (e.g., 3.17.0)
- **Plugin Still Active**: Plugin remains active after update
- **No Errors**: No error messages or warnings

**Verification**:
```bash
# Check new version
wp plugin list --name=choice-uft --fields=version

# Check update history log
wp option get cuft_update_history | jq .

# Check plugin directory structure
ls -la /wp-content/plugins/choice-uft/

# Verify directory name (no version suffix)
[ -d "/wp-content/plugins/choice-uft" ] && echo "Directory correct" || echo "Directory incorrect"
```

**Troubleshooting**:
- If update fails: Check PHP error log for details
- If directory has version suffix: Check `upgrader_source_selection` filter
- If plugin broken after update: Check backup restoration

---

### QS-3: Update via WP-CLI

**Test**: Execute update using WP-CLI command

**Prerequisites**:
- SSH/terminal access to server
- WP-CLI installed and functional

**Steps**:
1. SSH to server
2. Navigate to WordPress root directory
3. Run: `wp plugin update choice-uft`
4. Observe output

**Expected Results**:
- **Output Messages**:
  ```
  Downloading update from https://github.com/ChoiceOMG/choice-uft/releases/download/...
  Unpacking the update...
  Installing the latest version...
  Removing the old version of the plugin...
  Plugin updated successfully.
  Success: Updated 1 of 1 plugins.
  ```
- **Exit Code**: `0` (success)
- **Version Change**: Version updated to target version
- **No Errors**: No error messages in output or PHP error log

**Verification**:
```bash
# Check exit code
echo $?  # Should be 0

# Verify new version
wp plugin get choice-uft --field=version

# Check update history
wp option get cuft_update_history | jq .

# Check plugin still works
wp plugin status choice-uft
```

**Troubleshooting**:
- If exit code 1: Check error message in output
- If "update already in progress": Wait or check transient `cuft_update_context_*`
- If permissions error: Check file ownership and permissions

---

### QS-4: Bulk Update

**Test**: Update CUFT alongside other plugins using bulk update

**Prerequisites**:
- Multiple plugins with updates available (or WordPress core update)

**Steps**:
1. Navigate to `/wp-admin/update-core.php`
2. Scroll to "Plugins" section
3. Check checkbox for Choice Universal Form Tracker
4. Check checkbox for another plugin (or WordPress core)
5. Click "Update Plugins" button
6. Watch progress indicators

**Expected Results**:
- **Progress Indicators**: Each plugin shows progress separately
- **Success Messages**:
  - "Updating Choice Universal Form Tracker (X/Y)"
  - "Updated Choice Universal Form Tracker successfully"
- **Other Plugins**: Other updates complete successfully
- **No Interference**: CUFT update doesn't affect other updates
- **All Updates Complete**: All selected updates finish

**Verification**:
```bash
# Check all plugin versions
wp plugin list --fields=name,version

# Check update history for bulk update
wp option get cuft_update_history | jq 'last | .trigger_location'
# Should show "bulk_update"

# Verify no errors in PHP log
tail -n 50 /var/log/php-error.log | grep CUFT
```

**Troubleshooting**:
- If CUFT fails but others succeed: Isolation is working correctly (check error)
- If all updates fail: Check general WordPress update permissions
- If progress stuck: Check for concurrent updates or server timeout

---

### QS-5: Download Validation (Size Mismatch)

**Test**: Verify download validation catches incomplete downloads

**Simulation Methods**:

**Method 1: Network Interruption (Real-World Scenario)**
1. Start update process
2. During download, pause browser DevTools network
3. Wait for timeout
4. Resume network
5. Observe error handling

**Method 2: Mock Invalid Download (Testing Environment)**
```bash
# Modify expected size in transient to cause mismatch
wp transient set cuft_expected_download_size 9999999

# Trigger update
wp plugin update choice-uft
```

**Expected Results**:
- **Error Detection**: Update process detects size mismatch
- **Error Message**:
  ```
  Download verification failed: File size mismatch.
  Expected 2.5 MB, got 1.2 MB. Please try again.
  ```
- **Partial File Deleted**: Incomplete download removed from temp directory
- **Current Version Intact**: Plugin still at previous version
- **Retry Available**: User can retry update immediately

**Verification**:
```bash
# Check no orphaned files in temp
ls -la $(php -r "echo sys_get_temp_dir();") | grep choice-uft

# Check current version unchanged
wp plugin get choice-uft --field=version

# Check error logged
tail -n 20 /var/log/php-error.log | grep "CUFT WARNING: Download validation failed"

# Verify update can be retried
wp plugin update choice-uft --dry-run
```

**Troubleshooting**:
- If no error shown: Check validation is enabled
- If file not deleted: Check cleanup function
- If update proceeds despite mismatch: Check tolerance calculation

---

### QS-6: Automatic Rollback

**Test**: Verify automatic restoration when update fails

**Simulation Methods**:

**Method 1: Corrupt ZIP (Real-World Scenario)**
1. Download release ZIP manually
2. Corrupt ZIP file (truncate or edit with text editor)
3. Upload to custom location
4. Modify download URL to point to corrupted ZIP
5. Trigger update

**Method 2: Mock Extraction Failure (Testing Environment)**
```bash
# Make plugin directory read-only to force extraction failure
chmod 555 /wp-content/plugins/choice-uft/

# Trigger update
wp plugin update choice-uft

# Restore permissions after test
chmod 755 /wp-content/plugins/choice-uft/
```

**Expected Results**:
- **Backup Created**: Before update starts
  - File: `/wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip`
  - Size: >0 bytes
- **Update Fails**: During extraction/installation
- **Error Detected**: System recognizes update failure
- **Restoration Triggered**: Backup automatically extracted
- **Previous Version Restored**: Plugin back to working state (3.16.5)
- **Error Message**:
  ```
  Update failed during extraction.
  Previous version has been restored automatically.
  ```
- **Plugin Functional**: Plugin still works at previous version

**Verification**:
```bash
# Check current version restored
wp plugin get choice-uft --field=version
# Should show 3.16.5 (previous version)

# Check plugin still works
wp plugin status choice-uft

# Check backup file was created
ls -la /wp-content/uploads/cuft-backups/ | grep choice-uft

# Check error log for restoration
tail -n 50 /var/log/php-error.log | grep "CUFT"

# Verify plugin files intact
ls -la /wp-content/plugins/choice-uft/choice-universal-form-tracker.php
```

**Troubleshooting**:
- If restoration fails: Check timeout limit (10s)
- If backup not created: Check disk space and permissions
- If plugin broken: Manual reinstall required (check error message)

---

### QS-7: Directory Naming

**Test**: Verify directory renamed from GitHub format to WordPress format

**Steps**:
1. Note current plugin directory: `/wp-content/plugins/choice-uft/`
2. Trigger update from any source (Plugins page, WP-CLI, bulk)
3. Monitor extraction process
4. Verify final directory name

**Expected Results**:
- **GitHub ZIP Contains**: `choice-uft-v3.17.0/` directory
- **WordPress Expects**: `choice-uft/` directory
- **Directory Renamed**: During `upgrader_source_selection` filter
- **Final Location**: `/wp-content/plugins/choice-uft/` (no version suffix)
- **No Errors**: No "directory mismatch" errors
- **Update Succeeds**: Plugin updated successfully
- **Plugin Recognized**: WordPress recognizes plugin at correct location

**Verification**:
```bash
# Check directory name
ls -la /wp-content/plugins/ | grep choice-uft
# Should show "choice-uft" only, not "choice-uft-v3.17.0"

# Check no versioned directories
ls -la /wp-content/plugins/ | grep choice-uft-v
# Should return empty

# Verify plugin recognized
wp plugin list --name=choice-uft

# Check plugin file in correct location
[ -f "/wp-content/plugins/choice-uft/choice-universal-form-tracker.php" ] && echo "Correct" || echo "Incorrect"
```

**Troubleshooting**:
- If directory has version suffix: Check `upgrader_source_selection` filter
- If plugin not recognized: Check plugin file exists in directory
- If multiple directories: Old versioned directory from previous failed update

---

## Edge Case Tests (EC)

### EC-1: Backup Directory Not Writable

**Test**: Verify update aborts when backup cannot be created

**Steps**:
1. Make backup directory read-only:
   ```bash
   mkdir -p /wp-content/uploads/cuft-backups/
   chmod 555 /wp-content/uploads/cuft-backups/
   ```
2. Trigger update from Plugins page
3. Observe error message
4. Restore permissions:
   ```bash
   chmod 755 /wp-content/uploads/cuft-backups/
   ```

**Expected Results**:
- **Update Aborted**: Update does not proceed
- **Error Message**:
  ```
  Cannot create backup directory.
  Please ensure /wp-content/uploads/ is writable or contact your hosting provider.
  ```
- **Current Version Intact**: Plugin remains at previous version
- **No Partial Files**: No temporary files left behind

**Verification**:
```bash
# Check current version unchanged
wp plugin get choice-uft --field=version

# Check no backup created
ls -la /wp-content/uploads/cuft-backups/

# Check error logged
tail -n 20 /var/log/php-error.log | grep "backup_dir_not_writable"
```

---

### EC-2: Disk Space Insufficient

**Test**: Verify update aborts when disk space insufficient for backup

**Note**: This test is destructive and should only be run on test environments

**Steps**:
1. Fill disk to near capacity (simulate)
2. Trigger update
3. Observe error message

**Expected Results**:
- **Update Aborted**: Update does not start
- **Error Message**:
  ```
  Insufficient disk space to create backup.
  Free at least 5 MB and try again.
  ```
- **Current Version Intact**: Plugin unchanged
- **No Partial Backup**: No incomplete backup file

**Verification**:
```bash
# Check disk space
df -h /wp-content/uploads/

# Check no backup created
ls -la /wp-content/uploads/cuft-backups/ | grep choice-uft

# Check error logged
tail -n 20 /var/log/php-error.log | grep "disk_full"
```

---

### EC-3: Backup Restoration Fails

**Test**: Verify manual reinstall message when restoration fails

**Simulation**:
1. Create corrupted backup file before update
2. Trigger update that will fail
3. System attempts restoration from corrupted backup
4. Observe error handling

**Steps**:
```bash
# Create corrupted backup manually
echo "corrupted" > /wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip

# Set transient to use this backup
wp transient set cuft_backup_path /wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip

# Trigger update that will fail
# (use permission test from QS-6)
chmod 555 /wp-content/plugins/choice-uft/
wp plugin update choice-uft
chmod 755 /wp-content/plugins/choice-uft/
```

**Expected Results**:
- **Update Fails**: As expected
- **Restoration Attempted**: System tries to restore from backup
- **Restoration Fails**: Corrupted backup cannot be extracted
- **Critical Error Logged**: PHP error_log shows CRITICAL severity
- **Manual Reinstall Message**:
  ```
  Update failed and backup restoration also failed.
  Please reinstall plugin manually from GitHub:
  https://github.com/ChoiceOMG/choice-uft/releases/latest
  ```
- **Plugin May Be Broken**: User must manually reinstall

**Verification**:
```bash
# Check CRITICAL errors logged
tail -n 50 /var/log/php-error.log | grep "CUFT CRITICAL"

# Check error message displayed to user
# (inspect admin notice HTML)

# Manually reinstall
wget https://github.com/ChoiceOMG/choice-uft/releases/latest/download/choice-uft-v3.17.0.zip
wp plugin install choice-uft-v3.17.0.zip --activate --force
```

---

### EC-4: Unexpected ZIP Structure

**Test**: Verify error handling for ZIP with unusual structure

**Simulation**:
1. Create custom ZIP with incorrect structure:
   ```bash
   # Create ZIP without root directory (flat structure)
   cd /wp-content/plugins/choice-uft/
   zip -r /tmp/flat-structure.zip .
   ```
2. Modify download URL to use custom ZIP
3. Trigger update

**Expected Results**:
- **Pattern Detection**: Filter recognizes unusual structure
- **Error Returned**:
  ```
  Unexpected ZIP structure.
  Please report this issue to plugin developers.
  ```
- **Update Aborted**: Does not proceed with invalid structure
- **Rollback Occurs**: If any files extracted, restoration triggered

**Verification**:
```bash
# Check error logged
tail -n 20 /var/log/php-error.log | grep "incompatible_plugin_archive"

# Check current version unchanged
wp plugin get choice-uft --field=version

# Check no malformed directories
ls -la /wp-content/plugins/ | grep choice
```

---

### EC-5: Concurrent Updates (WP-CLI vs Admin)

**Test**: Verify concurrent update prevention

**Prerequisites**:
- Two terminal/browser sessions
- Ability to trigger updates simultaneously

**Steps**:
1. **Session 1**: Start update from WordPress admin (Plugins page)
2. **Session 2**: Immediately run `wp plugin update choice-uft`
3. Observe behavior in both sessions

**Expected Results**:
- **First Update**: Proceeds normally (Session 1)
- **Second Update**: Blocked (Session 2)
- **Error Message** (Session 2):
  ```
  Update already in progress by Admin User. Please wait.
  ```
- **Exit Code** (Session 2): `1` (failure)
- **First Update Continues**: Uninterrupted by second attempt
- **First Update Completes**: Successfully finishes

**Verification**:
```bash
# Check update context transient
wp transient list | grep cuft_update_context

# Check transient contents
wp transient get cuft_update_context_1 | jq .

# Verify only one update logged
wp option get cuft_update_history | jq 'last'

# Check final version
wp plugin get choice-uft --field=version
```

---

## Complete Workflow Tests

### Workflow 1: Fresh Install to Latest Version

**Scenario**: New WordPress site, installing plugin for first time

**Steps**:
1. Fresh WordPress installation
2. Install plugin from GitHub ZIP
3. Activate plugin
4. Check for updates
5. Update to latest version using Plugins page
6. Verify all features work

**Verification Points**:
- Initial installation succeeds
- Update detection works
- Plugin information modal displays
- Update executes successfully
- Directory naming correct
- No errors in log

---

### Workflow 2: Multiple Version Updates

**Scenario**: Update through multiple versions (3.15.0 → 3.16.5 → 3.17.0)

**Steps**:
1. Install older version (3.15.0)
2. Update to 3.16.5
3. Verify functionality
4. Update to 3.17.0
5. Verify functionality

**Verification Points**:
- Each update succeeds
- Update history shows all updates
- No data loss between updates
- Settings preserved
- All features functional

---

### Workflow 3: Recovery from Failed Update

**Scenario**: Simulate update failure and verify recovery process

**Steps**:
1. Current version: 3.16.5
2. Trigger update that will fail (use EC-3 simulation)
3. Verify restoration occurs
4. Fix issue (restore permissions)
5. Retry update
6. Verify success

**Verification Points**:
- First update fails as expected
- Restoration brings back previous version
- Plugin still functional after restoration
- Second update succeeds
- Final version correct
- No lingering backup files

---

## Cleanup and Reset

After testing, clean up test artifacts:

```bash
# Remove old backups
rm -rf /wp-content/uploads/cuft-backups/

# Clear update transients
wp transient delete cuft_plugin_info
wp transient delete cuft_expected_download_size
wp transient delete cuft_backup_path

# Clear update context transients
wp transient list | grep cuft_update_context | xargs -I {} wp transient delete {}

# Reset update history (if needed for clean slate)
wp option delete cuft_update_history

# Check for orphaned download files
ls -la $(php -r "echo sys_get_temp_dir();") | grep choice-uft

# Restore default permissions
chmod 755 /wp-content/plugins/choice-uft/
chmod 755 /wp-content/uploads/
```

---

## Troubleshooting Reference

### Common Issues

| Issue | Likely Cause | Solution |
|-------|--------------|----------|
| Modal shows "information not available" | GitHub API unavailable | Wait and retry, check network |
| Update fails with "directory mismatch" | Directory renaming not working | Check `upgrader_source_selection` filter |
| Update succeeds but plugin broken | Restoration failed | Manual reinstall from GitHub |
| "Insufficient disk space" error | Disk full | Free up space, retry |
| "Backup directory not writable" | Permissions issue | Check `/wp-content/uploads/` permissions |
| Changelog missing from modal | GitHub API rate limit | Wait for rate limit reset |
| Concurrent update error | Multiple updates running | Wait for first to finish |
| ZIP validation fails | Corrupted download | Retry update |

### Log Locations

| Log Type | Location | Filter Command |
|----------|----------|----------------|
| PHP Error Log | `/var/log/php-error.log` | `grep CUFT` |
| WordPress Debug Log | `/wp-content/debug.log` | `grep -i cuft` |
| Apache Error Log | `/var/log/apache2/error.log` | `grep -i cuft` |
| Nginx Error Log | `/var/log/nginx/error.log` | `grep -i cuft` |

### Verification Commands

```bash
# Check plugin status
wp plugin status choice-uft

# Check current version
wp plugin get choice-uft --field=version

# Check available updates
wp plugin update choice-uft --dry-run

# View update history
wp option get cuft_update_history | jq .

# List active transients
wp transient list | grep cuft

# Check backup directory
ls -la /wp-content/uploads/cuft-backups/

# Check plugin directory structure
ls -la /wp-content/plugins/ | grep choice-uft

# Test plugin functionality
wp eval "echo CUFT_VERSION;"
```

---

## Success Criteria Summary

All tests pass when:

- [x] **QS-1**: Plugin information modal displays complete information
- [x] **QS-2**: Update from Plugins page succeeds with correct directory
- [x] **QS-3**: WP-CLI update succeeds with exit code 0
- [x] **QS-4**: Bulk update works without interfering with other plugins
- [x] **QS-5**: Download validation catches size mismatches
- [x] **QS-6**: Automatic rollback restores previous version on failure
- [x] **QS-7**: Directory renamed from GitHub format to WordPress format
- [x] **EC-1**: Update aborts gracefully when backup directory not writable
- [x] **EC-2**: Update aborts gracefully when disk space insufficient
- [x] **EC-3**: Manual reinstall message shown when restoration fails
- [x] **EC-4**: Unusual ZIP structures rejected with clear error
- [x] **EC-5**: Concurrent updates prevented with informative error

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Testing
