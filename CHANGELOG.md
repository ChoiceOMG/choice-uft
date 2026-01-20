# Changelog

All notable changes to Choice Universal Form Tracker will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.20.1] - 2026-01-20

### Fixed
- **Critical**: Fixed PHP fatal error in Contact Form 7 tracking when form contains array values (checkboxes, multi-select fields)
  - `is_email()` and `preg_match()` now safely handle array form values
  - Arrays are flattened to first value before string operations
  - Non-string values are properly skipped

## [3.20.0] - 2025-12-17

### Added
- **Feature 010: Auto-BCC Testing Email System**
  - Automatically BCCs a testing email address on form submission notifications
  - Smart email type detection (form submissions, user registrations, password resets, etc.)
  - Rate limiting with configurable hourly thresholds
  - Duplicate detection (skips BCC if address already in TO/CC)
  - Admin settings interface with real-time validation
  - AJAX-powered configuration management
  - Test email functionality for verification
  - Database-level locking to prevent race conditions
  - Automatic transient cleanup (once per day)

- **Email Tracking Parameter Injection**
  - Automatically appends UTM parameters to form submission emails
  - Supports 6 UTM parameters (source, medium, campaign, term, content, id)
  - Supports 11 platform click IDs (Google, Facebook, Microsoft, TikTok, LinkedIn, etc.)
  - HTML and plain text email format detection
  - Clean formatting with separate sections for UTM params and click IDs
  - Graceful handling when no tracking data exists
  - Cross-framework compatible (CF7, Gravity Forms, Elementor, etc.)

- **New Admin Interface**
  - Auto-BCC settings tab in Settings → Universal Form Tracker
  - Real-time email validation with visual feedback
  - Email type multi-select (form submissions, registrations, etc.)
  - Rate limit configuration (threshold and action)
  - "Send Test Email" button for immediate verification
  - Modern CSS with responsive design

### Technical Details
- **New Classes**: 7 email infrastructure classes
  - `CUFT_Email_Interceptor` - BCC injection (priority 10)
  - `CUFT_Email_Tracking_Injector` - Tracking parameter injection (priority 15)
  - `CUFT_Email_Type_Detector` - Email classification
  - `CUFT_BCC_Rate_Limiter` - Rate limiting with MySQL locks
  - `CUFT_Auto_BCC_Config` - Configuration management
  - `CUFT_Auto_BCC_Manager` - Feature orchestration
  - `CUFT_Auto_BCC_Validator` - Email validation

- **Test Coverage**:
  - 7 integration tests (end-to-end scenarios)
  - 2 unit test suites (Email Type Detector, Rate Limiter)
  - 3 contract tests (API compliance)
  - All tests passing in CI

- **Security**:
  - CSRF protection via nonces
  - Capability checks (`update_plugins`)
  - Email sanitization and validation
  - XSS prevention with `esc_html()` and `sanitize_text_field()`
  - Race condition prevention with database locks

- **Performance**:
  - Hooks into `wp_mail` filter with priority management
  - WordPress transients for rate limiting (fast lookups)
  - Automatic cleanup prevents database bloat
  - Minimal overhead (only processes when feature enabled)

- **Compatibility**:
  - Works with SMTP plugins (WP Mail SMTP, Post SMTP, etc.)
  - Compatible with all major form builders
  - Backward compatible (no breaking changes)
  - Graceful degradation (never blocks primary emails)

### Files Changed
- **Added**: 13 new PHP classes, 2 admin assets, 1 admin view
- **Modified**: 3 core files (plugin main, admin class, admin view)
- **Lines**: +7,291 additions, -906 deletions

## [3.19.3] - 2025-10-14

### Fixed
- **Update Notification UX** - Removed redundant update notices to improve user experience
  - Removed simple one-line notice ("There is a new version...View Plugin Updates")
  - Kept robust notice with title, description, and action buttons
  - Update notices no longer shown on plugins page (redundant with WordPress native update row)
  - **Impact**: Eliminates duplicate notifications, reduces visual clutter
  - **Affected Files**:
    - `includes/class-cuft-admin.php` - Deprecated `check_update_notices()` method
    - `includes/admin/class-cuft-admin-notices.php` - Added plugins page check

### Changed
- **Notice Visibility** - Update notices now show on dashboard and other admin pages but NOT on plugins page
  - WordPress native update row on plugins page is sufficient
  - Consistent with WordPress UX best practices
  - Reduces user confusion from duplicate notifications

### Technical Details
- **Trigger**: User feedback on redundant update notifications on plugins page
- **Files Modified**: 2 files (admin class, admin notices class)
- **Lines Changed**: ~45 lines removed, 5 lines added
- **Backward Compatible**: Deprecated method kept as no-op for compatibility

## [3.19.2] - 2025-10-14

### Testing
- **Validation Release** - Test release to validate end-to-end update system functionality
  - Confirms GitHub release ZIP upload workflow
  - Validates Force Update UI "Check for Updates" button
  - Tests Force Reinstall operation from GitHub download
  - Verifies update history tracking and FIFO cleanup
  - Confirms permission error fixes from v3.19.1 work correctly
  - **Purpose**: End-to-end validation of Feature 009 on staging environment

### Technical Details
- **Purpose**: Validation release (no code changes)
- **Validates**: Complete update workflow from detection → download → installation
- **Environment**: cuft-choice-zone staging (Docker)
- **Previous Version**: 3.19.1

## [3.19.1] - 2025-10-14

### Fixed
- **Permission Error Handling** - Fixed fatal error when scanning plugin directory with restricted permissions
  - Fixed `RecursiveDirectoryIterator` permission errors in disk space validator
  - Fixed `RecursiveDirectoryIterator` permission errors in backup manager
  - Added graceful degradation with fallback size estimates (1MB minimum)
  - Added explicit `isReadable()` checks before accessing files/directories
  - Added `UnexpectedValueException` catch blocks for permission denied errors
  - Skips inaccessible files/directories instead of failing completely
  - Logs warnings for skipped items to aid troubleshooting
  - **Impact**: Force reinstall operations now succeed even with restricted directory permissions
  - **Affected Methods**:
    - `CUFT_Disk_Space_Validator::get_directory_size()`
    - `CUFT_Disk_Space_Validator::validate_space_for_reinstall()`
    - `CUFT_Backup_Manager::calculate_directory_size()`
    - `CUFT_Backup_Manager::add_directory_to_zip()`

### Technical Details
- **Trigger**: Manual validation revealed permission errors on `docs/` subdirectory
- **Error Message**: `RecursiveDirectoryIterator::__construct(...docs): Failed to open directory: Permission denied`
- **Files Modified**: 2 files (disk space validator, backup manager)
- **Lines Changed**: ~80 lines (enhanced error handling in 4 methods)
- **Testing**: Verified in Docker environment with mixed file permissions

### Migration Notes
- No changes required for existing installations
- Operations that previously failed with permission errors will now succeed
- Logs (PHP error_log) will show warnings for skipped files/directories

## [3.19.0] - 2025-10-14

### Added
- **Force Install Update Feature (Feature 009)** - Manual update control system with comprehensive safety mechanisms
  - Manual "Check for Updates" button bypasses automatic WordPress update schedule
  - "Force Reinstall" button downloads and reinstalls latest version from GitHub
  - Update history tracking with 7-day retention and FIFO limit (5 entries)
  - New "Force Update" tab in Settings → Universal Form Tracker
  - Real-time progress indicator during force reinstall operations
  - Operation locking prevents concurrent updates (transient-based, 120-second TTL)
  - Disk space validation (3x plugin size) before force reinstall
  - Automatic backup/restore on reinstall failures
  - WordPress plugin cache clearing for immediate version recognition

### Security
- **Capability Checks** - All manual update operations require `update_plugins` capability
- **Nonce Validation** - CSRF protection on all AJAX endpoints
- **DISALLOW_FILE_MODS Support** - Respects WordPress file modification lockdown constant
- **Input Validation** - All user inputs validated and sanitized
- **Directory Traversal Protection** - Backup paths validated to prevent attacks

### Performance
- **Timeout Enforcement** - Update checks timeout at 5 seconds, force reinstalls at 60 seconds
- **Transient Caching** - Installation state cached for 5 minutes to reduce GitHub API calls
- **History Cleanup** - Daily WP-Cron job removes entries older than 7 days
- **Efficient FIFO** - Update history maintains max 5 entries with O(1) operations

### Technical Details
- **Branch**: `009-force-install-update`
- **Specification**: `specs/009-force-install-update/`
- **Files Added**: 21 new files (3 infrastructure, 4 models, 1 service handler, 1 admin view, 1 JS module, 1 CSS stylesheet)
- **Files Modified**: 4 files (main plugin, admin class, AJAX handler, cron manager)
- **Lines of Code**: ~2,500 lines (PHP: ~1,800, JavaScript: ~280, CSS: ~300)

### Infrastructure
- `CUFT_Update_Lock_Manager` - Transient-based operation locking
- `CUFT_Disk_Space_Validator` - 3x plugin size validation
- `CUFT_Cache_Clearer` - WordPress cache clearing utility

### Data Models
- `CUFT_Force_Reinstall_Operation` - Reinstall operation state tracking
- `CUFT_Plugin_Installation_State` - Transient-cached installation state
- `CUFT_Update_History_Entry` - FIFO history with 7-day retention
- `CUFT_Update_Check_Request` - Ephemeral request lifecycle tracking

### Services
- `CUFT_Force_Update_Handler` - Main orchestrator for update operations
- Enhanced `CUFT_Updater_Ajax` with 3 new endpoints:
  - `cuft_check_updates` - Manual update check
  - `cuft_force_reinstall` - Force reinstall operation
  - `cuft_get_update_history` - History retrieval

### Admin UI
- Force Update tab with modern WordPress admin design
- Progress bar with shimmer animation
- History table with responsive mobile design
- AJAX-powered button state management
- Real-time status updates and error messages

### Integration
- Plugin activation schedules daily history cleanup cron
- Plugin deactivation cleans up transients and cron jobs
- Update history preserved across activation cycles
- All new classes loaded in plugin bootstrap

## [3.16.5] - 2025-10-11

**⚠️ NOTE: This version's changes were superseded by v3.19.0, which reintroduced manual update controls with proper implementation.**

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

**These features were re-implemented properly in v3.19.0 as the "Force Update" feature.**

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

- **3.19.x** - Manual Update Controls, Force Reinstall, UX Improvements
- **3.18.x** - (Reserved)
- **3.17.x** - Critical Update System Fixes
- **3.16.x** - Update System Improvements (superseded by 3.19.x)
- **3.15.x** - AI Development Workflow
- **3.14.x** - Testing Dashboard
- **3.13.x** - Form Tracking Enhancements
- **3.12.x** - Click Tracking Events Migration
- **3.11.x** - CryptoJS Integration
- **3.10.x** - Initial GA4 Support