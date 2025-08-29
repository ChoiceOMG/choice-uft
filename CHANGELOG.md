# Changelog

All notable changes to the Choice Universal Form Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **GA4 Standard Parameters**: Enhanced all events with Google Analytics 4 standard parameters for maximum compatibility
  - `page_location` - Current page URL
  - `page_referrer` - Previous page URL (JavaScript events only)
  - `page_title` - Current page title
  - `language` - User's language/locale
  - `screen_resolution` - User's screen resolution (JavaScript events only)
  - `engagement_time_msec` - Time engaged before event (JavaScript events only)
- **Event Source Identification**: Added `cuft_tracked: true` and `cuft_source` parameters to distinguish plugin-tracked events
- **Page Load Time Tracking**: Implemented global page load time tracking for accurate engagement time calculation
- **Generate Lead Events**: New admin option to automatically fire `generate_lead` events for qualified form submissions
  - Triggers when form has both user email and UTM campaign data
  - Configurable via admin settings page
  - Includes GA4-standard lead tracking parameters (`currency`, `value`)
  - Available for all supported form frameworks
  - Comprehensive debug logging for lead events
- **Browser Console Logging Control**: Granular control over JavaScript console logging
  - Three levels: No, Yes, Admin Only
  - "Admin Only" enables console logging only for logged-in administrators
  - Ideal for production debugging without exposing logs to visitors
  - Applies to all form tracking and link tracking JavaScript

### Enhanced

- **Form Submission Events**: All form tracking now includes GA4 standard parameters

  - Elementor Pro Forms (`cuft_source: "elementor_pro"` for JS, `"elementor_pro_server"` for PHP)
  - Contact Form 7 (`cuft_source: "contact_form_7"` for JS, `"contact_form_7_server"` for PHP)
  - Gravity Forms (`cuft_source: "gravity_forms"` for JS, `"gravity_forms_server"` for PHP)
  - Ninja Forms (`cuft_source: "ninja_forms"` for JS, `"ninja_forms_server"` for PHP)
  - Avada/Fusion Forms (`cuft_source: "avada_fusion"`)

- **Link Click Events**: Phone and email click tracking enhanced with GA4 parameters
  - Phone clicks (`phone_click` event with `cuft_source: "link_tracking"`)
  - Email clicks (`email_click` event with `cuft_source: "link_tracking"`)

### Fixed

- **Reserved Property Names**: Changed link click events to use non-reserved property names
  - `phone_click` events now use `clicked_phone` instead of `phone`
  - `email_click` events now use `clicked_email` instead of `email`

### Technical Improvements

- **Code Formatting**: Standardized JavaScript code formatting across all form tracking files using Prettier-style formatting
- **GA4 Compatibility**: Events now align with Google Analytics 4 enhanced measurement standards
- **Analytics Attribution**: Enhanced tracking source identification for better analytics insights
- **Lead Tracking Architecture**: Intelligent lead generation system with both client-side and server-side implementations
- **Debug Visibility**: Generate lead events are fully integrated into the debug logging system

### Performance

- **Engagement Time Calculation**: Accurate user engagement tracking relative to page load time
- **Non-Interfering Design**: Maintained defensive coding practices to ensure tracking never affects user experience

---

## Previous Versions

### [3.1.0] - Previous Release

- Added UTM campaign tracking for marketing attribution
- Universal form framework support with vanilla JavaScript
- Comprehensive debug logging system
- Framework detection and status reporting

### [3.0.0] - Major Refactor

- Universal form framework support
- Vanilla JavaScript implementation (no jQuery dependency)
- Modular structure with separate classes
- Comprehensive debug logging

### [2.0.0] - Structural Improvements

- Refactored into modular structure
- Separate classes for different form frameworks
- Improved error handling

### [1.2.0] - GTM Integration

- Added Google Tag Manager integration
- Introduced admin settings page
- Enhanced form tracking capabilities

### [1.0.0] - Initial Release

- Basic form and link tracking functionality
- Support for major WordPress form plugins
