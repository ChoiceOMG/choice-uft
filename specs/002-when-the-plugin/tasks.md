# Tasks: Admin Testing Dashboard

**Input**: Design documents from `/home/r11/dev/choice-uft/specs/002-when-the-plugin/`
**Prerequisites**: plan.md, research.md, data-model.md, contracts/ajax-endpoints.md, quickstart.md
**Branch**: `002-when-the-plugin`

## Overview

This task list implements an admin-only testing dashboard for the Choice Universal Form Tracker plugin. The dashboard enables WordPress administrators to generate test data, simulate tracking events, create test forms, and validate dataLayer events without affecting production analytics.

**Tech Stack**: PHP 7.0+, JavaScript ES6+, WordPress 5.0+, MySQL
**Performance Target**: <500ms for all operations
**Security**: Admin-only (manage_options), nonce validation, multi-layer security

---

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- All file paths are absolute from repository root: `/home/r11/dev/choice-uft/`

---

## Phase 3.1: Database & Infrastructure Setup

- [x] **T001** Create test events database table schema using dbDelta
  - File: `/home/r11/dev/choice-uft/includes/database/class-cuft-test-events-table.php`
  - Schema: id, session_id, event_type, event_data (JSON), test_mode, created_at
  - Indexes: session_id, event_type, created_at
  - Version tracking in wp_options (`cuft_test_events_db_version`)
  - Methods: create_table(), maybe_update(), drop_table()

- [x] **T002** Register table creation on plugin activation
  - File: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
  - Hook: register_activation_hook()
  - Call: CUFT_Test_Events_Table::maybe_update()
  - Ensure table created/updated on activation

---

## Phase 3.2: Admin Page Registration

- [x] **T003** Create admin testing dashboard class
  - File: `/home/r11/dev/choice-uft/includes/admin/class-cuft-testing-dashboard.php`
  - Register admin menu: add_options_page() with manage_options capability
  - Menu: Settings → Testing Dashboard
  - Render method: render_dashboard()
  - Enqueue scripts/styles for dashboard page only

- [x] **T004** Create dashboard view template
  - File: `/home/r11/dev/choice-uft/includes/admin/views/testing-dashboard.php`
  - Layout: WordPress .wrap, .card containers
  - Sections: Test Data Generator, Event Simulator, Test Form Builder, Event Viewer
  - Include nonce fields for security
  - Framework dropdown (Elementor, CF7, Ninja, Gravity, Avada)

- [x] **T005** Create dashboard CSS styling
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-testing-dashboard.css`
  - Use WordPress admin CSS classes (.wrap, .card, .button-primary)
  - Responsive design for mobile
  - Event viewer table styling
  - Loading indicators and status messages

---

## Phase 3.3: AJAX Endpoints (Backend)

- [x] **T006** [P] Create test data generator AJAX handler
  - File: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-test-data-generator.php`
  - Endpoint: wp_ajax_cuft_generate_test_data
  - Security: check_ajax_referer(), current_user_can('manage_options')
  - Generate: Click IDs (gclid, fbclid, msclkid, ttclid, etc.)
  - Generate: UTM parameters (source, medium, campaign, term, content)
  - Generate: Test email (test+{uniqid}@example.com)
  - Generate: Test phone (555-01XX format)
  - Return: JSON with session_id, clickIds, utmParams, contacts
  - Performance: <500ms

- [x] **T007** [P] Create event simulator AJAX handler
  - File: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-event-simulator.php`
  - Endpoint: wp_ajax_cuft_simulate_event
  - Security: nonce + capability check
  - Event types: phone_click, email_click, form_submit, generate_lead
  - Build event: cuft_tracked=true, cuft_source="testing_dashboard", test_mode=true
  - Save to database: CUFT_Test_Events_Table::insert_event()
  - Return: Complete event object + db_id
  - Performance: <500ms

- [x] **T008** [P] Create test form builder AJAX handler
  - File: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-test-form-builder.php`
  - Endpoint: wp_ajax_cuft_build_test_form
  - Security: nonce + capability check
  - Gravity Forms: Use GFAPI::add_form() for dynamic creation
  - Other frameworks: Return pre-built form IDs from wp_options
  - Pre-populate fields with test session data
  - Return: form_id, shortcode, rendered HTML
  - Performance: <500ms

- [x] **T009** [P] Create test events retrieval AJAX handler
  - File: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-test-events-ajax.php`
  - Endpoint: wp_ajax_cuft_get_test_events
  - Security: nonce + capability check
  - Filters: session_id, event_type, limit, offset
  - Query: CUFT_Test_Events_Table::get_events_by_session()
  - Return: Paginated events array with total count

- [x] **T010** [P] Create test events deletion AJAX handler
  - File: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-test-events-ajax.php` (same file as T009)
  - Endpoint: wp_ajax_cuft_delete_test_events
  - Security: nonce + capability check
  - Options: Delete by IDs, by session, or all test events
  - Call: CUFT_Test_Events_Table::delete() methods
  - Return: deleted_count, success message

---

## Phase 3.4: JavaScript Client-Side (Core Modules)

- [x] **T011** [P] Create test data storage manager (localStorage)
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-test-data-manager.js`
  - Class: CUFTTestDataStorage
  - Methods: saveSession(), getLatestSession(), getAllSessions(), clearAll()
  - FIFO enforcement: max 50 sessions
  - TTL cleanup: 24 hours
  - Storage key: 'cuft_test_sessions'
  - Error handling: QuotaExceededError, storage unavailable

- [x] **T012** [P] Create dataLayer event monitor
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-datalayer-monitor.js`
  - Class: CUFTDataLayerMonitor
  - Intercept: window.dataLayer.push()
  - Filter: test_mode events vs all events
  - FIFO limit: 100 events max
  - Methods: onEvent(), getTestEvents(), getCUFTEvents(), clearEvents()
  - Preserve GTM functionality (non-breaking intercept)

- [x] **T013** [P] Create event validator
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-event-validator.js`
  - Class: CUFTEventValidator
  - Validate: Required fields (cuft_tracked, cuft_source, test_mode, event)
  - Validate: snake_case naming (detect camelCase violations)
  - Validate: Data types (booleans, strings)
  - Return: EventValidationResult with errors array
  - Performance: <500ms

- [x] **T014** [P] Create AJAX client wrapper
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-ajax-client.js`
  - Class: CUFTAjaxClient
  - Methods: generateTestData(), simulateEvent(), buildTestForm(), getTestEvents(), deleteTestEvents()
  - Error handling: Try-catch, user-friendly messages
  - Nonce included in all requests
  - Uses fetch() API (vanilla JS, no jQuery)

---

## Phase 3.5: JavaScript Client-Side (Dashboard UI)

- [x] **T015** Create main dashboard controller
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-testing-dashboard.js`
  - Initialize all modules on DOM ready
  - Event handlers: Generate Data, Simulate Events, Build Form buttons
  - UI updates: Display generated data, show events, render forms
  - Error display: User-friendly notices (.notice-error, .notice-success)
  - Integrates: CUFTTestDataStorage, CUFTDataLayerMonitor, CUFTEventValidator, CUFTAjaxClient

- [x] **T016** Implement event viewer UI component
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-testing-dashboard.js` (same as T015)
  - Real-time event display in table (.wp-list-table)
  - Filter toggle: Test events only vs All events
  - Event details: Expandable JSON view
  - Validation indicator: Green checkmark (valid) / Red X (invalid)
  - Performance: Updates in <500ms

- [x] **T017** Implement test form renderer
  - File: `/home/r11/dev/choice-uft/assets/admin/cuft-testing-dashboard.js` (same as T015)
  - Receive form HTML from AJAX
  - Render in dashboard container
  - Attach event listeners for form submission
  - Pre-populated fields display
  - Edit field values button

---

## Phase 3.6: Integration & Glue Code

- [x] **T018** Register all AJAX handlers in plugin initialization
  - File: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
  - Instantiate: CUFT_Test_Data_Generator, CUFT_Event_Simulator, etc.
  - Hook: init action
  - Ensure all wp_ajax_* actions registered

- [x] **T019** Enqueue scripts and styles with localization
  - File: `/home/r11/dev/choice-uft/includes/admin/class-cuft-testing-dashboard.php`
  - Hook: admin_enqueue_scripts (only on testing dashboard page)
  - Scripts: cuft-testing-dashboard.js (depends on: test-data-manager, datalayer-monitor, event-validator, ajax-client)
  - Styles: cuft-testing-dashboard.css
  - Localize: wp_localize_script with ajaxUrl, nonce, debug flag
  - Variable: cuftConfig = { ajaxUrl, nonce, debug }

- [x] **T020** Create test events table CRUD methods
  - File: `/home/r11/dev/choice-uft/includes/database/class-cuft-test-events-table.php`
  - insert_event($session_id, $event_type, $event_data): Insert row, return ID
  - get_events_by_session($session_id): Get all events for session
  - get_events($filters): Get with filters (type, limit, offset)
  - delete_by_id($ids): Delete specific events
  - delete_by_session($session_id): Delete all from session
  - delete_all(): Truncate table
  - cleanup_old_events($days = 30): Auto-cleanup

---

## Phase 3.7: Testing & Validation

- [x] **T021** [P] Test admin page access control
  - File: `/home/r11/dev/choice-uft/tests/integration/test-admin-page-access.php`
  - Test: Admin user can access dashboard
  - Test: Non-admin redirected with "Access Denied"
  - Test: Nonce validation on form submissions
  - Assert: Page renders without errors

- [x] **T022** [P] Test AJAX generate test data endpoint
  - File: `/home/r11/dev/choice-uft/tests/integration/test-ajax-generate-data.php`
  - Mock: Admin user with manage_options
  - Call: wp_ajax_cuft_generate_test_data
  - Assert: Response contains clickIds, utmParams, testEmail, testPhone
  - Assert: Click IDs match format patterns
  - Assert: Response time <500ms

- [x] **T023** [P] Test AJAX simulate event endpoint
  - File: `/home/r11/dev/choice-uft/tests/integration/test-ajax-simulate-event.php`
  - Mock: Admin user
  - Call: wp_ajax_cuft_simulate_event with event_type='form_submit'
  - Assert: Event object returned with cuft_tracked=true, test_mode=true
  - Assert: Event saved to test_events table
  - Assert: Response time <500ms

- [x] **T024** [P] Test database table creation and CRUD
  - File: `/home/r11/dev/choice-uft/tests/integration/test-test-events-table.php`
  - Test: Table created on maybe_update()
  - Test: insert_event() returns ID
  - Test: get_events_by_session() returns correct events
  - Test: delete_all() truncates table
  - Test: cleanup_old_events() removes old data

- [x] **T025** Execute manual quickstart validation
  - File: `/home/r11/dev/choice-uft/specs/002-when-the-plugin/quickstart.md`
  - Complete all 11 test steps manually
  - Verify: Test data generation, event simulation, form building
  - Verify: dataLayer events, event validation, database storage
  - Verify: Performance (<500ms), UI responsiveness
  - Document: Any issues or edge cases discovered

---

## Phase 3.8: Polish & Documentation

- [x] **T026** [P] Add inline PHP documentation (PHPDoc)
  - Files: All PHP classes created in this feature
  - Document: Class purpose, method parameters, return types
  - Examples: AJAX handler usage patterns
  - Follow: WordPress Coding Standards

- [x] **T027** [P] Add inline JavaScript documentation (JSDoc)
  - Files: All JavaScript modules created in this feature
  - Document: Class methods, parameters, return values
  - Examples: Usage patterns for CUFTDataLayerMonitor, CUFTEventValidator
  - Follow: JavaScript documentation standards

- [x] **T028** Performance benchmarking
  - Test: AJAX endpoints under typical load
  - Measure: Response times for all operations
  - Assert: All operations <500ms (NFR-001, NFR-002, NFR-003)
  - Tools: Browser DevTools Performance tab, PHP profiling
  - Document: Performance metrics in quickstart.md

- [x] **T029** Cross-framework form testing
  - Test: Build test form for each framework (Elementor, CF7, Ninja, Gravity, Avada)
  - Verify: Form renders correctly, fields pre-populated
  - Verify: Form submission triggers production tracking events
  - Verify: Events match framework-specific format
  - Document: Framework compatibility matrix

- [x] **T030** Event isolation verification
  - Test: test_mode flag in all simulated events
  - Test: Events stored in separate test_events table
  - Test: Event viewer filter (test-only vs all)
  - Test: No pollution of production click_tracking table
  - Verify: FR-028, FR-029, FR-030 satisfied

---

## Dependencies

### Critical Path
```
T001 (DB table) → T002 (Activation hook)
T003 (Admin page class) → T004 (View template) → T005 (CSS)
T001 → T006, T007, T008, T009, T010 (AJAX handlers need DB table)
T011, T012, T013, T014 → T015 (Dashboard controller needs modules)
T015 → T016, T017 (UI components need controller)
T018 (Registration) requires all AJAX handlers (T006-T010)
T019 (Enqueue) requires all JS files (T011-T017)
```

### Test Dependencies
```
T021-T024 require implementation complete (T001-T020)
T025 requires T021-T024 passing
T028-T030 require T025 complete
```

---

## Parallel Execution Examples

### AJAX Handlers (Backend)
```bash
# Can run T006, T007, T008, T009 in parallel (different files):
Task: "Create test data generator AJAX handler in includes/ajax/class-cuft-test-data-generator.php"
Task: "Create event simulator AJAX handler in includes/ajax/class-cuft-event-simulator.php"
Task: "Create test form builder AJAX handler in includes/ajax/class-cuft-test-form-builder.php"
```

### JavaScript Modules (Frontend)
```bash
# Can run T011, T012, T013, T014 in parallel (different files):
Task: "Create test data storage manager in assets/admin/cuft-test-data-manager.js"
Task: "Create dataLayer monitor in assets/admin/cuft-datalayer-monitor.js"
Task: "Create event validator in assets/admin/cuft-event-validator.js"
Task: "Create AJAX client wrapper in assets/admin/cuft-ajax-client.js"
```

### Integration Tests
```bash
# Can run T021, T022, T023, T024 in parallel (different test files):
Task: "Test admin page access in tests/integration/test-admin-page-access.php"
Task: "Test AJAX generate data in tests/integration/test-ajax-generate-data.php"
Task: "Test AJAX simulate event in tests/integration/test-ajax-simulate-event.php"
Task: "Test database CRUD in tests/integration/test-test-events-table.php"
```

### Documentation Tasks
```bash
# Can run T026, T027 in parallel (different file sets):
Task: "Add PHPDoc to all PHP classes"
Task: "Add JSDoc to all JavaScript modules"
```

---

## Task Execution Notes

### TDD Approach
- Tests (T021-T024) should be written to validate contracts
- Tests may initially fail (expected) before implementation complete
- Run tests after each major implementation task

### WordPress Integration
- Use existing CUFT infrastructure patterns (similar to CUFT_Event_Recorder)
- Follow WordPress Coding Standards throughout
- Test in wp-pdev Docker environment (http://localhost:8080)

### Constitutional Compliance
Every task must ensure:
- ✅ JavaScript-First (vanilla JS, no jQuery dependency)
- ✅ DataLayer Standardization (snake_case, cuft_tracked, cuft_source)
- ✅ Error Handling (try-catch, graceful degradation)
- ✅ Security (nonce validation, capability checks, input sanitization)
- ✅ Performance (<500ms response times)

### File Locations Reference
- **PHP Classes**: `/home/r11/dev/choice-uft/includes/{category}/class-cuft-*.php`
- **JavaScript**: `/home/r11/dev/choice-uft/assets/admin/cuft-*.js`
- **CSS**: `/home/r11/dev/choice-uft/assets/admin/cuft-*.css`
- **Tests**: `/home/r11/dev/choice-uft/tests/integration/test-*.php`
- **Views**: `/home/r11/dev/choice-uft/includes/admin/views/*.php`

---

## Validation Checklist

- [x] All AJAX endpoints have security checks (nonce + capability)
- [x] All JavaScript uses vanilla JS (no jQuery)
- [x] All dataLayer events use snake_case naming
- [x] All tasks specify exact file paths
- [x] Parallel tasks are truly independent (different files)
- [x] Tests before implementation (TDD)
- [x] Performance targets documented (<500ms)
- [x] Constitutional principles referenced

---

## Success Criteria

**Feature Complete When**:
- ✅ All 30 tasks completed
- ✅ All integration tests passing (T021-T024)
- ✅ Manual quickstart validation complete (T025)
- ✅ Performance benchmarks met (T028)
- ✅ Cross-framework compatibility verified (T029)
- ✅ Event isolation verified (T030)
- ✅ No JavaScript errors in browser console
- ✅ All constitutional principles satisfied

---

*Generated from design documents: plan.md, data-model.md, contracts/ajax-endpoints.md, quickstart.md*
*Ready for implementation on branch: 002-when-the-plugin*
