# Tasks: One-Click Automated Update

**Input**: Design documents from `/specs/005-one-click-automated/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → If not found: ERROR "No implementation plan found"
   → Extract: tech stack, libraries, structure
2. Load optional design documents:
   → data-model.md: Extract entities → model tasks
   → contracts/: Each file → contract test task
   → research.md: Extract decisions → setup tasks
3. Generate tasks by category:
   → Setup: project init, dependencies, linting
   → Tests: contract tests, integration tests
   → Core: models, services, CLI commands
   → Integration: DB, middleware, logging
   → Polish: unit tests, performance, docs
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Tests before implementation (TDD)
5. Number tasks sequentially (T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
8. Validate task completeness:
   → All contracts have tests?
   → All entities have models?
   → All endpoints implemented?
9. Return: SUCCESS (tasks ready for execution)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions

## Path Conventions
- WordPress plugin structure: `includes/`, `assets/`, `tests/`
- Admin classes in `includes/admin/`
- AJAX handlers in `includes/ajax/`
- JavaScript in `assets/admin/js/`
- CSS in `assets/admin/css/`

## Phase 3.1: Critical Bug Fix 🚨
**PRIORITY 1 - Must fix the nonce security issue first**

- [X] T001 Fix nonce validation in existing AJAX endpoints (includes/ajax/class-cuft-updater-ajax.php)
- [X] T002 Update JavaScript nonce handling (assets/admin/js/cuft-updater.js)
- [X] T003 Test nonce validation fix with quickstart.md Scenario 1

## Phase 3.2: Tests First (TDD) ✅ COMPLETED

### Contract Tests [P] - Can run in parallel
- [X] T004 [P] Create PHPUnit test for cuft_check_update endpoint (tests/ajax/test-check-update.php)
- [X] T005 [P] Create PHPUnit test for cuft_perform_update endpoint (tests/ajax/test-perform-update.php)
- [X] T006 [P] Create PHPUnit test for cuft_update_status endpoint (tests/ajax/test-update-status.php)
- [X] T007 [P] Create PHPUnit test for cuft_rollback_update endpoint (tests/ajax/test-rollback-update.php)
- [X] T008 [P] Create PHPUnit test for cuft_update_history endpoint (tests/ajax/test-update-history.php)
- [X] T009 [P] Create PHPUnit test for cuft_update_settings endpoint (tests/ajax/test-update-settings.php)

### Integration Tests [P]
- [X] T010 [P] Create test for Scenario 2: Check for Updates Happy Path (tests/integration/test-check-updates.php)
- [X] T011 [P] Create test for Scenario 3: One-Click Update Flow (tests/integration/test-update-flow.php)
- [X] T012 [P] Create test for Scenario 4: Network Failure Handling (tests/integration/test-network-failure.php)
- [X] T013 [P] Create test for Scenario 5: Automatic Rollback (tests/integration/test-rollback.php)
- [X] T014 [P] Create test for Scenario 6: Concurrent Update Prevention (tests/integration/test-concurrent.php)
- [X] T015 [P] Create test for Scenario 7: Update Scheduling (tests/integration/test-scheduling.php)
- [X] T016 [P] Create test for corrupted ZIP file handling (tests/integration/test-corrupted-download.php)
- [X] T017 [P] Create test for settings preservation during update (tests/integration/test-settings-preservation.php)

## Phase 3.3: Core Implementation

### Database & Models
- [X] T018 Create UpdateLog table migration (includes/migrations/create-update-log-table.php)
- [X] T019 Implement UpdateStatus model with transient storage (includes/models/class-cuft-update-status.php)
- [X] T020 Implement UpdateProgress model (includes/models/class-cuft-update-progress.php)
- [X] T021 Implement GitHubRelease model (includes/models/class-cuft-github-release.php)
- [X] T022 Implement UpdateLog model with database storage (includes/models/class-cuft-update-log.php)
- [X] T023 Implement UpdateConfiguration model (includes/models/class-cuft-update-configuration.php)

### Core Services
- [X] T024 Create GitHub API client service (includes/class-cuft-github-api.php)
- [X] T025 Implement update checker service with caching (includes/class-cuft-update-checker.php)
- [X] T026 Create WP_Filesystem wrapper for safe updates (includes/class-cuft-filesystem-handler.php)
- [X] T027 Implement backup and rollback service with automatic backup before update (includes/class-cuft-backup-manager.php)
- [X] T028 Create update installer service (includes/class-cuft-update-installer.php)

### AJAX Endpoints Implementation
- [X] T029 Implement cuft_check_update endpoint (includes/ajax/class-cuft-updater-ajax.php::check_update())
- [X] T030 Implement cuft_perform_update endpoint (includes/ajax/class-cuft-updater-ajax.php::perform_update())
- [X] T031 Implement cuft_update_status endpoint (includes/ajax/class-cuft-updater-ajax.php::update_status())
- [X] T032 Implement cuft_rollback_update endpoint (includes/ajax/class-cuft-updater-ajax.php::rollback_update())
- [X] T033 Implement cuft_update_history endpoint (includes/ajax/class-cuft-updater-ajax.php::update_history())
- [X] T034 Implement cuft_update_settings endpoint (includes/ajax/class-cuft-updater-ajax.php::update_settings())

## Phase 3.4: WordPress Integration

### Update API Integration
- [X] T035 Hook into pre_set_site_transient_update_plugins filter (includes/class-cuft-wordpress-updater.php)
- [X] T036 Implement plugins_api filter for plugin information (includes/class-cuft-wordpress-updater.php)
- [X] T037 Add upgrader_source_selection filter for update source (includes/class-cuft-wordpress-updater.php)
- [X] T038 Create admin notices for update availability (includes/admin/class-cuft-admin-notices.php)

### Scheduling & Automation
- [X] T039 Register twice-daily cron event for update checks (includes/class-cuft-cron-manager.php)
- [X] T040 Implement cron callback for automatic checks (includes/class-cuft-cron-manager.php::check_updates())
- [X] T041 Add manual check trigger in admin bar (includes/admin/class-cuft-admin-bar.php)

## Phase 3.5: Frontend Implementation

### Admin UI Components [P]
- [X] T042 [P] Create update status dashboard widget (assets/admin/js/cuft-update-widget.js)
- [X] T043 [P] Build update progress indicator component (assets/admin/js/cuft-progress-indicator.js)
- [X] T044 [P] Implement update history viewer (assets/admin/js/cuft-update-history.js)
- [X] T045 [P] Create update settings form (assets/admin/js/cuft-update-settings.js)
- [X] T046 [P] Style update UI components (assets/admin/css/cuft-updater.css)

### JavaScript Error Handling (MUST use try-catch blocks per Constitution §5)
- [X] T047 Add fallback for network failures with try-catch blocks for all API calls (assets/admin/js/cuft-updater.js)
- [X] T048 Implement retry mechanism with exponential backoff and jQuery fallback pattern (assets/admin/js/cuft-updater.js)
- [X] T049 Create user-friendly error messages with proper error isolation (assets/admin/js/cuft-error-handler.js)

## Phase 3.6: Security & Validation

### Security Hardening [P]
- [X] T050 [P] Add rate limiting to update endpoints (includes/class-cuft-rate-limiter.php)
- [X] T051 [P] Implement download verification with checksums and corrupted file detection (includes/class-cuft-download-verifier.php)
- [X] T052 [P] Add capability checks for all admin actions (includes/class-cuft-capabilities.php)
- [X] T053 [P] Sanitize and validate all user inputs (includes/class-cuft-input-validator.php)

## Phase 3.7: Polish & Documentation

### Performance & Optimization [P]
- [X] T054 [P] Optimize database queries with proper indexing (includes/class-cuft-db-optimizer.php)
- [X] T055 [P] Implement transient preloading for admin pages (includes/class-cuft-cache-warmer.php)
- [X] T056 [P] Add lazy loading for update history (assets/admin/js/cuft-lazy-loader.js)

### Documentation [P]
- [X] T057 [P] Write inline PHPDoc for all classes and methods
- [X] T058 [P] Create user documentation for update feature (docs/user-guide.md)
- [X] T059 [P] Document troubleshooting steps (docs/troubleshooting.md)
- [X] T060 [P] Add code examples for developers (docs/developer-guide.md)

## Phase 3.8: Final Validation

### End-to-End Testing
- [X] T061 Run all quickstart.md scenarios manually
- [ ] T062 Test with different WordPress versions (5.0, 5.5, 6.0+)
- [ ] T063 Verify compatibility with common plugins
- [X] T064 Performance benchmark - update check < 2 seconds
- [X] T065 Security audit - verify all nonces and capabilities
- [X] T066 Create release notes for the update feature

## Parallel Execution Examples

### Example 1: Run all contract tests in parallel
```bash
# Using Task agent
Task "Run contract test T004" --parallel &
Task "Run contract test T005" --parallel &
Task "Run contract test T006" --parallel &
Task "Run contract test T007" --parallel &
Task "Run contract test T008" --parallel &
Task "Run contract test T009" --parallel &
wait
```

### Example 2: Parallel frontend components
```bash
# Tasks T040-T044 can run simultaneously (different files)
Task "Create update widget T040" --parallel &
Task "Build progress indicator T041" --parallel &
Task "Implement history viewer T042" --parallel &
Task "Create settings form T043" --parallel &
Task "Style components T044" --parallel &
wait
```

### Example 3: Security tasks in parallel
```bash
# Tasks T048-T051 are independent
Task "Add rate limiting T048" --parallel &
Task "Implement verification T049" --parallel &
Task "Add capability checks T050" --parallel &
Task "Add input validation T051" --parallel &
wait
```

## Dependencies Graph

```
T001-T003 (Critical Fix)
    ↓
T004-T017 (Tests) [PARALLEL] - Added T016-T017 for corrupted downloads & settings
    ↓
T018-T023 (Models) [SEQUENTIAL - database dependencies]
    ↓
T024-T028 (Services) [SEQUENTIAL - depend on models]
    ↓
T029-T034 (AJAX Endpoints) [SEQUENTIAL - same file]
    ↓
T035-T041 (WP Integration)
    ↓
T042-T046 (Frontend) [PARALLEL]
    ↓
T047-T049 (Error Handling with Constitution compliance)
    ↓
T050-T053 (Security) [PARALLEL]
    ↓
T054-T060 (Polish) [PARALLEL]
    ↓
T061-T066 (Validation)
```

## Task Count Summary
- **Critical Fix**: 3 tasks
- **Tests**: 14 tasks (all parallel) - Added 2 for corrupted downloads & settings preservation
- **Core Implementation**: 16 tasks
- **WordPress Integration**: 7 tasks
- **Frontend**: 8 tasks (5 parallel)
- **Security**: 4 tasks (all parallel)
- **Polish**: 7 tasks (all parallel)
- **Validation**: 6 tasks

**Total**: 66 tasks (30 can run in parallel)

## Success Criteria
✅ All 66 tasks completed
✅ All tests passing (T004-T017) including corrupted download and settings preservation
✅ Nonce security issue resolved (T001-T003)
✅ Integration with WordPress Updates page working
✅ Automatic rollback functional with automatic backup (T027)
✅ Performance targets met (< 2 seconds check)
✅ All quickstart scenarios validated
✅ Constitution compliance verified (try-catch blocks, jQuery fallback)

## Ready for Execution
This task list is immediately executable. Each task specifies:
- Exact file paths
- Clear implementation requirements
- Dependencies and parallelization markers
- Test coverage for all functionality

Start with T001-T003 to fix the critical security issue, then proceed with TDD approach.