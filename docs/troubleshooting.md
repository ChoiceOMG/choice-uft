# Troubleshooting Guide: One-Click Automated Updates

**Version**: 3.15.0
**Last Updated**: 2025-10-03

## Table of Contents

1. [Common Issues](#common-issues)
2. [Error Messages](#error-messages)
3. [Diagnostic Tools](#diagnostic-tools)
4. [Advanced Troubleshooting](#advanced-troubleshooting)
5. [Support Resources](#support-resources)

---

## Common Issues

### Issue: "Security Check Failed" Error

**Symptoms**:
- AJAX requests return "⚠️ Security check failed"
- Update check button doesn't work
- Browser console shows 403 errors

**Causes**:
- Invalid or expired nonce
- User session expired
- Browser cache issues
- Security plugin interference

**Solutions**:

1. **Clear Browser Cache**:
   ```
   - Chrome: Ctrl+Shift+Delete → Clear cached images and files
   - Firefox: Ctrl+Shift+Delete → Cached Web Content
   - Safari: Cmd+Option+E → Empty Caches
   ```

2. **Log Out and Back In**:
   - Log out of WordPress
   - Clear browser cookies for your site
   - Log back in as administrator

3. **Check Nonce Generation**:
   - Open browser DevTools Console
   - Run: `console.log(cuftUpdater)`
   - Verify `nonce` property exists and has a value
   - If missing, see [Nonce Not Generated](#nonce-not-generated)

4. **Disable Security Plugins Temporarily**:
   - Deactivate security plugins one by one
   - Test update check after each
   - If fixed, configure security plugin to allow CUFT AJAX

5. **Check User Capabilities**:
   ```php
   // Add to theme's functions.php temporarily
   add_action('admin_notices', function() {
       if (current_user_can('update_plugins')) {
           echo '<div class="notice notice-success"><p>User has update_plugins capability</p></div>';
       } else {
           echo '<div class="notice notice-error"><p>User lacks update_plugins capability</p></div>';
       }
   });
   ```

---

### Issue: Update Check Shows "No Update Available" When Update Exists

**Symptoms**:
- GitHub shows new release
- Plugin shows "You have the latest version"
- WordPress Updates page doesn't list plugin

**Causes**:
- Cached update status
- Version comparison mismatch
- GitHub API rate limiting
- Pre-release vs stable version mismatch

**Solutions**:

1. **Clear Transient Cache**:
   ```bash
   # Via WP-CLI
   wp transient delete cuft_update_status
   wp transient delete cuft_github_release_cache

   # Via WordPress admin
   # Install Query Monitor plugin and clear transients
   ```

2. **Force Update Check**:
   - Add `?force=true` to update check request
   - Open browser console:
     ```javascript
     fetch(cuftUpdater.ajaxUrl, {
         method: 'POST',
         headers: {'Content-Type': 'application/x-www-form-urlencoded'},
         body: new URLSearchParams({
             action: 'cuft_check_update',
             nonce: cuftUpdater.nonce,
             force: 'true'
         })
     }).then(r => r.json()).then(console.log);
     ```

3. **Check Version Format**:
   - Current version must be in format: `3.14.0`
   - GitHub release tag must be: `v3.14.0` or `3.14.0`
   - Check with: `wp plugin get choice-uft --field=version`

4. **Verify GitHub API Access**:
   ```bash
   # Test API access
   curl -I https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest

   # Check rate limit
   curl https://api.github.com/rate_limit
   ```

5. **Check Pre-Release Settings**:
   - Go to Settings → Universal Form Tracker → Updates
   - If "Include Pre-Releases" is disabled, only stable releases appear
   - Enable if you want beta versions

---

### Issue: Update Progress Stuck at 0%

**Symptoms**:
- Progress bar doesn't move
- "Update in progress" message but no activity
- Browser shows no network activity

**Causes**:
- JavaScript errors
- AJAX endpoint not responding
- File permission issues
- Server timeout

**Solutions**:

1. **Check Browser Console**:
   - Open DevTools (F12)
   - Look for JavaScript errors in Console tab
   - Look for failed network requests in Network tab

2. **Check Update Status**:
   ```javascript
   // Check current status
   fetch(cuftUpdater.ajaxUrl + '?action=cuft_update_status&nonce=' + cuftUpdater.nonce)
       .then(r => r.json())
       .then(data => {
           console.log('Status:', data.data.status);
           console.log('Progress:', data.data.percentage);
           console.log('Message:', data.data.message);
       });
   ```

3. **Clear Update Lock**:
   ```bash
   # Via WP-CLI
   wp transient delete cuft_update_in_progress
   wp transient delete cuft_update_status

   # Via SQL
   DELETE FROM wp_options WHERE option_name LIKE '_transient_cuft_update%';
   ```

4. **Check File Permissions**:
   ```bash
   # Check plugin directory permissions
   ls -la /path/to/wp-content/plugins/choice-uft/

   # Should show: drwxr-xr-x (755) for directories
   #              -rw-r--r-- (644) for files

   # Fix if needed
   chmod 755 /path/to/wp-content/plugins/choice-uft/
   chmod 644 /path/to/wp-content/plugins/choice-uft/*.php
   ```

5. **Increase PHP Timeouts**:
   ```php
   // Add to wp-config.php
   set_time_limit(300); // 5 minutes
   ini_set('max_execution_time', 300);
   ```

---

### Issue: Update Failed and Rolled Back

**Symptoms**:
- Update starts but fails midway
- Error message displayed
- Previous version restored
- Update history shows "failed" status

**Causes**:
- Corrupted download
- Extraction failure
- File permission issues
- Insufficient disk space
- PHP memory limit

**Solutions**:

1. **Check Error Details**:
   - Go to Settings → Universal Form Tracker → Updates → History
   - Find the failed update entry
   - Read the error details

2. **Verify Disk Space**:
   ```bash
   # Check available disk space
   df -h /path/to/wordpress/

   # Plugin needs at least 20 MB free
   ```

3. **Check PHP Memory Limit**:
   ```php
   // Add to wp-config.php
   define('WP_MEMORY_LIMIT', '256M');
   define('WP_MAX_MEMORY_LIMIT', '512M');
   ```

4. **Test Download Manually**:
   ```bash
   # Download latest release manually
   cd /tmp
   wget https://github.com/ChoiceOMG/choice-uft/releases/latest/download/choice-uft.zip

   # Verify ZIP integrity
   unzip -t choice-uft.zip
   ```

5. **Check WordPress Filesystem**:
   ```php
   // Add to theme's functions.php temporarily
   add_action('admin_notices', function() {
       global $wp_filesystem;
       if (WP_Filesystem()) {
           echo '<div class="notice notice-success"><p>WP_Filesystem initialized</p></div>';
       } else {
           echo '<div class="notice notice-error"><p>WP_Filesystem failed to initialize</p></div>';
       }
   });
   ```

---

### Issue: Nonce Not Generated

**Symptoms**:
- `cuftUpdater` JavaScript object is undefined
- `cuftUpdater.nonce` is missing
- All AJAX requests fail with security errors

**Causes**:
- JavaScript not enqueued properly
- Script dependency missing
- Admin page not loading scripts
- Theme/plugin conflict

**Solutions**:

1. **Verify Script is Enqueued**:
   ```bash
   # Check page source for cuft-updater script
   view-source:http://yoursite.com/wp-admin/plugins.php
   # Search for: cuft-updater.js
   ```

2. **Check Script Dependencies**:
   - Script depends on jQuery (optional)
   - View browser console for dependency errors

3. **Test Script Loading**:
   ```javascript
   // Run in browser console
   console.log('cuftUpdater defined:', typeof cuftUpdater !== 'undefined');
   console.log('cuftUpdater object:', cuftUpdater);
   ```

4. **Manually Localize Script** (temporary fix):
   ```php
   // Add to theme's functions.php
   add_action('admin_enqueue_scripts', function() {
       wp_localize_script('cuft-updater', 'cuftUpdater', array(
           'ajaxUrl' => admin_url('admin-ajax.php'),
           'nonce' => wp_create_nonce('cuft_updater_nonce')
       ));
   }, 20);
   ```

5. **Disable Plugin Conflicts**:
   - Deactivate all plugins except CUFT
   - Test if nonce appears
   - Reactivate plugins one by one to find conflict

---

### Issue: "Rate Limit Exceeded" Error

**Symptoms**:
- GitHub API returns 429 error
- Update check fails with rate limit message
- Can't fetch release information

**Causes**:
- Too many API requests in short time
- GitHub's unauthenticated API limit (60 requests/hour)
- Multiple sites using same IP

**Solutions**:

1. **Wait for Rate Limit Reset**:
   ```bash
   # Check when limit resets
   curl -I https://api.github.com/rate_limit
   # Look for: X-RateLimit-Reset header
   ```

2. **Use Authenticated Requests** (optional):
   ```php
   // Add GitHub personal access token to wp-config.php
   define('CUFT_GITHUB_TOKEN', 'your_personal_access_token');
   ```

3. **Increase Cache Duration**:
   - Update checks are cached for 12 hours by default
   - Don't use `force=true` frequently
   - Rely on automatic checks

4. **Check for Excessive Requests**:
   ```bash
   # Review update log for frequency
   wp db query "SELECT COUNT(*) as checks, DATE(timestamp) as date
                FROM wp_cuft_update_log
                WHERE action = 'check_completed'
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
                LIMIT 7;"
   ```

---

## Error Messages

### "Download Failed: Could not download update package"

**Meaning**: The plugin couldn't download the ZIP file from GitHub.

**Common Causes**:
- Network connectivity issues
- GitHub releases unavailable
- Firewall blocking download
- SSL certificate verification failure

**Solutions**:
```bash
# Test connectivity to GitHub
ping github.com
curl -I https://github.com/ChoiceOMG/choice-uft/releases/latest

# Check SSL certificates
curl -v https://github.com/ChoiceOMG/choice-uft/releases/latest 2>&1 | grep -i ssl

# Test download manually
wget https://github.com/ChoiceOMG/choice-uft/releases/latest/download/choice-uft.zip
```

---

### "Extraction Failed: Could not extract update files"

**Meaning**: The downloaded ZIP file couldn't be extracted.

**Common Causes**:
- Corrupted download
- Insufficient disk space
- File permission issues
- PHP ZIP extension missing

**Solutions**:
```bash
# Check PHP ZIP extension
php -m | grep zip
# If missing: sudo apt-get install php-zip (Ubuntu/Debian)

# Test extraction manually
unzip -t /path/to/choice-uft.zip

# Check temp directory permissions
ls -la /path/to/wp-content/upgrade/
chmod 755 /path/to/wp-content/upgrade/
```

---

### "Rollback Failed: Could not restore previous version"

**Meaning**: Update failed and automatic rollback also failed.

**Critical**: This requires immediate manual intervention.

**Solutions**:

1. **Manual Plugin Restore**:
   ```bash
   # Locate backup
   cd /path/to/wp-content/upgrade/
   ls -la | grep choice-uft

   # Restore manually
   rm -rf /path/to/wp-content/plugins/choice-uft/
   cp -r /path/to/backup/choice-uft/ /path/to/wp-content/plugins/
   ```

2. **Download Previous Version**:
   - Go to: https://github.com/ChoiceOMG/choice-uft/releases
   - Download previous stable version
   - Manually upload via WordPress admin or FTP

3. **Contact Support**:
   - Document all error messages
   - Provide update history log
   - Include WordPress and PHP versions

---

## Diagnostic Tools

### Browser Console Commands

```javascript
// Check update status
fetch(cuftUpdater.ajaxUrl + '?action=cuft_update_status&nonce=' + cuftUpdater.nonce)
    .then(r => r.json())
    .then(console.log);

// Check for updates
fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'cuft_check_update',
        nonce: cuftUpdater.nonce,
        force: 'true'
    })
}).then(r => r.json()).then(console.log);

// View update history
fetch(cuftUpdater.ajaxUrl + '?action=cuft_update_history&nonce=' + cuftUpdater.nonce + '&limit=10')
    .then(r => r.json())
    .then(data => console.table(data.data.entries));
```

### WP-CLI Commands

```bash
# Check current plugin version
wp plugin get choice-uft --field=version

# List all plugins
wp plugin list --format=table

# Check update transients
wp transient get cuft_update_status
wp transient get cuft_github_release_cache

# Clear update cache
wp transient delete cuft_update_status
wp transient delete cuft_github_release_cache

# Run update check manually
wp eval 'do_action("cuft_check_updates");'

# View update logs
wp db query "SELECT * FROM wp_cuft_update_log ORDER BY timestamp DESC LIMIT 10;"
```

### Database Queries

```sql
-- View recent update logs
SELECT * FROM wp_cuft_update_log
ORDER BY timestamp DESC
LIMIT 20;

-- Count failed updates
SELECT COUNT(*) as failures
FROM wp_cuft_update_log
WHERE status = 'error'
AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Check update configuration
SELECT option_value
FROM wp_options
WHERE option_name = 'cuft_update_config';

-- Clear all update transients
DELETE FROM wp_options
WHERE option_name LIKE '_transient_cuft_update%';
```

---

## Advanced Troubleshooting

### Enable Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

Check debug log:
```bash
tail -f /path/to/wp-content/debug.log
```

### Enable CUFT Debug Mode

Add to browser console:
```javascript
localStorage.setItem('cuft_debug', 'true');
location.reload();
```

### Test Mock Update

```javascript
// Simulate update with mock data
fetch(cuftUpdater.ajaxUrl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'cuft_perform_update',
        nonce: cuftUpdater.nonce,
        version: 'latest',
        mock: 'true',
        dry_run: 'true'
    })
}).then(r => r.json()).then(console.log);
```

---

## Support Resources

### Documentation
- [User Guide](user-guide.md) - Complete feature documentation
- [Developer Guide](developer-guide.md) - API and code examples

### Online Resources
- **GitHub Issues**: https://github.com/ChoiceOMG/choice-uft/issues
- **WordPress Support**: WordPress.org support forums
- **Plugin Website**: https://choiceomg.com/

### Reporting Bugs

When reporting issues, include:

1. **WordPress Environment**:
   ```bash
   wp core version
   wp plugin list --status=active
   wp theme list --status=active
   ```

2. **Error Details**:
   - Complete error message
   - Update history entry
   - Browser console errors
   - PHP error log entries

3. **Steps to Reproduce**:
   - Exact steps taken
   - Expected behavior
   - Actual behavior

4. **System Information**:
   - WordPress version
   - PHP version
   - MySQL version
   - Server OS
   - Web server (Apache/Nginx)

---

**Last Updated**: 2025-10-03
**Plugin Version**: 3.15.0
