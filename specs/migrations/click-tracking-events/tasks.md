# Tasks: Click Tracking Events Migration

**Input**: Design documents from `/specs/migrations/click-tracking-events/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

## Execution Flow
```
1. Load plan.md from feature directory ✅
2. Load optional design documents ✅
   → spec.md: Feature requirements
   → research.md: Technical decisions
   → contracts/ajax-endpoint.md: AJAX endpoint contract
   → contracts/webhook-api.md: Webhook compatibility
   → quickstart.md: Testing scenarios
3. Generate tasks by category:
   → Setup: Migration class, backup utility
   → Tests: Contract tests (AJAX, webhook), integration tests
   → Core: Click tracker methods, AJAX handler
   → Integration: JavaScript event recording, admin UI
   → Polish: Performance validation, documentation
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Tests before implementation (TDD)
5. Number tasks sequentially (T001, T002...)
6. Return: Ready for execution
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions

## Path Conventions
WordPress plugin structure with files at repository root:
- `/includes/` - PHP classes
- `/assets/` - JavaScript files
- `/admin/` - Admin interface
- `/migrations/` - Database migrations

---

## Phase 3.1: Setup & Database Migration

### T001: Create Migration Class Structure ✅
**File**: `/home/r11/dev/choice-uft/includes/migrations/class-cuft-migration-3-12-0.php`
**Description**: Create migration class with up(), down(), and create_backup() methods. Implement schema changes (add events JSON column, add idx_date_updated index). Implement hybrid rollback strategy (restore schema, preserve qualified/score, discard events).
**Dependencies**: None
**Status**: COMPLETED

### T002: Create Migration Backup Utility ✅
**File**: `/home/r11/dev/choice-uft/includes/migrations/class-cuft-migration-3-12-0.php`
**Description**: Implement create_backup() method to create timestamped backup table before migration. Store backup table name in wp_options for rollback reference.
**Dependencies**: T001
**Status**: COMPLETED

### T003: Test Migration in Development Environment ✅
**Manual**: Run migration via WP-CLI or admin interface
**Description**: Execute migration up() in wp-pdev environment. Verify events column and index created. Test backup creation. Document any issues.
**Dependencies**: T002
**Status**: COMPLETED
**Results**:
- ✅ Events column created with JSON type
- ✅ idx_date_updated index created successfully
- ✅ Backup table created: wp_cuft_click_tracking_backup_20250930_020533
- ✅ Migration status stored in wp_options

---

## Phase 3.2: Tests First (TDD) ⚠️ MUST COMPLETE BEFORE 3.3

**CRITICAL: These tests MUST be written and MUST FAIL before ANY implementation**

### T004: [P] Contract Test - AJAX Endpoint Valid Event ✅
**File**: `/home/r11/dev/choice-uft/tests/test-ajax-endpoint.php`
**Description**: Create PHPUnit test for valid event recording. Test POST to `cuft_record_event` with valid nonce, click_id, and event_type. Assert success response with event_count. Verify event recorded in database.
**Contract**: `/specs/migrations/click-tracking-events/contracts/ajax-endpoint.md`
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T013 implemented

### T005: [P] Contract Test - AJAX Endpoint Security ✅
**File**: `/home/r11/dev/choice-uft/tests/test-ajax-security.php`
**Description**: Create PHPUnit tests for security validation. Test invalid nonce (expect 403), invalid event_type (expect 400), missing click_id (expect 400). Test event type whitelist enforcement.
**Contract**: `/specs/migrations/click-tracking-events/contracts/ajax-endpoint.md`
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T013 implemented

### T006: [P] Contract Test - AJAX Event Deduplication ✅
**File**: `/home/r11/dev/choice-uft/tests/test-ajax-deduplication.php`
**Description**: Create PHPUnit test for event deduplication. Record phone_click twice with same click_id. Assert only one event exists with latest timestamp. Verify date_updated reflects most recent event.
**Contract**: `/specs/migrations/click-tracking-events/contracts/ajax-endpoint.md`
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T011-T012 implemented

### T007: [P] Contract Test - AJAX FIFO Cleanup ✅
**File**: `/home/r11/dev/choice-uft/tests/test-ajax-fifo.php`
**Description**: Create PHPUnit test for 100-event limit. Record 105 events for single click_id. Assert final count is 100. Verify oldest events removed (FIFO). Check date_updated reflects newest event.
**Contract**: `/specs/migrations/click-tracking-events/contracts/ajax-endpoint.md`
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T011-T012 implemented

### T008: [P] Contract Test - Webhook Backward Compatibility ✅
**File**: `/home/r11/dev/choice-uft/tests/test-webhook-compatibility.php`
**Description**: Create PHPUnit tests for webhook API. Test GET/POST with valid key updates qualified/score. Verify response format unchanged. Test error responses (invalid key, missing click_id, invalid score). Assert 100% backward compatibility.
**Contract**: `/specs/migrations/click-tracking-events/contracts/webhook-api.md`
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T014 implemented

### T009: [P] Integration Test - Phone Click Event Recording ✅
**File**: `/home/r11/dev/choice-uft/tests/test-phone-click-integration.php`
**Description**: Create integration test simulating phone link click. Mock JavaScript fetch to AJAX endpoint. Verify phone_click event recorded. Test with and without existing click_id. Validate error isolation (no JavaScript errors on failure).
**Scenario**: quickstart.md Step 3
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T013-T016 implemented

### T010: [P] Integration Test - Form Submit Event Recording ✅
**File**: `/home/r11/dev/choice-uft/tests/test-form-submit-integration.php`
**Description**: Create integration test simulating form submission. Mock Elementor/CF7/Ninja/Gravity/Avada form success. Verify form_submit event recorded. Test generate_lead event when email+phone+click_id present.
**Scenario**: quickstart.md Step 4
**Dependencies**: None
**Status**: COMPLETED - Tests will fail until T013-T021 implemented

---

## Phase 3.3: Core Implementation (ONLY after tests are failing)

### T011: Add Event Methods to Click Tracker
**File**: `/home/r11/dev/choice-uft/includes/class-cuft-click-tracker.php`
**Description**: Implement add_event($click_id, $event_type) method. Use JSON_ARRAY_APPEND for new events. Implement event deduplication (update timestamp for duplicate event types). Update date_updated to latest event timestamp. Add get_events($click_id) method to retrieve event array.
**Dependencies**: T001 (migration complete), T004-T007 (tests failing)

### T012: Implement FIFO Event Cleanup
**File**: `/home/r11/dev/choice-uft/includes/class-cuft-click-tracker.php`
**Description**: Add enforce_event_limit($click_id) method. Check JSON_LENGTH, if >100 remove oldest events. Sort by timestamp, keep newest 100. Log cleanup in debug mode only.
**Dependencies**: T011

### T013: Create AJAX Event Recorder Handler
**File**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-event-recorder.php`
**Description**: Create AJAX handler class with record_event() method. Register wp_ajax and wp_ajax_nopriv hooks. Implement nonce validation (check_ajax_referer). Sanitize click_id and event_type inputs. Validate event_type against whitelist. Call CUFT_Click_Tracker::add_event(). Return JSON response with event_count.
**Contract**: `/specs/migrations/click-tracking-events/contracts/ajax-endpoint.md`
**Dependencies**: T011 (add_event available), T004-T005 (tests failing)

### T014: Add Webhook Event Recording
**File**: `/home/r11/dev/choice-uft/includes/class-cuft-webhook-handler.php`
**Description**: Update existing webhook handler. After successful update, record status_qualified event if qualified=1. Record score_updated event if score increased. Wrap event recording in try-catch (never break webhook). Maintain 100% backward compatibility.
**Contract**: `/specs/migrations/click-tracking-events/contracts/webhook-api.md`
**Dependencies**: T011 (add_event available), T008 (tests failing)

### T015: Register AJAX Endpoint and Enqueue Nonce
**File**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
**Description**: Instantiate CUFT_Event_Recorder class in plugin initialization. Enqueue cuftConfig JavaScript object with ajaxUrl and nonce. Use wp_localize_script to pass config to all scripts. Set nonce action to 'cuft-event-recorder'.
**Dependencies**: T013 (AJAX handler exists)

---

## Phase 3.4: JavaScript Integration

### T016: [P] Add Event Recording to cuft-links.js
**File**: `/home/r11/dev/choice-uft/assets/links/cuft-links.js`
**Description**: Create recordEvent(clickId, eventType) function using fetch API fire-and-forget pattern. Add try-catch wrappers for error isolation. Hook into existing phone link click handler to record phone_click event. Hook into existing email link click handler to record email_click event. Silent failures in production, log in debug mode only.
**Dependencies**: T015 (AJAX endpoint registered), T009 (test failing)

### T017: [P] Add Event Recording to cuft-elementor-forms.js
**File**: `/home/r11/dev/choice-uft/assets/forms/cuft-elementor-forms.js`
**Description**: Import shared recordEvent() function. Hook into existing submit_success handler. After dataLayer push, call recordEvent(clickId, 'form_submit'). Extract click_id from form data or UTM storage. Never block form submission.
**Dependencies**: T015 (AJAX endpoint registered), T010 (test failing)

### T018: [P] Add Event Recording to cuft-cf7-forms.js
**File**: `/home/r11/dev/choice-uft/assets/forms/cuft-cf7-forms.js`
**Description**: Import shared recordEvent() function. Hook into existing wpcf7mailsent handler. After dataLayer push, call recordEvent(clickId, 'form_submit'). Extract click_id from form data or UTM storage. Never block form submission.
**Dependencies**: T015 (AJAX endpoint registered), T010 (test failing)

### T019: [P] Add Event Recording to cuft-ninja-forms.js
**File**: `/home/r11/dev/choice-uft/assets/forms/cuft-ninja-forms.js`
**Description**: Import shared recordEvent() function. Hook into existing submit handler. After dataLayer push, call recordEvent(clickId, 'form_submit'). Extract click_id from form data or UTM storage. Never block form submission.
**Dependencies**: T015 (AJAX endpoint registered), T010 (test failing)

### T020: [P] Add Event Recording to cuft-gravity-forms.js
**File**: `/home/r11/dev/choice-uft/assets/forms/cuft-gravity-forms.js`
**Description**: Import shared recordEvent() function. Hook into existing submit handler. After dataLayer push, call recordEvent(clickId, 'form_submit'). Extract click_id from form data or UTM storage. Never block form submission.
**Dependencies**: T015 (AJAX endpoint registered), T010 (test failing)

### T021: [P] Add Event Recording to cuft-avada-forms.js
**File**: `/home/r11/dev/choice-uft/assets/forms/cuft-avada-forms.js`
**Description**: Import shared recordEvent() function. Hook into existing submit handler. After dataLayer push, call recordEvent(clickId, 'form_submit'). Extract click_id from form data or UTM storage. Never block form submission.
**Dependencies**: T015 (AJAX endpoint registered), T010 (test failing)

---

## Phase 3.5: Admin Interface

### T022: Add Events Timeline Column to Admin Table
**File**: `/home/r11/dev/choice-uft/admin/class-cuft-admin.php`
**Description**: Add events column to click tracking admin table. Display events as timeline (event type + timestamp). Sort events chronologically (newest first). Add CSS for event badges (different colors per event type). Remove utm_source and platform columns from display.
**Dependencies**: T011 (get_events method available)

### T023: Add Event Type Filter to Admin Interface
**File**: `/home/r11/dev/choice-uft/admin/class-cuft-admin.php`
**Description**: Add dropdown filter for event types (phone_click, email_click, form_submit, generate_lead). Filter records using JSON_CONTAINS query. Add "Last Activity" sort option using date_updated index.
**Dependencies**: T022 (events column displayed)

### T024: Implement Feature Flag Controls
**File**: `/home/r11/dev/choice-uft/admin/class-cuft-admin.php`
**Description**: Add feature flag toggles to admin settings page. Create cuft_click_event_tracking_enabled option (enable/disable event recording). Create cuft_click_event_tracking_display option (show/hide events in admin). Implement shadow mode (write events when enabled=true, display=false). Add explanatory text for shadow mode testing.
**Dependencies**: T022 (admin interface exists)

---

## Phase 3.6: Integration Testing & Validation

### T025: Execute Quickstart Validation - Database Schema
**Manual**: Follow quickstart.md Steps 1-2
**Description**: Verify events column exists with JSON type. Verify idx_date_updated index created. Verify platform and utm_source columns removed. Document any schema discrepancies.
**Scenario**: quickstart.md Step 1
**Dependencies**: T001 (migration complete)

### T026: Execute Quickstart Validation - AJAX Endpoint
**Manual**: Follow quickstart.md Step 2
**Description**: Test AJAX endpoint via browser console. Verify valid event recording succeeds. Test invalid nonce/event_type errors. Verify event appears in database. Document response times.
**Scenario**: quickstart.md Step 2
**Dependencies**: T013 (AJAX handler complete)

### T027: Execute Quickstart Validation - Phone/Email Links
**Manual**: Follow quickstart.md Step 3
**Description**: Test phone link click records phone_click event. Test email link click records email_click event. Verify events in database with correct timestamps. Test error isolation (broken AJAX doesn't break links).
**Scenario**: quickstart.md Step 3
**Dependencies**: T016 (cuft-links.js complete)

### T028: Execute Quickstart Validation - Form Submissions
**Manual**: Follow quickstart.md Step 4
**Description**: Test form submission records form_submit event. Test generate_lead fires when email+phone+click_id present. Verify events across all frameworks (Elementor, CF7, Ninja, Gravity, Avada). Check dataLayer events still fire correctly.
**Scenario**: quickstart.md Step 4
**Dependencies**: T017-T021 (all form scripts complete)

### T029: Execute Quickstart Validation - Event Deduplication
**Manual**: Follow quickstart.md Step 5
**Description**: Record duplicate phone_click events. Verify only one event exists with latest timestamp. Test deduplication across all event types. Verify date_updated reflects most recent event.
**Scenario**: quickstart.md Step 5
**Dependencies**: T012 (deduplication implemented)

### T030: Execute Quickstart Validation - Admin Interface
**Manual**: Follow quickstart.md Step 6
**Description**: Verify events timeline displays correctly. Test event type filtering. Test last activity sorting. Verify event badges styled correctly. Check responsive design on mobile.
**Scenario**: quickstart.md Step 6
**Dependencies**: T022-T023 (admin UI complete)

### T031: Execute Quickstart Validation - Webhook Compatibility
**Manual**: Follow quickstart.md Step 7
**Description**: Test webhook updates via curl. Verify qualified/score updates work unchanged. Verify status_qualified and score_updated events recorded. Confirm response format unchanged (backward compatibility). Test error responses (invalid key, missing click_id).
**Scenario**: quickstart.md Step 7
**Dependencies**: T014 (webhook events complete)

### T032: Execute Quickstart Validation - FIFO Cleanup
**Manual**: Follow quickstart.md Step 8
**Description**: Record 105 events for single click_id. Verify event count capped at 100. Verify oldest events removed (FIFO). Check performance of JSON_LENGTH operations. Document cleanup timing.
**Scenario**: quickstart.md Step 8 (Optional)
**Dependencies**: T012 (FIFO cleanup implemented)

---

## Phase 3.7: Performance & Polish

### T033: [P] Benchmark JSON Operations Performance
**Manual**: Use MySQL EXPLAIN and timing
**Description**: Benchmark JSON_ARRAY_APPEND operations (<12ms target). Benchmark JSON_LENGTH checks (<5ms target). Benchmark JSON_EXTRACT queries with idx_date_updated. Verify aggregate overhead <10%. Document results in performance log.
**Dependencies**: T011 (event methods complete)

### T034: [P] Benchmark AJAX Endpoint Performance
**Manual**: Use browser DevTools Network tab
**Description**: Measure AJAX endpoint response times (<100ms p95 target). Test with varying event counts (1, 50, 100 events). Measure fire-and-forget latency. Verify non-blocking behavior. Document results.
**Dependencies**: T013 (AJAX handler complete)

### T035: [P] Benchmark Admin Table Load Performance
**Manual**: Use WordPress Query Monitor plugin
**Description**: Measure admin table load time with events displayed. Test with records having 1, 50, 100 events each. Verify acceptable performance (<500ms page load). Identify slow queries. Optimize if needed.
**Dependencies**: T022 (admin UI complete)

### T036: Test Rollback Procedure
**Manual**: Execute migration down() method
**Description**: Trigger migration rollback in wp-pdev environment. Verify events column removed. Verify idx_date_updated index removed. Verify qualified/score values preserved (hybrid rollback). Test backup restoration if needed. Document rollback timing and success.
**Scenario**: quickstart.md Rollback Testing
**Dependencies**: T002 (backup utility complete)

### T037: Update CLAUDE.md with Migration Status
**File**: `/home/r11/dev/choice-uft/CLAUDE.md`
**Description**: Update "Active Migration" section to "Completed Migration". Document final implementation details. Add troubleshooting tips from testing. Update version to 3.12.0. Mark all tasks as completed.
**Dependencies**: T025-T032 (all validation complete)

### T038: Update CHANGELOG.md for v3.12.0
**File**: `/home/r11/dev/choice-uft/CHANGELOG.md`
**Description**: Add v3.12.0 entry with feature summary. Document database changes (events column, indexes). List new AJAX endpoint. Note backward compatibility preserved. Include migration instructions.
**Dependencies**: T037 (CLAUDE.md updated)

---

## Dependencies

### Critical Path
```
T001 (Migration) → T002 (Backup) → T003 (Test Migration)
  ↓
T004-T010 (All Tests - parallel, must FAIL)
  ↓
T011 (Click Tracker) → T012 (FIFO) → T013 (AJAX Handler) → T015 (Register Endpoint)
  ↓                      ↓
T014 (Webhook)       T016-T021 (JavaScript - parallel)
  ↓                      ↓
T022 (Admin UI) → T023 (Filters) → T024 (Feature Flags)
  ↓
T025-T032 (Validation - sequential)
  ↓
T033-T036 (Performance - parallel) → T037 (Docs) → T038 (Changelog)
```

### Blocking Relationships
- **T001 blocks**: T002, T003, T004-T010 (need schema)
- **T004-T010 block**: T011-T014 (TDD - tests must fail first)
- **T011 blocks**: T012, T013, T014, T022
- **T013 blocks**: T015
- **T015 blocks**: T016-T021
- **T022 blocks**: T023, T024, T030
- **T012 blocks**: T029, T032
- **T016-T021 block**: T027, T028
- **T025-T032 block**: T033-T036 (validate before optimization)

### Parallel Opportunities
- **T004-T010**: All test files (different files, no dependencies)
- **T016-T021**: All JavaScript files (different files, independent)
- **T017-T021**: All form framework scripts (different files)
- **T033-T035**: All performance benchmarks (different focus areas)

---

## Parallel Execution Examples

### Launch All Contract Tests Together (Phase 3.2)
```bash
# After T001-T003 complete, launch all tests in parallel
# These MUST fail before implementation begins
Task: "Create contract test for AJAX valid event recording in tests/test-ajax-endpoint.php"
Task: "Create contract test for AJAX security validation in tests/test-ajax-security.php"
Task: "Create contract test for AJAX event deduplication in tests/test-ajax-deduplication.php"
Task: "Create contract test for AJAX FIFO cleanup in tests/test-ajax-fifo.php"
Task: "Create contract test for webhook backward compatibility in tests/test-webhook-compatibility.php"
Task: "Create integration test for phone click events in tests/test-phone-click-integration.php"
Task: "Create integration test for form submit events in tests/test-form-submit-integration.php"
```

### Launch All JavaScript Updates Together (Phase 3.4)
```bash
# After T015 complete, launch all JavaScript updates in parallel
Task: "Add event recording to phone/email links in assets/links/cuft-links.js"
Task: "Add form_submit recording to Elementor forms in assets/forms/cuft-elementor-forms.js"
Task: "Add form_submit recording to CF7 forms in assets/forms/cuft-cf7-forms.js"
Task: "Add form_submit recording to Ninja forms in assets/forms/cuft-ninja-forms.js"
Task: "Add form_submit recording to Gravity forms in assets/forms/cuft-gravity-forms.js"
Task: "Add form_submit recording to Avada forms in assets/forms/cuft-avada-forms.js"
```

### Launch All Performance Benchmarks Together (Phase 3.7)
```bash
# After T025-T032 complete, launch all benchmarks in parallel
Task: "Benchmark JSON operations performance (<12ms target)"
Task: "Benchmark AJAX endpoint performance (<100ms target)"
Task: "Benchmark admin table load performance (<500ms target)"
```

---

## Task Execution Notes

### Test-Driven Development (TDD)
1. **Phase 3.2 MUST complete first**: All tests written and failing
2. **Phase 3.3 makes tests pass**: Implement code to satisfy tests
3. **Never implement without failing tests**: Ensures tests are valid

### Error Isolation Pattern
All JavaScript event recording must follow:
```javascript
try {
    fetch(cuftConfig.ajaxUrl, {
        method: 'POST',
        body: formData
    }).catch(error => {
        if (cuftConfig.debug) console.warn('Event recording failed:', error);
    });
} catch (error) {
    if (cuftConfig.debug) console.error('Event recording exception:', error);
}
```

### Constitutional Compliance
- ✅ JavaScript-First: Use fetch(), jQuery fallback
- ✅ DataLayer Standards: snake_case, cuft_tracked, cuft_source
- ✅ Framework Compatibility: Silent exits, no interference
- ✅ Event Firing Rules: Unchanged for form_submit/generate_lead
- ✅ Error Handling: Try-catch wrappers, fallback chains
- ✅ Performance: <10% overhead, <100ms operations
- ✅ Security: Nonce validation, input sanitization, whitelists

### Commit Strategy
- Commit after each task completion
- Commit message format: `feat: [Task ID] - [Brief description]`
- Example: `feat: T011 - Add event methods to click tracker`

---

## Validation Checklist

### Design Coverage
- [x] All contracts have corresponding tests (T004-T008)
- [x] All entities have implementation tasks (CUFT_Click_Tracker, CUFT_Event_Recorder)
- [x] All tests come before implementation (T004-T010 before T011-T014)
- [x] All quickstart scenarios have validation tasks (T025-T032)

### Task Quality
- [x] Parallel tasks truly independent (different files, no shared state)
- [x] Each task specifies exact file path
- [x] No task modifies same file as another [P] task
- [x] Dependencies clearly documented
- [x] Performance targets specified (<10% overhead, <100ms operations)

### Constitutional Alignment
- [x] JavaScript-First approach maintained (fetch primary, jQuery fallback)
- [x] DataLayer standards preserved (snake_case, required fields)
- [x] Framework compatibility maintained (silent exits, no interference)
- [x] Error handling comprehensive (try-catch, fallback chains)
- [x] Security measures implemented (nonce, sanitization, validation)

---

**Total Tasks**: 38 numbered, dependency-ordered tasks
**Parallelizable**: ~50% of tasks marked with [P]
**Critical Path**: Migration → Tests → Core → JavaScript → Admin → Validation → Polish
**Estimated Effort**: 3-5 days for full implementation
**Constitutional Compliance**: ✅ All principles maintained

---

*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
*Tasks generated by /tasks command on 2025-09-29*
*Ready for execution following Test-Driven Development (TDD) approach*