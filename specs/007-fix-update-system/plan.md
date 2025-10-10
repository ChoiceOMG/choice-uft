# Implementation Plan: Fix Update System Inconsistencies

**Branch**: `007-fix-update-system` | **Date**: 2025-10-07 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-fix-update-system/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   → If not found: ERROR "No feature spec at {path}"
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   → Detect Project Type from context (web=frontend+backend, mobile=app+api)
   → Set Structure Decision based on project type
3. Fill the Constitution Check section based on the content of the constitution document.
4. Evaluate Constitution Check section below
   → If violations exist: Document in Complexity Tracking
   → If no justification possible: ERROR "Simplify approach first"
   → Update Progress Tracking: Initial Constitution Check
5. Execute Phase 0 → research.md
   → If NEEDS CLARIFICATION remain: ERROR "Resolve unknowns"
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file
7. Re-evaluate Constitution Check section
   → If new violations: Refactor design, return to Phase 1
   → Update Progress Tracking: Post-Design Constitution Check
8. Plan Phase 2 → Describe task generation approach (DO NOT create tasks.md)
9. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 7. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary

The Choice Universal Form Tracker plugin has multiple inconsistencies and UX issues in its WordPress admin update system. The update system displays conflicting information across interfaces (admin notices, admin bar, Updates tab), fails security validation on AJAX requests, and doesn't properly refresh status indicators after updates. This plan addresses standardizing the update feedback across all UI locations to align with WordPress admin notice and update API standards.

## Technical Context

**Language/Version**: PHP 7.4+ (WordPress plugin)
**Primary Dependencies**: WordPress 5.0+, WordPress HTTP API, WordPress Update API, WordPress Admin Bar API
**Storage**: WordPress options table (wp_options) for update configuration, transients for caching, user meta for dismissals
**Testing**: PHPUnit for unit tests, WordPress integration tests for AJAX endpoints
**Target Platform**: WordPress 5.0+ on PHP 7.4+ (Linux, Apache/Nginx)
**Project Type**: single (WordPress plugin with PHP backend and JavaScript frontend)
**Performance Goals**:
- AJAX responses <500ms P95
- Update check <5 seconds (as per FR-008)
- UI refresh <100ms after status changes
**Constraints**:
- Must follow WordPress admin notice standards (positioning, markup, dismissibility)
- Must use WordPress nonce validation for all AJAX requests
- Must work with both GitHub auto-update and WordPress repository updates
- Admin bar must refresh without full page reload
**Scale/Scope**:
- 5 AJAX endpoints (check_update, perform_update, update_status, rollback_update, update_history)
- 3 UI locations (admin notices, admin bar, Updates tab)
- Last 5 updates history retention (as per FR-009)
- Update checks every 6 hours (current implementation)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Core Principles Review

**✅ JavaScript-First Compatibility Principle**:
- Admin bar and notice interactions use vanilla JavaScript with jQuery fallback
- AJAX calls use native fetch API with XMLHttpRequest fallback
- Event handling uses native JavaScript CustomEvent with jQuery events as fallback

**✅ DataLayer Standardization Rule**:
- Not applicable - this feature doesn't involve form tracking or dataLayer events

**✅ Framework Compatibility Principle**:
- Not applicable - this feature is admin-only and doesn't interact with form frameworks

**✅ Event Firing Rules**:
- Not applicable - this feature doesn't generate tracking events

**✅ Error Handling Philosophy**:
- AJAX error handling implements graceful degradation
- Network failures don't break admin UI
- All external API calls wrapped in try-catch blocks
- Fallback chains for status retrieval: Transient → Database → Default state

**✅ Testing Requirements**:
- AJAX endpoint contract tests (existing: test-check-update.php, test-perform-update.php, etc.)
- Integration tests for update flow (existing: test-update-flow.php)
- UI interaction tests for admin notices and admin bar

**✅ Performance Constraints**:
- Update status checks complete within 5 seconds (FR-008)
- AJAX responses target <500ms P95
- DOM updates <100ms (notice positioning, admin bar refresh)
- No memory leaks from event listeners

**✅ Security Principles**:
- All AJAX endpoints use nonce validation (`cuft_updater_nonce`)
- Capability checks for `update_plugins` permission
- Input sanitization on all POST/GET parameters
- No PII logged to console in production

### Implementation Standards Review

**✅ Code Organization**:
- PHP: `/includes/class-cuft-wordpress-updater.php`, `/includes/ajax/class-cuft-updater-ajax.php`
- JavaScript: `/assets/admin/js/cuft-admin-bar.js`, `/assets/admin/js/cuft-admin-notices.js`
- Follows existing file structure conventions

**✅ Event Handling Standards**:
- Admin bar uses WordPress `admin_bar_menu` action
- Admin notices use WordPress `admin_notices` action
- JavaScript event listeners properly cleaned up

**✅ Debug Mode Standards**:
- Console logging only when debug mode enabled
- Structured logging with context (action, version, status)
- Production deployments have zero console output unless errors occur

**✅ Documentation Standards**:
- Inline PHPDoc comments for all methods
- References specification document
- Change documentation in implementation

**Status**: ✅ **PASS** - No constitutional violations detected

## Project Structure

### Documentation (this feature)
```
specs/007-fix-update-system/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
│   ├── admin-notices-api.md
│   ├── admin-bar-api.md
│   ├── ajax-endpoints.md
│   └── update-status-api.md
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
includes/
├── admin/
│   ├── class-cuft-admin-notices.php    # Admin notice display (FIX positioning)
│   └── class-cuft-admin-bar.php        # Admin bar integration (FIX refresh)
├── ajax/
│   └── class-cuft-updater-ajax.php     # AJAX handlers (FIX security)
├── models/
│   ├── class-cuft-update-status.php    # Update status model (SYNC)
│   ├── class-cuft-update-progress.php  # Update progress tracking
│   └── class-cuft-update-log.php       # Update history (last 5)
├── class-cuft-wordpress-updater.php    # WordPress update integration (SYNC)
└── class-cuft-github-updater.php       # GitHub updater (SYNC)

assets/admin/js/
├── cuft-admin-bar.js                   # Admin bar JavaScript (FIX refresh)
└── cuft-admin-notices.js               # Notice interaction JavaScript

tests/
├── ajax/
│   ├── test-check-update.php           # Existing
│   ├── test-perform-update.php         # Existing
│   └── test-update-status.php          # Existing
└── integration/
    └── test-update-flow.php             # Existing
```

**Structure Decision**: Option 1 (Single project) - WordPress plugin with PHP backend and JavaScript frontend

## Phase 0: Outline & Research

Research tasks to resolve technical uncertainties and establish best practices:

1. **WordPress Admin Notice Standards Research**
   - Official WordPress documentation on admin notice positioning
   - Standard markup and CSS classes for notices
   - Best practices for notice dismissibility and persistence
   - How WordPress core handles notice placement (above vs beside page title)

2. **WordPress Update Transient System Research**
   - How `pre_set_site_transient_update_plugins` works
   - Transient cache invalidation strategies
   - Synchronization between manual and automatic update checks
   - WordPress update status persistence across page loads

3. **WordPress Admin Bar API Research**
   - Admin bar node refresh without full page reload
   - JavaScript event handling for admin bar updates
   - Custom admin bar styling and animation
   - Admin bar caching and dynamic content

4. **WordPress Nonce Validation Best Practices**
   - Current nonce issues causing "Security check failed"
   - Nonce action naming conventions
   - Nonce lifespan and renewal strategies
   - AJAX nonce validation patterns

5. **Concurrent Update Handling Research**
   - WordPress update locking mechanisms
   - Transient-based semaphore implementation
   - Race condition prevention strategies
   - Update coordination between multiple admin users

6. **Update History Management Research**
   - FIFO (First-In-First-Out) implementation for last 5 updates
   - Database vs transient storage trade-offs
   - Update log data structure and indexing
   - Log cleanup and rotation strategies

**Output**: research.md with all findings and technical decisions

## Phase 1: Design & Contracts

### Data Models

1. **Update Status Model** (`CUFT_Update_Status`)
   - Current version
   - Latest available version
   - Update availability flag
   - Last check timestamp
   - Update source (GitHub/WordPress)

2. **Update Progress Model** (`CUFT_Update_Progress`)
   - Update ID
   - Status (pending, in_progress, complete, failed)
   - Progress percentage
   - Current step message
   - Started timestamp
   - Completed timestamp

3. **Update Log Model** (`CUFT_Update_Log`)
   - Entry ID
   - Timestamp
   - Action (check, download, install, rollback)
   - Status (success, failure, in_progress)
   - Version from
   - Version to
   - User ID
   - Error message (if failed)
   - Duration

4. **Admin Notice State**
   - Notice type (info, warning, error, success)
   - Message content
   - Dismissibility
   - Display location (above/beside title)
   - Version associated
   - User dismissal state

5. **Admin Bar State**
   - Update available flag
   - Latest version
   - Next scheduled check time
   - Current check status

### API Contracts

1. **AJAX Endpoint: `cuft_check_update`**
   - Request: `{ nonce, force: boolean }`
   - Response: `{ success: boolean, update_available: boolean, current_version, latest_version, last_check }`
   - Error codes: `invalid_nonce`, `insufficient_permissions`, `check_failed`

2. **AJAX Endpoint: `cuft_perform_update`**
   - Request: `{ nonce, version: string, backup: boolean }`
   - Response: `{ success: boolean, update_id, status, message }`
   - Error codes: `invalid_nonce`, `update_in_progress`, `update_failed`

3. **AJAX Endpoint: `cuft_update_status`**
   - Request: `{ nonce, update_id: string }`
   - Response: `{ success: boolean, status, percentage, message, started_at, elapsed_seconds }`
   - Error codes: `invalid_nonce`, `status_failed`

4. **Admin Notice Positioning API**
   - Hook: `admin_notices`
   - Placement: Above page title (standard WordPress location)
   - Markup: `<div class="notice notice-{type} is-dismissible">`
   - Dismissal: User meta storage with AJAX handler

5. **Admin Bar Refresh API**
   - JavaScript event: `cuft_update_status_changed`
   - Event data: `{ update_available, latest_version, current_version }`
   - Refresh trigger: After AJAX update check completes
   - No page reload required

### Contract Tests

Contract tests already exist in `/tests/ajax/` and `/tests/integration/`:
- `test-check-update.php` - AJAX check endpoint
- `test-perform-update.php` - AJAX perform endpoint
- `test-update-status.php` - AJAX status endpoint
- `test-update-flow.php` - Integration test

New contract tests needed:
- Admin notice positioning validation
- Admin bar refresh validation
- Update status synchronization validation

### Quickstart Test Scenarios

From feature spec user scenarios:

1. **Scenario: Admin notice positioning**
   - Given: Admin user on plugin settings page with update available
   - When: Page loads
   - Then: Notice appears above page title in standard WordPress `.notice` area

2. **Scenario: Admin bar refresh after update**
   - Given: Admin has successfully updated plugin
   - When: Viewing admin bar
   - Then: "CUFT Update" indicator reflects current version immediately (no page refresh)

3. **Scenario: Consistent version display**
   - Given: Admin views Updates tab
   - When: Checking version status
   - Then: All UI elements show consistent version information

4. **Scenario: Secure update button**
   - Given: Admin clicks "Download & Install Update" button
   - When: Request is processed
   - Then: Completes successfully with proper nonce validation (no "Security check failed")

5. **Scenario: Synchronized update indicators**
   - Given: Update is available
   - When: Checking status in different interfaces
   - Then: All indicators show same update availability consistently

**Output**: data-model.md, /contracts/, quickstart.md, CLAUDE.md

## Phase 2: Task Planning Approach

*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `.specify/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs (contracts, data model, quickstart)
- Each AJAX endpoint contract → contract test validation task [P]
- Each data model → model validation task [P]
- Each admin notice scenario → implementation task
- Each admin bar scenario → implementation task
- Each quickstart scenario → integration test task

**Ordering Strategy**:
- Phase 1: Fix admin notice positioning (FR-001)
- Phase 2: Fix nonce validation for AJAX endpoints (FR-004)
- Phase 3: Implement admin bar refresh (FR-002)
- Phase 4: Synchronize update status across interfaces (FR-003, FR-005)
- Phase 5: Implement update history retention (FR-009)
- Phase 6: Add concurrent update handling (FR-010)
- Phase 7: Integration testing and validation

**Estimated Output**: 35-40 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation

*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking

*No constitutional violations detected - section not needed*

## Progress Tracking

*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [x] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented (none)

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
