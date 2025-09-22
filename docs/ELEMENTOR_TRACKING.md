# Elementor Forms Tracking Documentation

## Overview

The Choice Universal Form Tracker provides comprehensive tracking for Elementor Pro forms with multiple fallback mechanisms to ensure reliable data capture across all Elementor versions.

## Implementation Details

### Event Detection Methods

Our Elementor forms tracking uses a multi-layered approach to ensure form submissions are captured:

#### 1. Native JavaScript Events (Primary - Elementor 3.5+)
```javascript
document.addEventListener("submit_success", function(event) {
    // Handle form submission with event.detail
});
```

#### 2. jQuery Events (Fallback - Older Versions)
```javascript
jQuery(document).on("submit_success", function(event, response) {
    // Handle form submission with response data
});
```

#### 3. MutationObserver (Success Messages)
Watches for DOM changes indicating successful submission:
- `.elementor-message-success`
- `.elementor-form-success-message`

#### 4. Ajax Interceptors
Intercepts both fetch and XMLHttpRequest calls to detect form submissions:
- Monitors requests to `admin-ajax.php`
- Checks for `elementor_pro_forms_send_form` action
- Validates success responses

#### 5. jQuery.ajaxComplete (When Available)
Additional layer for jQuery-based submissions.

### Form Identification

The tracker identifies forms using multiple methods:

1. **Event Target**: Direct form element from event
2. **Tracking Attribute**: Forms marked with `data-cuft-tracking="pending"`
3. **Visible Forms**: Active forms with user input
4. **Parent Traversal**: Finding forms related to success messages

## Events Fired

### form_submit Event

Fired on every successful form submission.

**Payload Structure:**
```javascript
{
  event: "form_submit",
  formType: "elementor",
  formId: "contact-form-1",
  formName: "Contact Form",
  submittedAt: "2025-01-22T10:30:00.000Z",

  // User Data (if available)
  user_email: "user@example.com",
  user_phone: "+1234567890",

  // UTM Parameters (if available)
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "summer_sale",
  utm_term: "keyword",
  utm_content: "ad_variant_a",

  // Click IDs (if available)
  click_id: "abc123",
  gclid: "xyz789",
  fbclid: "meta456",

  // GA4 Standard Parameters
  page_location: "https://example.com/contact",
  page_referrer: "https://google.com",
  page_title: "Contact Us",
  language: "en-US",
  screen_resolution: "1920x1080",
  engagement_time_msec: 45000,

  // Tracking Metadata
  cuft_tracked: true,
  cuft_source: "elementor_pro"
}
```

### generate_lead Event

Fired only when ALL three conditions are met:
1. **Click ID** is present (any supported click ID)
2. **Email** field has a value
3. **Phone** field has a value

**Additional Payload Fields:**
```javascript
{
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_source: "elementor_pro_lead"
  // ... plus all fields from form_submit
}
```

## Supported Click IDs

The following click ID parameters are automatically detected and tracked:

| Parameter | Platform | Description |
|-----------|----------|-------------|
| `click_id` | Generic | Universal click identifier |
| `gclid` | Google Ads | Standard Google click ID |
| `gbraid` | Google Ads | iOS app-to-web journeys |
| `wbraid` | Google Ads | Web-to-app journeys |
| `fbclid` | Meta (Facebook/Instagram) | Meta advertising click ID |
| `msclkid` | Microsoft/Bing | Microsoft Advertising |
| `ttclid` | TikTok | TikTok Ads |
| `li_fat_id` | LinkedIn | LinkedIn Ads |
| `twclid` | Twitter/X | Twitter/X Ads |
| `snap_click_id` | Snapchat | Snapchat Ads |
| `pclid` | Pinterest | Pinterest Ads |

## Data Sources Priority

Tracking parameters are retrieved in the following order:

1. **URL Parameters**: Fresh data from current page URL
2. **SessionStorage**: Data stored during the session
3. **Cookies**: Persistent data in `cuft_utm_data` cookie
4. **Empty Object**: Graceful fallback if no data available

## Configuration

### Enable Tracking

Tracking is automatically enabled when Elementor Pro is detected. No additional configuration required.

### Debug Mode

Enable detailed console logging:

```javascript
window.cuftElementor = {
  console_logging: true,      // Enable debug logs
  generate_lead_enabled: true  // Enable lead generation events
};
```

### Custom Configuration (PHP)

```php
// Disable Elementor tracking
add_filter('cuft_track_elementor_forms', '__return_false');

// Customize lead generation
add_filter('cuft_elementor_generate_lead_enabled', '__return_true');
```

## Troubleshooting

### Common Issues

#### Form submission not tracked
1. Check browser console for errors
2. Verify Elementor Pro is installed and active
3. Enable debug mode to see detailed logs
4. Check if form has proper `.elementor-form` class

#### Click IDs not captured
1. Verify parameters are in URL
2. Check SessionStorage for stored data
3. Inspect cookies for `cuft_utm_data`
4. Enable debug logging to trace data flow

#### Generate lead not firing
1. Verify all three requirements are met (click_id, email, phone)
2. Check console logs for specific missing fields
3. Ensure generate_lead is enabled in configuration

### Debug Commands

Run in browser console:

```javascript
// Check current tracking data
cuftGetTrackingData()

// View stored session data
JSON.parse(sessionStorage.getItem('cuft_tracking_data'))

// Check cookie data
document.cookie.split(';').find(c => c.trim().startsWith('cuft_utm_data'))

// Enable debug logging
window.cuftElementor = { console_logging: true }
```

## Browser Compatibility

| Browser | Minimum Version | Notes |
|---------|----------------|--------|
| Chrome | 60+ | Full support |
| Firefox | 55+ | Full support |
| Safari | 11+ | Full support |
| Edge | 79+ | Full support |
| IE | Not supported | Use fallback methods |

## Elementor Version Compatibility

| Elementor Version | Support Level | Method Used |
|-------------------|--------------|-------------|
| 3.7+ (Pro) | ✅ Full | Native events + all fallbacks |
| 3.5+ | ✅ Full | Native events + all fallbacks |
| 3.0 - 3.4 | ✅ Full | jQuery events + fallbacks |
| < 3.0 | ⚠️ Partial | Ajax interceptors only |

## Performance Considerations

- Event listeners are passive where possible
- MutationObserver uses specific selectors to minimize overhead
- Ajax interceptors are lightweight and don't modify responses
- Debug logging only outputs when explicitly enabled
- No jQuery dependency - works with pure JavaScript

## Security

- All user input is sanitized before tracking
- No sensitive data is logged in production
- Click IDs are validated against known patterns
- AJAX nonces are verified on server-side
- Cookie data is properly encoded/decoded