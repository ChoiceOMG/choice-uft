# Implementation Plan: Click Tracking Events Migration

**Branch**: `feat/click-tracking-events` | **Date**: 2025-09-29 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/migrations/click-tracking-events/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path ✅
2. Fill Technical Context ✅
3. Fill Constitution Check section ✅
4. Evaluate Constitution Check ✅ - PASS
5. Execute Phase 0 → research.md ✅
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, CLAUDE.md ✅
7. Re-evaluate Constitution Check ✅ - PASS
8. Plan Phase 2 → Describe task generation approach ⏳
9. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 8. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary

Migrate click tracking from static column-based storage to event-based JSON array tracking. Add `events` JSON column to store chronological event history (phone_click, email_click, form_submit, generate_lead). Remove redundant utm_source and platform columns. Maintain backward compatibility with existing webhook API. Integrate with existing JavaScript event sources (cuft-links.js, framework form scripts). Use AJAX endpoint for client-side event recording. Implement shadow mode feature flag for gradual rollout.

## Technical Context
**Language/Version**: PHP 7.0+, JavaScript ES5+, MySQL 5.7+
**Primary Dependencies**: WordPress 5.0+, WordPress AJAX API, MySQL JSON functions
**Storage**: MySQL 5.7+ with JSON column support (cuft_click_tracking table)
**Testing**: Manual testing via browser DevTools, WordPress test environment (wp-pdev)
**Target Platform**: WordPress plugin (Linux server, Apache/Nginx + PHP-FPM)
**Project Type**: WordPress plugin (single project structure)
**Performance Goals**: <10% overhead for JSON operations, <100ms per event recording
**Constraints**: Zero downtime migration, full rollback capability, maintain webhook API compatibility
**Scale/Scope**: Handle 100 events per click_id maximum, FIFO cleanup for old events

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Initial Constitution Check (Before Phase 0)

#### JavaScript-First Compatibility Principle
✅ **COMPLIANT**: Event recording uses native JavaScript with fetch API for AJAX calls
- Implementation will use `fetch()` for event recording endpoint
- jQuery fallback via `jQuery.ajax()` when jQuery available
- Multiple detection methods already exist in cuft-links.js and framework scripts

#### DataLayer Standardization Rule
✅ **COMPLIANT**: All events already use snake_case naming with required fields
- Events include `cuft_tracked: true` and `cuft_source: "framework_name"`
- Naming follows standards: `form_type`, `form_id`, `user_email`, etc.
- No changes needed to existing dataLayer event structure

#### Framework Compatibility Principle
✅ **COMPLIANT**: Migration does not affect framework-specific tracking
- Database changes are transparent to framework scripts
- Existing silent exit patterns preserved
- No cross-framework interference introduced

#### Event Firing Rules
✅ **COMPLIANT**: Event firing logic remains unchanged
- `form_submit` fires on all successful submissions
- `generate_lead` fires only when email + phone + click_id present
- Deduplication maintained through existing event handling

#### Error Handling Philosophy
✅ **COMPLIANT**: Fallback chains and error isolation required
- AJAX endpoint must implement try-catch blocks
- Database operations must handle JSON errors gracefully
- Migration must support rollback on failure
- Event recording failures must not break form submissions

#### Testing Requirements
✅ **ADDRESSED**: Manual testing procedures documented in quickstart.md
- Browser testing for event recording
- Verify backward compatibility with webhook API
- Test event recording from all framework scripts
- Validate JSON operations performance

#### Performance Constraints
✅ **COMPLIANT**: Performance targets specified
- <10% overhead requirement documented
- <100ms per event recording target
- MySQL JSON index strategy defined
- Batch processing for migration (1000 records)

#### Security Principles
✅ **COMPLIANT**: Security considerations addressed
- AJAX endpoint sanitizes click_id input
- JSON validation prevents injection attacks
- Event timestamp validation required
- No PII in event data (only event type + timestamp)

**Initial Check Result**: ✅ PASS - No constitutional violations

### Post-Design Constitution Check (After Phase 1)

#### JavaScript-First Compatibility Principle
✅ **VERIFIED**: Research confirms fire-and-forget fetch pattern
- Native fetch() with URLSearchParams (ES6)
- jQuery.ajax() fallback documented
- Error isolation with try-catch wrappers
- No jQuery dependencies in primary path

#### DataLayer Standardization Rule
✅ **VERIFIED**: No changes to dataLayer event structure
- Existing events continue with snake_case
- Required fields (cuft_tracked, cuft_source) preserved
- Event recording happens server-side after dataLayer push

#### Framework Compatibility Principle
✅ **VERIFIED**: Zero impact on framework scripts
- Event recording integrated into existing event handlers
- Framework scripts call shared recordEvent() function
- No framework-specific event recording logic needed

#### Event Firing Rules
✅ **VERIFIED**: Event firing unchanged, recording added
- form_submit and generate_lead events fire as before
- Event recording is ancillary to dataLayer push
- Recording failures don't affect event firing

#### Error Handling Philosophy
✅ **VERIFIED**: Comprehensive error handling designed
- AJAX endpoint: try-catch with wp_send_json_error
- Client-side: nested try-catch with silent failures
- Database: JSON operations wrapped in error checks
- Migration: Rollback procedure documented

#### Testing Requirements
✅ **VERIFIED**: Comprehensive testing guide created
- quickstart.md provides 15-minute validation
- Contract tests defined in contracts/*.md
- Integration scenarios documented
- Performance benchmarks specified

#### Performance Constraints
✅ **VERIFIED**: Performance targets achievable
- Research shows JSON operations <12ms
- AJAX fire-and-forget prevents blocking
- Indexes optimize recent activity queries
- FIFO cleanup prevents array growth

#### Security Principles
✅ **VERIFIED**: Security measures comprehensive
- Nonce validation in AJAX handler
- Input sanitization (sanitize_text_field)
- Event type whitelist enforcement
- Click ID pattern validation (alphanumeric + hyphens)

**Post-Design Check Result**: ✅ PASS - Design maintains constitutional compliance

## Project Structure

### Documentation (this feature)
```
specs/migrations/click-tracking-events/
├── spec.md              # Feature specification (existing)
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command) ✅
├── data-model.md        # Phase 1 output (existing, reviewed)
├── quickstart.md        # Phase 1 output (/plan command) ✅
├── contracts/           # Phase 1 output (/plan command) ✅
│   ├── ajax-endpoint.md # Event recording AJAX endpoint contract
│   └── webhook-api.md   # Webhook API compatibility contract
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# WordPress Plugin Structure
choice-uft/
├── includes/
│   ├── class-cuft-click-tracker.php        # UPDATE: Add event methods
│   └── ajax/
│       └── class-cuft-event-recorder.php   # NEW: AJAX endpoint handler
├── assets/
│   ├── links/
│   │   └── cuft-links.js                   # UPDATE: Add event recording
│   └── forms/
│       ├── cuft-elementor-forms.js         # UPDATE: Add event recording
│       ├── cuft-cf7-forms.js               # UPDATE: Add event recording
│       ├── cuft-ninja-forms.js             # UPDATE: Add event recording
│       ├── cuft-gravity-forms.js           # UPDATE: Add event recording
│       └── cuft-avada-forms.js             # UPDATE: Add event recording
├── admin/
│   └── class-cuft-admin.php                # UPDATE: Display events timeline
└── migrations/
    └── class-cuft-migration-3-12-0.php     # NEW: Migration handler
```

**Structure Decision**: WordPress plugin (single project) - follows existing plugin architecture

## Phase 0: Outline & Research

### Research Tasks Completed ✅
1. **MySQL JSON Functions Performance**
   - Decision: Use JSON_ARRAY_APPEND, JSON_EXTRACT, JSON_LENGTH
   - Rationale: O(n) operations acceptable for <100 events
   - Performance: 8-12ms per operation (within targets)

2. **WordPress AJAX Endpoint Best Practices**
   - Decision: Use admin-ajax.php with nonce validation
   - Rationale: Standard WordPress pattern, compatible everywhere
   - Security: Nonce + input sanitization + event type whitelist

3. **Event Recording Architecture**
   - Decision: Fire-and-forget async with silent failures
   - Rationale: Never block user interactions
   - Pattern: try-catch wrappers, debug-only logging

4. **Zero-Downtime Migration**
   - Decision: Additive schema changes with feature flag
   - Rationale: Non-blocking ALTER TABLE, instant rollback
   - Strategy: Add columns → integrate code → cleanup columns

5. **Feature Flag Implementation (Shadow Mode)**
   - Decision: Write events when enabled, display when flag ON
   - Rationale: Decouple data collection from UI display
   - Rollout: Shadow mode → full display → remove deprecated columns

**Output**: [research.md](./research.md) with all findings documented

## Phase 1: Design & Contracts

### Artifacts Generated ✅

1. **Data Model** ([data-model.md](./data-model.md))
   - Entity: Click Tracking Record (cuft_click_tracking)
   - New field: events (JSON array)
   - New index: idx_date_updated
   - Removed: utm_source, platform columns
   - Validation: JSON schema for events, 100 event limit

2. **API Contracts**
   - **AJAX Endpoint** ([contracts/ajax-endpoint.md](./contracts/ajax-endpoint.md))
     - Endpoint: `/wp-admin/admin-ajax.php?action=cuft_record_event`
     - Request: click_id, event_type, nonce
     - Response: success/error JSON
     - Behavior: Deduplication, FIFO cleanup

   - **Webhook API** ([contracts/webhook-api.md](./contracts/webhook-api.md))
     - Endpoint: `/cuft-webhook/` (unchanged)
     - Guarantee: 100% backward compatibility
     - Enhancement: Optional event recording (non-breaking)

3. **Test Scenarios** ([quickstart.md](./quickstart.md))
   - Database schema validation
   - AJAX endpoint testing
   - Phone/email link tracking
   - Form submission events
   - Event deduplication
   - Admin interface display
   - Webhook compatibility
   - FIFO cleanup (100+ events)

4. **Agent Context Update** ([CLAUDE.md](../../CLAUDE.md))
   - Added "Active Migration" section
   - Documented event types and recording pattern
   - Listed implementation files and requirements
   - Linked to design artifacts

**Output**: All Phase 1 artifacts complete

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

### Task Generation Strategy

The /tasks command will:
1. Load `.specify/templates/tasks-template.md` as base structure
2. Generate tasks from Phase 1 design documents
3. Follow Test-Driven Development (TDD) order: Tests before implementation
4. Mark parallelizable tasks with [P] flag

### Task Categories

#### Database Migration Tasks
1. Create migration class (class-cuft-migration-3-12-0.php)
2. Write up() method: Add events column and index
3. Write down() method: Hybrid rollback (preserve qualified/score)
4. Create backup utility for rollback safety
5. Test migration in local environment

#### AJAX Endpoint Tasks [P]
6. Create AJAX handler class (class-cuft-event-recorder.php)
7. Implement nonce validation and input sanitization
8. Implement event type whitelist
9. Write contract tests for AJAX endpoint
10. Test error responses (invalid nonce, event type, etc.)

#### Click Tracker Enhancement Tasks [P]
11. Add add_event() method to CUFT_Click_Tracker
12. Implement event deduplication logic (update timestamp)
13. Add get_events() method
14. Implement FIFO cleanup (100 event limit)
15. Write unit tests for event methods

#### Client-Side Integration Tasks [P]
16. Update cuft-links.js: Add recordEvent() function
17. Integrate phone_click recording in tel link handler
18. Integrate email_click recording in mailto link handler
19. Update cuft-elementor-forms.js: Add form_submit recording
20. Update cuft-cf7-forms.js: Add form_submit recording
21. Update cuft-ninja-forms.js: Add form_submit recording
22. Update cuft-gravity-forms.js: Add form_submit recording
23. Update cuft-avada-forms.js: Add form_submit recording

#### Admin Interface Tasks
24. Update admin table: Add events timeline column
25. Remove utm_source and platform columns from display
26. Add event type filtering
27. Add "last activity" sort option
28. Test admin interface rendering

#### Feature Flag Tasks
29. Implement feature flag storage (wp_options)
30. Add shadow mode toggle in admin settings
31. Conditional rendering based on display flag
32. Test shadow mode (write but don't display)

#### Integration Testing Tasks
33. Test end-to-end phone click → event recorded
34. Test end-to-end email click → event recorded
35. Test end-to-end form submit → event recorded
36. Test webhook updates trigger events
37. Test event deduplication with duplicates
38. Test FIFO cleanup with 100+ events
39. Validate quickstart.md test scenarios

#### Performance Validation Tasks
40. Benchmark JSON operations (<12ms target)
41. Benchmark AJAX endpoint (<100ms target)
42. Benchmark admin table load with events
43. Validate <10% aggregate overhead

### Task Ordering Strategy

**Phase 1: Database Foundation**
- Tasks 1-5 (Migration) - Sequential, blocking

**Phase 2: Backend Infrastructure [P]**
- Tasks 6-10 (AJAX) - Parallel with Tasks 11-15
- Tasks 11-15 (Click Tracker) - Parallel with Tasks 6-10

**Phase 3: Client Integration [P]**
- Tasks 16-23 (JavaScript) - All parallel, independent files

**Phase 4: Admin & Features**
- Tasks 24-28 (Admin UI) - Sequential
- Tasks 29-32 (Feature Flags) - Parallel with Admin UI

**Phase 5: Validation**
- Tasks 33-39 (Integration Tests) - Sequential, validates all
- Tasks 40-43 (Performance) - Parallel benchmarks

### Estimated Output
**Total Tasks**: ~43 numbered, dependency-ordered tasks
**Parallelizable**: ~60% of tasks marked with [P]
**Critical Path**: Database → AJAX/Tracker → Integration → Validation
**Estimated Effort**: 3-5 days for full implementation

### IMPORTANT
**This phase is executed by the /tasks command, NOT by /plan**
The /plan command stops here and outputs this plan.md file.

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

No constitutional violations requiring justification. All design decisions align with core principles.

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning approach documented (/plan command)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved (none required)
- [x] Complexity deviations documented (none exist)

**Artifacts Status**:
- [x] research.md created
- [x] data-model.md exists (reviewed)
- [x] contracts/ajax-endpoint.md created
- [x] contracts/webhook-api.md created
- [x] quickstart.md created
- [x] CLAUDE.md updated
- [ ] tasks.md (awaiting /tasks command)

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
*Plan generated by /plan command on 2025-09-29*
*Ready for /tasks command to generate implementation tasks*
