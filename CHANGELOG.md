# Changelog

All notable changes to Choice Universal Form Tracker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.16.5] - 2025-10-11

### Changed
- **Update System Simplification** - Removed custom update buttons and simplified to use WordPress native update system only
  - Removed "Update Now" button from Updates tab (was causing "git url could not be constructed" errors)
  - Removed "Check for Updates" button from Updates tab
  - All update notifications now direct users to WordPress plugins page (`/wp-admin/plugins.php`)
  - Update process now exclusively uses WordPress core update mechanism
  - **Impact**: Cleaner UX, fewer failure points, leverages WordPress native update reliability

### Fixed
- **Update Notification on Updates Tab** - Notification no longer appears when user is already viewing the Updates tab
- **Update Link Redirect** - Changed "View Plugin Updates" button to "Update Plugin" and redirected to WordPress plugins page
- **Custom Update Failures** - Eliminated "git url could not be constructed" errors by removing problematic custom update implementation

### Removed
- Custom "Update Now" AJAX button and associated handlers
- Custom "Check for Updates" button
- Direct update functionality from plugin's Settings/Updates pages

### Technical Details
- Modified 2 files (admin notices and admin page rendering)
- Preserved all backend update infrastructure (version detection, GitHub release fetching, update history)
- WordPress native update system continues to work correctly via `pre_set_site_transient_update_plugins` hook
- Zero breaking changes to backend functionality

### Migration Notes
- Users will now update via **Plugins → Choice Universal Form Tracker → Update Now** (WordPress native)
- Admin bar "CUFT Update" menu still shows update availability and links to plugins page
- Update history and status display remain functional on plugin's Updates tab
- All automated update checks and version detection continue to work

## [3.16.4] - 2025-10-10

### Fixed
- **Type Mismatch Errors** - Fixed fatal PHP errors caused by accessing `CUFT_GitHub_Release` objects as arrays
  - Fixed `check_for_updates()` - Now uses `$release->get_download_url()` instead of `$release['download_url']`
  - Fixed `process_update()` - Now uses `$release->get_version()` instead of `$release['version']`
  - Fixed `plugin_information()` - Now uses all object methods correctly
  - **Impact**: Plugins page (`/wp-admin/plugins.php`) now loads without fatal errors
  - **Impact**: "Download and Install Update" and "Re-install" features now work correctly
- **Notification Dismissal UX** - Admin notices now fade out over 500ms and are removed from DOM after dismissal (no page reload required)
- **View Plugin Updates Link** - Changed to redirect to plugin's own Updates tab instead of plugins page to avoid crashes
- **Progress Polling** - "Update Now" button now shows real-time progress with 2-second polling intervals instead of showing "Complete" then resetting

### Added
- **WP_DEBUG Error Enhancement** - Detailed error messages now shown when `WP_DEBUG` is enabled
  - Added debug context to all AJAX error responses (exception details, file, line, trace)
  - Added error logging to PHP error_log for update failures
  - Enhanced error data includes memory usage, PHP version, WordPress version
  - Debug info only visible when `define('WP_DEBUG', true)` in wp-config.php
- **Enhanced Error Context** - Update installer now provides detailed error data including update ID, version info, backup status

### Technical Details
- Modified 5 files with type mismatch fixes
- Added WP_DEBUG-conditional error reporting across 3 files
- All existing functionality preserved, only error handling enhanced
- Zero breaking changes - backward compatible

## [3.16.3] - 2025-10-08

### Fixed
- **Admin Notice Positioning** - Fixed admin notices appearing beside page title instead of above it by ensuring `.wp-header-end` marker is present
- **Admin Bar Dynamic Refresh** - Admin bar now updates without page reload using JavaScript polling (5-minute intervals)
- **Consistent Version Display** - All interfaces (Admin Bar, Plugins page, Updates page, Settings page) now show consistent version information
- **AJAX Security Validation** - Fixed "Security check failed" errors by properly implementing nonce validation with `cuft_updater_nonce`
- **Synchronized Update Indicators** - Update availability now consistent across all UI locations using site transients
- **Context-Aware Cache Timeouts** - Implemented smart cache timeouts based on WordPress context (1 minute for Updates page, 1 hour for Plugins page, 12 hours for background)
- **Cache Invalidation** - Automatic cache clearing after updates via `upgrader_process_complete` hook
- **Update Check Performance** - Update checks now complete within 5-second target
- **Update History FIFO** - Properly maintains last 5 updates with FIFO cleanup
- **Concurrent Update Handling** - Prevents concurrent updates with proper user conflict information (409 errors)

### Added
- **User Tracking in Updates** - Update progress now tracks which admin user initiated the update
- **Update Completion Transient** - Success notices display once after update completion
- **Integration Tests** - 6 new integration test files with 64 test methods covering all update scenarios
- **Unit Tests** - Comprehensive data model validation tests

### Changed
- **Transient Storage** - Switched from regular transients to site transients for multisite compatibility
- **Admin Bar Polling** - Optimized to only poll when tab is active (uses `document.visibilityState`)
- **Error Messages** - More descriptive error messages including user information in concurrent update conflicts

### Performance Improvements
- DOM updates complete in <100ms
- Status synchronization within 5 seconds
- Update checks complete in <5 seconds
- Efficient FIFO cleanup for update history

### Technical Details
- **Branch**: `007-fix-update-system`
- **Specification**: `specs/007-fix-update-system/`
- **Test Coverage**: 64 test methods across 6 integration test files
- **Lines of Test Code**: ~3,500

## [3.16.2] - 2025-10-07

### Changed
- Disabled legacy GitHub updater
- Enhanced update check frequency

## [3.16.1] - Previous Release

### Added
- Custom GTM Server Domain with Health Checks

## [3.16.0] - Previous Release

### Added
- Initial implementation of One-Click Automated Update feature (005)

## [3.15.0] - Previous Release

### Added
- AI Agent Integration (Phase 5)
- Constitutional compliance validation
- Pre-commit hooks for code validation

## [3.14.0] - Previous Release

### Added
- Testing Dashboard Form Builder
- Framework adapters for all supported form plugins
- PostMessage protocol for iframe communication
- Test mode isolation to prevent real form submissions

---

*For earlier versions, please refer to the git history.*

## Version History Summary

- **3.16.x** - Update System Improvements
- **3.15.x** - AI Development Workflow
- **3.14.x** - Testing Dashboard
- **3.13.x** - Form Tracking Enhancements
- **3.12.x** - Click Tracking Events Migration
- **3.11.x** - CryptoJS Integration
- **3.10.x** - Initial GA4 Support