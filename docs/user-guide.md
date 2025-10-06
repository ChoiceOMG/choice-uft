# User Guide: One-Click Automated Updates

**Version**: 3.15.0
**Last Updated**: 2025-10-03

## Overview

The Choice Universal Form Tracker plugin includes an automated update feature that keeps your plugin current with the latest releases from GitHub. This guide explains how to use the update feature effectively.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Checking for Updates](#checking-for-updates)
3. [Installing Updates](#installing-updates)
4. [Update Settings](#update-settings)
5. [Update History](#update-history)
6. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Prerequisites

- WordPress 5.0 or higher
- Administrator account with `update_plugins` capability
- Active internet connection for GitHub API access
- Write permissions to the WordPress plugins directory

### Initial Setup

The update feature is enabled by default. No additional configuration is required to start receiving update notifications.

---

## Checking for Updates

### Automatic Checks

The plugin automatically checks for updates **twice daily** (every 12 hours). When a new version is available, you'll see:

1. **Admin Dashboard Notice**: A notification on the Dashboard showing "Update Available"
2. **Plugins Page Badge**: An "Update Available" badge next to the plugin name
3. **WordPress Updates Page**: The plugin will appear in the list of available updates

### Manual Checks

To manually check for updates:

#### Method 1: WordPress Updates Page
1. Go to **Dashboard → Updates**
2. Click the **"Check Again"** button at the top of the page
3. The plugin will fetch the latest release information from GitHub

#### Method 2: Plugins Page
1. Go to **Plugins → Installed Plugins**
2. Find "Choice Universal Form Tracker"
3. Click **"Check for Updates"** link (if available)

#### Method 3: Admin Bar
1. Look for the **admin bar menu** at the top of any admin page
2. Click **"CUFT"** → **"Check for Updates"**

---

## Installing Updates

### One-Click Update Process

When an update is available:

1. **Navigate to Updates Page**:
   - Go to **Dashboard → Updates**
   - Or go to **Plugins** and click **"Update Available"**

2. **Review Update Information**:
   - Current version
   - New version number
   - Release date
   - Changelog (what's new)
   - Download size

3. **Click "Update Now"**:
   - Click the **"Update Now"** button next to the plugin name

4. **Watch the Progress**:
   The update happens in stages:
   - ✓ Checking for updates... (2-3 seconds)
   - ✓ Downloading update package... (5-10 seconds)
   - ✓ Creating backup... (2-3 seconds)
   - ✓ Installing update... (3-5 seconds)
   - ✓ Verifying installation... (1-2 seconds)
   - ✓ **Update complete!**

5. **Verify Success**:
   - You'll see a success message
   - The plugin version number will be updated
   - All your settings will be preserved

### What Happens During Update

1. **Backup Creation**: The current plugin version is backed up automatically
2. **Download**: The new version is downloaded from GitHub
3. **Verification**: The downloaded file is verified for integrity
4. **Installation**: The new version is installed
5. **Cleanup**: Old files are removed and cache is cleared

### Automatic Rollback

If anything goes wrong during the update:

- The system **automatically restores** the previous version
- You'll see an error message explaining what happened
- Your plugin remains functional with the previous version
- All settings and data are preserved

---

## Update Settings

### Accessing Settings

1. Go to **Settings → Universal Form Tracker**
2. Click the **"Updates"** tab
3. Configure your preferences

### Available Settings

#### Auto-Update Frequency

Choose how often to check for updates:

- **Twice Daily** (Recommended): Checks every 12 hours
- **Daily**: Checks once per day
- **Weekly**: Checks once per week
- **Manual Only**: Disables automatic checks

#### Pre-Release Versions

- **Enabled**: Receive updates for beta and pre-release versions
- **Disabled** (Recommended): Only stable releases are offered

#### Automatic Backups

- **Enabled** (Recommended): Always create backup before updating
- **Disabled**: Skip backup creation (not recommended)

#### Email Notifications

Enter an email address to receive notifications when:
- New updates are available
- Updates are installed successfully
- Updates fail and require attention

### Saving Settings

1. Make your changes
2. Click **"Save Settings"**
3. You'll see a confirmation message

---

## Update History

### Viewing Update History

1. Go to **Settings → Universal Form Tracker → Updates**
2. Scroll to the **"Update History"** section
3. View recent update activity

### History Information

For each update attempt, you'll see:

- **Date & Time**: When the action occurred
- **Action**: What happened (check, update, rollback)
- **Status**: Success, failure, or in progress
- **Version**: Old and new version numbers (if applicable)
- **Details**: Additional information about the action

### Filtering History

Use the filter options to:
- Show only successful updates
- Show only failed attempts
- Filter by date range
- Search by version number

### History Retention

- Update logs are kept for **90 days** by default
- Older logs are automatically cleaned up
- You can export history before cleanup if needed

---

## Troubleshooting

For detailed troubleshooting instructions, see [troubleshooting.md](troubleshooting.md).

### Quick Fixes

#### Update Button Not Appearing

- Clear browser cache
- Clear WordPress transient cache
- Force a manual update check
- Verify you have `update_plugins` capability

#### Update Stuck at 0%

- Check browser console for JavaScript errors
- Verify internet connection
- Check file permissions on plugins directory
- Wait and try again (might be GitHub API rate limit)

#### "Security Check Failed" Error

- Clear browser cache
- Log out and log back in
- Verify you're logged in as an administrator
- Check that nonces are being generated correctly

#### Update Failed and Rolled Back

- Check the error message for specific details
- Verify write permissions on plugins directory
- Ensure sufficient disk space
- Try again after a few minutes

### Getting Help

If you continue experiencing issues:

1. Check the [troubleshooting guide](troubleshooting.md)
2. Review update history for error details
3. Check WordPress error logs
4. Contact plugin support with:
   - WordPress version
   - Plugin version
   - Error messages from update history
   - Browser console errors

---

## Best Practices

### Before Updating

- ✅ **Backup your site** (recommended but optional - plugin auto-backs up itself)
- ✅ **Check compatibility** - review changelog for breaking changes
- ✅ **Test on staging** - if you have a staging environment
- ✅ **Schedule during low traffic** - minimize user impact

### After Updating

- ✅ **Verify functionality** - test your forms to ensure tracking works
- ✅ **Check update history** - confirm successful installation
- ✅ **Review changelog** - understand what changed
- ✅ **Clear caches** - if using caching plugins

### Update Safety

The plugin is designed with safety in mind:

- **Automatic backups** before each update
- **Automatic rollback** if anything fails
- **Settings preservation** during updates
- **No data loss** - all tracking data is preserved
- **Concurrent update prevention** - only one update at a time

---

## Frequently Asked Questions

### How often should I update?

Update as soon as a new stable version is available. Updates include:
- Bug fixes
- Security patches
- New features
- Performance improvements

### Will updating break my forms?

No. The plugin is designed to maintain backward compatibility. However:
- Review the changelog before updating
- Test on staging if you have custom integrations
- Automatic rollback protects you if issues occur

### Can I skip versions?

Yes. You can update directly to the latest version without installing intermediate versions.

### What happens if an update fails?

The plugin will:
1. Automatically restore the previous version
2. Show you an error message
3. Log the failure in update history
4. Keep your site functional

### Can I disable automatic updates?

Yes. Change the update frequency to "Manual Only" in settings. You'll still see update notifications but won't receive automatic checks.

### How do I revert to a previous version?

If you need to revert:
1. The automatic backup is stored temporarily
2. Use the "Rollback" feature in update history
3. Or manually install an older version from GitHub releases

---

## Support

For additional help:

- **Documentation**: See [troubleshooting.md](troubleshooting.md) and [developer-guide.md](developer-guide.md)
- **Issue Tracker**: Report bugs at https://github.com/ChoiceOMG/choice-uft/issues
- **Plugin Support**: Contact via WordPress.org support forums

---

**Last Updated**: 2025-10-03
**Plugin Version**: 3.15.0
