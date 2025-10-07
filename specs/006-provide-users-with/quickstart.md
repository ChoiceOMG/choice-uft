# Quick Start Guide: Custom GTM Server Domain with Health Checks

## Overview
This guide helps you quickly set up and test the custom GTM server domain feature with automatic health monitoring.

## Prerequisites
- WordPress 5.0+ with administrator access
- Choice Universal Form Tracker plugin installed
- A GTM container ID configured
- (Optional) A custom first-party GTM server domain

## Setup Steps

### Step 1: Fix the Security Issue (Critical)

**Problem**: AJAX health checks fail with "Security check failed"

**Quick Fix**: Update nonce verification in 4 locations:

1. Open `/includes/class-cuft-admin.php`
2. Find and replace these lines:

```php
// Line 780 - BEFORE
wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )

// Line 780 - AFTER
wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )

// Line 940 - BEFORE
wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )

// Line 940 - AFTER
wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )

// Line 1073 - BEFORE
wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )

// Line 1073 - AFTER
wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )

// Line 2066 - BEFORE
wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )

// Line 2066 - AFTER
wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )
```

### Step 2: Configure Custom Server

1. Navigate to **Settings → Universal Form Tracker**
2. Find the **Server-side GTM** section
3. Enter your custom server URL (e.g., `https://gtm.yourdomain.com`)
4. Click **Test Server** to validate
5. If validation passes, click **Save Changes**

### Step 3: Verify Configuration

Check that settings were saved correctly:

```bash
# Using WP-CLI
wp option get cuft_sgtm_enabled
wp option get cuft_sgtm_url
wp option get cuft_sgtm_active_server

# Or check in WordPress admin
# Settings → Universal Form Tracker → Server-side GTM section
```

### Step 4: Test Health Check

1. Click **Run Health Check Now** button in admin
2. Check the status display:
   - ✅ Green: Custom server is active
   - ⚠️ Yellow: Using fallback (custom server unavailable)
   - ❌ Red: Configuration error

### Step 5: Verify Frontend Loading

1. Open your site's homepage
2. Open browser DevTools → Elements
3. Search for "googletagmanager" in the HTML
4. Verify the script source:

```html
<!-- If using custom server -->
<script src="https://gtm.yourdomain.com/gtm.js?id=GTM-XXXXX"></script>

<!-- If using fallback -->
<script src="https://www.googletagmanager.com/gtm.js?id=GTM-XXXXX"></script>
```

## Testing Scenarios

### Scenario 1: Happy Path (Custom Server Works)

```bash
# 1. Configure working custom server
Enter URL: https://gtm-server.example.com
Click: Test Server
Expected: "✅ Server validated successfully"

# 2. Save configuration
Click: Save Changes
Expected: Settings saved, using custom server

# 3. View frontend
Open: Homepage
Check: Script loads from custom domain
```

### Scenario 2: Server Initially Offline

```bash
# 1. Configure offline server
Enter URL: https://offline.example.com
Click: Test Server
Expected: "⚠️ Server validation failed"

# 2. Save anyway (with fallback)
Click: Save Changes
Expected: Settings saved, using Google fallback

# 3. View frontend
Open: Homepage
Check: Script loads from googletagmanager.com
```

### Scenario 3: Server Goes Down After Working

```bash
# 1. Configure working server
Setup: Working custom server

# 2. Simulate server failure
Action: Take custom server offline

# 3. Wait for health check
Wait: 6 hours (or trigger manual check)
Expected: System switches to fallback

# 4. Check admin notice
Open: WordPress admin
Expected: "⚠️ Custom GTM server unavailable"
```

### Scenario 4: Server Recovery

```bash
# 1. Start in fallback mode
Setup: Custom server offline, using fallback

# 2. Bring server back online
Action: Restore custom server

# 3. Trigger health checks
Click: Run Health Check Now (3 times)
Expected: After 3rd success, switches to custom

# 4. Verify recovery
Check: Active server = custom
Check: Admin notice about recovery
```

## Debugging

### Check Current Status

```javascript
// In browser console
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'cuft_get_sgtm_status',
        nonce: cuftAdmin.nonce
    },
    success: function(response) {
        console.log('Status:', response.data);
    }
});
```

### Force Health Check

```javascript
// Trigger immediate health check
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'cuft_manual_health_check',
        nonce: cuftAdmin.nonce
    },
    success: function(response) {
        console.log('Health check:', response.data);
    }
});
```

### View Debug Information

```javascript
// Enable debug mode
window.cuftConfig = window.cuftConfig || {};
window.cuftConfig.debug = true;

// Check GTM source
const gtmScript = document.querySelector('[src*="gtm.js"]');
console.log('GTM loading from:', gtmScript.src);
console.log('GTM attributes:', gtmScript.dataset);
```

## Common Issues

### Issue: "Security check failed"

**Solution**: Ensure nonce verification uses `cuft_admin` action (Step 1)

### Issue: Health check always fails

**Possible Causes**:
1. URL uses HTTP instead of HTTPS
2. Server doesn't return valid GTM JavaScript
3. Firewall blocking WordPress server
4. Timeout too short (increase from 5 to 10 seconds for testing)

### Issue: Not switching back to custom server

**Solution**: Must have 3 consecutive successful health checks
```bash
# Check counter
wp option get cuft_sgtm_health_consecutive_success

# Reset if needed
wp option update cuft_sgtm_health_consecutive_success 0
```

### Issue: No admin notices appearing

**Solution**: Check if notices are enabled
```php
// Add to wp-config.php for testing
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Testing with Mock Server

### Quick Python Mock Server

```python
# mock_gtm_server.py
from http.server import HTTPServer, BaseHTTPRequestHandler

class GTMHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        if '/gtm.js' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'application/javascript')
            self.end_headers()
            self.wfile.write(b'/* Mock GTM script */ (function(){window.google_tag_manager=true;}());')
        elif '/ns.html' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'text/html')
            self.end_headers()
            self.wfile.write(b'<html><!-- Google Tag Manager --></html>')
        else:
            self.send_response(404)
            self.end_headers()

if __name__ == '__main__':
    server = HTTPServer(('localhost', 8888), GTMHandler)
    print('Mock GTM server running on http://localhost:8888')
    server.serve_forever()
```

Run with: `python3 mock_gtm_server.py`

Test with: Configure `http://localhost:8888` as custom server

## Performance Validation

### Check Health Check Timing

```bash
# View cron schedules
wp cron event list

# Check next scheduled health check
wp cron event list | grep cuft_scheduled_health_check
```

### Monitor Server Response Time

```javascript
// Time a health check
console.time('healthCheck');
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'cuft_test_sgtm',
        nonce: cuftAdmin.nonce,
        sgtm_url: 'https://gtm.example.com'
    },
    complete: function() {
        console.timeEnd('healthCheck');
    }
});
```

Target: < 5 seconds for timeout, typically < 1 second for success

## Rollback Procedure

If you need to disable the feature:

```bash
# Via WP-CLI
wp option update cuft_sgtm_enabled false

# Or in database
UPDATE wp_options SET option_value = '0' WHERE option_name = 'cuft_sgtm_enabled';

# Or in WordPress admin
Settings → Universal Form Tracker → Server-side GTM → Uncheck "Enable"
```

This immediately reverts to using Google's default GTM endpoints.