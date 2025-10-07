# Frontend Loading Contract: Custom GTM Server

## Overview
This document defines how GTM container scripts are loaded on the frontend when a custom server is configured.

## Script Loading Logic

### Decision Flow
```
Page Load
    ↓
Is custom server enabled?
    No → Load from Google default
    Yes ↓
Is active_server == 'custom'?
    No → Load from Google default (fallback mode)
    Yes → Load from custom server URL
```

### Implementation in PHP

```php
class CUFT_GTM_Loader {

    /**
     * Get the appropriate GTM server URL
     * @return string The server URL to use for loading GTM
     */
    public function get_gtm_server_url() {
        // Check if custom server is enabled
        $enabled = get_option('cuft_sgtm_enabled', false);
        if (!$enabled) {
            return 'https://www.googletagmanager.com';
        }

        // Check if we should use custom server
        $active_server = get_option('cuft_sgtm_active_server', 'fallback');
        if ($active_server !== 'custom') {
            return 'https://www.googletagmanager.com';
        }

        // Get and validate custom URL
        $custom_url = get_option('cuft_sgtm_url', '');
        if (empty($custom_url)) {
            return 'https://www.googletagmanager.com';
        }

        // Remove trailing slash if present
        return rtrim($custom_url, '/');
    }

    /**
     * Output GTM container script in <head>
     */
    public function output_gtm_head_script() {
        $gtm_id = get_option('cuft_gtm_id', '');
        if (empty($gtm_id)) {
            return;
        }

        $server_url = $this->get_gtm_server_url();
        ?>
        <!-- Google Tag Manager -->
        <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '<?php echo esc_url($server_url); ?>/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
        </script>
        <!-- End Google Tag Manager -->
        <?php
    }

    /**
     * Output GTM noscript in <body>
     */
    public function output_gtm_body_script() {
        $gtm_id = get_option('cuft_gtm_id', '');
        if (empty($gtm_id)) {
            return;
        }

        $server_url = $this->get_gtm_server_url();
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="<?php echo esc_url($server_url); ?>/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
}
```

## URL Patterns

### Google Default Endpoints
- **GTM JavaScript**: `https://www.googletagmanager.com/gtm.js?id=GTM-XXXXX`
- **NoScript iframe**: `https://www.googletagmanager.com/ns.html?id=GTM-XXXXX`

### Custom Server Endpoints
- **GTM JavaScript**: `https://gtm.example.com/gtm.js?id=GTM-XXXXX`
- **NoScript iframe**: `https://gtm.example.com/ns.html?id=GTM-XXXXX`

## Frontend Health Check Integration

### Background Health Check on Page Load
```javascript
// Only run if custom server is configured
if (window.cuftConfig && window.cuftConfig.custom_server_enabled) {

    // Run health check 30 seconds after page load (non-blocking)
    setTimeout(function() {

        // Skip if last check was within 1 hour
        const lastCheck = localStorage.getItem('cuft_last_health_check');
        if (lastCheck && (Date.now() - parseInt(lastCheck)) < 3600000) {
            return;
        }

        // Perform background health check
        fetch(cuftConfig.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'cuft_background_health_check',
                nonce: cuftConfig.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            // Store last check time
            localStorage.setItem('cuft_last_health_check', Date.now().toString());

            // If server status changed, reload may be needed on next page
            if (data.data && data.data.status_changed) {
                console.info('GTM server status changed:', data.data.active_server);
            }
        })
        .catch(error => {
            // Silent fail - don't interrupt user experience
            if (window.cuftConfig.debug) {
                console.warn('Background health check failed:', error);
            }
        });

    }, 30000); // 30 seconds delay
}
```

## Fallback Behavior

### Automatic Fallback Triggers
1. **Health Check Failure**: Server doesn't respond within 5 seconds
2. **Invalid Response**: Server responds but doesn't serve valid GTM scripts
3. **Network Error**: DNS failure, connection refused, etc.

### Fallback Process
```
Custom Server Fails
    ↓
Set active_server = 'fallback'
    ↓
Increment consecutive_failure counter
    ↓
Next page loads use Google default
    ↓
Continue background health checks
```

### Recovery Process
```
Custom Server Succeeds (while in fallback)
    ↓
Increment consecutive_success counter
    ↓
Counter >= 3?
    No → Stay in fallback
    Yes ↓
Set active_server = 'custom'
    ↓
Reset consecutive_success to 0
    ↓
Next page loads use custom server
```

## Data Attributes for Debugging

The implementation adds data attributes to help with debugging:

```html
<!-- When using custom server -->
<script data-cuft-gtm-source="custom"
        data-cuft-gtm-server="https://gtm.example.com"
        src="https://gtm.example.com/gtm.js?id=GTM-XXXXX"></script>

<!-- When using fallback -->
<script data-cuft-gtm-source="fallback"
        data-cuft-gtm-server="https://www.googletagmanager.com"
        data-cuft-fallback-reason="health_check_failed"
        src="https://www.googletagmanager.com/gtm.js?id=GTM-XXXXX"></script>
```

## Performance Considerations

1. **Server URL Caching**: The server URL is determined once per page load and cached
2. **No Blocking Operations**: Health checks never block page rendering
3. **LocalStorage Throttling**: Frontend health checks limited to once per hour
4. **Lazy Background Checks**: Frontend checks delayed 30 seconds after load

## Error Handling

### Script Loading Errors
If GTM script fails to load (from either source):
1. Browser's onerror handler logs to console (if debug mode)
2. Tracking continues to fail gracefully
3. No automatic retry on same page load
4. Next page load will use current server configuration

### Network Errors
- Connection failures don't break the page
- Scripts loaded with `async` attribute
- Timeout errors handled gracefully
- No user-facing error messages

## Debug Mode

When `window.cuftConfig.debug = true`:

```javascript
// Log GTM source on page load
console.log('GTM loading from:', document.querySelector('[data-cuft-gtm-source]').dataset);

// Log health check results
console.log('Health check result:', healthCheckResponse);

// Log fallback reasons
if (fallbackReason) {
    console.warn('GTM using fallback due to:', fallbackReason);
}
```

## WordPress Hooks

### Filters
```php
// Filter the GTM server URL before use
add_filter('cuft_gtm_server_url', function($url) {
    // Custom logic to modify server URL
    return $url;
});

// Filter whether to use custom server
add_filter('cuft_use_custom_server', function($use_custom) {
    // Force fallback in certain conditions
    return $use_custom;
});
```

### Actions
```php
// Fired when switching to fallback
do_action('cuft_gtm_fallback_activated', $reason);

// Fired when switching to custom
do_action('cuft_gtm_custom_activated');

// Fired after health check
do_action('cuft_health_check_complete', $result);
```