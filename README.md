# Choice Universal Form Tracker

Universal WordPress plugin for tracking form submissions across multiple frameworks and link clicks via Google Tag Manager's dataLayer.

## Features

- **Universal Form Support**: Tracks Avada/Fusion, Elementor Pro, Contact Form 7, Ninja Forms, and Gravity Forms
- **Smart Detection**: Automatically detects available form frameworks and email fields to prevent false positives
- **Enhanced Form Tracking**: Pushes `form_submit` events with `user_email` and `user_phone` data
- **Improved Success Detection**: Advanced Avada form success state detection with comprehensive debugging
- **Link Tracking**: Tracks `phone_click` events on tel: links
- **GTM Integration**: Optional Google Tag Manager container injection
- **UTM Campaign Tracking**: Automatic detection and attribution of marketing campaigns
- **Click ID Tracking**: Support for all major advertising platform click IDs (Google, Facebook, Microsoft, TikTok, LinkedIn, Twitter, Snapchat, Pinterest)
- **GA4 Enhanced Compatibility**: Full Google Analytics 4 parameter alignment for maximum analytics value
- **Generate Lead Events**: Automatic lead generation tracking for qualified form submissions
- **Console Logging Control**: Granular browser console logging (No/Yes/Admin Only)
- **Debug Logging**: Comprehensive logging system for troubleshooting
- **Admin Dashboard**: Beautiful admin interface with framework status and debug logs
- **JavaScript-First Design**: Prefers vanilla JavaScript with jQuery fallbacks for maximum compatibility
- **Server-Side GTM Support**: Optional first-party tracking through custom domain (new in v3.5.0)

## File Structure

```
choice-universal-form-tracker.php    # Main plugin file
includes/
├── class-cuft-admin.php             # Admin settings page
├── class-cuft-gtm.php               # GTM integration
├── class-cuft-form-detector.php     # Framework detection
├── class-cuft-form-tracker.php      # Form tracking coordinator
├── class-cuft-link-tracking.php     # Link click tracking
├── class-cuft-logger.php            # Debug logging system
├── class-cuft-utm-tracker.php       # UTM campaign tracking
└── forms/                           # Framework-specific handlers
    ├── class-cuft-avada-forms.php
    ├── class-cuft-elementor-forms.php
    ├── class-cuft-cf7-forms.php
    ├── class-cuft-ninja-forms.php
    └── class-cuft-gravity-forms.php
assets/
├── cuft-links.js                    # Link tracking JavaScript
├── cuft-utm-tracker.js              # UTM parameter and Click ID detection
├── cuft-utm-utils.js                # UTM and Click ID utilities for forms
├── icon.svg                         # Plugin icon
└── forms/                           # Framework-specific JavaScript
    ├── cuft-avada-forms.js
    ├── cuft-elementor-forms.js
    ├── cuft-cf7-forms.js
    ├── cuft-ninja-forms.js
    └── cuft-gravity-forms.js
```

## Core Design Principles

### JavaScript-First Approach
The plugin prioritizes pure vanilla JavaScript implementations with jQuery as a fallback option. This ensures:
- Maximum compatibility across different WordPress setups
- Works with or without jQuery
- Supports modern Elementor (3.5+) and legacy versions
- Multiple fallback detection methods for reliability

### Robust Event Tracking
Implements multiple layers of event detection:
1. Native JavaScript CustomEvents (modern browsers)
2. jQuery events (when available)
3. MutationObserver (DOM changes)
4. Ajax interceptors (fetch and XMLHttpRequest)
5. Form submission handlers

### Data Fallback Chain
Tracking data is retrieved with graceful degradation:
```
URL Parameters → SessionStorage → Cookies → Empty Object
```

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure GTM container ID in Settings > Universal Form Tracker

## Configuration

### GTM Integration

- Go to **Settings > Universal Form Tracker**
- Enter your GTM container ID (format: GTM-XXXX or GTM-XXXXXXX for older/newer containers)
- Enable debug logging if needed
- Save settings

### Framework Support

The plugin automatically detects and supports:

- **Avada/Fusion Forms**: Built-in theme forms
- **Elementor Pro Forms**: Advanced form widget
- **Contact Form 7**: Popular contact form plugin
- **Ninja Forms**: Drag & drop form builder
- **Gravity Forms**: Advanced form solution

### Server-Side GTM (sGTM) Support

**New in v3.5.0**: Enable first-party tracking through your own domain to avoid third-party cookie restrictions and improve tracking reliability.

**Configuration:**

1. Navigate to **Settings > Universal Form Tracker**
2. Enable "Server-Side GTM" checkbox
3. Enter your server GTM URL (e.g., `https://gtm.yourdomain.com`)
   - For local development: `.localnet` domains are supported (e.g., `https://tagging-server.localnet`)
   - SSL verification is automatically disabled for `.localnet` domains to support self-signed certificates
4. Click "Test Connection" to validate endpoints
5. Save settings once validation succeeds

**Benefits:**

- **First-Party Tracking**: Scripts served from your domain avoid browser restrictions
- **Improved Privacy**: Better control over data collection and processing
- **Ad Blocker Resistant**: Less likely to be blocked than third-party scripts
- **Cookie Compliance**: First-party cookies have better browser support
- **Faster Loading**: Can leverage your CDN and caching infrastructure

**Requirements:**

- A configured server-side GTM container
- Proxy endpoints for `/gtm.js` and `/ns.html`
- HTTPS connection to your server GTM domain

**Fallback**: If validation fails or sGTM is disabled, the plugin automatically uses standard Google Tag Manager URLs.

### UTM Campaign Tracking

The plugin automatically tracks UTM parameters for marketing attribution:

- Detects UTM parameters when users first visit your site
- Stores campaign data in user session for 30 days
- Includes UTM data in all form submissions for proper attribution
- Supports all standard UTM parameters: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`

### GA4 Enhanced Measurement Compatibility

This plugin is designed to be **additive** with Google Analytics 4's enhanced measurement, not competitive:

**Why GA4 Enhanced Measurement Misses WordPress Form Plugins:**

- GA4's built-in `form_submit` tracking only detects standard HTML form submissions
- Popular WordPress form plugins (Elementor Pro, Contact Form 7, Gravity Forms, etc.) use AJAX submissions and custom event systems
- These bypass GA4's automatic detection, creating tracking blind spots

**Our Additive Approach:**

- ✅ **Fills the Gap**: Tracks forms that GA4 enhanced measurement cannot see
- ✅ **No Conflicts**: Uses unique event parameters (`cuft_tracked: true`, specific `cuft_source` values)
- ✅ **GA4 Compatible**: All events include standard GA4 parameters (`page_location`, `engagement_time_msec`, etc.)
- ✅ **Future-Proof**: Aligns with Google's recommended tracking practices

**Result**: Complete form tracking coverage with both GA4's native capabilities AND comprehensive WordPress form plugin support.

### Generate Lead Events

The plugin can automatically fire `generate_lead` events for high-value form submissions:

**Configuration:**

- Enable via **Settings > Universal Form Tracker > Generate Lead Events**
- Automatically triggers when forms are submitted with both:
  - User email address
  - UTM campaign data (indicating traffic from marketing campaigns)

**Benefits:**

- ✅ **Conversion Tracking**: Perfect for GA4 conversion goals and Google Ads tracking
- ✅ **Lead Quality**: Only fires for submissions with marketing attribution
- ✅ **Standard Compliance**: Uses GA4's recommended `generate_lead` event structure
- ✅ **Debug Visibility**: All lead events appear in debug logs when enabled

### Browser Console Logging

Control JavaScript console output for debugging:

**Settings Options:**

- **No**: Disable all browser console logging (production default)
- **Yes**: Enable console logging for all visitors (development mode)
- **Admin Only**: Enable console logging only for logged-in administrators (recommended for production debugging)

**Benefits:**

- ✅ **Production Safe**: Debug live sites without exposing console logs to visitors
- ✅ **Developer Friendly**: Full debugging capabilities when needed
- ✅ **Performance Optimized**: No console overhead when disabled

### Filters

```php
// Disable plugin entirely
add_filter( 'cuft_enabled', '__return_false' );

// Enable debug logging
add_filter( 'cuft_debug', '__return_true' );
```

## DataLayer Events

### Form Submit

```javascript
{
  event: 'form_submit',
  formType: 'contact_form_7',        // Framework identifier
  formId: 'contact-form-1',
  formName: 'Contact Form',
  user_email: 'user@example.com',
  user_phone: '1234567890',

  // GA4 Standard Parameters
  page_location: 'https://example.com/contact',
  page_referrer: 'https://google.com',
  page_title: 'Contact Us',
  language: 'en-US',
  screen_resolution: '1920x1080',
  engagement_time_msec: 15432,

  // Plugin Identification
  cuft_tracked: true,
  cuft_source: 'contact_form_7',     // Specific tracking source

  // UTM Campaign Attribution
  utm_source: 'google',
  utm_medium: 'cpc',
  utm_campaign: 'summer_sale',
  utm_term: 'contact_form',
  utm_content: 'header_cta',

  // Click ID Attribution (when available)
  gclid: 'TeSter-123',               // Google Ads click ID
  fbclid: 'IwAR1234567890',          // Facebook/Meta Ads click ID
  msclkid: 'abc123def456',           // Microsoft Ads click ID
  ttclid: 'tiktok_click_123',        // TikTok Ads click ID
  li_fat_id: 'linkedin_123',         // LinkedIn Ads click ID

  submittedAt: '2024-01-01T12:00:00.000Z'
}
```

### Phone Click

```javascript
{
  event: 'phone_click',
  clicked_phone: '1234567890',       // Non-reserved property name
  href: 'tel:+1-234-567-8890',

  // GA4 Standard Parameters
  page_location: 'https://example.com/contact',
  page_referrer: 'https://google.com',
  page_title: 'Contact Us',
  language: 'en-US',
  screen_resolution: '1920x1080',
  engagement_time_msec: 8245,

  // Plugin Identification
  cuft_tracked: true,
  cuft_source: 'link_tracking',

  clickedAt: '2024-01-01T12:00:00.000Z'
}
```

### Email Click

```javascript
{
  event: 'email_click',
  clicked_email: 'contact@example.com', // Non-reserved property name
  href: 'mailto:contact@example.com',

  // GA4 Standard Parameters
  page_location: 'https://example.com/contact',
  page_referrer: 'https://google.com',
  page_title: 'Contact Us',
  language: 'en-US',
  screen_resolution: '1920x1080',
  engagement_time_msec: 12156,

  // Plugin Identification
  cuft_tracked: true,
  cuft_source: 'link_tracking',

  clickedAt: '2024-01-01T12:00:00.000Z'
}
```

### Generate Lead (when enabled)

```javascript
{
  event: 'generate_lead',
  currency: 'USD',
  value: 0,

  // GA4 Standard Parameters
  page_location: 'https://example.com/contact',
  page_referrer: 'https://google.com',
  page_title: 'Contact Us',
  language: 'en-US',
  screen_resolution: '1920x1080',
  engagement_time_msec: 15432,

  // Plugin Identification
  cuft_tracked: true,
  cuft_source: 'contact_form_7_lead',   // Indicates lead source

  // Form Context
  formType: 'contact_form_7',
  formId: 'contact-form-1',
  formName: 'Contact Form',

  // UTM Campaign Attribution (required for lead generation)
  utm_source: 'google',
  utm_medium: 'cpc',
  utm_campaign: 'summer_sale',
  utm_term: 'contact_form',
  utm_content: 'header_cta',

  submittedAt: '2024-01-01T12:00:00.000Z'
}
```

## Requirements

- WordPress 5.0+
- Modern browser support (IE11+)
- No jQuery dependency

## Click ID Support

The plugin automatically detects and tracks click IDs from all major advertising platforms:

### Supported Click IDs

| Platform      | Parameter       | Description                                     |
| ------------- | --------------- | ----------------------------------------------- |
| Google Ads    | `gclid`         | Standard Google Ads click ID                    |
| Google Ads    | `gbraid`        | Google Ads click ID for iOS app-to-web journeys |
| Google Ads    | `wbraid`        | Google Ads click ID for web-to-app journeys     |
| Facebook/Meta | `fbclid`        | Facebook/Instagram Ads click ID                 |
| Microsoft Ads | `msclkid`       | Microsoft Advertising (Bing Ads) click ID       |
| TikTok Ads    | `ttclid`        | TikTok Ads click ID                             |
| LinkedIn Ads  | `li_fat_id`     | LinkedIn Ads click ID                           |
| Twitter/X Ads | `twclid`        | Twitter/X Ads click ID                          |
| Snapchat Ads  | `snap_click_id` | Snapchat Ads click ID                           |
| Pinterest Ads | `pclid`         | Pinterest Ads click ID                          |

### How It Works

1. **Automatic Detection**: Click IDs are automatically detected from URL parameters when visitors land on your site
2. **Persistent Storage**: Click IDs are stored for 30 days (same as UTM parameters) to attribute form submissions
3. **Form Attribution**: When a form is submitted, any stored click IDs are included in the dataLayer event
4. **Cross-Platform Support**: Works with all major advertising platforms for comprehensive attribution

### Example Usage

When a visitor clicks a Google Ads link:

```
https://yoursite.com/contact?gclid=TeSter-123&utm_source=google&utm_campaign=summer_sale
```

The form submission will include:

```javascript
{
  event: 'form_submit',
  gclid: 'TeSter-123',
  utm_source: 'google',
  utm_campaign: 'summer_sale',
  // ... other form data
}
```

## Version History

- **3.3.0**: Major Avada form tracking bug fixes - improved AJAX support, enhanced success detection, resolved duplicate submissions, better error handling, and compatibility with latest Avada/Fusion versions
- **3.2.1**: Enhanced Avada form tracking with improved success detection, email field validation, comprehensive debugging, and click ID support for all major advertising platforms
- **3.1.0**: Added UTM campaign tracking for marketing attribution
- **3.0.0**: Universal form framework support, vanilla JS, debug logging, framework detection
- **2.0.0**: Refactored into modular structure with separate classes
- **1.2.0**: Added GTM integration and admin settings
- **1.0.0**: Initial release with form and link tracking

## Support

For issues and feature requests, please contact Choice OMG at https://choice.marketing
