# Implementation Guide: Fix Update System Inconsistencies

**Feature**: 007-fix-update-system
**Date**: 2025-10-07
**Purpose**: Quick reference for implementers - maps tasks to detailed documentation

---

## Quick Reference Index

### Research Sections (research.md)

| Section | Lines | Topic | Referenced by Tasks |
|---------|-------|-------|---------------------|
| **1. WordPress Admin Notice Standards** | 13-135 | Notice positioning, markup, dismissal | T002, T003, T027 |
| **2. WordPress Update Transient System** | 137-263 | Context-aware caching, site transients | T004, T005, T010, T011, T024 |
| **3. WordPress Admin Bar Dynamic Updates** | 265-378 | Periodic polling, DOM manipulation | T017, T018, T019, T021 |
| **4. WordPress Nonce Validation** | 380-488 | Nonce patterns, common issues | T009, T012-T016, T020 |
| **5. Concurrent Update Handling** | 490-594 | Transient locks, race conditions | T006, T013, T030 |
| **6. Update History Management** | 596-720 | FIFO implementation, database schema | T007, T016, T029 |

### Key Research Subsections

**Admin Notice Positioning**:
- HTML pattern: research.md lines 24-32
- CSS classes: research.md lines 70-79
- Dismissal pattern: research.md lines 84-110
- Current issues: research.md lines 122-134

**Context-Aware Caching**:
- WordPress timing table: research.md lines 154-163
- Timeout map code: research.md lines 194-201
- Current issues: research.md lines 222-232
- Recommended fix: research.md lines 235-247

**Admin Bar Polling**:
- Polling pattern: research.md lines 303-313
- DOM update pattern: research.md lines 315-339
- Performance considerations: research.md lines 372-378

**Nonce Validation**:
- Proper pattern: research.md lines 398-418
- Common issues: research.md lines 434-475
  - Action mismatch: lines 434-443
  - Not passed in request: lines 446-461
  - JavaScript undefined: lines 463-467

**Concurrent Updates**:
- Lock acquisition: research.md lines 499-524
- Race condition handling: research.md lines 553-562
- Lock strategies: research.md lines 565-578

**FIFO Implementation**:
- Database schema: research.md lines 605-620
- FIFO code pattern: research.md lines 622-692

---

## Data Models (data-model.md)

### Model 1: Update Status Model
**Location**: data-model.md lines 29-78
**Key Fields**: current_version, latest_version, update_available, last_check, source
**Storage**: Site transient `cuft_update_status`
**Referenced by**: T004, T005, T010, T024

### Model 2: Update Progress Model
**Location**: data-model.md lines 80-132
**Key Fields**: update_id, status, percentage, message, user_id, timestamps
**Storage**: Regular transient `cuft_update_in_progress`
**Referenced by**: T006, T013, T014, T030

### Model 3: Update Log Model
**Location**: data-model.md lines 134-190
**Key Fields**: id, timestamp, action, status, versions, user_id, duration
**Storage**: Database table `wp_cuft_update_log`
**Referenced by**: T007, T016, T029

### Model 4: Admin Notice State
**Location**: data-model.md lines 192-225
**Key Fields**: notice_type, message, dismissibility, version, user_dismissal
**Storage**: User meta `cuft_dismissed_update_{version}`
**Referenced by**: T003, T008, T027

### Model 5: Admin Bar State
**Location**: data-model.md lines 227-260
**Key Fields**: update_available, latest_version, next_check, status
**Storage**: Rendered server-side, updated client-side
**Referenced by**: T017, T018, T019, T021

---

## API Contracts (contracts/ajax-endpoints.md)

### Endpoint 1: Check for Updates
**Location**: contracts/ajax-endpoints.md lines 36-121
**Action**: `cuft_check_update`
**Performance**: <5 seconds (line 118)
**Referenced by**: T012

### Endpoint 2: Perform Update
**Location**: contracts/ajax-endpoints.md lines 123-199
**Action**: `cuft_perform_update`
**Concurrent Error**: HTTP 409 (lines 165-181)
**Referenced by**: T013

### Endpoint 3: Get Update Status
**Location**: contracts/ajax-endpoints.md lines 203-305
**Action**: `cuft_update_status`
**Performance**: <100ms (lines 301-305)
**Referenced by**: T014, T017

### Endpoint 4: Rollback Update
**Location**: contracts/ajax-endpoints.md lines 308-363
**Action**: `cuft_rollback_update`
**Referenced by**: T015

### Endpoint 5: Get Update History
**Location**: contracts/ajax-endpoints.md lines 366-438
**Action**: `cuft_update_history`
**FIFO Limit**: Max 5 entries (line 384)
**Referenced by**: T016

### Endpoint 6: Dismiss Update Notice
**Location**: contracts/ajax-endpoints.md lines 441-491
**Action**: `cuft_dismiss_update_notice`
**Referenced by**: T003, T008

---

## Test Scenarios (quickstart.md)

### Scenario 1: Admin Notice Positioning (FR-001)
**Location**: quickstart.md lines 22-72
**Validates**: `.wp-header-end` marker, notice above title
**Referenced by**: T002, T003, T027

### Scenario 2: Admin Bar Refresh (FR-002)
**Location**: quickstart.md lines 75-122
**Validates**: Dynamic updates, no page reload, periodic polling
**Referenced by**: T017, T018, T019, T021

### Scenario 3: Consistent Version Display (FR-003)
**Location**: quickstart.md lines 125-165
**Validates**: Same version across all UI locations
**Referenced by**: T024, T025

### Scenario 4: Secure Update Button (FR-004)
**Location**: quickstart.md lines 168-247
**Validates**: Nonce validation, no "Security check failed"
**Referenced by**: T009, T028

### Scenario 5: Synchronized Update Indicators (FR-005)
**Location**: quickstart.md lines 250-292
**Validates**: All indicators update within 5 seconds
**Referenced by**: T024, T025

### Scenario 6: Update History (FR-009)
**Location**: quickstart.md lines 295-333
**Validates**: Last 5 updates retained, FIFO cleanup
**Referenced by**: T007, T029

### Scenario 7: Concurrent Updates (FR-010)
**Location**: quickstart.md lines 336-386
**Validates**: Only one update at a time, 409 error
**Referenced by**: T006, T030

---

## Task-to-Detail Mapping

### Phase 1: Setup & Validation (T001-T003)

**T001: Validate Existing Test Infrastructure**
- No research references (foundational task)
- Verifies existing tests in `tests/ajax/` directory

**T002: Fix Admin Notice Positioning Structure**
- 📖 Research: Section 1 (lines 13-135) - WordPress admin notice standards
- 📋 Pattern: research.md lines 24-32 - HTML structure with `.wp-header-end`
- ⚠️ Issues: research.md lines 122-134 - Current implementation problems
- ✅ Test: quickstart.md Scenario 1 (lines 22-72)

**T003: Fix Admin Notices Hook Integration**
- 📖 Research: Section 1 (lines 13-135) - Notice markup and positioning
- 📋 Pattern: research.md lines 42-67 - WordPress markup examples
- 📝 CSS: research.md lines 70-79 - Notice CSS classes
- 📋 Model: data-model.md Model 4 (lines 192-225) - Admin Notice State
- ✅ Test: quickstart.md Scenario 1 (lines 22-72)

---

### Phase 2: Data Model Updates (T004-T008)

**T004: Fix Update Status Model - Switch to Site Transients**
- 📖 Research: Section 2 (lines 137-263) - Update transient system
- 📋 Rationale: research.md lines 219-220 - Multisite compatibility
- 📋 Model: data-model.md Model 1 (lines 29-78) - Update Status schema
- ⚠️ Issues: research.md line 219 - Current uses regular transients

**T005: Implement Context-Aware Cache Timeout**
- 📖 Research: Section 2 (lines 137-263) - Context-aware caching
- 📊 Timing Table: research.md lines 154-163 - WordPress core timing
- 📝 Code Pattern: research.md lines 194-201 - Timeout map implementation
- ⚠️ Current Issue: research.md lines 222-232 - Fixed 6-hour cache
- ✅ Recommended Fix: research.md lines 235-247 - Context-aware method
- 📋 Model: data-model.md Model 1 (lines 29-78) - Update Status

**T006: Update Progress Model - Add User ID Tracking**
- 📖 Research: Section 5 (lines 490-594) - Concurrent update handling
- 📝 Pattern: research.md lines 499-524 - Lock acquisition with user tracking
- 📋 Model: data-model.md Model 2 (lines 80-132) - Update Progress schema
- ✅ Test: quickstart.md Scenario 7 (lines 336-386) - Concurrent updates

**T007: Update Log Model - Verify FIFO Implementation**
- 📖 Research: Section 6 (lines 596-720) - Update history management
- 📊 Schema: research.md lines 605-620 - Database table structure
- 📝 Code Pattern: research.md lines 622-692 - FIFO cleanup implementation
- 📋 Model: data-model.md Model 3 (lines 134-190) - Update Log schema
- ✅ Test: quickstart.md Scenario 6 (lines 295-333)

**T008: Admin Notice State - Implement Dismissal Cleanup**
- 📖 Research: Section 1 (lines 84-110) - Persistent dismissal pattern
- 📋 Model: data-model.md Model 4 (lines 192-225) - Admin Notice State
- 📝 Contract: contracts/ajax-endpoints.md lines 441-491 - Dismiss endpoint

**T008a: Validate All Data Models** (NEW)
- 📋 Validates: All models from T004-T008
- 📊 Models: data-model.md sections 1-4
- Purpose: Gate before AJAX implementation

---

### Phase 3: AJAX Endpoint Fixes (T009-T016)

**T009: Fix Nonce Validation in AJAX Handler**
- 📖 Research: Section 4 (lines 380-488) - Nonce validation best practices
- 📝 Pattern: research.md lines 398-418 - Proper nonce creation/validation
- ⚠️ Issues: research.md lines 434-475 - Common nonce problems
  - Action mismatch: lines 434-443
  - Not passed in request: lines 446-461
  - JavaScript undefined: lines 463-467
- ⚠️ Current Issue: research.md lines 470-475 - Likely causes
- ✅ Test: quickstart.md Scenario 4 (lines 168-247)

**T010: Update WordPress Updater - Context-Aware Checks**
- 📖 Research: Section 2 (lines 137-263) - Update transient system
- 📝 Pattern: research.md lines 235-247 - Context-aware timeout method
- ⚠️ Current Issue: research.md lines 222-232 - Fixed timeout problem
- 📊 Timing: research.md lines 154-163 - WordPress timing table
- 📋 Model: data-model.md Model 1 (lines 29-78) - Update Status
- Dependencies: Requires T005 context-aware timeout method

**T011: Add Cache Invalidation After Updates**
- 📖 Research: Section 2 (lines 137-263) - Transient synchronization
- 📝 Hook: WordPress `upgrader_process_complete` action
- 📊 Timing: research.md line 158 - 0 second timeout after update
- Integration: Coordinates with T023 (update completion transient)

**T012: Test AJAX Check Update Endpoint**
- 📝 Contract: contracts/ajax-endpoints.md lines 36-121 - Endpoint spec
- 📊 Performance: contracts/ajax-endpoints.md line 118 - <5 seconds target
- 📋 Request Format: lines 45-52
- 📋 Success Response: lines 62-78
- 📋 Error Responses: lines 80-114
- Dependencies: T009 (nonce fix), T010 (context-aware)

**T013: Test AJAX Perform Update Endpoint**
- 📝 Contract: contracts/ajax-endpoints.md lines 123-199 - Endpoint spec
- 📋 Concurrent Error: lines 165-181 - HTTP 409 handling
- 📖 Research: research.md lines 553-562 - Race condition handling
- 📋 Model: data-model.md Model 2 (lines 80-132) - Update Progress
- ✅ Test: quickstart.md Scenario 7 (lines 336-386)

**T014: Test AJAX Update Status Endpoint**
- 📝 Contract: contracts/ajax-endpoints.md lines 203-305 - Endpoint spec
- 📊 Performance: lines 301-305 - <100ms target
- 📋 Model: data-model.md Model 2 (lines 80-132) - Update Progress
- 📋 User Tracking: Validates T006 implementation

**T015: Test AJAX Rollback Endpoint**
- 📝 Contract: contracts/ajax-endpoints.md lines 308-363 - Endpoint spec
- 📋 Model: data-model.md Model 3 (lines 134-190) - Update Log

**T016: Test AJAX Update History Endpoint**
- 📝 Contract: contracts/ajax-endpoints.md lines 366-438 - Endpoint spec
- 📊 FIFO Limit: line 384 - Max 5 entries
- 📋 Model: data-model.md Model 3 (lines 134-190) - Update Log
- ✅ Test: quickstart.md Scenario 6 (lines 295-333)

---

### Phase 4: Admin Bar Dynamic Updates (T017-T021)

**T017: Implement Admin Bar Periodic Polling - JavaScript**
- 📖 Research: Section 3 (lines 265-378) - Admin bar dynamic updates
- 📝 Pattern: research.md lines 303-313 - Polling implementation
- 📊 Performance: research.md lines 372-378 - Polling optimizations
  - 30-60 second interval
  - Stop when tab inactive
  - Exponential backoff on errors
- 📝 Contract: contracts/ajax-endpoints.md lines 203-305 - Status endpoint
- ✅ Test: quickstart.md Scenario 2 (lines 75-122)

**T018: Implement Admin Bar DOM Update Logic - JavaScript**
- 📖 Research: Section 3 (lines 265-378) - DOM manipulation
- 📝 Pattern: research.md lines 315-339 - DOM update implementation
- 📊 Performance: <100ms for DOM updates
- 📋 Model: data-model.md Model 5 (lines 227-260) - Admin Bar State
- ✅ Test: quickstart.md Scenario 2 (lines 75-122)

**T019: Add Admin Bar CSS for Badge Styling**
- 📖 Research: Section 3 (lines 287-299) - Badge HTML structure
- WordPress Standard: Match core update badge styling
- Integration: Works with T018 badge creation

**T020: Verify Admin Bar Nonce in Localized Script**
- 📖 Research: Section 4 (lines 398-404) - Nonce localization pattern
- Dependencies: T009 (nonce validation fix)
- Validates: JavaScript has access to nonce for polling

**T021: Integration Test - Admin Bar Refresh**
- ✅ Test: quickstart.md Scenario 2 (lines 75-122)
- Validates: T017, T018, T019, T020 integration

---

### Phase 5: Update Status Synchronization (T022-T026)

**T022: [RESERVED - Currently skipped in numbering]**

**T023: Add Update Completion Transient**
- 📖 Research: Section 2 - Transient coordination
- Integration: Coordinates with T011 cache invalidation
- Purpose: Signal successful update to all interfaces

**T024: Synchronize Update Status Across Interfaces**
- 📖 Research: Section 2 (lines 137-263) - Transient synchronization
- ✅ Test: quickstart.md Scenario 3 (lines 125-165) + Scenario 5 (lines 250-292)
- 📋 Model: data-model.md Model 1 (lines 29-78) - Update Status
- Integration Points:
  - Admin Bar: `includes/admin/class-cuft-admin-bar.php`
  - Admin Notices: `includes/admin/class-cuft-admin-notices.php`
  - WordPress Updater: `includes/class-cuft-wordpress-updater.php`
  - GitHub Updater: `includes/class-cuft-github-updater.php`

**T025: Integration Test - Status Synchronization**
- ✅ Test: quickstart.md Scenario 3 (lines 125-165) + Scenario 5 (lines 250-292)
- Validates: T024 implementation

**T026: Performance Test - Update Check Timing**
- 📊 Targets:
  - Update check: <5 seconds (FR-008)
  - Status endpoint: <500ms
  - DOM updates: <100ms

---

### Phase 6: Integration Testing (T027-T033)

**T027: Integration Test - Admin Notice Positioning**
- ✅ Test: quickstart.md Scenario 1 (lines 22-72)
- Validates: T002, T003

**T028: Integration Test - Secure Update Button**
- ✅ Test: quickstart.md Scenario 4 (lines 168-247)
- Validates: T009 nonce validation

**T029: Integration Test - Update History FIFO**
- ✅ Test: quickstart.md Scenario 6 (lines 295-333)
- Validates: T007 FIFO implementation

**T030: Integration Test - Concurrent Updates**
- ✅ Test: quickstart.md Scenario 7 (lines 336-386)
- Validates: T006, T013

**T031: Full Update Flow Integration Test**
- Tests: Complete cycle (check → perform → monitor → complete)
- Validates: All AJAX endpoints working together

**T032: Manual Testing - Quickstart Scenarios**
- ✅ All Scenarios: quickstart.md lines 22-386
- Final validation before QA

**T033: Browser Compatibility Testing**
- Browsers: Chrome, Firefox, Safari, Edge
- Focus: Admin bar polling, DOM updates, AJAX

---

## File Map

### PHP Files to Modify

| File Path | Tasks | Current Issues | Research Ref |
|-----------|-------|----------------|--------------|
| `includes/admin/class-cuft-admin.php` | T002 | Missing `.wp-header-end` marker | research.md L24-32 |
| `includes/admin/class-cuft-admin-notices.php` | T003, T008 | Wrong positioning, line 42-44 exclusions | research.md L42-67, L84-110 |
| `includes/admin/class-cuft-admin-bar.php` | T019, T020 | CSS, nonce localization | research.md L287-299, L398-404 |
| `includes/ajax/class-cuft-updater-ajax.php` | T009, T012-T016 | Nonce validation (line 25, 66-78) | research.md L398-488 |
| `includes/class-cuft-wordpress-updater.php` | T010, T011, T023, T024 | Fixed timeout (lines 85-91) | research.md L222-247 |
| `includes/class-cuft-github-updater.php` | T024 | Cache coordination | - |
| `includes/models/class-cuft-update-status.php` | T004, T005 | Regular transients, fixed timeout | research.md L194-247 |
| `includes/models/class-cuft-update-progress.php` | T006 | Missing user_id tracking | research.md L499-524 |
| `includes/models/class-cuft-update-log.php` | T007 | Verify FIFO cleanup | research.md L622-692 |
| `uninstall.php` | T008 | Add dismissal cleanup | - |

### JavaScript Files to Modify

| File Path | Tasks | Current Issues | Research Ref |
|-----------|-------|----------------|--------------|
| `assets/admin/js/cuft-admin-bar.js` | T017, T018 | No polling, no DOM updates | research.md L303-339 |

### New Files to Create

| File Path | Tasks | Purpose |
|-----------|-------|---------|
| `tests/unit/test-update-log.php` | T007 | FIFO validation unit tests |
| `tests/unit/test-data-models.php` | T008a | All data model validation |
| `tests/integration/test-admin-bar-refresh.php` | T021 | Admin bar refresh testing |
| `tests/integration/test-status-synchronization.php` | T025 | Status sync validation |
| `tests/integration/test-admin-notice-positioning.php` | T027 | Notice positioning tests |
| `tests/integration/test-secure-update-button.php` | T028 | Nonce validation tests |
| `tests/integration/test-update-history-fifo.php` | T029 | FIFO cleanup tests |
| `tests/integration/test-concurrent-updates.php` | T030 | Concurrent handling tests |
| `tests/performance/test-update-check-performance.php` | T026 | Performance benchmarks |
| `specs/007-fix-update-system/migration-guide.md` | T039 | Migration guide (if needed) |

---

## Critical Path with References

```
T001 (validate tests)
  ↓
T004 (site transients) → research.md L219-220
  ↓
T005 (context-aware timeout) → research.md L194-201, L154-163
  ↓
T009 (nonce fix) → research.md L398-488
  ↓
T010 (context-aware checks) → research.md L235-247
  ↓
T011 (cache invalidation) → research.md L158
  ↓
T020 (nonce verification) → research.md L398-404
  ↓
T017 (polling) → research.md L303-313
  ↓
T018 (DOM updates) → research.md L315-339
  ↓
T024 (synchronization) → research.md L137-263
  ↓
T032 (manual tests) → quickstart.md
  ↓
T040 (final check)
```

---

## Usage Tips

### For Each Task

1. **Before starting**: Read all referenced research sections
2. **During implementation**: Refer to code patterns and examples
3. **After implementation**: Validate against quickstart scenarios
4. **When stuck**: Check "Current Issues" section in research

### Quick Lookups

**Need WordPress timing info?** → research.md lines 154-163
**Need nonce pattern?** → research.md lines 398-418
**Need FIFO code?** → research.md lines 622-692
**Need polling pattern?** → research.md lines 303-313
**Need context timeout code?** → research.md lines 194-201

### Common Questions

**Q: Where's the database schema for update log?**
A: research.md lines 605-620, data-model.md Model 3

**Q: How do I implement context-aware caching?**
A: research.md lines 194-201 (code), lines 154-163 (timing table)

**Q: What's the proper admin notice markup?**
A: research.md lines 42-67 (examples), lines 70-79 (CSS classes)

**Q: How do I test my changes?**
A: quickstart.md has 7 manual test scenarios with step-by-step instructions

---

**Last Updated**: 2025-10-07
**Maintained By**: Feature 007 implementation team
**Status**: Ready for use
