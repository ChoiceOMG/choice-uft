# Feature Specification: Update System Implementation Gaps

**Feature Branch**: `008-fix-critical-gaps`
**Created**: 2025-10-11
**Status**: Draft
**Input**: User description: "Fix critical gaps in update system: Add missing update execution tasks (FR-301 to FR-303), implement plugins_api filter (FR-102), add directory naming fix (FR-103), and clarify ZIP validation and file preservation mechanisms. Simplified to use only WordPress standard update methods (Plugins page, WP-CLI, bulk updates) - no custom Settings page UI."

## Execution Flow (main)
```
1. Parse user description from Input
   → Identified gaps from Feature 007 analysis
2. Extract key concepts from description
   → Actors: WordPress administrators
   → Actions: Execute updates, validate downloads, preserve files, display plugin info
   → Data: Update packages, plugin metadata, backup files
   → Constraints: WordPress core compatibility, security validation
3. For each unclear aspect:
   → All aspects clarified from Feature 007 context
4. Fill User Scenarios & Testing section
   → User flows defined for each missing FR
5. Generate Functional Requirements
   → FR-102: Plugin information modal (plugins_api filter)
   → FR-103: Directory naming fix (upgrader_source_selection hook)
   → FR-301 to FR-303: Update execution via different methods (Plugins page, WP-CLI, bulk updates)
   → Enhanced FR-401 to FR-404: Detailed error handling
6. Identify Key Entities
   → Plugin metadata, Download package, Backup archive
7. Run Review Checklist
   → No implementation details exposed
   → All requirements testable
8. Return: SUCCESS (spec ready for planning)
```

---

## ⚡ Context

This specification addresses **critical implementation gaps** identified in Feature 007 (Fix Update System Integration). The analysis revealed:

- **52% requirement coverage**: Only 11 of 21 functional requirements had implementation tasks
- **Missing core functionality**: No tasks for actual update execution (FR-301 to FR-303: Plugins page, WP-CLI, bulk updates)
- **Underspecified security**: ZIP validation and file preservation mechanisms undefined
- **Missing WordPress integration**: plugins_api filter and directory naming fix not implemented

This feature fills those gaps to complete the update system implementation.

---

## Clarifications

### Session 2025-10-11

- Q: When should backups be deleted? (FR-402 specified "7 days after successful update" but differs from WordPress patterns) → A: Immediately after successful update (standard WordPress pattern, no retention)
- Q: Should Settings page have custom "Update Now" button? → A: No. Removed FR-302 and Settings page update functionality entirely. Updates only via standard WordPress Plugins page, WP-CLI, and bulk updates. Aligns with simplicity requirements and WordPress conventions.
- Q: What should happen if restoration takes longer than 10 seconds? → A: Abort restoration at 10s, show manual reinstall message (hard timeout enforcement)
- Q: When should invalid/incomplete downloads be deleted? → A: Both immediate deletion after validation failure AND scheduled daily cleanup via WordPress cron (catches orphaned files from crashes)
- Q: What should happen if GitHub API fails when fetching changelog for the modal? → A: Show all other modal information, omit changelog section (partial degradation - graceful failure)
- Q: Should FR-401 validate file size matches expected size from GitHub API? → A: No. Removed file size validation entirely. GitHub releases are a trusted source - only validate that downloaded file is a valid ZIP archive. Simplified FR-401, removed Scenario 5 (size mismatch), and removed size fields from Download Package entity. Avoids overengineering for trusted source.

### Session 2025-10-11 (Continued - UI Simplification)

**Feature 007 UI Components to Remove** (align with WordPress conventions, reduce complexity):

1. **Admin Bar Integration**: Remove "CUFT Update" menu item from admin bar entirely. No custom update indicators in admin bar.

2. **Plugin Options Page - Updates Tab**: Remove entire "Updates" tab and all related update execution code from plugin's Settings page (`Settings → Universal Form Tracker`). This includes:
   - Update Now button
   - Update progress UI
   - Update history display (if on this tab)
   - All AJAX handlers for settings page updates

3. **Plugin Options Page - GitHub Auto-Updates Section**: Remove "GitHub Auto-Updates" configuration section from plugin settings. Users will manage auto-updates using WordPress's standard auto-update toggle on the Plugins page (`Plugins → Installed Plugins → Enable/Disable Auto-updates`), consistent with all other plugins.

4. **Admin Notification Behavior**: When an update is available:
   - Show standard WordPress admin notice: "There is a new version of Choice Universal Form Tracker available."
   - Include "View Plugin Updates" button that links to WordPress Plugins page (`/wp-admin/plugins.php`), NOT the plugin's settings page
   - Notice must be **dismissible** (show X button)
   - After user dismisses, DO NOT show the notice again until a different version becomes available
   - Store dismissal state per version number (e.g., if user dismisses notice for v3.17.0, don't show again for 3.17.0, but DO show for 3.17.1)

**Rationale**: These changes align with WordPress plugin conventions where:
- Updates are managed exclusively via WordPress Plugins page
- Auto-update preferences are controlled via standard WordPress UI (per-plugin toggle)
- Plugins don't create redundant update UIs in their own settings pages
- Admin bar is reserved for critical notifications, not routine update checks
- Simplifies codebase by removing 300+ lines of custom UI code

---

## User Scenarios & Testing

### Primary User Story

As a WordPress administrator managing the Choice Universal Form Tracker plugin installed from GitHub, I need a complete and reliable update system that:

1. **Shows complete plugin information** when I click "View Details" on an available update
2. **Executes updates correctly** whether I trigger them from the Plugins page, Dashboard Updates page, or WP-CLI
3. **Validates downloaded files** to ensure they're not corrupted or malicious before installation
4. **Preserves my current version** automatically so I can rollback if the update fails
5. **Handles directory naming correctly** so WordPress installs the plugin to the right location

Without these capabilities, the update system cannot function as a complete solution.

### Acceptance Scenarios

#### Scenario 1: View Complete Plugin Information
- **Given**: An administrator viewing the Plugins page
- **And**: A plugin update is available
- **When**: The administrator clicks "View Details" link
- **Then**: A modal opens showing complete plugin information:
  - Plugin name and author
  - Current version and new version
  - Full changelog from GitHub release notes
  - WordPress compatibility (Requires at least, Tested up to)
  - PHP compatibility (Requires PHP)
  - File size
  - Last updated date
- **And**: The modal has a working "Update Now" button
- **And**: No errors or broken formatting appear

#### Scenario 2: Update from WordPress Plugins Page
- **Given**: An administrator on wp-admin/plugins.php
- **And**: CUFT plugin has an available update
- **When**: Administrator clicks WordPress's native "Update Now" button
- **Then**: WordPress downloads the ZIP file from GitHub
- **And**: WordPress extracts the ZIP file
- **And**: Plugin directory is renamed from `choice-uft-vX.X.X` to `choice-uft`
- **And**: WordPress completes installation to `/wp-content/plugins/choice-uft/`
- **And**: Update succeeds with success message
- **And**: Plugin version reflects new version

#### Scenario 3: Update via WP-CLI
- **Given**: Administrator has SSH/shell access to server
- **When**: Administrator runs `wp plugin update choice-uft`
- **Then**: WP-CLI detects available update
- **And**: WP-CLI downloads and installs update
- **And**: Output shows progress information
- **And**: Command exits with code 0 (success)
- **And**: Plugin updated to new version

#### Scenario 4: Bulk Update from Dashboard
- **Given**: Administrator viewing Dashboard → Updates
- **And**: CUFT plugin appears in "Plugins" update list
- **And**: WordPress core also has an update available
- **When**: Administrator selects both updates and clicks "Update Plugins"
- **Then**: Both updates execute successfully
- **And**: CUFT update doesn't interfere with WordPress core update
- **And**: All updates complete without errors

#### Scenario 5: Update Failure with Automatic Rollback
- **Given**: Update download completes successfully
- **And**: Current plugin files backed up to `/wp-content/uploads/cuft-backups/choice-uft-3.16.5-backup.zip`
- **When**: ZIP extraction fails due to corrupted archive
- **Then**: System detects extraction failure
- **And**: Error message appears: "Update failed during extraction. Previous version restored automatically."
- **And**: Backup is restored to `/wp-content/plugins/choice-uft/`
- **And**: Plugin functionality continues with previous version
- **And**: User can attempt update again

#### Scenario 6: Directory Naming Correction
- **Given**: GitHub release ZIP contains directory `choice-uft-v3.17.0/`
- **And**: WordPress expects directory `choice-uft/`
- **When**: WordPress extracts the ZIP file during update
- **Then**: System detects naming mismatch via `upgrader_source_selection` hook
- **And**: Directory is renamed from `choice-uft-v3.17.0/` to `choice-uft/`
- **And**: WordPress installs to correct location `/wp-content/plugins/choice-uft/`
- **And**: No "directory mismatch" errors occur
- **And**: Update completes successfully

### Edge Cases

**EC-1: Backup Directory Not Writable**
- **Given**: `/wp-content/uploads/cuft-backups/` doesn't exist or isn't writable
- **When**: System attempts to create backup before update
- **Then**: Error message: "Cannot create backup directory. Please ensure /wp-content/uploads/ is writable or contact your hosting provider."
- **And**: Update is aborted (doesn't proceed without backup)
- **And**: Current version remains intact

**EC-2: Disk Space Insufficient for Backup**
- **Given**: Server disk space is nearly full
- **When**: System attempts to create backup ZIP (requires 5 MB)
- **Then**: Backup creation fails with disk space error
- **And**: Error message: "Insufficient disk space to create backup. Free at least 5 MB and try again."
- **And**: Update is aborted
- **And**: Current version remains intact

**EC-3: Backup Restoration Fails**
- **Given**: Update extraction failed
- **And**: System attempts to restore from backup
- **And**: Backup file is corrupted or missing
- **When**: Restoration is attempted
- **Then**: Error message: "Update failed and backup restoration also failed. Please reinstall plugin manually from GitHub."
- **And**: System logs detailed error to PHP error_log
- **And**: Plugin may be in broken state (requires manual intervention)

**EC-4: GitHub Release ZIP Structure Changes**
- **Given**: GitHub changes ZIP structure to nest directory differently
- **When**: Update is attempted
- **Then**: Directory naming fix detects unusual structure
- **And**: If pattern unrecognizable, update fails with clear error
- **And**: Error message: "Unexpected ZIP structure. Please report this issue to plugin developers."
- **And**: Rollback occurs automatically

**EC-5: WP-CLI Update During Active Admin Update**
- **Given**: Admin user starts update via WordPress admin
- **And**: Update is in progress
- **When**: Another user attempts `wp plugin update choice-uft` via WP-CLI
- **Then**: WP-CLI detects update in progress
- **And**: Error message: "Update already in progress by Admin User. Please wait."
- **And**: WP-CLI exits with code 1 (failure)
- **And**: First update continues uninterrupted

---

## Requirements

### Functional Requirements

#### Category: WordPress Integration (Missing from 007)

**FR-102: Complete Plugin Information Modal**
- System MUST respond to WordPress `plugins_api` filter with complete plugin information
- Information MUST include: name, slug, version, author, author_profile, homepage, requires, tested, requires_php, download_link, last_updated, sections (description, installation), banners, icons
- Changelog section MUST be fetched from GitHub release notes
- If GitHub API fails (unavailable, rate-limited, error), system MUST omit changelog section and display other information (graceful degradation)
- Modal MUST display without errors (even if partial information available)
- "Update Now" button in modal MUST trigger update successfully

**FR-103: Directory Naming Fix**
- System MUST hook into `upgrader_source_selection` filter
- System MUST detect when extracted directory name doesn't match expected name
- Expected directory name MUST be `choice-uft` (without version suffix)
- System MUST rename directory from GitHub format (`choice-uft-vX.X.X`) to WordPress format (`choice-uft`)
- Renaming MUST occur before WordPress validates installation directory
- If source directory not found, system MUST return WP_Error with clear message
- If rename operation fails, system MUST return WP_Error with filesystem error details
- System MUST only rename CUFT plugin directories, not affect other plugin updates

#### Category: Update Execution (Critical Gap from 007)

**FR-301: Update Execution via WordPress Plugins Page**
- System MUST work correctly with WordPress's native "Update Now" button on Plugins page
- Update process MUST follow standard WordPress Plugin_Upgrader flow
- System MUST NOT implement custom download/install logic (rely on WordPress core)
- Update MUST complete within 60 seconds on a stable connection (download speed ≥1 Mbps, packet loss <5%)
- Progress MUST be displayed using WordPress's native progress UI
- Success/failure messages MUST be displayed using WordPress's native admin notices
- System MUST log update attempt to plugin's update history (FR-601 from 007)

**FR-302: Update Execution via WP-CLI**
- System MUST integrate with WordPress's standard WP-CLI update mechanism
- Command `wp plugin update choice-uft` MUST detect available updates correctly
- CLI update MUST use same Plugin_Upgrader flow as admin UI updates
- CLI MUST output progress information: "Downloading...", "Unpacking...", "Installing..."
- CLI MUST return exit code 0 on success, 1 on failure
- CLI MUST display version change: "Updated choice-uft from 3.16.5 to 3.17.0"
- CLI update MUST log to update history same as admin updates

**FR-303: Bulk Update Support**
- Plugin MUST appear in Dashboard → Updates page plugin list when update available
- Plugin MUST be selectable for bulk updates alongside other plugins
- Bulk update MUST work correctly when combined with WordPress core updates
- Bulk update MUST NOT interfere with other plugin updates in same batch
- Bulk update progress MUST be displayed in WordPress's bulk update progress UI
- If bulk update fails for CUFT, other plugins MUST continue updating (isolation)
- Bulk update results MUST show clear success/failure status for CUFT

#### Category: Download Validation (Enhanced from 007 FR-401)

**FR-401: Download Validation and Integrity Checks**
- System MUST verify downloaded file is a valid ZIP archive using WordPress's built-in ZIP validation
- If ZIP validation fails, system MUST abort and display: "Downloaded file is not a valid ZIP archive. Please try again or contact support."
- System MUST NOT extract ZIP files that fail validation
- System MUST delete invalid downloads immediately after validation failure
- System MUST schedule daily WordPress cron job to cleanup orphaned download files (catches files from crashes or interrupted updates)
- All validation failures MUST be logged to PHP error_log with details
- System MUST preserve current plugin version if download validation fails

**FR-402: Automatic File Preservation and Rollback**
- System MUST create backup of current plugin files before starting update
- Backup location MUST be `/wp-content/uploads/cuft-backups/choice-uft-[VERSION]-backup.zip`
- Backup filename MUST include current version number for identification
- Backup MUST be created using WordPress's ZIP filesystem methods
- If backup creation fails (disk space, permissions), update MUST be aborted with error message
- Backup MUST be deleted immediately after successful update (standard WordPress pattern)
- If update fails at any stage (download, extract, install), system MUST automatically restore from backup
- Restoration process MUST extract backup ZIP to `/wp-content/plugins/choice-uft/`
- Restoration MUST complete within 10 seconds (hard timeout)
- If restoration exceeds 10 seconds, system MUST abort restoration and display: "Update failed and automatic restoration timed out. Please reinstall plugin manually from GitHub: [URL]"
- If restoration fails for any reason, system MUST log CRITICAL error and display manual reinstall message
- After successful restoration (within timeout), system MUST display: "Update failed: [reason]. Previous version (X.X.X) has been restored automatically."

**FR-403: Error Message Clarity (Enhanced from 007 FR-404)**
- All error messages MUST explain what went wrong in plain, non-technical language
- All error messages MUST suggest specific corrective actions
- Error messages MUST include relevant context (version attempting, error code, affected files)
- Examples of required error messages:
  - Download failure: "Update download failed due to network error. Please check your internet connection and try again."
  - Extraction failure: "Cannot extract update files. The download may be corrupted. Please try again."
  - Permission error: "Cannot write to /wp-content/plugins/choice-uft/. Please check file permissions (should be 755) or contact your hosting provider."
  - Disk space error: "Insufficient disk space to complete update. Free at least [X] MB and try again."
  - Backup failure: "Cannot create backup before update. Ensure /wp-content/uploads/ is writable."
  - Restoration timeout: "Update failed and automatic restoration timed out. Please reinstall plugin manually from GitHub: [URL]"
- Error messages for administrators MUST be stored in update history log
- Error messages MUST never expose server paths or sensitive information to non-administrators

**FR-404: Security Validation During Updates (Enhanced from 007 FR-403)**
- All update actions MUST validate WordPress nonces (action: `update-plugin`)
- All update actions MUST verify user has `update_plugins` capability
- Download URLs MUST be validated as HTTPS GitHub CDN URLs only
- System MUST reject download URLs not matching pattern: `https://github.com/ChoiceOMG/choice-uft/releases/download/*`
- ZIP file extraction MUST use WordPress's safe extraction methods (no shell commands)
- Update process MUST respect WordPress's `DISALLOW_FILE_MODS` constant (if set, show error)
- Update process MUST check filesystem write permissions before starting
- All file operations MUST use WordPress Filesystem API (not direct PHP file functions)

### Key Entities

**Plugin Metadata (for plugins_api response)**
- Name: "Choice Universal Form Tracker"
- Slug: "choice-uft"
- Version: Semver format (e.g., "3.17.0")
- Author: "Choice Marketing"
- Author Profile: Link to GitHub organization
- Homepage: Plugin repository URL
- Requires: Minimum WordPress version (e.g., "5.0")
- Tested: Maximum tested WordPress version (e.g., "6.7")
- Requires PHP: Minimum PHP version (e.g., "7.0")
- Download Link: Direct URL to GitHub release ZIP
- Last Updated: ISO 8601 date from GitHub release
- Sections: Object containing description, changelog, installation sections
- Banners: URLs to banner images (if provided)
- Icons: URLs to icon images (if provided)

**Download Package**
- Source URL: GitHub release asset URL (HTTPS)
- Validation Status: Boolean (pass/fail)
- Validation Errors: Array of error messages
- Local Path: Temporary file path where download stored

**Backup Archive**
- Source Version: Version being backed up (e.g., "3.16.5")
- Backup Path: Full path to ZIP file in uploads/cuft-backups/
- Created Date: Timestamp of backup creation
- File Size: Size of backup ZIP in bytes
- Backup Status: "created" | "restored" | "deleted"
- Lifecycle: Created before update, deleted immediately on success, kept only if update fails

**Update Execution Context**
- trigger_location: "plugins_page" | "updates_page" | "wp_cli" | "bulk_update"
- user_id: WordPress user who initiated update
- user_display_name: For concurrent update detection
- started_timestamp: ISO 8601 timestamp
- completed_timestamp: ISO 8601 timestamp (or null if in progress)
- duration: Seconds elapsed
- previous_version: Version before update
- target_version: Version being installed
- status: "pending" | "downloading" | "extracting" | "installing" | "complete" | "failed" | "rolled_back"
- error_message: Null or error description
- progress_percentage: 0-100 for UI display

---

## Success Criteria

### Must Have (Critical - Blocks Feature 007 Completion)

1. ✅ Plugin information modal displays complete information (FR-102)
2. ✅ Directory naming automatically corrected during updates (FR-103)
3. ✅ Updates work from WordPress Plugins page (FR-301)
4. ✅ Updates work via WP-CLI (FR-302)
5. ✅ Bulk updates work correctly (FR-303)
6. ✅ Downloaded ZIP files validated before extraction (FR-401)
7. ✅ Automatic backup and rollback on failure (FR-402)
8. ✅ Clear error messages for all failure modes (FR-403)
9. ✅ Security validation enforced throughout update process (FR-404)

### Should Have (Important)

*All critical requirements covered in "Must Have" section above.*

### Nice To Have (Future Enhancement)

10. Update execution logs visible in plugin admin interface
11. Backup management UI (view/delete old backups)
12. Download progress percentage in real-time
13. Update changelog preview before confirming update
14. Scheduled updates (automatic at specific time)
15. Email notification on update completion
16. Update diff showing changed files
17. Backup restoration from any previous version (not just most recent)
18. Integration with WordPress's auto-update system

---

## Out of Scope

- Custom update server implementation (GitHub releases remain source)
- Plugin marketplace integration
- License key validation (plugin is open-source)
- Delta updates (downloading only changed files)
- Update channels beyond stable (beta, alpha channels out of scope)
- Multi-site network-wide update controls (standard WordPress multisite behavior only)
- Update scheduling UI (use WordPress core cron events)

---

## Dependencies & Assumptions

### Dependencies

**Feature 007 Completion**:
- Update Status model (site transients) must be functional for version detection
- Update Log (FIFO) must be working for update history tracking

**WordPress Core APIs**:
- `plugins_api` filter for plugin information modal
- `upgrader_source_selection` filter for directory renaming
- `Plugin_Upgrader` class for update execution
- `WP_Filesystem` API for file operations
- `WP_Ajax_Upgrader_Skin` for progress display
- WordPress ZIP filesystem methods

**External Services**:
- GitHub API for release information
- GitHub CDN for ZIP downloads

### Assumptions

**A1: WordPress Plugin_Upgrader Reliability**
- Assumption: WordPress's built-in Plugin_Upgrader class handles updates correctly
- Validation: Tested across WordPress 5.0 to 6.7
- Risk: Low (WordPress core API, battle-tested)
- Mitigation: Extensive testing across WordPress versions

**A2: GitHub Release ZIP Structure**
- Assumption: GitHub always creates ZIP files with directory name `choice-uft-[tag_name]`
- Validation: Current observed behavior
- Risk: Medium (GitHub could change ZIP generation)
- Mitigation: FR-103 validates structure and fails gracefully if unexpected

**A3: Filesystem Write Permissions**
- Assumption: WordPress installation has write permissions to plugins directory
- Validation: Check permissions before update
- Risk: Medium (shared hosting may restrict)
- Mitigation: FR-404 checks permissions and provides clear error messages

**A4: Backup Storage Availability**
- Assumption: At least 10 MB available in /wp-content/uploads/ for backups
- Validation: Check available disk space before creating backup
- Risk: Medium (low-storage hosting environments)
- Mitigation: FR-402 aborts update if backup creation fails

**A5: Network Reliability**
- Assumption: Server can maintain stable connection for 30-60 second download
- Validation: Use WordPress HTTP API with timeouts and retries
- Risk: Medium (flaky networks, large files)
- Mitigation: FR-401 validates downloaded files, allows retry

---

## Review & Acceptance Checklist

### Content Quality
- [x] No implementation details (languages, frameworks, specific APIs)
- [x] Focused on user value and business needs
- [x] Written for stakeholders (administrators, site owners)
- [x] All mandatory sections completed
- [x] Context provided (gaps from Feature 007 analysis)

### Requirement Completeness
- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable (10 must-have items)
- [x] Scope is clearly bounded (out of scope section)
- [x] Dependencies and assumptions identified (5 assumptions documented)
- [x] Edge cases documented with expected behavior (5 edge cases)

### Requirement Quality
- [x] FR-102: Testable via modal inspection, no ambiguity
- [x] FR-103: Testable via directory verification, mechanism clear
- [x] FR-301 to FR-304: Testable via different update methods, acceptance defined
- [x] FR-401: Testable via intentional download failures, validation steps specified
- [x] FR-402: Testable via simulated failures, rollback procedure defined
- [x] FR-403: Testable via error scenario matrix, message templates provided
- [x] FR-404: Testable via security audit, validation points enumerated

---

## Execution Status

- [x] User description parsed (from Feature 007 analysis)
- [x] Key concepts extracted (missing FRs, underspecified requirements)
- [x] Ambiguities clarified (6 clarifications in Session 2025-10-11)
- [x] User scenarios defined (6 scenarios + 5 edge cases)
- [x] Requirements generated (7 functional requirements: FR-102, FR-103, FR-301 to FR-303, FR-401 to FR-404)
- [x] Entities identified (4 key entities)
- [x] Review checklist passed

---

## Next Steps

1. ✅ Specification complete and ready for planning phase
2. ⏳ Run `/plan` command to create implementation plan
3. ⏳ Research WordPress Plugin_Upgrader integration patterns
4. ⏳ Research commercial plugin update mechanisms (reference implementations)
5. ⏳ Design plugins_api response structure
6. ⏳ Design backup/restore workflow
7. ⏳ Create comprehensive test plan for all update execution methods
8. ⏳ Implement in coordination with Feature 007 completion

---

**Status**: Ready for Planning Phase
**Priority**: CRITICAL (Blocks Feature 007 completion)
**Estimated Effort**: 5-8 days
**Risk Level**: MEDIUM (WordPress core API integration, well-documented but complex)
**Target Version**: v3.17.0 (together with Feature 007)
