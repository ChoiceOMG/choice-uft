# Changelog

All notable changes to the Choice Universal Form Tracker plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.9.5] - 2025-09-24

### Fixed

- **Generate Lead Event**: Fixed Elementor test forms not firing `generate_lead` event
- **Testing Controls**: Fixed initialization bug where control states weren't set on form load
- **Event Logic**: Testing controls now properly initialize with default checked states for required fields
- **Form Tracking**: Generate lead events now fire correctly when all requirements are met

### Technical

- Fixed `setupTestingControlListeners` to initialize form dataset properties on load
- Added call to `updateFormBasedOnControls` during control initialization
- Ensures testing controls reflect proper checked/unchecked state from the start
- Resolves issue where required fields were incorrectly disabled by undefined dataset values

## [3.9.4] - 2025-09-23

### Fixed

- **CRITICAL Plugin Directory Issue**: Updated release system to use `choice-uft.zip` naming (without version number)
- **WordPress Installation Fix**: Plugin now extracts correctly to `/wp-content/plugins/choice-uft/` instead of version-specific directories
- **Update System Fix**: GitHub updater now expects correct zip filename for automatic updates
- **Directory Path Issues**: Resolves fatal errors and plugin deactivation on updates

### Technical

- Updated GitHub updater class to look for `choice-uft.zip` specifically
- Modified GitHub Actions workflow to create properly named zip files
- Updated all documentation to use correct naming convention
- Added critical warnings about zip file naming requirements

### Breaking Change

- **Release assets now named `choice-uft.zip`** (previously `choice-uft-v{version}.zip`)
- This ensures consistent plugin directory structure across all WordPress installations
- Resolves the "two plugin listings" and automatic deactivation issues

## [3.9.3] - 2025-09-23

### Fixed

- **Elementor Test Forms**: Fixed "Could not find email or phone inputs" error in Submit Test Form functionality
- **Field Detection**: Updated legacy test forms field selectors to match framework-specific naming conventions
- **Test Form Compatibility**: Elementor forms now properly detect `form_fields[email]` and `form_fields[phone]` inputs

### Technical

- Enhanced `cuft-test-forms.js` field detection to use comprehensive selectors
- Aligned legacy test code with modern framework-specific test implementations
- Maintained backward compatibility with all form types

## [3.9.2] - 2025-09-23

### Fixed

- **Critical Plugin Installation**: Fixed zip file directory structure causing double plugin listings and installation failures
- **WordPress Compatibility**: Plugin now extracts correctly to `/wp-content/plugins/choice-uft/` instead of nested subdirectory

### Technical

- Corrected zip file structure to have plugin files at root level instead of nested in subdirectory
- This resolves the "two plugin listings" issue where WordPress couldn't properly recognize the plugin

## [3.9.1] - 2025-09-23

### Fixed

- **Asset Loading**: Fixed CUFT_URL constant missing trailing slash causing 404 errors on test form assets
- **Tracking Display Sync**: Test forms now show actual stored values instead of hardcoded placeholders
- **Data Consistency**: Values displayed in test forms now match values submitted to Tag Manager
- **SessionStorage Sync**: Updated sessionStorage before test form submissions to ensure consistency

### Technical

- Enhanced `cuft-test-common.js` with new tracking sync functions
- Applied consistent updates across all framework-specific test files (Avada, Elementor, CF7, Gravity, Ninja)
- Maintained backward compatibility and existing functionality
- All syntax checks pass for PHP 7.4+ and JavaScript

## [3.8.16] - 2025-09-23

### Added

- **Test Forms Interface**: Added comprehensive test forms for all supported form plugins
  - Elementor Forms test interface with form submission simulation
  - Contact Form 7, Gravity Forms, Ninja Forms, and Avada Forms test interfaces
  - Common test utilities for form validation and event tracking
  - Enhanced admin interface for testing form tracking functionality

### Improved

- **Admin Interface**: Enhanced admin page layout and styling
- **Update Mechanism**: Improved update interface with better user feedback
- **Code Organization**: Added test form assets in organized structure

## [3.8.15] - 2025-09-23

### Fixed

- **Plugin Reinstall Fix**: Fixed "Re-install Current Version" functionality that was incorrectly showing "up-to-date" error
  - Implemented proper reinstall logic using WordPress `install()` method with overwrite for same-version reinstalls
  - Added automatic plugin reactivation after successful reinstalls
  - Regular version updates continue using the standard `upgrade()` method
  - Reinstall feature now works correctly for testing updater mechanisms

## [3.8.14] - 2025-09-23

### Fixed

- **Critical Auto-Update Fix**: Resolved fatal PHP error preventing plugin updates
  - Removed dangerous `eval()` usage from admin update handler
  - Fixed `WP_Upgrader_Skin` class loading issue that caused "Class not found" errors
  - Created separate upgrader skin class file for proper initialization
  - Added proper safety checks before attempting updates
  - Plugin auto-updates now work correctly without fatal errors

### Security

- **Eliminated eval() Usage**: Replaced `eval()` with safer class loading mechanism
  - Removed security vulnerability from dynamic class creation
  - Implemented proper class file inclusion pattern
  - Added comprehensive error handling for update process

## [3.8.13] - 2025-09-23

### Fixed

- **Form Tracking Issues**: Major fixes across all form frameworks
  - `generate_lead` event now correctly requires email + phone + click_id (not utm_campaign)
  - Fixed Avada forms not triggering tracking events
  - Fixed Ninja Forms not triggering tracking events
  - Resolved Elementor forms not detecting email/phone fields

- **Field Detection**: Enhanced field detection for all form types
  - Added support for Elementor-specific attributes (data-field-type, data-original-name)
  - Improved detection of form field arrays (form_fields[email])
  - Added support for field variations (email/e-mail, phone/tel/mobile/number)
  - Now checks labels, placeholders, ARIA labels, and parent containers

- **Browser Console Errors**: Fixed regex pattern errors
  - Replaced regex testing with safe string checking
  - Added try-catch blocks for pattern validation
  - Eliminates "invalid character in class in regular expression" errors

### Improved

- **Value Capture Timing**: Better handling of form values
  - Captures field values at submit time (before form is cleared)
  - Stores values as data attributes on form element
  - Uses stored values when processing success events
  - Ensures values are available even after form resets

## [3.8.12] - 2025-01-23

### Testing

- **Version Bump for Update Testing**: Test release to verify update mechanism
  - Testing GitHub updater functionality
  - Verifying update notices display correctly
  - Confirming download and installation process works
  - No functional changes in this release

## [3.8.11] - 2025-01-23

### Fixed

- **Update Notice Direction**: Fixed update notification to guide users correctly
  - Update notice now directs to "Settings → Force Update" instead of WordPress Plugins page
  - Provides clear path to the plugin's update mechanism
  - Users are directed to the proper Force Update Check button in settings
  - Maintains link to view release notes on GitHub

- **Simplified Update Notice**: Removed complex styling from update notifications
  - Removed custom background, borders, and width calculations from update message
  - Now uses standard WordPress update notice formatting
  - Simplified to just show version and release notes link
  - Removed changelog preview to keep notices clean

### Simplified

- **Plugin Links**: Streamlined plugin page links
  - Kept only essential "Settings" link in action links
  - Removed redundant GitHub and releases links
  - Kept only "Support" link in meta for help access
  - Removed all inline CSS styling from links

## [3.8.10] - 2025-01-23

### Added

- **Enhanced Plugin Page Display**: Improved plugin appearance on WordPress plugins page
  - Added "Settings" link next to Deactivate for quick access
  - Added "GitHub" link for repository access
  - Added "View Releases" link in plugin details
  - Added "Changelog" and "Support" links for better user experience
  - Custom update notification with direct links to release notes
  - Update messages now show preview of what's new

### Improved

- **Update Experience**: Better visibility for GitHub-based updates
  - Clear indication that updates come from GitHub, not WordPress.org
  - Direct links to view release notes before updating
  - Force update check link in update notification
  - Styled update messages for better readability

## [3.8.9] - 2025-01-23

### Critical Fix

- **Fixed Auto-Update Installation**: Resolved critical issue preventing plugin updates
  - Removed custom download handler that was interfering with WordPress update process
  - Let WordPress handle the download natively for better compatibility
  - Removed fallback to GitHub archive URLs which created incorrect folder structure
  - Now always uses proper release asset URLs for updates
  - If no release asset is found, fails gracefully instead of installing wrong structure

## [3.8.8] - 2025-01-23

### Improved

- **Admin Notice Behavior**: Enhanced admin notice visibility and dismissal
  - Admin notice no longer shows on the plugin's settings page (redundant)
  - Implemented persistent dismiss functionality per user
  - Notice dismissal is remembered via user meta
  - Added AJAX handler for smooth dismiss experience
  - Each admin user can independently dismiss the notice

## [3.8.7] - 2025-01-23

### Fixed

- **Admin Notice Positioning**: Reverted admin notice to standard WordPress positioning
  - Removed complex CSS flex positioning that forced notice to right side
  - Removed custom alignment and width restrictions
  - Notice now appears in standard admin notice area with other WordPress notices
  - Simplified HTML structure to single paragraph format

## [3.8.6] - 2025-01-23

### Fixed

- **Enhanced Update Mechanism**: Improved force update check functionality
  - Force check now clears all plugin and WordPress update transients
  - Clears cached asset URLs for all versions
  - Triggers WordPress update check automatically
  - Better handles redirect chains for GitHub release assets

### Improved

- **Admin Panel Updates**: Better update management interface
  - Clearer update status messages
  - Improved force update button functionality
  - Better error handling and user feedback

## [3.8.5] - 2025-01-23

### Fixed

- **Critical Download Issue**: Fixed plugin update download failures
  - Simplified download process to use WordPress's built-in download_url function
  - Added proper redirect handling for GitHub release assets
  - Improved error handling and logging during download
  - Fixed issue where downloads would fail silently

### Improved

- **Update Process Reliability**: Enhanced download mechanism
  - Better fallback handling for network issues
  - More detailed error messages for troubleshooting
  - Proper temporary file management

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
