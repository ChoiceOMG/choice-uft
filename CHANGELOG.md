# Changelog

All notable changes to the Choice Universal Form Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.8.4] - 2025-01-23

### Fixed

- **Critical Auto-Update Issue**: Fixed GitHub updater to download proper release assets
  - Updater now uses uploaded plugin zip files instead of raw source archives
  - Added automatic detection of release assets via GitHub API
  - Includes fallback to source archive if no release asset is available
  - Prevents development files from being installed on production sites

### Improved

- **Release Process**: Enhanced auto-update reliability
  - Added caching for release asset URLs to reduce API calls
  - Better error handling and logging for update failures
  - Consistent version numbering across all plugin files

## [3.8.3] - 2025-01-22

### Fixed

- **AJAX Response Handling**: Fixed "undefined" error in update check
  - Corrected response data access pattern (response.data instead of response directly)
  - Fixed all AJAX handlers to properly access WordPress JSON response structure
  - Added fallback error messages for undefined response properties

### Improved

- **Error Handling**: Better error message display in admin interface
  - Added multiple fallback checks for error messages
  - Improved user feedback when API calls fail

## [3.8.2] - 2025-01-22

### Fixed

- **Critical PHP Error**: Resolved WP_Upgrader_Skin class loading issue
  - Fixed nested class declaration error in admin update handler
  - Modified CUFT_Ajax_Upgrader_Skin to be created at runtime using eval()
  - Ensured WordPress upgrader classes are loaded before extending them

### Improved

- **Documentation**: Reorganized and cleaned up testing documentation
  - Moved detailed testing procedures to docs/TESTING.md
  - Streamlined CLAUDE.md with essential testing checklist
  - Better organization of development guidelines

## [3.8.0] - 2025-01-22

### Added

- **Mock Form Submission Testing**: New testing capability for debugging and verification
  - Test form submission buttons in admin panel for each detected framework
  - Frontend test page generation with actual form elements
  - Email notifications for test submissions to verify tracking pipeline
  - Helps debug form tracking issues without live form submissions

### Fixed

- **CI/CD Workflow Issues**: Resolved dependency and validation problems
  - Fixed composer plugin permissions for WPCS and PHPCompatibility
  - Corrected version conflicts in PHP_CodeSniffer dependencies
  - Improved grep command handling in security checks
  - Made security warnings non-blocking to prevent false positives

### Improved

- **Security Checks**: Enhanced validation with reduced false positives
  - Better handling of legitimate $_POST usage with nonce validation
  - Improved detection patterns for actual security issues
  - More informative output showing actual matches found

## [3.7.0] - 2025-01-20

### Fixed

- **WordPress Coding Standards**: Complete compliance overhaul
  - Replaced all `wp_die(json_encode())` with `wp_send_json_error()` and `wp_send_json_success()`
  - Fixed AJAX response handling to use WordPress standard functions
  - Added proper nonce validation with isset() checks in all AJAX handlers
  - Standardized error and success responses across all endpoints

### Enhanced

- **Security Improvements**: Strengthened validation and checks
  - Added missing isset() checks before nonce validation
  - Improved input validation in AJAX handlers
  - Enhanced error handling with proper WordPress functions

- **CI/CD Workflows**: More comprehensive automated testing
  - Added check for `wp_die(json_encode())` anti-pattern
  - Improved class declaration detection in PHP files
  - Enhanced security checks with better error reporting
  - Added validation for proper WordPress coding standards

### Technical

- **Code Quality**: WordPress best practices implementation
  - Consistent use of `wp_send_json_*` functions for AJAX responses
  - Proper error handling without exposing sensitive information
  - Cleaner, more maintainable AJAX handler code
  - Better alignment with WordPress core coding standards

## [3.6.1] - 2025-01-20

### Fixed

- **Fatal Error on Activation**: Fixed PHP fatal error caused by class declaration inside method
  - Moved `CUFT_Ajax_Upgrader_Skin` class to separate file
  - Proper class loading with existence check
  - Maintains all update functionality

### Added

- **GitHub CI/CD Workflows**: Automated testing on commits and pull requests
  - PHP syntax checking across multiple versions (7.4, 8.0, 8.1, 8.2)
  - PHP compatibility verification
  - WordPress coding standards checks
  - Security vulnerability scanning
  - JavaScript syntax validation
  - Plugin structure verification

### Technical

- **Code Quality**: Improved code structure and testing
  - Separated concerns with dedicated class files
  - Added automated checks to prevent future issues
  - Version consistency validation

## [3.6.0] - 2025-01-20

### Added

- **One-Click Update Installation**: Install updates directly from the admin settings page
  - New "Download & Install Update" button appears when updates are available
  - No need to navigate to the plugins page anymore
  - Real-time progress indicators during installation
  - Automatic page reload after successful update

### Enhanced

- **Improved Update Experience**: Streamlined update process
  - AJAX-powered installation with no page redirects
  - Confirmation dialog before installing updates
  - Progressive status messages during installation
  - Clear error messages if installation fails
  - Automatic cleanup of update transients

### Technical

- **Update Handler**: New AJAX endpoint for plugin self-updates
  - Uses WordPress Plugin_Upgrader class
  - Custom upgrader skin for capturing output
  - Proper permission checks (update_plugins capability)
  - Downloads directly from GitHub releases

## [3.5.2] - 2025-01-20

### Fixed

- **sGTM Validation Persistence**: Fixed validation status being lost when saving settings
  - Corrected URL comparison logic happening after update
  - Added debug output for administrators when debug mode enabled
  - Improved validation state management

## [3.5.1] - 2025-01-20

### Fixed

- **GitHub Updater One-Click Updates**: Fixed WordPress plugin update functionality
  - Corrected update transient response to use stdClass object instead of array
  - Fixed plugin info API response format for proper WordPress integration
  - Added missing plugin and id properties for update identification
  - Resolved "Update now" button display issue on plugins page

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
