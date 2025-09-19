# Changelog

All notable changes to the Choice Universal Form Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.5.0] - 2025-01-20

### Added

- **Server-Side GTM (sGTM) Support**: First-party tracking through custom domain
  - Enable custom server URLs for GTM script delivery
  - Built-in endpoint validation with real-time connection testing
  - AJAX-powered configuration testing in admin panel
  - Support for both `/gtm.js` and `/ns.html` endpoints
  - Automatic fallback to standard GTM if validation fails
  - Status display in Framework Detection section
- **Enhanced Admin Interface**: Improved settings organization
  - New Server-Side GTM configuration section
  - Dynamic UI with show/hide based on configuration
  - Real-time validation feedback
  - Clear status indicators for sGTM configuration

### Enhanced

- **First-Party Tracking Benefits**: When sGTM is enabled
  - Avoid third-party cookie restrictions
  - Reduce ad blocker interference
  - Improve tracking reliability
  - Better privacy compliance
  - Leverage your own CDN and caching

### Technical

- **Backward Compatibility**: 100% backward compatible
  - Feature is disabled by default
  - No changes to existing functionality
  - Seamless upgrade path for all users
  - Standard GTM continues to work exactly as before

## [3.4.0] - 2024-12-XX

### Added

- **GitHub Auto-Update Feature**: Seamless plugin updates from GitHub
  - Automatic update checking from GitHub releases
  - No authentication required for public repository
  - WordPress native update notifications
  - Manual update check functionality
  - Configurable enable/disable option

## [3.3.0] - 2024-12-XX

### Fixed

- **Avada Form Tracking Reliability**: Major bug fixes for form submission detection
  - Fixed inconsistent form submission tracking in AJAX forms
  - Resolved duplicate event firing issues
  - Enhanced timing of form initialization to prevent missed submissions
  - Improved form selector specificity to avoid conflicts with other plugins
- **AJAX Form Support**: Enhanced support for Avada AJAX form submissions
  - Fixed success state detection timing issues
  - Improved submit button click detection accuracy
  - Resolved form validation interference problems
  - Enhanced error handling for failed AJAX requests
- **Email Field Validation**: Improved form filtering to prevent false positives
  - Fixed email field detection in complex form structures
  - Resolved issues with dynamically generated forms
  - Enhanced field validation for custom input types
  - Fixed compatibility with Avada form builder variations
- **Debug Logging**: Enhanced debugging capabilities for troubleshooting
  - Fixed debug message formatting and clarity
  - Improved error logging with stack traces
  - Enhanced form detection logging with detailed information
  - Fixed console logging control issues

### Enhanced

- **Avada Theme Compatibility**: Improved compatibility with latest Avada/Fusion versions
  - Updated selectors for Avada 7.11+ form structures
  - Enhanced support for new Fusion form builder components
  - Improved compatibility with Avada performance optimizations
  - Fixed conflicts with Avada's lazy loading features
- **Error Handling**: Comprehensive error handling improvements
  - Added try-catch blocks around critical form detection code
  - Improved graceful degradation when forms fail to initialize
  - Enhanced error reporting for debugging purposes
  - Fixed memory leaks in form event listeners

## [3.2.1] - 2024-12-XX

### Added

- **Click ID Tracking**: Comprehensive support for all major advertising platform click IDs
  - Google Ads: `gclid`, `gbraid`, `wbraid`
  - Facebook/Meta Ads: `fbclid`
  - Microsoft Ads: `msclkid`
  - TikTok Ads: `ttclid`
  - LinkedIn Ads: `li_fat_id`
  - Twitter/X Ads: `twclid`
  - Snapchat Ads: `snap_click_id`
  - Pinterest Ads: `pclid`
  - 30-day persistence matching UTM parameter storage
  - Automatic inclusion in all form submission events
- **Enhanced Avada Form Success Detection**: Improved success state detection for Avada/Fusion forms
  - Added `fusion-form-response-success` class detection (primary indicator)
  - Enhanced container-based success message detection
  - Added parent element success class checking
  - Comprehensive success state debugging
- **Email Field Validation**: Smart form filtering to prevent false positives
  - Only tracks forms with email fields (contact/lead forms)
  - Skips search forms and other non-conversion forms
  - Applies to both AJAX and standard form submission tracking
- **Comprehensive Debug Logging**: Enhanced debugging for Avada form tracking
  - Form detection logging with detailed form information
  - Success state checking with step-by-step progress
  - AJAX form watcher setup and click detection
  - DataLayer push confirmation and error handling

### Enhanced

- **Avada Form Tracking**: Significantly improved reliability and debugging
  - AJAX form support with submit button click detection
  - Multiple success detection patterns for different Avada configurations
  - Dynamic form detection for forms added after page load
  - Comprehensive logging throughout the tracking process
- **UTM and Click ID Architecture**: Unified tracking system
  - Backward compatibility maintained for existing UTM functions
  - New tracking functions support both UTM and Click ID parameters
  - Enhanced utilities automatically include click IDs in form events
  - Server-side tracking updated to handle all tracking parameters

### Fixed

- **Form Detection Issues**: Resolved Avada form tracking reliability
  - Enhanced form identification patterns
  - Improved success state recognition
  - Better handling of AJAX form submissions
  - Eliminated false positives from search forms

### Technical Improvements

- **Tracking Parameter Architecture**: Expanded from UTM-only to comprehensive tracking
  - JavaScript functions support both legacy and new parameter sets
  - PHP backend updated to handle click ID parameters
  - Session and cookie storage enhanced for all tracking parameters
  - Utilities automatically include click IDs in dataLayer events

---

## Previous Versions

### [3.1.0] - Previous Release

- **GA4 Standard Parameters**: Enhanced all events with Google Analytics 4 standard parameters
- **Generate Lead Events**: New admin option to automatically fire `generate_lead` events for qualified form submissions
- **Browser Console Logging Control**: Granular control over JavaScript console logging (No/Yes/Admin Only)
- **Event Source Identification**: Added `cuft_tracked: true` and `cuft_source` parameters
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
