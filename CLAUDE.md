# CLAUDE.md - Development Guidelines for Choice Universal Form Tracker

## CRITICAL: Always Reference Specifications First

### Before ANY Code Changes
1. **MANDATORY**: Read and understand relevant specifications:
   - [.specify/memory/constitution.md](.specify/memory/constitution.md) - Core principles and standards
   - [specs/core/dataLayer.spec.md](specs/core/dataLayer.spec.md) - DataLayer event requirements
   - [specs/core/tracking-params.spec.md](specs/core/tracking-params.spec.md) - UTM/Click ID handling
   - Framework-specific specs in [specs/frameworks/](specs/frameworks/)
   - [specs/testing/test-suite.spec.md](specs/testing/test-suite.spec.md) - Testing requirements
   - [.specify/memory/agents.md](.specify/memory/agents.md) - AI development guidelines
   - [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md) - Code review checklist

2. **VALIDATE**: Ensure proposed changes align with constitutional principles
3. **CHECK**: Verify compatibility with existing implementations
4. **PLAN**: Reference implementation plan templates if creating new features

### Implementation and Migration Templates
When implementing new features or migrating existing code:
- **New Features**: Use [.specify/templates/implementation-plan-template.md](.specify/templates/implementation-plan-template.md)
- **Code Updates**: Use [.specify/templates/migration-plan-template.md](.specify/templates/migration-plan-template.md)
- **All Changes**: Follow the constitutional compliance checklist
- **Risk Assessment**: Always include risk mitigation strategies

### Mandatory Pre-Commit Validation
Before committing any code changes, ALWAYS verify using [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md):
- [ ] All relevant specifications reviewed and followed
- [ ] Constitutional principles compliance verified
- [ ] Framework-specific requirements met
- [ ] Silent exit implemented for non-relevant frameworks
- [ ] DataLayer events use snake_case naming
- [ ] Required fields (cuft_tracked, cuft_source) included
- [ ] Error handling implemented with fallbacks
- [ ] Performance requirements considered
- [ ] Security requirements met (input sanitization)

## Fix Update System Inconsistencies (007) - COMPLETED ✅

### Feature Overview
**Status**: Implementation Complete
**Version**: 3.16.3
**Branch**: `007-fix-update-system`
**Specs**: [specs/007-fix-update-system/](specs/007-fix-update-system/)

The WordPress admin update system inconsistencies have been fixed. The update system now displays consistent information across all interfaces (admin notices, admin bar, Updates tab), passes security validation on AJAX requests, and properly refreshes status indicators after updates.

### Completed Fixes
1. **Admin Notice Positioning** (FR-001) ✅ - Notices properly positioned above page title using `.wp-header-end` marker
2. **Admin Bar Refresh** (FR-002) ✅ - Admin bar updates without page reload via JavaScript polling
3. **Consistent Version Display** (FR-003) ✅ - All interfaces use same site transient source
4. **Secure Update Button** (FR-004) ✅ - AJAX endpoints properly validate nonces (no more "Security check failed")
5. **Synchronized Update Indicators** (FR-005) ✅ - Update availability consistent across all UI locations
6. **Context-Aware Caching** (FR-006) ✅ - Smart cache timeouts based on WordPress context
7. **Cache Invalidation** (FR-007) ✅ - Automatic cache clearing after updates
8. **Update Checks** (FR-008) ✅ - Complete within 5-second target
9. **Update History** (FR-009) ✅ - FIFO retention of last 5 updates
10. **Concurrent Updates** (FR-010) ✅ - Proper handling with user conflict information

### Key Implementation Components
- **Data Models**: Update Status (site transients), Update Progress (user tracking), Update Log (FIFO)
- **AJAX Endpoints**: All secured with `cuft_updater_nonce` validation
- **Admin Bar**: Dynamic refresh with 5-minute polling interval
- **Integration Tests**: 6 comprehensive test files with 64 test methods
- **Performance**: DOM updates <100ms, status sync <5s, update checks <5s

### Testing
- **Unit Tests**: Data models fully validated (`tests/unit/test-data-models.php`)
- **Integration Tests**: All quickstart scenarios covered
  - `test-admin-bar-refresh.php` (T021)
  - `test-status-synchronization.php` (T025)
  - `test-admin-notice-positioning.php` (T027)
  - `test-secure-update-button.php` (T028)
  - `test-update-history-fifo.php` (T029)
  - `test-concurrent-updates.php` (T030)

### Documentation
- **Implementation Guide**: [specs/007-fix-update-system/implementation-guide.md](specs/007-fix-update-system/implementation-guide.md)
- **Quick Start**: [specs/007-fix-update-system/quickstart.md](specs/007-fix-update-system/quickstart.md)
- **Tasks Summary**: [specs/007-fix-update-system/INTEGRATION-TESTS-SUMMARY.md](specs/007-fix-update-system/INTEGRATION-TESTS-SUMMARY.md)

## Fix Critical Update System Gaps (008) - COMPLETED ✅

### Feature Overview
**Status**: Implementation Complete
**Version**: 3.17.0
**Branch**: `008-fix-critical-gaps`
**Specs**: [specs/008-fix-critical-gaps/](specs/008-fix-critical-gaps/)

Feature 008 completes the WordPress update system by implementing critical missing components: plugin information modal, directory naming fixes, download validation, automatic backup/restore, error message templates, and security validation. All implementations follow WordPress conventions and integrate seamlessly with WordPress's native Plugin_Upgrader.

### Completed Features

#### FR-102: Plugin Information Modal ✅
- **Implementation**: `includes/update/class-cuft-plugin-info.php`
- **Filter**: `plugins_api` hook for "View Details" modal
- **Features**:
  - Complete plugin metadata (name, version, author, requires, tested, requires_php)
  - GitHub API integration for changelog and release notes
  - 12-hour transient caching with ETag support
  - Graceful degradation when GitHub API unavailable
  - Rate limit handling
  - HTML sanitization for security
- **Tests**: 11 test methods in `tests/integration/update/test-plugins-page-modal.php`

#### FR-103: Directory Naming Fix ✅
- **Implementation**: `includes/update/class-cuft-directory-fixer.php`
- **Filter**: `upgrader_source_selection` hook
- **Features**:
  - Renames GitHub ZIP directories to WordPress format
  - Supports multiple patterns: `choice-uft-v3.17.0/`, `choice-uft-3.17.0/`, `ChoiceOMG-choice-uft-abc1234/`, `choice-uft-master/`
  - All patterns normalized to `choice-uft/`
  - Pass-through for non-CUFT plugins and themes
  - Error handling for unrecognized patterns
- **Tests**: 15 test methods in `tests/integration/update/test-directory-naming.php`

#### FR-301-303: Update Execution via WordPress Standard Methods ✅
- **Implementation**: WordPress native Plugin_Upgrader (no custom code)
- **Features**:
  - Plugins page "Update Now" button integration
  - WP-CLI `wp plugin update` integration
  - Bulk update compatibility
  - Update history logging with trigger detection (plugins_page, wp_cli, bulk_update, auto_update)
  - FIFO retention (last 5 updates)
- **Logging**: `includes/update/class-cuft-update-logger.php`
- **Tests**: 25 test methods across 3 integration test files

#### FR-401: Download Validation ✅
- **Implementation**: `includes/update/class-cuft-update-validator.php`
- **Hook**: `upgrader_pre_install` filter
- **Features**:
  - File size validation with ±5% tolerance
  - ZIP format validation using WordPress methods
  - Immediate cleanup on validation failure
  - Daily cron job for orphaned file cleanup (24-hour retention)
  - Comprehensive error messages
- **Tests**: 10 test methods in `tests/integration/update/test-download-validation.php`

#### FR-402: Automatic Backup and Rollback ✅
- **Implementation**: `includes/update/class-cuft-backup-manager.php`
- **Hooks**: `upgrader_pre_install`, `upgrader_install_package_result`, `upgrader_process_complete`
- **Features**:
  - Automatic backup creation before update (`/wp-content/uploads/cuft-backups/`)
  - Backup filename includes version: `choice-uft-3.16.5-backup.zip`
  - Automatic restoration on update failure (10-second timeout)
  - Timeout abort with manual reinstall instructions
  - Automatic backup deletion after successful update
  - Disk space validation before backup
  - Permission checks for backup directory
- **Tests**: 7 test methods in `tests/integration/update/test-backup-restore.php`

#### FR-403: Error Message Clarity ✅
- **Implementation**: `includes/update/class-cuft-error-messages.php`
- **Features**:
  - 30+ standardized error code constants
  - User-friendly messages with corrective actions
  - FIFO logging to update history (last 5 entries)
  - Severity levels: CRITICAL, ERROR, WARNING
  - PII protection (server paths admin-only)
  - PHP error_log integration for debugging
  - Context-aware messages with variable substitution
- **Helper Methods**: `create_error()`, `display_admin_notice()`, `log_error()`

#### FR-404: Security Validation ✅
- **Implementation**: `includes/update/class-cuft-update-security.php`
- **Hooks**: `upgrader_pre_download`, `upgrader_pre_install`
- **Features**:
  - Nonce validation for update actions (`update-plugin`)
  - Capability checks (`update_plugins` permission)
  - GitHub-only URL validation (releases/download and zipball patterns)
  - DISALLOW_FILE_MODS constant check
  - Filesystem permission validation
  - Complete security validation wrapper: `validate_complete()`
  - Context sanitization for safe logging

### Key Implementation Components

#### WordPress Integration
- **plugins_api filter**: Plugin information modal (FR-102)
- **upgrader_source_selection filter**: Directory naming fix (FR-103)
- **upgrader_pre_download hook**: Security validation (FR-404)
- **upgrader_pre_install hook**: Download validation, backup creation, security checks (FR-401, FR-402, FR-404)
- **upgrader_install_package_result hook**: Automatic restoration on failure (FR-402)
- **upgrader_process_complete hook**: Update logging, backup cleanup (FR-301-303, FR-402)
- **WordPress Filesystem API**: All file operations use WP_Filesystem
- **WordPress HTTP API**: GitHub API requests
- **WordPress Transients API**: Caching with 12-hour TTL
- **WordPress Cron API**: Daily orphaned file cleanup

#### Error Handling
- **30+ Error Codes**: Comprehensive coverage of all failure scenarios
- **Three Severity Levels**:
  - CRITICAL: Restoration failed, manual reinstall required
  - ERROR: Update failed but system stable
  - WARNING: Non-critical issues
- **User-Friendly Messages**: Clear explanations with corrective actions
- **Developer Logging**: PHP error_log for debugging
- **FIFO Retention**: Last 5 entries to prevent log bloat

#### Security Features
- Nonce validation on all update operations
- Capability checks (update_plugins)
- GitHub-only download URL validation
- DISALLOW_FILE_MODS constant respected
- Filesystem permission validation
- Input sanitization
- PII protection in logs

### Testing

#### Automated Test Suite
- **Total Test Files**: 11 (5 edge case + 6 integration)
- **Total Test Methods**: 92
- **Pass Rate**: 100% ✅

#### Test Coverage by Feature
- **FR-102 (Plugin Info Modal)**: 11 tests
- **FR-103 (Directory Naming)**: 15 tests
- **FR-301-303 (Update Execution)**: 25 tests (across 3 files)
- **FR-401 (Download Validation)**: 10 tests
- **FR-402 (Backup/Restore)**: 7 tests
- **EC-1 (Backup Dir Not Writable)**: 6 tests
- **EC-2 (Disk Space Insufficient)**: 6 tests
- **EC-3 (Restoration Fails)**: 9 tests
- **EC-4 (Unexpected ZIP Structure)**: 7 tests
- **EC-5 (Concurrent Updates)**: 8 tests

#### Integration Tests
- `tests/integration/update/test-plugins-page-modal.php` - Plugin information modal (T006)
- `tests/integration/update/test-directory-naming.php` - Directory naming fix (T007)
- `tests/integration/update/test-plugins-page-update.php` - Plugins page update (T017)
- `tests/integration/update/test-wp-cli-update.php` - WP-CLI update (T018)
- `tests/integration/update/test-bulk-update.php` - Bulk update (T019)
- `tests/integration/update/test-download-validation.php` - Download validation (T025)
- `tests/integration/update/test-backup-restore.php` - Backup/restore workflow (T032)

#### Edge Case Tests
- `tests/integration/update/test-edge-case-backup-dir.php` - Backup directory permissions (T040)
- `tests/integration/update/test-edge-case-disk-space.php` - Disk space validation (T041)
- `tests/integration/update/test-edge-case-restore-fail.php` - Restoration failure (T042)
- `tests/integration/update/test-edge-case-zip-structure.php` - ZIP structure validation (T043)
- `tests/integration/update/test-edge-case-concurrent.php` - Concurrent update prevention (T044)

#### Unit Tests
- `tests/unit/update/test-plugin-info-contract.php` - plugins_api filter contract (T002)
- `tests/unit/update/test-directory-fixer-contract.php` - upgrader_source_selection contract (T003)
- `tests/unit/update/test-backup-manager-contract.php` - Backup/restore contract (T004)
- `tests/unit/update/test-update-validator-contract.php` - Download validation contract (T005)

### Performance Validation

All operations meet target performance metrics:
- **Plugin Info API**: < 100ms (with cache)
- **Directory Renaming**: < 50ms
- **File Size Validation**: < 20ms
- **ZIP Format Validation**: < 200ms
- **Backup Creation**: Target < 10 seconds
- **Backup Restoration**: Target < 10 seconds (10s timeout enforced)
- **Update Logging**: < 10ms

### Security Audit

✅ **PASSED** - All security checks verified:
- Nonce validation on all update actions
- Capability checks enforced
- URL validation (GitHub-only)
- DISALLOW_FILE_MODS respected
- Filesystem permissions checked
- PII protection in logs
- FIFO retention prevents overflow
- Input sanitization implemented

### Documentation
- **Implementation Plan**: [specs/008-fix-critical-gaps/plan.md](specs/008-fix-critical-gaps/plan.md)
- **Quick Start Guide**: [specs/008-fix-critical-gaps/quickstart.md](specs/008-fix-critical-gaps/quickstart.md)
- **Tasks**: [specs/008-fix-critical-gaps/tasks.md](specs/008-fix-critical-gaps/tasks.md)
- **Data Models**: [specs/008-fix-critical-gaps/data-model.md](specs/008-fix-critical-gaps/data-model.md)
- **API Contracts**: [specs/008-fix-critical-gaps/contracts/](specs/008-fix-critical-gaps/contracts/)
- **Quickstart Validation Results**: [specs/008-fix-critical-gaps/QUICKSTART-RESULTS.md](specs/008-fix-critical-gaps/QUICKSTART-RESULTS.md)

### Migration from Feature 007

Feature 008 includes complete removal of Feature 007 custom UI:
- ✅ Removed custom "Updates" tab from Settings page
- ✅ Removed "GitHub Auto-Updates" section from Settings tab
- ✅ Removed custom AJAX endpoints (`cuft_manual_update_check`, `cuft_install_update`)
- ✅ Removed deprecated JavaScript files (`cuft-updater.js`, `cuft-update-widget.js`, etc.)
- ✅ Removed custom admin bar update indicator
- ✅ Commented out deprecated class includes (class-cuft-update-installer.php, class-cuft-admin-bar.php)
- ✅ WordPress Plugins page is now the sole update interface

### Known Limitations

1. **GitHub API Rate Limiting**: Plugin info modal may show reduced information when rate limit exceeded (graceful degradation implemented)
2. **Large Plugin Size**: Backup creation may timeout for plugins >50MB (10-second timeout with manual reinstall message)
3. **Concurrent Update Prevention**: Relies on WordPress's built-in transient locking (race conditions possible in rare cases)
4. **ZIP Structure Detection**: Only recognizes known GitHub ZIP patterns (unusual patterns rejected by design)

### Troubleshooting

#### Update Fails with "Directory Mismatch"
- **Cause**: Directory renaming not working
- **Fix**: Check `upgrader_source_selection` filter is registered
- **Verify**: `ls -la /wp-content/plugins/` should show `choice-uft/` not `choice-uft-v3.17.0/`

#### Modal Shows "Information Not Available"
- **Cause**: GitHub API unavailable or rate limit exceeded
- **Fix**: Wait and retry, check network connectivity
- **Verify**: `wp transient get cuft_plugin_info` should show cached data

#### Update Succeeds But Plugin Broken
- **Cause**: Restoration failed after update failure
- **Fix**: Manual reinstall from GitHub required
- **Message**: Error notice will include GitHub release URL

#### "Insufficient Disk Space" Error
- **Cause**: Not enough space for backup creation
- **Fix**: Free up disk space, retry update
- **Check**: `df -h /wp-content/uploads/`

#### "Backup Directory Not Writable"
- **Cause**: Permission issue with `/wp-content/uploads/`
- **Fix**: Ensure web server can write to uploads directory
- **Check**: `ls -la /wp-content/ | grep uploads`

---

## Phase 5: AI Agent Integration - COMPLETED ✅

The AI development workflow has been successfully implemented with:

### ✅ Completed Setup
1. **AI Environment Configuration**: CLAUDE.md updated with mandatory specification references
2. **Code Pattern Implementation**: All framework files implement mandatory patterns (silent exit, framework detection, etc.)
3. **Pre-Commit Validation**: Automated pre-commit hook validates constitutional compliance
4. **Template Integration**: Implementation and migration plan templates referenced in workflow
5. **CI/CD Pipeline**: Constitutional compliance validation in GitHub Actions workflow

### ✅ Validation Systems
- **Pre-commit Hook**: `/home/r11/dev/choice-uft/.git/hooks/pre-commit` - Validates code changes before commit
- **GitHub Actions**: `.github/workflows/constitutional-compliance.yml` - CI/CD validation pipeline
- **Review Checklist**: `.specify/memory/review-checklist.md` - Comprehensive code review requirements
- **AI Guidelines**: `.specify/memory/agents.md` - Detailed AI development instructions

### ✅ Templates Available
- **New Features**: `.specify/templates/implementation-plan-template.md`
- **Code Updates**: `.specify/templates/migration-plan-template.md`

All future AI-assisted development will now automatically reference specifications first and maintain constitutional compliance.

## Testing Dashboard Form Builder (v3.14.0) ✅

### Feature Overview
**Status**: Implementation Complete
**Version**: 3.14.0
**Branch**: `003-testing-dashboard-form`
**Specs**: [specs/003-testing-dashboard-form/](specs/003-testing-dashboard-form/)

The Testing Dashboard Form Builder allows admins to generate real test forms within active form frameworks, populate them with test data, and validate tracking without affecting production analytics.

### Key Components

#### Backend Infrastructure
- **Form Builder Core**: `includes/admin/class-cuft-form-builder.php` - Main form builder class
- **AJAX Endpoints**: `includes/ajax/class-cuft-form-builder-ajax.php` - Handles all AJAX requests
- **Adapter Factory**: `includes/admin/class-cuft-adapter-factory.php` - Lazy-loads framework adapters
- **Framework Adapters**:
  - `includes/admin/framework-adapters/abstract-cuft-adapter.php` - Base adapter class
  - `includes/admin/framework-adapters/class-cuft-elementor-adapter.php`
  - `includes/admin/framework-adapters/class-cuft-cf7-adapter.php`
  - `includes/admin/framework-adapters/class-cuft-gravity-adapter.php`
  - `includes/admin/framework-adapters/class-cuft-ninja-adapter.php`
  - `includes/admin/framework-adapters/class-cuft-avada-adapter.php`

#### Frontend Assets
- **Main Controller**: `assets/admin/js/cuft-form-builder.js` - Dashboard UI controller
- **Iframe Bridge**: `assets/admin/js/cuft-iframe-bridge.js` - PostMessage communication
- **Test Mode Script**: `assets/admin/js/cuft-test-mode.js` - Field population & event capture
- **Styles**: `assets/admin/css/cuft-form-builder.css` - Form builder UI styles

#### AJAX Endpoints
1. **POST `/wp-admin/admin-ajax.php?action=cuft_create_test_form`** - Creates a test form
2. **GET `/wp-admin/admin-ajax.php?action=cuft_get_test_forms`** - Retrieves test forms
3. **POST `/wp-admin/admin-ajax.php?action=cuft_delete_test_form`** - Deletes a test form
4. **POST `/wp-admin/admin-ajax.php?action=cuft_populate_form`** - Generates test data
5. **POST `/wp-admin/admin-ajax.php?action=cuft_test_submit`** - Validates submission
6. **GET `/wp-admin/admin-ajax.php?action=cuft_get_frameworks`** - Lists available frameworks

### Usage Guide

#### Accessing the Form Builder
1. Navigate to **Settings → Testing Dashboard**
2. Scroll to the **Test Form Builder** section
3. Select a framework from the dropdown (only active frameworks shown)
4. Select a template (currently: "Basic Contact Form")
5. Click **"Create Test Form"**

#### Testing Workflow
1. **Create Form**: Click "Create Test Form" - form loads in iframe
2. **Populate Data**: Click "Populate Test Data" - fields auto-fill
3. **Submit Form**: Click "Submit Form" or use iframe submit button
4. **Validate Events**: Check "Captured Events" panel for tracking data
5. **Review Results**: Check "Validation Results" for compliance
6. **Cleanup**: Click "Delete Test Form" when done

### PostMessage Protocol

#### Dashboard → Iframe Messages
```javascript
// Populate fields
iframe.contentWindow.postMessage({
  action: 'cuft_populate_fields',
  nonce: cuftFormBuilder.nonce,
  data: {
    fields: { name: 'Test User', email: 'test@example.com', ... },
    options: { trigger_events: true, clear_first: true }
  }
}, window.location.origin);

// Trigger submission
iframe.contentWindow.postMessage({
  action: 'cuft_trigger_submit',
  nonce: cuftFormBuilder.nonce
}, window.location.origin);
```

#### Iframe → Dashboard Messages
```javascript
// Form loaded
window.parent.postMessage({
  action: 'cuft_form_loaded',
  data: { framework: 'elementor', form_id: 'form-123', ready: true }
}, window.location.origin);

// Form submitted
window.parent.postMessage({
  action: 'cuft_form_submitted',
  data: {
    form_data: { ... },
    tracking_event: { event: 'form_submit', cuft_tracked: true, ... }
  }
}, window.location.origin);
```

### Security Features
- **Nonce Validation**: All AJAX requests require valid nonces
- **Origin Validation**: PostMessage communication validates origin
- **Capability Checks**: Only admins can create/manage test forms
- **Test Mode Isolation**: Test forms don't trigger real emails/webhooks

### Troubleshooting

#### Form Won't Create
- Verify framework plugin is active
- Check browser console for JavaScript errors
- Ensure admin permissions (`manage_options` capability)
- Check PHP error logs for server-side issues

#### Fields Won't Populate
- Confirm iframe loaded successfully
- Check postMessage protocol in browser console
- Verify test mode script is enqueued (`?test_mode=1` in URL)
- Try manual population via browser console

#### Events Not Captured
- Ensure dataLayer interceptor is active
- Check that form framework tracking script loaded
- Verify `cuft_tracked: true` in events
- Review validation results for missing fields

### Debug Commands

```javascript
// Enable debug mode
window.CUFTFormBuilder.debugMode = true;
window.CUFTTestMode.debugMode = true;

// Check form builder state
console.log(window.CUFTFormBuilder.currentForm);
console.log(window.CUFTFormBuilder.capturedEvents);

// Manually send message to iframe
const iframe = document.getElementById('cuft-test-iframe');
window.cuftBridge.sendToIframe(iframe, 'cuft_populate_fields', {
  fields: { email: 'test@example.com' }
});

// View all framework adapters
console.log(CUFT_Adapter_Factory::get_frameworks_info());
```

### Design Artifacts
- [PostMessage Protocol](specs/003-testing-dashboard-form/contracts/postmessage-protocol.md)
- [Quick Start Guide](specs/003-testing-dashboard-form/quickstart.md)
- [Implementation Tasks](specs/003-testing-dashboard-form/tasks.md)

### Additional Infrastructure (Phases 3.7-3.9) ✅

#### Test Mode Manager
- **File**: `includes/class-cuft-test-mode.php`
- **Purpose**: Prevents real form actions during testing
- **Features**:
  - Detects `?test_mode=1` parameter
  - Blocks Contact Form 7 emails (`wpcf7_skip_mail`)
  - Blocks Gravity Forms emails (`gform_pre_send_email`)
  - Blocks Ninja Forms actions
  - Blocks Elementor Pro actions (emails, webhooks, redirects)
  - Displays visual test mode indicator
  - Returns fake success for wp_mail()

#### Test Form Routing
- **File**: `includes/class-cuft-test-routing.php`
- **Purpose**: Custom routing for test forms by instance_id
- **Features**:
  - Custom rewrite rules (`/cuft-test-form/{instance_id}`)
  - Query var registration (cuft_test_form, form_id, test_mode)
  - Automatic redirect to actual form with test_mode parameter
  - 404 handling for missing forms
  - Test mode script enqueuing

#### Form Templates
- **File**: `includes/class-cuft-form-template.php`
- **Purpose**: Template storage and management
- **Features**:
  - wp_options storage for templates
  - Template validation
  - Default templates: "Basic Contact Form", "Lead Generation Form"
  - Test data generation based on field types
  - Template CRUD operations

#### Test Sessions
- **File**: `includes/class-cuft-test-session.php`
- **Purpose**: Ephemeral test session management
- **Features**:
  - Transient-based storage (1 hour TTL)
  - Event recording
  - Validation result storage
  - Form data collection
  - Auto-cleanup on expiry
  - Session listing and retrieval

#### Compliance Validator
- **File**: `includes/class-cuft-form-builder-validator.php`
- **Purpose**: Constitutional compliance validation
- **Features**:
  - Validates `cuft_tracked: true` requirement
  - Validates `cuft_source` field presence
  - Checks snake_case naming convention
  - Verifies required fields
  - Tracks click IDs
  - Validates generate_lead requirements
  - Generates compliance reports

---

## Google Tag Manager Container Templates

### Pre-configured GTM Templates Available

The plugin includes sanitized, ready-to-import GTM container templates with all necessary tracking configurations:

#### 📥 GTM Server Container Template
- **Location**: `gtm-server/CUFT - Server Defaults.json`
- **Type**: Server-Side GTM Container
- **Documentation**: `gtm-server/README.md`
- **Includes**:
  - GA4 Event Tag (server-side)
  - Facebook Conversions API
  - Conversion Linker
  - Pre-configured variables and triggers
  - UTM and click ID tracking

#### 📥 GTM Web Container Template
- **Location**: `gtm-web-client/CUFT - Web Defaults.json`
- **Type**: Web GTM Container
- **Documentation**: `gtm-web-client/README.md`
- **Includes**:
  - GA4 Configuration Tag
  - Form Submit event tracking
  - Generate Lead event tracking
  - DataLayer variables
  - UTM parameter variables
  - Click ID variables

### Important: Replace Placeholder IDs Before Publishing

**These templates use sanitized placeholder IDs that MUST be replaced:**

- `GTM-XXXXX1` → Your Server GTM Container ID
- `GTM-XXXXX2` → Your Web GTM Container ID (server reference)
- `GTM-XXXXX3` → Your Web GTM Container ID
- `G-XXXXXXXXX` → Your GA4 Measurement ID
- `1234567890` → Your GTM Account ID
- `987654321` → Your Container ID (numeric)
- `tagging-server.example.com` → Your actual server URL

### Quick Start

1. **Import Template**: GTM Admin → Import Container
2. **Replace IDs**: Find and replace placeholder values
3. **Configure Destinations**: Update GA4, Facebook Pixel, etc.
4. **Test**: Use GTM Preview mode
5. **Publish**: After testing

**Full instructions available in each README.md file.**

---

## Completed Migration: Click Tracking Events (v3.12.0) ✅

### Migration Overview
**Status**: Implementation Complete, Production Ready
**Version**: 3.12.0
**Branch**: `feat/click-tracking-events`
**Spec**: [specs/migrations/click-tracking-events/spec.md](specs/migrations/click-tracking-events/spec.md)

The click tracking system has been enhanced with event-based chronological tracking:
- **New**: JSON `events` column for event chronology (MySQL JSON type)
- **Deprecated**: `utm_source` and `platform` columns (retained for transition period, will be removed in Phase 5)
- **Added**: `idx_date_updated` index for recent activity queries
- **New**: AJAX endpoint `/wp-admin/admin-ajax.php?action=cuft_record_event` for client-side event recording
- **Enhanced**: Admin UI with events timeline, filtering, and sorting

### Key Implementation Details

#### Event Types Supported
- `phone_click` - Tel link clicks from cuft-links.js
- `email_click` - Mailto link clicks from cuft-links.js
- `form_submit` - Form submission events from framework scripts
- `generate_lead` - Qualified lead events (email + phone + click_id)

#### Event Recording Pattern
```javascript
// Fire-and-forget async pattern (never block user interactions)
function recordEvent(clickId, eventType) {
    try {
        fetch(cuftConfig.ajaxUrl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'cuft_record_event',
                nonce: cuftConfig.nonce,
                click_id: clickId,
                event_type: eventType
            })
        }).catch(error => {
            // Silent fail in production
            if (cuftConfig.debug) {
                console.warn('Event recording failed:', error);
            }
        });
    } catch (error) {
        // Never break user functionality
    }
}
```

#### Database Operations
```php
// Add event with deduplication (updates timestamp for duplicate event types)
CUFT_Click_Tracker::add_event($click_id, $event_type);

// Get all events for a click_id
CUFT_Click_Tracker::get_events($click_id);

// FIFO cleanup enforces 100 event limit
```

#### Migration Strategy
- **Phase 1**: Add events column (nullable, non-breaking)
- **Phase 2**: Integrate event recording (feature flag controlled)
- **Phase 3**: Shadow mode rollout (write events, hide UI)
- **Phase 4**: Full rollout (show events in admin)
- **Phase 5**: Remove deprecated columns

#### Rollback Strategy (Hybrid)
If rollback needed:
- ✅ Restore schema (remove events column)
- ✅ Preserve qualified/score updates (business-critical)
- ✅ Discard event data

### Implementation Files (All Complete ✅)
- ✅ `includes/class-cuft-click-tracker.php` - Event methods with deduplication and FIFO cleanup
- ✅ `includes/ajax/class-cuft-event-recorder.php` - AJAX handler with nonce validation
- ✅ `assets/cuft-links.js` - Event recording for phone/email clicks
- ✅ `assets/cuft-dataLayer-utils.js` - Centralized event recording for all form frameworks
- ✅ `includes/class-cuft-admin.php` - Events timeline display, filtering, and sorting
- ✅ `includes/migrations/class-cuft-migration-3-12-0.php` - Database migration with hybrid rollback

### Implementation Status
**All Phases Complete**:
- ✅ **Phase 3.1**: Database migration infrastructure
- ✅ **Phase 3.2**: TDD test suite (unit + integration tests)
- ✅ **Phase 3.3**: Core event tracking implementation
- ✅ **Phase 3.4**: JavaScript integration (links + forms)
- ✅ **Phase 3.5**: Admin interface with events display
- ✅ **Phase 3.6**: Integration testing and validation
- ✅ **Phase 3.7**: Performance benchmarking and documentation

### Testing Results
- ✅ Database schema validated (JSON column, indexes)
- ✅ AJAX endpoint functional (nonce security working)
- ✅ Phone/email link tracking operational
- ✅ Form submission events recording correctly
- ✅ Event deduplication working as designed
- ✅ Admin UI displaying events timeline
- ✅ Webhook API backward compatible
- ✅ FIFO cleanup maintaining 100-event limit

### Troubleshooting Tips

**AJAX Endpoint Issues**
- Ensure `cuftConfig` JavaScript object is available globally
- Check browser console for nonce errors
- Verify AJAX URL points to `/wp-admin/admin-ajax.php`
- Flush rewrite rules: `wp rewrite flush` (for custom webhook URL)

**Events Not Recording**
- Verify `click_id` is present in URL parameters or sessionStorage
- Check `cuftConfig.debug` for detailed logging
- Ensure migration has been run (`CUFT_DB_Migration::run_migrations()`)
- Verify `events` column exists: `SHOW COLUMNS FROM wp_cuft_click_tracking`

**Admin UI Not Showing Events**
- Check if `events` column contains valid JSON: `SELECT click_id, events FROM wp_cuft_click_tracking`
- Verify `CUFT_Click_Tracker::get_events()` returns array
- Clear browser cache if events column appears empty

**Performance Issues**
- JSON operations target: <12ms for add_event, <5ms for get_events
- AJAX response time target: <100ms P95
- Run performance tests: `php tests/performance/test-json-performance.php`
- Monitor using `EXPLAIN` for query optimization

### Design Artifacts
- [research.md](specs/migrations/click-tracking-events/research.md) - Technical research findings
- [data-model.md](specs/migrations/click-tracking-events/data-model.md) - Database schema details
- [contracts/ajax-endpoint.md](specs/migrations/click-tracking-events/contracts/ajax-endpoint.md) - AJAX API contract
- [contracts/webhook-api.md](specs/migrations/click-tracking-events/contracts/webhook-api.md) - Webhook compatibility
- [quickstart.md](specs/migrations/click-tracking-events/quickstart.md) - Testing guide

## Core Development Principles

### JavaScript-First Approach

**Principle: Maximize compatibility by preferring pure JavaScript over jQuery**

1. **Primary Implementation**: Always implement functionality using pure vanilla JavaScript first
2. **jQuery as Fallback**: Add jQuery implementations as a secondary option when available
3. **Multiple Fallback Methods**: Implement multiple detection and tracking methods to ensure maximum compatibility

#### Implementation Strategy
```javascript
// 1. Try native JavaScript first
if (window.CustomEvent) {
  document.addEventListener('submit_success', handler);
}

// 2. Add jQuery listener if available
if (window.jQuery) {
  jQuery(document).on('submit_success', handler);
}

// 3. Add additional fallback methods
// - MutationObserver for DOM changes
// - Ajax interceptors
// - Form submit handlers
```

### Event Tracking Robustness

The plugin implements multiple layers of event detection:

1. **Native JavaScript Events** (Elementor 3.5+)
2. **jQuery Events** (older Elementor versions)
3. **MutationObserver** (watches for success messages)
4. **Ajax Interceptors** (fetch and XMLHttpRequest)
5. **jQuery.ajaxComplete** (when jQuery is available)

This ensures form submissions are tracked regardless of:
- Elementor version (Pro or Free)
- jQuery availability
- JavaScript framework conflicts
- Custom implementations

### Data Retrieval Fallback Chain

**Graceful degradation for tracking data retrieval:**

```
URL Parameters → SessionStorage → Cookies → Empty Object
```

Each source is wrapped in try-catch blocks to ensure failures don't break the tracking.

### DataLayer Parameter Naming Convention

**All dataLayer parameters use consistent snake_case naming:**

- ✅ `form_type` (not `formType`)
- ✅ `form_id` (not `formId`)
- ✅ `form_name` (not `formName`)
- ✅ `user_email` (not `userEmail`)
- ✅ `user_phone` (not `userPhone`)
- ✅ `submitted_at` (not `submittedAt`)
- ✅ `cuft_tracked: true`
- ✅ `cuft_source: "framework_name"`

This ensures GTM compatibility and consistent data across all frameworks.

## Multi-Framework Implementation

### Supported Form Frameworks

The plugin supports multiple form frameworks with specialized tracking for each:

1. **Elementor Pro Forms** (primary focus)
2. **Contact Form 7**
3. **Ninja Forms**
4. **Gravity Forms**
5. **Avada/Fusion Forms**

Each framework has dedicated tracking scripts that:
- Listen for framework-specific events
- Extract form data using framework conventions
- Apply consistent dataLayer parameter naming
- Handle framework-specific success states

### Cross-Framework Compatibility

**Multiple frameworks can coexist on the same page without interference:**

- Each framework script only processes its own forms
- Non-relevant forms are ignored silently (no console noise)
- Framework detection happens before any logging
- Scripts exit early for non-matching forms

### Event Handling Strategy

Different frameworks use different event approaches:

**Event-Based Frameworks:**
- **Elementor**: Listens for `submit_success` events
- **Contact Form 7**: Listens for `wpcf7mailsent` events

**Submit-Based Frameworks:**
- **Avada**: Listens for `submit` events with `.fusion-form` detection
- **Ninja Forms**: Listens for `submit` events with `.nf-form-cont` detection
- **Gravity Forms**: Listens for `submit` events with `.gform_form` detection

## Elementor Forms Implementation

### Event Handling

Elementor forms fire a `submit_success` event after successful submission. Our implementation:

1. **Listens for multiple event types**:
   - `submit_success` (native and jQuery)
   - `elementor/frontend/form_success`
   - `elementor/popup/hide`

2. **Form Detection Methods**:
   - Event target traversal
   - Pending tracking attribute
   - Visible form detection
   - Recent interaction detection

### Required Fields for Events

#### form_submit Event
Fires on every successful form submission with:
- Form ID and name
- UTM parameters (if available)
- Click IDs (if available)
- User email and phone (if provided)
- GA4 standard parameters

#### generate_lead Event
Only fires when ALL three conditions are met:
1. **Click ID** present (click_id, gclid, fbclid, or any supported click ID)
2. **Email** field has a value
3. **Phone** field has a value

### Click ID Support

The following click IDs are tracked:
- `click_id` (generic)
- `gclid` (Google Ads)
- `gbraid` (Google iOS)
- `wbraid` (Google Web-to-App)
- `fbclid` (Facebook/Meta)
- `msclkid` (Microsoft/Bing)
- `ttclid` (TikTok)
- `li_fat_id` (LinkedIn)
- `twclid` (Twitter/X)
- `snap_click_id` (Snapchat)
- `pclid` (Pinterest)

## Testing

For testing form tracking, use real forms on your site with browser DevTools Console to monitor dataLayer events. Verify that:

- Form submissions trigger `form_submit` event with `cuft_tracked: true`
- Events use snake_case field names (`form_type`, `user_email`, etc.)
- UTM parameters are captured from all sources
- Click IDs are properly tracked
- Events fire only once per submission

For comprehensive testing procedures and debugging guides, see:
- **[docs/TESTING.md](docs/TESTING.md)** - Full testing documentation

## Debug Mode

Enable debug logging by setting:
```javascript
window.cuftElementor = {
  console_logging: true,
  generate_lead_enabled: true
};
```

This will output detailed tracking information to the console.

### Expected DataLayer Event Format

**Standard form_submit Event:**
```javascript
{
  event: "form_submit",
  form_type: "elementor",               // Framework identifier
  form_id: "elementor-widget-7a2c4f9",  // Form's unique ID
  form_name: "Contact Form",            // Human-readable form name
  user_email: "user@example.com",       // Email field value
  user_phone: "123-456-7890",           // Phone field value
  submitted_at: "2025-01-01T12:00:00Z", // ISO timestamp
  cuft_tracked: true,                   // Added by production code
  cuft_source: "elementor_pro",         // Added by production code
  click_id: "abc123",                   // If present
  gclid: "xyz789",                      // If present
  utm_source: "google",                 // If present
  utm_medium: "cpc",                    // If present
  utm_campaign: "summer_sale",          // If present
  utm_term: "contact_form",             // If present
  utm_content: "sidebar"                // If present
}
```

**generate_lead Event** (only when email + phone + click_id present):
```javascript
{
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead",
  // All form_submit fields also included
}
```

## Browser Compatibility

The plugin is designed to work with:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Elementor 3.0+ (optimized for 3.5+)
- Elementor Pro 3.0+ (optimized for 3.7+)
- With or without jQuery
- WordPress 5.0+
- PHP 7.0+

## Release Creation Process

### When Creating a New Release

**IMPORTANT: Always create and upload a zip file for WordPress installations**

1. **Update Version Numbers**:
   - Update version in `choice-universal-form-tracker.php` header comment
   - Update `CUFT_VERSION` constant in the same file
   - Update `CHANGELOG.md` with new version entry

2. **Create Installation Zip**:
   ```bash
   # Create zip file excluding development files
   # CRITICAL: WordPress Release ZIP Naming Convention:
   # - ZIP FILENAME: 'choice-uft-v3.xx.xx.zip' (includes version for GitHub releases)
   # - FOLDER INSIDE: 'choice-uft/' (NO version number - required for WordPress auto-updater)
   # This ensures WordPress extracts to /wp-content/plugins/choice-uft/ correctly

   cd /path/to/parent/directory
   zip -r choice-uft-v[VERSION].zip choice-uft/ \
     -x "choice-uft/.git/*" \
     -x "choice-uft/.github/*" \
     -x "choice-uft/.gitignore" \
     -x "choice-uft/node_modules/*" \
     -x "choice-uft/.env" \
     -x "choice-uft/*.zip"
   ```

3. **Create GitHub Release**:
   ```bash
   # Create release with comprehensive notes
   gh release create v[VERSION] --title "Version [VERSION]" --notes "[Release notes]"

   # Upload the zip file to release assets
   gh release upload v[VERSION] choice-uft-v[VERSION].zip --clobber
   ```

4. **Verify Release**:
   - Check that zip file is attached to release assets
   - Verify download link works
   - Ensure WordPress auto-updater can detect the new version

### Example Release Commands
```bash
# For version 3.10.1 (UPDATED PROCESS)
cd /home/r11/dev
zip -r choice-uft-v3.10.1.zip choice-uft/ \
  -x "choice-uft/.git/*" "choice-uft/.github/*" "choice-uft/.gitignore" \
  -x "choice-uft/node_modules/*" "choice-uft/.env" "choice-uft/*.zip"

gh release create v3.10.1 --title "Version 3.10.1" --notes "Release notes here"
gh release upload v3.10.1 choice-uft-v3.10.1.zip --clobber
```

## Important Notes

1. **Never depend solely on jQuery** - It may not be available
2. **Always provide fallbacks** - Multiple detection methods ensure reliability
3. **Test without jQuery** - Verify pure JavaScript paths work
4. **Handle errors gracefully** - Use try-catch blocks liberally
5. **Log in debug mode only** - Minimize console output in production
6. **Always create release zip files** - Required for WordPress installations and auto-updates
7. **CRITICAL: WordPress ZIP Naming Convention**:
   - **ZIP FILENAME**: `choice-uft-v3.xx.xx.zip` (includes version for GitHub releases and downloads)
   - **FOLDER INSIDE**: `choice-uft/` (NO version number - required for WordPress auto-updater)
   - This ensures WordPress extracts to `/wp-content/plugins/choice-uft/` correctly, not `choice-uft-v3.xx.xx/`

## Admin Notifications

The plugin displays WordPress admin notices to help administrators configure and monitor the plugin:

### Notice Types

1. **GTM ID Missing (Persistent Warning)**
   - **Type**: Warning (yellow/orange)
   - **Dismissible**: No - persists until GTM ID is configured
   - **Message**: "GTM container ID is missing or invalid. Please configure your GTM ID to enable conversion tracking."
   - **Action**: Displays until a valid GTM ID (format: GTM-XXXXXXX) is added in Settings

2. **Plugin Active (Success - Dismissible)**
   - **Type**: Success (green)
   - **Dismissible**: Yes - can be dismissed by clicking the X
   - **Message**: Shows framework count and configured GTM ID
   - **Behavior**: Once dismissed, won't show again for that user (stored in user meta)

### Resetting Dismissed Notices

If you need to see the success notice again after dismissing it:
```php
// Reset for current user
delete_user_meta(get_current_user_id(), 'cuft_notice_dismissed');

// Reset for specific user
delete_user_meta($user_id, 'cuft_notice_dismissed');

// Reset for all users (use with caution)
delete_metadata('user', null, 'cuft_notice_dismissed', '', true);
```

### Notice Behavior

- **Appears on**: All admin pages except the plugin's own settings page
- **Permissions**: Only shown to users with `manage_options` capability (administrators)
- **Priority**: GTM missing warning takes precedence over success notice
- **AJAX Handler**: `cuft_dismiss_notice` handles dismissal via user meta

## Troubleshooting

### Common Issues and Fixes

#### GTM Tags Not Firing

**Problem**: Google Tag Manager tags don't fire when forms are submitted.

**Causes & Solutions**:
1. **Wrong field names**: Ensure events use `form_type` (not `form_framework`)
2. **Missing cuft_tracked**: Verify events have `cuft_tracked: true`
3. **Missing cuft_source**: Verify events have `cuft_source: "framework_name"`

#### Cross-Framework Console Noise

**Problem**: Multiple framework scripts logging messages for non-relevant forms.

**Fixed**: Framework detection now happens before logging. Scripts exit silently for non-matching forms.

#### Multiple Frameworks Conflicting

**Problem**: Different form frameworks interfere with each other on the same page.

**Fixed**: Each framework script only processes its own forms and ignores others silently.

### Debug Commands

**Check dataLayer events in browser console:**
```javascript
// View all dataLayer events
console.log(window.dataLayer);

// Monitor new events
window.dataLayer.push = function(event) {
  console.log('dataLayer event:', event);
  Array.prototype.push.call(window.dataLayer, event);
};

// Check for CUFT events specifically
window.dataLayer.filter(e => e.cuft_tracked);
```

**Enable framework-specific debugging:**
```javascript
// Elementor debugging
window.cuftElementor = {
  console_logging: true,
  generate_lead_enabled: true
};

// Avada debugging
window.cuftAvada = {
  console_logging: true
};

// Global UTM debugging
window.cuftUtm = {
  console_logging: true
};
```
