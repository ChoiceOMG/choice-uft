# Tasks: Update System Implementation Gaps (Feature 008)

**Input**: Design documents from `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/, quickstart.md
**Branch**: `008-fix-critical-gaps`

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → ✅ Loaded - WordPress Plugin (PHP 7.0+), WordPress 5.0+ APIs
   → Tech stack: Plugin_Upgrader, WP_Filesystem, GitHub API, PHPUnit
2. Load optional design documents:
   → ✅ data-model.md: 4 entities (Plugin Metadata, Download Package, Backup Archive, Update Execution Context)
   → ✅ contracts/: 4 files (plugins-api-filter, upgrader-source-selection, backup-restore-workflow, download-validation)
   → ✅ research.md: WordPress update patterns, filesystem API, GitHub integration
   → ✅ quickstart.md: 7 scenarios + 5 edge cases
3. Generate tasks by category:
   → Migration: Feature 007 audit and cleanup (T000 series)
   → Tests: Contract tests for 4 filters/workflows [P]
   → Core: 5 implementation classes (Plugin Info, Directory Fixer, Validator, Backup Manager, Security)
   → Integration: WordPress hooks, update history logging, cron jobs
   → Polish: Integration tests, quickstart validation, documentation
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Migration before tests (clean foundation)
   → Tests before implementation (TDD)
5. Number tasks sequentially (T000, T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
8. Validate task completeness:
   → ✅ All contracts have tests
   → ✅ All entities have implementations
   → ✅ All FRs have integration tests
9. Return: SUCCESS (45 tasks ready for execution)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions
- Migration tasks (T000 series) must complete before FR implementation

## Path Conventions
- **WordPress Plugin**: `includes/`, `assets/`, `tests/` at repository root
- Plugin structure: `includes/update/` for update system classes
- Tests: `tests/unit/` and `tests/integration/` for PHPUnit tests
- Assets: `assets/admin/js/` for admin JavaScript (if needed)

---

## Phase 3.0: Feature 007 Migration (CRITICAL - Must Complete First)
**GATE**: These tasks establish a clean foundation. Feature 008 implementation cannot begin until all are complete.

- [x] **T000** [P] Audit Feature 007 for custom update execution logic and UI
  - Search codebase for anti-patterns using grep commands from plan.md lines 527-554
  - Identify custom download logic (`wp_remote_get.*\.zip`, `file_get_contents.*github`)
  - Identify custom install logic (`ZipArchive`, `unzip_file`, direct file operations)
  - Identify custom update triggers (`wp_ajax.*update`, custom WP-CLI commands)
  - Identify custom UI components (`admin_bar_menu`, Settings page "Updates" tab, "GitHub Auto-Updates" section)
  - Document all findings in `/home/r11/dev/choice-uft/specs/007-fix-update-system/AUDIT-RESULTS.md`
  - Create removal plan listing files to modify and code to remove
  - **Estimated**: 2-3 hours
  - **Success Criteria**: Complete inventory of conflicting code with line numbers and file paths
  - **COMPLETED**: Audit results documented with 7 files to delete, 2 to modify, detailed removal plan

- [x] **T000a** Remove custom download/install logic from Feature 007
  - Remove any custom download functions identified in T000 audit
  - Remove any custom install/extraction functions
  - Remove AJAX endpoints that trigger updates (keep status-only endpoints)
  - Remove custom WP-CLI update commands (e.g., `wp cuft update`)
  - Keep status display and logging components
  - Commit changes with message: "fix: Remove custom update execution logic (Feature 007 cleanup)"
  - **Depends on**: T000
  - **Estimated**: 3-4 hours
  - **Success Criteria**: No custom download/install code remains, WordPress Plugin_Upgrader handles all updates
  - **COMPLETED**: Deleted installer, admin bar classes; removed perform_update/rollback_update endpoints; commit 1bf5361

- [ ] **T000b** Remove custom update UI from Feature 007 (WordPress convention alignment)
  - Remove admin bar "CUFT Update" menu item (`admin_bar_menu` hook registration)
  - Remove Settings page "Updates" tab (tab registration and content rendering)
  - Remove Settings page "GitHub Auto-Updates" section
  - Remove all AJAX handlers for Settings page update triggers
  - Remove related JavaScript files (e.g., `assets/admin/js/cuft-update-progress.js`)
  - Remove related CSS for update UI (e.g., `assets/admin/css/cuft-update.css`)
  - Commit changes with message: "fix: Remove custom update UI, align with WordPress conventions (Feature 007 cleanup)"
  - **Depends on**: T000
  - **Estimated**: 2-3 hours
  - **Success Criteria**: No admin bar indicator, no Settings page update UI, WordPress Plugins page is sole update interface

- [ ] **T000c** Modify admin notice behavior (align with WordPress conventions)
  - Update admin notice to be dismissible per version (add dismiss button)
  - Change link from Settings page to Plugins page (`/wp-admin/plugins.php`)
  - Update message text to: "There is a new version of Choice Universal Form Tracker available."
  - Update button text to: "View Plugin Updates" (links to Plugins page)
  - Implement version-specific dismissal state storage (user meta: `cuft_notice_dismissed_v{VERSION}`)
  - Test notice appears for new versions after dismissing older version
  - Commit changes with message: "fix: Update admin notice behavior to WordPress standards (Feature 007 cleanup)"
  - **Depends on**: T000
  - **Estimated**: 1-2 hours
  - **Success Criteria**: Notice is dismissible, links to Plugins page, respects per-version dismissal

- [ ] **T000d** Test WordPress native update flow without Feature 007 interference
  - Verify `wp plugin update choice-uft` works correctly
  - Verify Plugins page "Update Now" button works correctly
  - Verify bulk updates work (Dashboard → Updates)
  - Verify no custom code is intercepting updates
  - Verify admin bar no longer shows update indicator
  - Verify Settings page no longer has Updates tab
  - Verify WordPress auto-update toggle works (Plugins page)
  - Document test results in `/home/r11/dev/choice-uft/specs/007-fix-update-system/MIGRATION-TEST-RESULTS.md`
  - **Depends on**: T000a, T000b, T000c
  - **Estimated**: 1-2 hours
  - **Success Criteria**: All update methods work via WordPress native flow, no Feature 007 interference

---

## Phase 3.1: Setup
- [ ] **T001** Create update system directory structure
  - Create `/home/r11/dev/choice-uft/includes/update/` directory
  - Create `/home/r11/dev/choice-uft/tests/unit/update/` directory
  - Create `/home/r11/dev/choice-uft/tests/integration/update/` directory
  - Verify WordPress Filesystem API is available
  - **Depends on**: T000d (migration complete)
  - **Estimated**: 15 minutes

---

## Phase 3.2: Tests First (TDD) ⚠️ MUST COMPLETE BEFORE 3.3
**CRITICAL: These tests MUST be written and MUST FAIL before ANY implementation**

- [ ] **T002** [P] Contract test: plugins_api filter (Plugin Metadata) in `/home/r11/dev/choice-uft/tests/unit/update/test-plugin-info-contract.php`
  - Test `plugins_api` filter returns complete Plugin Metadata object
  - Test returns false for non-CUFT plugins (pass-through)
  - Test changelog section omitted when GitHub API unavailable
  - Test all required fields present: name, slug, version, author, requires, tested, download_link
  - Verify response structure matches WordPress's `plugins_api` expected format
  - **Reference**: `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/contracts/plugins-api-filter.md`
  - **Estimated**: 1 hour

- [ ] **T003** [P] Contract test: upgrader_source_selection filter in `/home/r11/dev/choice-uft/tests/unit/update/test-directory-fixer-contract.php`
  - Test directory renamed from `choice-uft-v3.17.0` to `choice-uft`
  - Test returns WP_Error when source directory not found
  - Test pass-through for non-CUFT plugins
  - Test WP_Error when rename operation fails
  - **Reference**: `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/contracts/upgrader-source-selection-filter.md`
  - **Estimated**: 1 hour

- [ ] **T004** [P] Contract test: backup/restore workflow in `/home/r11/dev/choice-uft/tests/unit/update/test-backup-manager-contract.php`
  - Test `create_backup()` creates ZIP in `/wp-content/uploads/cuft-backups/`
  - Test `restore_backup()` extracts to correct location within 10s timeout
  - Test `delete_backup()` removes backup file
  - Test WP_Error on disk space insufficient
  - Test WP_Error on permissions denied
  - Test timeout abort at 10 seconds with manual reinstall message
  - **Reference**: `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/contracts/backup-restore-workflow.md`
  - **Estimated**: 1.5 hours

- [ ] **T005** [P] Contract test: download validation in `/home/r11/dev/choice-uft/tests/unit/update/test-update-validator-contract.php`
  - Test `validate_file_size()` with ±5% tolerance
  - Test `validate_zip_format()` using WordPress ZIP validation
  - Test `cleanup_invalid_download()` immediate deletion after failure
  - Test scheduled daily cleanup via WordPress cron
  - **Reference**: `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/contracts/download-validation.md`
  - **Estimated**: 1 hour

- [ ] **T006** [P] Integration test: Plugin information modal in `/home/r11/dev/choice-uft/tests/integration/update/test-plugins-page-modal.php`
  - Test modal displays when clicking "View Details" on Plugins page
  - Test all plugin metadata present (name, author, versions, changelog, compatibility)
  - Test "Update Now" button present and functional
  - Test graceful degradation when GitHub API fails (no changelog section)
  - **Reference**: quickstart.md QS-1
  - **Estimated**: 1 hour

- [ ] **T007** [P] Integration test: Directory naming correction in `/home/r11/dev/choice-uft/tests/integration/update/test-directory-naming.php`
  - Test directory renamed from GitHub format to WordPress format during update
  - Test WordPress installs to `/wp-content/plugins/choice-uft/`
  - Test no "directory mismatch" errors occur
  - **Reference**: quickstart.md QS-7
  - **Estimated**: 45 minutes

---

## Phase 3.3: Core Implementation (ONLY after tests are failing)

### FR-102: Plugin Information Modal

- [ ] **T008** Implement CUFT_Plugin_Info class in `/home/r11/dev/choice-uft/includes/update/class-cuft-plugin-info.php`
  - Create class with `plugins_api` filter hook registration
  - Implement slug detection (`choice-uft`)
  - Return false for non-CUFT plugins (pass-through)
  - Include hardcoded Plugin Metadata fields (name, author, homepage, requires, tested, requires_php)
  - **Depends on**: T002 failing
  - **Estimated**: 1.5 hours

- [ ] **T009** Implement GitHub API changelog fetcher with caching in `/home/r11/dev/choice-uft/includes/update/class-cuft-plugin-info.php`
  - Fetch release notes from GitHub Releases API
  - Cache in WordPress transient (1-hour TTL)
  - Use ETag headers for conditional requests
  - **Depends on**: T008
  - **Estimated**: 2 hours

- [ ] **T010** Implement graceful degradation for GitHub API failure in `/home/r11/dev/choice-uft/includes/update/class-cuft-plugin-info.php`
  - Detect API unavailable (timeout, rate limit, 404)
  - Return plugin metadata without changelog section
  - Log error to PHP error_log
  - **Depends on**: T009
  - **Estimated**: 1 hour

- [ ] **T011** Integration test: Verify modal displays complete info in `/home/r11/dev/choice-uft/tests/integration/update/test-plugin-info-modal.php`
  - Run test T006 against implementation
  - Verify all scenarios pass
  - **Depends on**: T010
  - **Estimated**: 30 minutes

### FR-103: Directory Naming Fix

- [ ] **T012** Implement CUFT_Directory_Fixer class in `/home/r11/dev/choice-uft/includes/update/class-cuft-directory-fixer.php`
  - Create class with `upgrader_source_selection` filter hook registration
  - Detect extracted directory name pattern (`choice-uft-v*`)
  - Rename to `choice-uft` using WP_Filesystem
  - Return WP_Error on rename failure
  - **Depends on**: T003 failing
  - **Estimated**: 2 hours

- [ ] **T013** Implement directory name detection and validation in `/home/r11/dev/choice-uft/includes/update/class-cuft-directory-fixer.php`
  - Verify source directory exists before rename
  - Check if plugin basename matches CUFT
  - Pass-through for non-CUFT plugins
  - Return WP_Error with clear message for unrecognized structure
  - **Depends on**: T012
  - **Estimated**: 1.5 hours

- [ ] **T014** Integration test: Verify directory renamed correctly in `/home/r11/dev/choice-uft/tests/integration/update/test-directory-renamed.php`
  - Run test T007 against implementation
  - Verify directory naming works
  - **Depends on**: T013
  - **Estimated**: 30 minutes

### FR-301-303: Update Execution via WordPress Standard Methods

- [ ] **T015** Verify WordPress Plugin_Upgrader integration (no custom code needed)
  - Document that WordPress's native "Update Now" button uses Plugin_Upgrader
  - Confirm no custom implementation required for FR-301 (Plugins page update)
  - Confirm no custom implementation required for FR-302 (WP-CLI update)
  - Confirm no custom implementation required for FR-303 (bulk update)
  - **Estimated**: 30 minutes

- [ ] **T016** Implement update history logging hook in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-logger.php`
  - Hook into `upgrader_process_complete` action
  - Log update attempts to Feature 007's update history (FIFO)
  - Record: user_id, previous_version, target_version, status, timestamp
  - **Depends on**: T015
  - **Estimated**: 1.5 hours

- [ ] **T017** [P] Integration test: Update from Plugins page in `/home/r11/dev/choice-uft/tests/integration/update/test-plugins-page-update.php`
  - Simulate clicking "Update Now" on Plugins page
  - Verify update completes successfully
  - Verify update history logged
  - **Reference**: quickstart.md QS-2
  - **Estimated**: 1 hour

- [ ] **T018** [P] Integration test: Update via WP-CLI in `/home/r11/dev/choice-uft/tests/integration/update/test-wp-cli-update.php`
  - Execute `wp plugin update choice-uft` via WP-CLI
  - Verify exit code 0 on success
  - Verify version updated
  - **Reference**: quickstart.md QS-3
  - **Estimated**: 1 hour

- [ ] **T019** [P] Integration test: Bulk update in `/home/r11/dev/choice-uft/tests/integration/update/test-bulk-update.php`
  - Select CUFT + another plugin for bulk update
  - Verify both updates succeed
  - Verify no interference
  - **Reference**: quickstart.md QS-4
  - **Estimated**: 1 hour

### FR-401: Download Validation

- [ ] **T020** Implement CUFT_Update_Validator class in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-validator.php`
  - Create class with validation methods
  - Hook into `upgrader_pre_install` filter
  - **Depends on**: T005 failing
  - **Estimated**: 1 hour

- [ ] **T021** Implement file size validation with ±5% tolerance in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-validator.php`
  - Compare downloaded file size with expected size from GitHub API
  - Allow ±5% variance for compression differences
  - Return WP_Error with message: "Download verification failed: File size mismatch. Expected X MB, got Y MB."
  - **Depends on**: T020
  - **Estimated**: 1.5 hours

- [ ] **T022** Implement ZIP format validation using WordPress methods in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-validator.php`
  - Use WordPress's built-in ZIP validation (WP_Filesystem::is_file_writable check pattern)
  - Return WP_Error: "Downloaded file is not a valid ZIP archive."
  - Log validation failures to PHP error_log with details
  - **Depends on**: T021
  - **Estimated**: 1 hour

- [ ] **T023** Implement immediate cleanup on validation failure in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-validator.php`
  - Delete invalid/incomplete downloads immediately after detection
  - Use WP_Filesystem for safe file deletion
  - **Depends on**: T022
  - **Estimated**: 45 minutes

- [ ] **T024** Implement daily cron job for orphaned file cleanup in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-validator.php`
  - Register WordPress cron event (daily)
  - Scan temp directory for orphaned CUFT download files
  - Delete files older than 24 hours
  - **Depends on**: T023
  - **Estimated**: 1.5 hours

- [ ] **T025** Integration test: Download validation in `/home/r11/dev/choice-uft/tests/integration/update/test-download-validation.php`
  - Simulate partial download (size mismatch)
  - Verify error message shown
  - Verify partial file deleted
  - **Reference**: quickstart.md QS-5
  - **Estimated**: 1 hour

### FR-402: Automatic Backup and Rollback

- [ ] **T026** Implement CUFT_Backup_Manager class in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Create class with backup/restore/delete methods
  - Use WordPress ZIP filesystem methods
  - **Depends on**: T004 failing
  - **Estimated**: 1.5 hours

- [ ] **T027** Implement backup creation with WordPress ZIP methods in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Create backup ZIP: `/wp-content/uploads/cuft-backups/choice-uft-{VERSION}-backup.zip`
  - Include current version number in filename
  - Return WP_Error on disk space insufficient or permissions denied
  - Check available disk space before creating backup
  - **Depends on**: T026
  - **Estimated**: 2 hours

- [ ] **T028** Implement pre-update backup hook integration in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Hook into `upgrader_pre_install` filter
  - Create backup before update starts
  - Abort update if backup creation fails
  - **Depends on**: T027
  - **Estimated**: 1 hour

- [ ] **T029** Implement restore on update failure with 10s timeout in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Hook into `upgrader_install_package_result` filter (detect failures)
  - Extract backup ZIP to `/wp-content/plugins/choice-uft/`
  - Implement 10-second hard timeout using `set_time_limit()` or timer check
  - **Depends on**: T028
  - **Estimated**: 2 hours

- [ ] **T030** Implement timeout abort with manual reinstall message in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Detect timeout exceeded (>10 seconds)
  - Abort restoration process
  - Display error: "Update failed and automatic restoration timed out. Please reinstall plugin manually from GitHub: [URL]"
  - Log CRITICAL error to PHP error_log
  - **Depends on**: T029
  - **Estimated**: 1.5 hours

- [ ] **T031** Implement post-success backup deletion in `/home/r11/dev/choice-uft/includes/update/class-cuft-backup-manager.php`
  - Hook into `upgrader_process_complete` action
  - Delete backup immediately after successful update (standard WordPress pattern)
  - Verify backup deleted, log warning if deletion fails
  - **Depends on**: T030
  - **Estimated**: 1 hour

- [ ] **T032** Integration test: Full backup/restore workflow in `/home/r11/dev/choice-uft/tests/integration/update/test-backup-restore.php`
  - Simulate update failure (corrupted ZIP)
  - Verify backup created before update
  - Verify automatic restoration triggered
  - Verify previous version restored
  - Verify error message shown
  - **Reference**: quickstart.md QS-6
  - **Estimated**: 1.5 hours

### FR-403: Error Message Clarity

- [ ] **T033** Implement error message templates in `/home/r11/dev/choice-uft/includes/update/class-cuft-error-messages.php`
  - Create constants for all error scenarios from spec.md FR-403
  - Include: download failure, extraction failure, permission error, disk space error, backup failure, restoration timeout, version mismatch
  - Each message includes: what went wrong + corrective action + relevant context
  - **Estimated**: 1.5 hours

- [ ] **T034** Implement error message logging to update history in `/home/r11/dev/choice-uft/includes/update/class-cuft-error-messages.php`
  - Log all error messages to Feature 007's update history log
  - Include error context: version, user, timestamp, error code
  - Never expose server paths to non-administrators (PII protection)
  - **Depends on**: T033
  - **Estimated**: 1 hour

### FR-404: Security Validation

- [ ] **T035** Implement nonce validation wrapper in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-security.php`
  - Validate WordPress nonces for update actions (action: `update-plugin`)
  - Return WP_Error on nonce validation failure
  - **Estimated**: 1 hour

- [ ] **T036** Implement capability check wrapper in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-security.php`
  - Verify user has `update_plugins` capability
  - Return WP_Error: "You do not have permission to update plugins"
  - **Depends on**: T035
  - **Estimated**: 45 minutes

- [ ] **T037** Implement URL validation (GitHub CDN only) in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-security.php`
  - Validate download URLs match pattern: `https://github.com/ChoiceOMG/choice-uft/releases/download/*`
  - Reject URLs not matching pattern
  - Return WP_Error: "Invalid download URL. Security check failed."
  - **Depends on**: T036
  - **Estimated**: 1 hour

- [ ] **T038** Implement DISALLOW_FILE_MODS check in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-security.php`
  - Check if `DISALLOW_FILE_MODS` constant is set to true
  - Return WP_Error: "File modifications are disabled on this site (DISALLOW_FILE_MODS)"
  - **Depends on**: T037
  - **Estimated**: 30 minutes

- [ ] **T039** Implement filesystem permission check in `/home/r11/dev/choice-uft/includes/update/class-cuft-update-security.php`
  - Check write permissions on `/wp-content/plugins/choice-uft/` before update
  - Check write permissions on `/wp-content/uploads/cuft-backups/` before backup
  - Return WP_Error with specific path and recommended permissions (755)
  - **Depends on**: T038
  - **Estimated**: 1 hour

---

## Phase 3.4: Integration & Edge Cases

- [ ] **T040** [P] Edge case test: Backup directory not writable in `/home/r11/dev/choice-uft/tests/integration/update/test-edge-case-backup-dir.php`
  - Simulate `/wp-content/uploads/cuft-backups/` not writable
  - Verify error message: "Cannot create backup directory. Please ensure /wp-content/uploads/ is writable."
  - Verify update aborted
  - **Reference**: quickstart.md EC-1
  - **Estimated**: 45 minutes

- [ ] **T041** [P] Edge case test: Disk space insufficient in `/home/r11/dev/choice-uft/tests/integration/update/test-edge-case-disk-space.php`
  - Simulate insufficient disk space (mock)
  - Verify error message: "Insufficient disk space to create backup. Free at least X MB and try again."
  - Verify update aborted
  - **Reference**: quickstart.md EC-2
  - **Estimated**: 45 minutes

- [ ] **T042** [P] Edge case test: Backup restoration fails in `/home/r11/dev/choice-uft/tests/integration/update/test-edge-case-restore-fail.php`
  - Simulate corrupted backup file
  - Verify error message: "Update failed and backup restoration also failed. Please reinstall plugin manually from GitHub."
  - Verify CRITICAL error logged
  - **Reference**: quickstart.md EC-3
  - **Estimated**: 1 hour

- [ ] **T043** [P] Edge case test: Unexpected ZIP structure in `/home/r11/dev/choice-uft/tests/integration/update/test-edge-case-zip-structure.php`
  - Simulate GitHub ZIP structure change
  - Verify directory naming fix detects unusual structure
  - Verify error message: "Unexpected ZIP structure. Please report this issue to plugin developers."
  - Verify automatic rollback
  - **Reference**: quickstart.md EC-4
  - **Estimated**: 1 hour

- [ ] **T044** [P] Edge case test: Concurrent updates in `/home/r11/dev/choice-uft/tests/integration/update/test-edge-case-concurrent.php`
  - Simulate WP-CLI update while admin UI update in progress
  - Verify second update blocked (if Feature 007 has locking mechanism)
  - Verify appropriate error message
  - **Reference**: quickstart.md EC-5
  - **Estimated**: 1 hour

---

## Phase 3.5: Polish & Documentation

- [ ] **T045** Update quickstart.md with final test scenarios
  - Verify all 7 scenarios (QS-1 to QS-7) are accurate
  - Verify all 5 edge cases (EC-1 to EC-5) are testable
  - Add troubleshooting section with common issues and fixes
  - **File**: `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/quickstart.md`
  - **Estimated**: 1.5 hours

- [ ] **T046** Run full quickstart validation
  - Execute all 7 quickstart scenarios manually
  - Execute all 5 edge case tests manually
  - Document results in `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/QUICKSTART-RESULTS.md`
  - **Depends on**: T045
  - **Estimated**: 2 hours

- [ ] **T047** Update CLAUDE.md with feature completion
  - Add Feature 008 to completed features section
  - Document new update system architecture
  - Include references to WordPress Plugin_Upgrader integration
  - Note Feature 007 migration completed
  - **File**: `/home/r11/dev/choice-uft/CLAUDE.md`
  - **Estimated**: 30 minutes

- [ ] **T048** Performance validation
  - Verify update checks complete in <5 seconds
  - Verify backup creation completes in <10 seconds
  - Verify backup restoration completes in <10 seconds (or times out)
  - Verify ZIP validation completes in <2 seconds
  - Document results in `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/PERFORMANCE-RESULTS.md`
  - **Estimated**: 1.5 hours

- [ ] **T049** Security audit
  - Verify all nonce validations working
  - Verify capability checks working (`update_plugins`)
  - Verify URL validation rejects non-GitHub URLs
  - Verify DISALLOW_FILE_MODS respected
  - Verify filesystem permissions checked
  - Document results in `/home/r11/dev/choice-uft/specs/008-fix-critical-gaps/SECURITY-AUDIT.md`
  - **Estimated**: 1.5 hours

---

## Dependencies

### Phase Dependencies
- **Phase 3.0 (Migration)** must complete before any other phase
- **Phase 3.2 (Tests)** before **Phase 3.3 (Implementation)**
- **Phase 3.3 (Implementation)** before **Phase 3.4 (Integration)**
- **Phase 3.4 (Integration)** before **Phase 3.5 (Polish)**

### Task Dependencies
- T000 blocks T000a, T000b, T000c
- T000a, T000b, T000c block T000d
- T000d blocks T001 (all migration must complete first)
- T002 blocks T008 (test before implementation)
- T003 blocks T012
- T004 blocks T026
- T005 blocks T020
- T008 blocks T009
- T009 blocks T010
- T010 blocks T011
- T012 blocks T013
- T013 blocks T014
- T015 blocks T016
- T020 blocks T021
- T021 blocks T022
- T022 blocks T023
- T023 blocks T024
- T026 blocks T027
- T027 blocks T028
- T028 blocks T029
- T029 blocks T030
- T030 blocks T031
- T033 blocks T034
- T035 blocks T036
- T036 blocks T037
- T037 blocks T038
- T038 blocks T039
- T045 blocks T046

---

## Parallel Execution Examples

### Migration Phase (T000 series)
```bash
# T000 can start immediately (audit is read-only)
Task: "Audit Feature 007 for custom update execution logic and UI"

# After T000 completes, T000a, T000b, T000c can run in parallel (different files)
Task: "Remove custom download/install logic from Feature 007"
Task: "Remove custom update UI from Feature 007 (WordPress convention alignment)"
Task: "Modify admin notice behavior (align with WordPress conventions)"
```

### Contract Tests (Phase 3.2)
```bash
# All contract tests can run in parallel (different test files)
Task: "Contract test: plugins_api filter in tests/unit/update/test-plugin-info-contract.php"
Task: "Contract test: upgrader_source_selection filter in tests/unit/update/test-directory-fixer-contract.php"
Task: "Contract test: backup/restore workflow in tests/unit/update/test-backup-manager-contract.php"
Task: "Contract test: download validation in tests/unit/update/test-update-validator-contract.php"
Task: "Integration test: Plugin information modal in tests/integration/update/test-plugins-page-modal.php"
Task: "Integration test: Directory naming correction in tests/integration/update/test-directory-naming.php"
```

### Integration Tests (Phase 3.3)
```bash
# These integration tests can run in parallel (different test files)
Task: "Integration test: Update from Plugins page in tests/integration/update/test-plugins-page-update.php"
Task: "Integration test: Update via WP-CLI in tests/integration/update/test-wp-cli-update.php"
Task: "Integration test: Bulk update in tests/integration/update/test-bulk-update.php"
```

### Edge Case Tests (Phase 3.4)
```bash
# All edge case tests can run in parallel (different test files)
Task: "Edge case test: Backup directory not writable in tests/integration/update/test-edge-case-backup-dir.php"
Task: "Edge case test: Disk space insufficient in tests/integration/update/test-edge-case-disk-space.php"
Task: "Edge case test: Backup restoration fails in tests/integration/update/test-edge-case-restore-fail.php"
Task: "Edge case test: Unexpected ZIP structure in tests/integration/update/test-edge-case-zip-structure.php"
Task: "Edge case test: Concurrent updates in tests/integration/update/test-edge-case-concurrent.php"
```

---

## Notes

### Execution Rules
- **[P] tasks** = different files, no dependencies, can run in parallel
- **Migration (T000 series)** must complete before Feature 008 implementation begins
- **Tests must fail** before implementing corresponding feature
- **Commit after each task** with descriptive messages
- **Avoid**: vague tasks, same file conflicts, skipping tests

### WordPress Integration Points
- Use `plugins_api` filter for plugin information modal (FR-102)
- Use `upgrader_source_selection` filter for directory naming (FR-103)
- Use `upgrader_pre_install` for validation and backup (FR-401, FR-402)
- Use `upgrader_process_complete` for logging and cleanup (FR-301-303)
- Use WordPress Filesystem API (WP_Filesystem) for all file operations
- Use WordPress HTTP API for GitHub API requests
- Use WordPress Transients API for caching
- Use WordPress Cron API for scheduled cleanup

### Testing Strategy
- **Unit tests** (contract tests): Verify individual components
- **Integration tests**: Verify WordPress hooks and full update flow
- **Edge case tests**: Verify error handling and boundary conditions
- **Manual tests** (quickstart.md): Verify user-facing scenarios

---

## Validation Checklist
*GATE: Verify before marking feature complete*

- [x] All contracts have corresponding tests (4 contracts → T002-T005)
- [x] All entities have implementations (4 entities → 5 implementation classes)
- [x] All tests come before implementation (T002-T007 before T008+)
- [x] Parallel tasks truly independent (different files verified)
- [x] Each task specifies exact file path
- [x] No task modifies same file as another [P] task
- [x] Migration tasks precede all implementation (T000 series first)
- [x] All FRs have integration tests (FR-102 to FR-404 covered)
- [x] All quickstart scenarios testable (7 scenarios + 5 edge cases)
- [x] Performance targets defined (T048)
- [x] Security audit included (T049)

---

**Total Tasks**: 49 (T000-T049)
**Estimated Total Time**: 55-65 hours
**Critical Path**: T000 → T000d → T002-T007 → Implementation → Integration → Polish
