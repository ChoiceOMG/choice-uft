# Implementation Plan: Update System Implementation Gaps

**Branch**: `008-fix-critical-gaps` | **Date**: 2025-10-11 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → ✅ Loaded successfully
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → ✅ All clarifications resolved in Session 2025-10-11
   → Project Type: WordPress Plugin (PHP + JavaScript)
   → Structure Decision: WordPress plugin structure (not web/mobile app)
3. Fill the Constitution Check section
   → ✅ Evaluated against constitution principles
4. Evaluate Constitution Check section
   → ✅ No violations - aligns with constitutional principles
   → Update Progress Tracking: Initial Constitution Check: PASS
5. Execute Phase 0 → research.md
   → ✅ Research WordPress Plugin_Upgrader patterns
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, CLAUDE.md
   → ✅ Generate design artifacts
7. Re-evaluate Constitution Check
   → ✅ Design aligns with constitution
   → Update Progress Tracking: Post-Design Constitution Check: PASS
8. Plan Phase 2 → Describe task generation approach
   → ✅ Ready for /tasks command
9. STOP - Ready for /tasks command
   → ✅ Plan complete
```

**IMPORTANT**: The /plan command STOPS at step 8. Phase 2 is executed by /tasks command.

## Summary

This feature completes the critical implementation gaps identified in Feature 007's update system. The specification was simplified during clarification to remove custom Settings page UI and focus on WordPress standard update methods only.

**Primary Requirement**: Implement missing update execution functionality (FR-301 to FR-303), plugin information modal (FR-102), directory naming fix (FR-103), and enhanced security/validation (FR-401 to FR-404).

**Technical Approach**: Hook into WordPress's native Plugin_Upgrader flow using filters (`plugins_api`, `upgrader_source_selection`) and proper WordPress Filesystem API. No custom download/install logic - rely entirely on WordPress core APIs for maximum compatibility and reliability.

## Technical Context

**Language/Version**: PHP 7.0+ (WordPress compatibility requirement)
**Primary Dependencies**:
- WordPress 5.0+ core APIs (Plugin_Upgrader, WP_Filesystem, WP_Ajax_Upgrader_Skin)
- WordPress HTTP API (for GitHub API requests)
- WordPress Transients API (for caching)
- WordPress Cron API (for scheduled cleanup)

**Storage**:
- WordPress site transients (update status, progress tracking)
- WordPress options table (plugin metadata)
- Filesystem: `/wp-content/uploads/cuft-backups/` (backup storage)
- Temporary directory for downloads (WordPress temp dir)

**Testing**:
- PHPUnit for unit tests
- WordPress test suite integration
- Manual testing via quickstart scenarios

**Target Platform**: WordPress 5.0+ on any hosting environment (shared, VPS, cloud)

**Project Type**: WordPress Plugin (PHP backend, no separate frontend)

**Performance Goals**:
- Update checks: <5 seconds (from Feature 007)
- Backup creation: <10 seconds
- Backup restoration: <10 seconds (hard timeout)
- ZIP validation: <2 seconds
- AJAX progress polling: <100ms response time

**Constraints**:
- Must work with WordPress's DISALLOW_FILE_MODS constant
- Must respect filesystem permissions (shared hosting restrictions)
- Must handle GitHub API rate limiting (60 requests/hour unauthenticated)
- Must work without WP-CLI (fallback to admin UI)
- No custom download/install logic (use WordPress core only)

**Scale/Scope**:
- Single plugin update system
- ~5-10 MB ZIP file downloads
- ~5-10 MB backup files
- GitHub API: 1-2 requests per update check
- Supports 10+ update methods (Plugins page, WP-CLI, bulk, etc.)

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Evaluation Against Constitution Principles

**Principle 1: JavaScript-First Compatibility** - ✅ NOT APPLICABLE
- This feature is WordPress admin/backend PHP only
- No form tracking JavaScript involved
- Progress polling uses standard WordPress AJAX patterns

**Principle 2: DataLayer Standardization** - ✅ NOT APPLICABLE
- This feature is not tracking-related
- No dataLayer events generated

**Principle 3: Framework Compatibility** - ✅ NOT APPLICABLE
- This feature is update system, not form framework
- No cross-framework interference concerns

**Principle 4: Event Firing Rules** - ✅ NOT APPLICABLE
- This feature does not generate form_submit or generate_lead events

**Principle 5: Error Handling Philosophy** - ✅ ALIGNED
- FR-402: Automatic rollback with 10s timeout (graceful degradation)
- FR-401: Multiple validation layers (fallback chains: size check → ZIP validation → extraction)
- FR-403: Clear error messages with recovery actions (error isolation)
- All file operations wrapped in WordPress error handling (try-catch equivalent)

**Principle 6: Testing Requirements** - ✅ ALIGNED
- Quickstart.md will provide manual test scenarios (production flow testing)
- Each FR has acceptance criteria (universal test coverage)
- Integration tests verify all update methods (cross-method validation)

**Principle 7: Performance Constraints** - ✅ ALIGNED
- Update checks: <5s (Feature 007 requirement)
- Backup/restore: <10s each (specified in FR-402)
- ZIP validation: <2s (lightweight operation)
- All operations within acceptable WordPress admin performance

**Principle 8: Security Principles** - ✅ ALIGNED
- FR-404: Nonce validation on all update actions
- FR-404: Capability checks (`update_plugins`)
- FR-404: URL validation (GitHub CDN only)
- FR-404: WordPress Filesystem API (no direct file ops)
- FR-403: No server paths in non-admin error messages (PII protection)

**Conclusion**: ✅ **PASS** - All applicable constitutional principles satisfied. No violations to document in Complexity Tracking.

## Project Structure

### Documentation (this feature)
```
specs/008-fix-critical-gaps/
├── spec.md              # Feature specification
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
│   ├── plugins-api-filter.md
│   ├── upgrader-source-selection-filter.md
│   ├── backup-restore-workflow.md
│   └── download-validation.md
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (WordPress plugin structure)
```
choice-uft/
├── includes/
│   ├── update/
│   │   ├── class-cuft-plugin-info.php        # FR-102: plugins_api filter
│   │   ├── class-cuft-directory-fixer.php    # FR-103: upgrader_source_selection
│   │   ├── class-cuft-update-validator.php   # FR-401: Download validation
│   │   ├── class-cuft-backup-manager.php     # FR-402: Backup/restore
│   │   └── class-cuft-update-security.php    # FR-404: Security validation
│   └── ajax/
│       └── class-cuft-update-progress.php    # Progress polling endpoint
├── assets/admin/js/
│   └── cuft-update-progress.js               # Progress polling client (if needed)
└── tests/
    ├── unit/
    │   ├── test-plugin-info.php
    │   ├── test-directory-fixer.php
    │   ├── test-update-validator.php
    │   ├── test-backup-manager.php
    │   └── test-update-security.php
    └── integration/
        ├── test-plugins-page-update.php
        ├── test-wp-cli-update.php
        ├── test-bulk-update.php
        └── test-rollback-workflow.php
```

**Structure Decision**: WordPress Plugin structure (standard WP conventions, not separate frontend/backend)

## Phase 0: Outline & Research

### Research Tasks

**R1: WordPress Plugin_Upgrader Integration Patterns**
- Objective: Understand how to properly hook into WordPress's native update flow
- Research Areas:
  - `plugins_api` filter usage and response format
  - `upgrader_source_selection` filter for directory renaming
  - Plugin_Upgrader class lifecycle and hooks
  - WP_Ajax_Upgrader_Skin for progress display
  - WordPress error handling with WP_Error
- Deliverable: Document standard patterns and anti-patterns

**R2: WordPress Filesystem API Best Practices**
- Objective: Understand safe file operations for backup/restore
- Research Areas:
  - WP_Filesystem initialization and credentials
  - ZIP creation/extraction methods
  - Permission checking before file operations
  - Handling DISALLOW_FILE_MODS constant
  - Temporary file management
- Deliverable: Filesystem operation patterns and error handling

**R3: GitHub API Integration for Plugin Metadata**
- Objective: Fetch release information and changelog reliably
- Research Areas:
  - GitHub Releases API endpoints
  - Rate limiting (60 req/hour unauthenticated)
  - Caching strategies for plugin metadata
  - Graceful degradation when API unavailable
  - Release asset URL patterns
- Deliverable: GitHub API integration pattern with caching

**R4: WordPress Update System Architecture**
- Objective: Understand how commercial plugins implement updates
- Research Areas:
  - Reference implementations (ACF Pro, Gravity Forms, GitHub Updater)
  - Update transient structure
  - Update checker scheduling
  - WP-CLI integration points
  - Bulk update handling
- Deliverable: Commercial plugin update patterns

**R5: Backup and Rollback Strategies**
- Objective: Implement reliable backup/restore mechanism
- Research Areas:
  - WordPress backup plugins (UpdraftPlus, BackWPup)
  - ZIP file integrity verification
  - Atomic filesystem operations
  - Recovery from partial failures
  - Timeout handling in WordPress
- Deliverable: Backup/restore architecture and timeout patterns

**Output**: `research.md` with consolidated findings and decisions

## Phase 1: Design & Contracts

### 1. Data Model Extraction

**Entities to document in `data-model.md`**:

**Plugin Metadata (plugins_api response)**
- Fields: name, slug, version, author, author_profile, homepage, requires, tested, requires_php, download_link, last_updated, sections (description, changelog, installation), banners, icons
- Source: GitHub API + hardcoded plugin info
- Validation: Version format (semver), URL format (HTTPS), date format (ISO 8601)
- Caching: WordPress transient, 1-hour TTL
- Relationships: None (read-only DTO)

**Download Package (validation state)**
- Fields: source_url, expected_size, actual_size, validation_status, validation_errors, local_path, file_hash
- Source: GitHub CDN download + validation
- Validation: File size (±5% tolerance), ZIP format validation
- Lifecycle: Created → Validated → Deleted (immediate on failure, delayed on success)
- Relationships: None (transient state)

**Backup Archive (filesystem + metadata)**
- Fields: source_version, backup_path, created_date, file_size, backup_status
- Source: Plugin directory ZIP compression
- Validation: File size > 0, ZIP integrity, write permissions
- Lifecycle: Created (before update) → Restored (on failure) | Deleted (on success)
- State transitions: created → restored | deleted
- Relationships: None (filesystem artifact)

**Update Execution Context (transient tracking)**
- Fields: trigger_location, user_id, user_display_name, started_timestamp, completed_timestamp, duration, previous_version, target_version, status, error_message, progress_percentage
- Source: WordPress user context + update progress
- Validation: User has update_plugins capability
- State transitions: pending → downloading → extracting → installing → complete | failed | rolled_back
- Relationships: Links to WordPress user (user_id)

### 2. API Contracts Generation

**Contract Files to create in `contracts/` directory**:

**`plugins-api-filter.md`** (FR-102)
- Hook: `plugins_api` filter
- Input: $res (false), $action ('plugin_information'), $args (object with slug)
- Output: Object with plugin metadata OR false (pass-through)
- Success: Returns complete plugin metadata object
- Failure: Returns false (WordPress shows default modal)
- Edge Cases: GitHub API unavailable (omit changelog section)

**`upgrader-source-selection-filter.md`** (FR-103)
- Hook: `upgrader_source_selection` filter
- Input: $source (extracted directory path), $remote_source (download path), $upgrader (Plugin_Upgrader instance), $hook_extra (array with plugin basename)
- Output: String (corrected directory path) OR WP_Error (on failure)
- Success: Returns `/path/to/choice-uft/` (without version)
- Failure: Returns WP_Error with message
- Edge Cases: Non-CUFT plugin (pass-through), unrecognized structure (error)

**`backup-restore-workflow.md`** (FR-402)
- Operations: create_backup(), restore_backup(), delete_backup()
- create_backup(): Input (current version), Output (backup path OR WP_Error)
- restore_backup(): Input (backup path), Output (success boolean OR WP_Error), Timeout (10s hard limit)
- delete_backup(): Input (backup path), Output (success boolean)
- Edge Cases: Disk space insufficient, permissions denied, backup corrupted, timeout exceeded

**`download-validation.md`** (FR-401)
- Operations: validate_file_size(), validate_zip_format(), cleanup_invalid_download()
- validate_file_size(): Input (file path, expected size), Output (boolean), Tolerance (±5%)
- validate_zip_format(): Input (file path), Output (boolean), Method (WordPress ZIP validation)
- cleanup_invalid_download(): Input (file path), Output (success boolean), Triggers (immediate + daily cron)

### 3. Contract Tests Generation

*Tests will be created during task execution phase - documented here for reference*

**Test Files**:
- `tests/unit/test-plugin-info.php` - plugins_api filter response format
- `tests/unit/test-directory-fixer.php` - directory renaming logic
- `tests/unit/test-update-validator.php` - file validation logic
- `tests/unit/test-backup-manager.php` - backup/restore operations
- `tests/integration/test-full-update-flow.php` - end-to-end update workflow

### 4. Quickstart Test Scenarios

**Quickstart scenarios extracted from spec User Scenarios**:

**QS-1: Plugin Information Modal** (Scenario 1)
- Navigate to Plugins page
- Verify update available
- Click "View Details"
- Verify modal shows: name, author, versions, changelog, compatibility, file size, last updated
- Verify "Update Now" button present

**QS-2: Update from Plugins Page** (Scenario 2)
- Navigate to Plugins page
- Click "Update Now" button
- Verify progress messages
- Verify success message
- Verify plugin version updated

**QS-3: Update via WP-CLI** (Scenario 3)
- SSH to server
- Run `wp plugin update choice-uft`
- Verify progress output
- Verify exit code 0
- Verify version updated

**QS-4: Bulk Update** (Scenario 4)
- Navigate to Dashboard → Updates
- Select CUFT + WordPress core
- Click "Update Plugins"
- Verify both updates succeed
- Verify no interference

**QS-5: Download Validation** (Scenario 5)
- Simulate partial download (network interruption)
- Verify size mismatch detected
- Verify error message shown
- Verify partial file deleted
- Verify retry available

**QS-6: Automatic Rollback** (Scenario 6)
- Simulate corrupted ZIP extraction
- Verify backup created
- Verify restoration triggered
- Verify previous version restored
- Verify error message shown

**QS-7: Directory Naming** (Scenario 7)
- Trigger update (any method)
- Verify directory renamed from `choice-uft-v3.17.0` to `choice-uft`
- Verify WordPress installs to correct location
- Verify no errors

**Edge Case Tests**:
- EC-1: Backup directory not writable
- EC-2: Disk space insufficient
- EC-3: Backup restoration fails
- EC-4: Unexpected ZIP structure
- EC-5: Concurrent updates (WP-CLI vs Admin)

### 5. Update Agent Context

**Execute incremental agent context update**:
```bash
.specify/scripts/bash/update-agent-context.sh claude
```

This will update `/home/r11/dev/choice-uft/CLAUDE.md` with:
- New technical context: WordPress Plugin_Upgrader integration, backup/restore patterns
- New feature: Feature 008 - Update System Implementation Gaps
- Recent changes: Added update execution, plugin info modal, directory naming fix, backup/restore
- Preserved manual sections between markers
- Kept under 150 lines

**Output**: Phase 1 complete with all design artifacts generated

## Feature 007 Integration & Migration Strategy
*CRITICAL: Clarification of what to keep, remove, and audit from Feature 007*

### Background: The Conflicting Systems Problem

Feature 007 (v3.16.3) addressed "Fix Update System Inconsistencies" and provided:
- Update Status model (site transients)
- Update Progress tracking
- Update Log (FIFO, last 5 updates)
- Admin notices and admin bar integration
- AJAX endpoints with nonce validation
- Context-aware caching

**However**, the spec analysis (lines 247-292) indicates:
> "Spec describes building custom update logic (v3.16.3) that was removed, but new approach lacks constitutional validation"

This suggests Feature 007 **may have implemented custom download/install logic** that conflicts with WordPress's native Plugin_Upgrader system.

### Clear Delineation: Keep vs. Remove

#### ✅ KEEP from Feature 007 (Minimal Status Tracking)

These components are **display/monitoring only** and do NOT interfere with WordPress's update execution:

1. **Update Status Display** (site transients)
   - Purpose: Show "Update Available" messaging
   - Location: Likely `includes/class-cuft-update-checker.php` or similar
   - Action: KEEP - This just displays status, doesn't execute updates

2. **Admin Notices** (MODIFIED behavior)
   - Purpose: Show update available notices
   - Location: `admin_notices` hook
   - Action: KEEP with changes:
     - Must be dismissible (X button)
     - Must link to Plugins page (`/wp-admin/plugins.php`), NOT settings page
     - Must store dismissal state per version (don't show again for same version)
     - Show standard message: "There is a new version of Choice Universal Form Tracker available."
     - Button text: "View Plugin Updates" (links to Plugins page)

3. **Update History Log (FIFO)**
   - Purpose: Track update attempts/results
   - Location: Likely `includes/class-cuft-update-log.php` or options storage
   - Action: KEEP - Logging only, add hook integration for Feature 008
   - Note: May need to move display to Dashboard or separate admin page (not Settings page Updates tab)

4. **Nonce Validation Infrastructure**
   - Purpose: Secure AJAX endpoints
   - Location: AJAX handler classes
   - Action: KEEP - Security infrastructure is reusable (if needed for progress polling)

5. **Context-Aware Caching**
   - Purpose: Cache update check results
   - Location: Transient management code
   - Action: KEEP - Performance optimization for checks

#### ❌ REMOVE from Feature 007 (Custom Update Execution & UI)

These components **bypass WordPress's Plugin_Upgrader** or **duplicate WordPress's native UI** and must be removed:

**Custom Update Execution** (conflicts with WordPress Plugin_Upgrader):

1. **Custom Download Logic** (if exists)
   - Anti-pattern: Direct HTTP download of ZIP files
   - Location: Any code using `wp_remote_get()` or `file_get_contents()` for plugin ZIP
   - Action: REMOVE - WordPress Plugin_Upgrader handles downloads

2. **Custom Install Logic** (if exists)
   - Anti-pattern: Direct ZIP extraction, file copying
   - Location: Any code using `ZipArchive`, `unzip`, or direct file operations for plugin install
   - Action: REMOVE - WordPress Plugin_Upgrader handles installation

3. **Custom "Update Now" Button Handler** (if exists)
   - Anti-pattern: AJAX endpoint that executes full update process
   - Location: AJAX action like `cuft_execute_update` that calls custom download/install
   - Action: REMOVE - WordPress's native "Update Now" button should be used

4. **Custom WP-CLI Update Command** (if exists)
   - Anti-pattern: Custom WP-CLI command `wp cuft update`
   - Location: WP-CLI command registration
   - Action: REMOVE - Standard `wp plugin update choice-uft` should work

5. **Update Progress AJAX Endpoint** (audit carefully)
   - Potential issue: If this endpoint **triggers** updates, remove it
   - Acceptable use: If this endpoint only **reports** update status, keep it
   - Location: AJAX action like `cuft_update_progress`
   - Action: AUDIT - Remove if it triggers updates, keep if status-only

**Custom Update UI** (duplicates WordPress's native UI, violates WordPress conventions):

6. **Admin Bar Integration** (REMOVE ENTIRELY)
   - Anti-pattern: Custom "CUFT Update" menu item in admin bar
   - Location: `admin_bar_menu` hook registration
   - Action: REMOVE - WordPress Plugins page is the standard location for update management
   - Rationale: Admin bar should only show critical notifications, not routine update indicators

7. **Plugin Settings Page - Updates Tab** (REMOVE ENTIRELY)
   - Anti-pattern: Custom update UI in plugin's settings page (`Settings → Universal Form Tracker → Updates`)
   - Location: Settings page tab registration, tab content rendering
   - Action: REMOVE - Includes:
     - "Update Now" button
     - Update progress UI
     - Update status display
     - All AJAX handlers for settings page updates
   - Rationale: WordPress convention is to manage updates via Plugins page only

8. **Plugin Settings Page - GitHub Auto-Updates Section** (REMOVE ENTIRELY)
   - Anti-pattern: Custom auto-update toggle in plugin settings
   - Location: Settings page section, option storage for auto-update preference
   - Action: REMOVE - WordPress 5.5+ provides standard auto-update toggle on Plugins page
   - Rationale: Auto-update preferences should be controlled via WordPress's native per-plugin toggle

### Audit Strategy: Finding Conflicting Code

**Files to audit** (likely locations of custom update logic):

```
Priority 1 (most likely to contain custom logic):
- includes/class-cuft-updater.php          # If exists, likely custom
- includes/ajax/class-cuft-update-ajax.php # Check AJAX actions
- includes/class-cuft-plugin-updater.php   # If exists, likely custom
- includes/update/*.php                     # Any existing update classes

Priority 2 (may contain hooks/triggers):
- choice-universal-form-tracker.php        # Main plugin file
- includes/class-cuft-admin.php            # Admin integration
- includes/class-cuft-init.php             # Initialization

Priority 3 (less likely but check):
- assets/admin/js/cuft-update.js           # Frontend update triggers
- includes/cli/class-cuft-cli.php          # Custom WP-CLI commands
```

**Code patterns to search for** (anti-patterns indicating custom logic and UI):

```bash
# Search for custom download logic
grep -r "wp_remote_get.*\.zip" includes/
grep -r "file_get_contents.*github.*releases" includes/
grep -r "download_url.*plugin" includes/

# Search for custom install logic
grep -r "ZipArchive" includes/
grep -r "unzip_file" includes/  # OK if in backup/restore context
grep -r "copy.*wp-content/plugins" includes/
grep -r "WP_Filesystem.*move.*plugins" includes/

# Search for custom update triggers
grep -r "wp_ajax.*update" includes/
grep -r "function.*execute_update" includes/
grep -r "Plugin_Upgrader" includes/  # OK if just observing, bad if calling directly

# Search for WP-CLI custom commands
grep -r "WP_CLI::add_command.*update" includes/

# Search for custom UI components (to be removed)
grep -r "admin_bar_menu" includes/
grep -r "'Updates'" includes/  # Settings page tab
grep -r "\"Updates\"" includes/
grep -r "cuft.*update.*tab" includes/
grep -r "GitHub Auto-Updates" includes/
grep -r "auto.*update.*toggle" includes/
grep -r "cuft-update-progress" assets/
grep -r "cuft.*update.*css" assets/
```

**Safe patterns** (these are OK, indicate proper integration):

```php
// ✅ SAFE: Observing WordPress's update process
add_filter( 'upgrader_post_install', ... );
add_action( 'upgrader_process_complete', ... );

// ✅ SAFE: Providing plugin metadata
add_filter( 'plugins_api', ... );
add_filter( 'site_transient_update_plugins', ... );

// ✅ SAFE: Status display only (with modified behavior)
add_action( 'admin_notices', ... );  // Dismissible per version, links to Plugins page

// ✅ SAFE: Logging only
add_action( 'upgrader_process_complete', 'log_update_result' );
```

**NOTE**: Previously `add_action( 'admin_bar_menu', ... )` was considered safe for display, but per WordPress conventions and Feature 008 clarifications, admin bar integration for routine update indicators is now considered an anti-pattern and should be removed.

**Dangerous patterns** (these indicate custom execution):

```php
// ❌ DANGEROUS: Custom download
$zip = wp_remote_get( $github_url );
file_put_contents( $temp_file, $zip );

// ❌ DANGEROUS: Custom extraction
$zip = new ZipArchive();
$zip->extractTo( WP_PLUGIN_DIR );

// ❌ DANGEROUS: Direct file operations
copy( $temp_dir, WP_PLUGIN_DIR . '/choice-uft' );

// ❌ DANGEROUS: Custom update trigger via AJAX
function execute_update_ajax() {
    // Downloads and installs plugin
    $this->download_plugin();
    $this->install_plugin();
}
add_action( 'wp_ajax_cuft_update_now', 'execute_update_ajax' );
```

### Migration Tasks (to be added to tasks.md)

**Pre-Implementation Audit Tasks** (add as T000 series, before FR implementation):

```
T000: Audit Feature 007 for custom update execution logic and UI [P]
- Search codebase for anti-patterns (download, install, ZipArchive)
- Search for admin bar integration code
- Search for Settings page Updates tab code
- Search for GitHub Auto-Updates section code
- Document all custom update code and UI components found
- Create removal plan for conflicting components
- Estimated: 2-3 hours

T000a: Remove custom download/install logic from Feature 007
- Remove any custom download functions
- Remove any custom install/extraction functions
- Remove AJAX endpoints that trigger updates (keep status-only endpoints)
- Remove custom WP-CLI update commands
- Keep status display and logging components
- Estimated: 3-4 hours

T000b: Remove custom update UI from Feature 007 (WordPress convention alignment)
- Remove admin bar "CUFT Update" menu item (`admin_bar_menu` hook)
- Remove Settings page "Updates" tab (tab registration and content)
- Remove Settings page "GitHub Auto-Updates" section
- Remove all AJAX handlers for Settings page update triggers
- Remove related JavaScript files (cuft-update-progress.js, etc.)
- Remove related CSS for update UI
- Estimated: 2-3 hours

T000c: Modify admin notice behavior (align with WordPress conventions)
- Update admin notice to be dismissible per version
- Change link from Settings page to Plugins page (`/wp-admin/plugins.php`)
- Update message text to standard WordPress format
- Implement version-specific dismissal state storage
- Test notice appears for new versions after dismissing older version
- Estimated: 1-2 hours

T000d: Test WordPress native update flow without Feature 007 interference
- Verify `wp plugin update choice-uft` works
- Verify Plugins page "Update Now" button works
- Verify bulk updates work
- Verify no custom code is intercepting updates
- Verify admin bar no longer shows update indicator
- Verify Settings page no longer has Updates tab
- Verify WordPress auto-update toggle works (Plugins page)
- Estimated: 1-2 hours
```

### Integration Points: Feature 007 ↔ Feature 008

**How Feature 008 will use Feature 007's retained components**:

| Feature 007 Component | Feature 008 Usage |
|-----------------------|-------------------|
| Update Status (transients) | Feature 008 reads this to determine if update available (display only) |
| Update History Log | Feature 008 writes to this after update completes (via `upgrader_process_complete` hook) |
| Admin Notices | Feature 008 may add notices for validation failures, rollback messages |
| Nonce Infrastructure | Feature 008 reuses nonces for AJAX progress polling (if implemented) |
| Context-Aware Caching | Feature 008 respects cache timeouts for GitHub API requests |

**What Feature 008 replaces entirely**:

| Replaced Component | Feature 008 Replacement |
|--------------------|-------------------------|
| Custom download logic | WordPress Plugin_Upgrader handles downloads |
| Custom install logic | WordPress Plugin_Upgrader handles installation |
| Custom "Update Now" AJAX | WordPress native "Update Now" button |
| Custom update triggers | WordPress hooks (`plugins_api`, `upgrader_source_selection`) |
| Custom WP-CLI command | Standard `wp plugin update choice-uft` |

### Success Criteria for Migration

**Before Feature 008 implementation can begin**, verify:

- [ ] Feature 007 audit complete (T000)
- [ ] All custom download/install code removed (T000a)
- [ ] All custom update UI removed (T000b)
- [ ] Admin notice behavior updated (T000c)
- [ ] WordPress native update flow tested and working (T000d)
- [ ] Feature 007 components documented as keep/remove
- [ ] No AJAX endpoints that trigger updates remain
- [ ] No custom WP-CLI update commands remain
- [ ] Admin bar no longer has update menu item
- [ ] Settings page no longer has Updates tab
- [ ] Settings page no longer has GitHub Auto-Updates section

**Definition of "Clean Foundation"**:

Feature 007 should provide **only**:
1. Update check scheduling (transients)
2. Update history logging (storage)
3. Admin notifications (dismissible, version-specific, links to Plugins page)

Feature 007 should **NOT** provide:
1. Update execution
2. Download handling
3. ZIP extraction
4. File installation
5. Custom update buttons beyond WordPress standard
6. Admin bar update indicators
7. Settings page update UI
8. Custom auto-update toggles

### Risk Mitigation

**Risk**: Feature 007 contains deeply integrated custom logic that's difficult to remove

**Mitigation**:
1. T000 audit task identifies scope of removal
2. Create Feature 007 rollback branch before removal
3. Test suite validates WordPress native flow works
4. Phased removal: Remove most dangerous code first (download/install), then cleanup UI triggers

**Risk**: Removing Feature 007 code breaks update status display

**Mitigation**:
1. Keep all UI/display components
2. Only remove execution logic
3. Test update availability detection still works after removal
4. Feature 008 hooks maintain Feature 007 logging integration

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:

1. **Load contracts and data model** from Phase 1
2. **Generate tasks in TDD order**:
   - Contract test tasks first [P]
   - Implementation tasks to satisfy contracts
   - Integration test tasks last

3. **Task breakdown by FR**:

   **FR-102: Plugin Information Modal** (~5 tasks)
   - T001: Create plugin-info contract test (plugins_api filter response) [P]
   - T002: Implement CUFT_Plugin_Info class with plugins_api filter hook
   - T003: Implement GitHub API changelog fetcher with caching
   - T004: Implement graceful degradation (omit changelog on API failure)
   - T005: Integration test: Verify modal displays complete info

   **FR-103: Directory Naming Fix** (~4 tasks)
   - T006: Create directory-fixer contract test (upgrader_source_selection filter) [P]
   - T007: Implement CUFT_Directory_Fixer class with filter hook
   - T008: Implement directory name detection and rename logic
   - T009: Integration test: Verify directory renamed correctly

   **FR-301: Plugins Page Update** (~3 tasks)
   - T010: Verify WordPress Plugin_Upgrader integration (no custom code needed)
   - T011: Implement update history logging hook
   - T012: Integration test: Update from Plugins page

   **FR-302: WP-CLI Update** (~2 tasks)
   - T013: Verify WP-CLI integration (no custom code needed)
   - T014: Integration test: Update via WP-CLI

   **FR-303: Bulk Update** (~2 tasks)
   - T015: Verify bulk update compatibility (no custom code needed)
   - T016: Integration test: Bulk update with other plugins

   **FR-401: Download Validation** (~6 tasks)
   - T017: Create validation contract test (file size + ZIP format) [P]
   - T018: Implement CUFT_Update_Validator class
   - T019: Implement file size validation (±5% tolerance)
   - T020: Implement ZIP format validation (WordPress methods)
   - T021: Implement immediate cleanup on validation failure
   - T022: Implement daily cron job for orphaned file cleanup

   **FR-402: Backup/Restore** (~8 tasks)
   - T023: Create backup-manager contract test (create/restore/delete) [P]
   - T024: Implement CUFT_Backup_Manager class
   - T025: Implement backup creation with WordPress ZIP methods
   - T026: Implement pre-update backup hook integration
   - T027: Implement restore on update failure with 10s timeout
   - T028: Implement timeout abort with manual reinstall message
   - T029: Implement post-success backup deletion
   - T030: Integration test: Full backup/restore workflow

   **FR-403: Error Messages** (~2 tasks)
   - T031: Implement error message templates (all scenarios from spec)
   - T032: Implement error message logging to update history

   **FR-404: Security Validation** (~5 tasks)
   - T033: Implement nonce validation wrapper
   - T034: Implement capability check wrapper
   - T035: Implement URL validation (GitHub CDN only)
   - T036: Implement DISALLOW_FILE_MODS check
   - T037: Implement filesystem permission check

   **Integration & Documentation** (~3 tasks)
   - T038: Create quickstart.md test scenarios
   - T039: Run full quickstart validation
   - T040: Update CLAUDE.md with feature completion

**Ordering Strategy**:
- Migration tasks first (T000 series - audit and cleanup Feature 007)
- Contract tests next (can run in parallel [P])
- Implementation tasks follow contracts (sequential per FR)
- Integration tests after implementation (validate full flows)
- Dependencies respected: FR-402 depends on FR-401 (validation before backup)

**Estimated Output**: ~45 numbered, ordered tasks in tasks.md
- T000 series (migration): 5 tasks (T000, T000a, T000b, T000c, T000d)
- FR implementation: 40 tasks (T001-T040)

**Parallel Markers**:
- [P] on T000 audit task (can run concurrently with planning)
- [P] on contract test tasks (T002, T003, T004, T005)
- [P] on independent implementation tasks where no shared state

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

**No violations detected** - Constitution Check passed for all applicable principles.

This feature aligns naturally with the project's constitutional principles:
- Error handling follows graceful degradation patterns (Principle 5)
- Testing approach follows production flow requirements (Principle 6)
- Performance targets are within WordPress admin norms (Principle 7)
- Security validation uses WordPress standards (Principle 8)

**No complexity deviations to document.**

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved (5 clarifications in Session 2025-10-11)
- [x] Complexity deviations documented (N/A - no deviations)

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
*Feature 008 ready for /tasks command*
