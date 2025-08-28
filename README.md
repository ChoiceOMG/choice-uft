# Choice Universal Form Tracker

Universal WordPress plugin for tracking form submissions across multiple frameworks and link clicks via Google Tag Manager's dataLayer.

## Features

- **Universal Form Support**: Tracks Avada/Fusion, Elementor Pro, Contact Form 7, Ninja Forms, and Gravity Forms
- **Smart Detection**: Automatically detects available form frameworks
- **Form Tracking**: Pushes `form_submit` events with `user_email` and `user_phone` data
- **Link Tracking**: Tracks `phone_click` events on tel: links
- **GTM Integration**: Optional Google Tag Manager container injection
- **UTM Campaign Tracking**: Automatic detection and attribution of marketing campaigns
- **Debug Logging**: Comprehensive logging system for troubleshooting
- **Admin Dashboard**: Beautiful admin interface with framework status and debug logs
- **jQuery-Free**: Uses vanilla JavaScript for maximum compatibility

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
├── cuft-utm-tracker.js              # UTM parameter detection
├── cuft-utm-utils.js                # UTM utilities for forms
├── icon.svg                         # Plugin icon
└── forms/                           # Framework-specific JavaScript
    ├── cuft-avada-forms.js
    ├── cuft-elementor-forms.js
    ├── cuft-cf7-forms.js
    ├── cuft-ninja-forms.js
    └── cuft-gravity-forms.js
```

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure GTM container ID in Settings > Universal Form Tracker

## Configuration

### GTM Integration
- Go to **Settings > Universal Form Tracker**
- Enter your GTM container ID (format: GTM-XXXXXXX)
- Enable debug logging if needed
- Save settings

### Framework Support
The plugin automatically detects and supports:
- **Avada/Fusion Forms**: Built-in theme forms
- **Elementor Pro Forms**: Advanced form widget
- **Contact Form 7**: Popular contact form plugin
- **Ninja Forms**: Drag & drop form builder
- **Gravity Forms**: Advanced form solution

### UTM Campaign Tracking
The plugin automatically tracks UTM parameters for marketing attribution:
- Detects UTM parameters when users first visit your site
- Stores campaign data in user session for 30 days
- Includes UTM data in all form submissions for proper attribution
- Supports all standard UTM parameters: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`

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
  formType: 'contact_form_7',    // Framework identifier
  formId: 'contact-form-1',
  formName: 'Contact Form',
  user_email: 'user@example.com',
  user_phone: '1234567890',
  utm_source: 'google',          // Campaign attribution
  utm_medium: 'cpc',
  utm_campaign: 'summer_sale',
  utm_term: 'contact_form',
  utm_content: 'header_cta',
  submittedAt: '2024-01-01T12:00:00.000Z'
}
```

### Phone Click
```javascript
{
  event: 'phone_click',
  phone: '1234567890',
  href: 'tel:+1-234-567-8890',
  clickedAt: '2024-01-01T12:00:00.000Z'
}
```

## Requirements

- WordPress 5.0+
- Modern browser support (IE11+)
- No jQuery dependency

## Version History

- **3.1.0**: Added UTM campaign tracking for marketing attribution
- **3.0.0**: Universal form framework support, vanilla JS, debug logging, framework detection
- **2.0.0**: Refactored into modular structure with separate classes
- **1.2.0**: Added GTM integration and admin settings
- **1.0.0**: Initial release with form and link tracking

## Support

For issues and feature requests, please contact Choice OMG at https://choice.marketing
