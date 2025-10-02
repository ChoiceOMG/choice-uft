# Implementation Plan: Admin Testing Dashboard

**Branch**: `002-when-the-plugin` | **Date**: 2025-09-30 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/home/r11/dev/choice-uft/specs/002-when-the-plugin/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path ✅
   → Found: Admin Testing Dashboard specification
2. Fill Technical Context (scan for NEEDS CLARIFICATION) ✅
   → All clarifications resolved in Session 2025-09-30
   → Project Type: WordPress Plugin (PHP + JavaScript)
   → Structure Decision: WordPress plugin structure
3. Fill Constitution Check section ✅
   → Based on Choice UFT constitution v1.0
4. Evaluate Constitution Check section
   → Status: IN PROGRESS
5. Execute Phase 0 → research.md
   → Status: PENDING
6. Execute Phase 1 → contracts, data-model.md, quickstart.md
   → Status: PENDING
7. Re-evaluate Constitution Check
   → Status: PENDING
8. Plan Phase 2 → Describe task generation approach
   → Status: PENDING
9. STOP - Ready for /tasks command
```

## Summary

An admin-only testing dashboard for the Choice Universal Form Tracker plugin that allows WordPress administrators to validate all conversion tracking features (click tracking, form submissions, lead generation events) without affecting production analytics. The dashboard will provide one-click tools to generate test data, simulate tracking events, create test forms across multiple frameworks, and validate dataLayer events in real-time with configurable filtering.

## Technical Context

**Language/Version**: PHP 7.0+, JavaScript ES6+
**Primary Dependencies**: WordPress 5.0+, existing CUFT plugin infrastructure
**Storage**: Browser localStorage (test data), MySQL (separate test events table)
**Testing**: WordPress test framework, manual browser testing, dataLayer validation
**Target Platform**: WordPress admin dashboard (browser-based)
**Project Type**: WordPress plugin (PHP backend + JavaScript frontend)
**Performance Goals**: <500ms response time for all simulated events and test data generation
**Constraints**: Admin-only access (manage_options), no production data pollution, constitutional compliance
**Scale/Scope**: Single admin user sessions, lightweight testing tool

**Clarified Requirements** (from spec.md Session 2025-09-30):
1. **Test Data Persistence**: Browser storage (localStorage) - persists across reloads until browser cache cleared
2. **Test Event Isolation**: Dual approach - test_mode: true flag in dataLayer + separate database table
3. **Performance Target**: <500ms for all event triggering and validation
4. **Event Viewer Scope**: Configurable filter (admin toggle between test-only vs all events)
5. **Form Framework Selection**: Dynamic detection of installed frameworks, dropdown shows only available options

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Constitutional Alignment Analysis

#### ✅ Principle 1: JavaScript-First Compatibility
- **Status**: COMPLIANT
- **Implementation**: Dashboard will use vanilla JavaScript for all client-side functionality (test data generation, event simulation, dataLayer monitoring)
- **jQuery**: Not required for this feature (admin-only, modern browser environment)

#### ✅ Principle 2: DataLayer Standardization
- **Status**: COMPLIANT
- **Implementation**: All simulated events will include:
  - `cuft_tracked: true`
  - `cuft_source: "testing_dashboard"`
  - `test_mode: true` (FR-028)
  - snake_case naming for all fields

#### ✅ Principle 3: Framework Compatibility
- **Status**: COMPLIANT
- **Implementation**: Test form generator will use existing production framework scripts (FR-017), ensuring identical tracking behavior
- **Non-Interference**: Test events isolated via test_mode flag and separate DB table

#### ✅ Principle 4: Event Firing Rules
- **Status**: COMPLIANT
- **Implementation**: Simulated events will follow same rules:
  - form_submit: Always fires with required fields
  - generate_lead: Only when email + phone + click_id present (FR-011)

#### ✅ Principle 5: Error Handling Philosophy
- **Status**: COMPLIANT
- **Implementation**:
  - Try-catch blocks for all simulation functions
  - Graceful degradation when GTM/dataLayer missing (edge case documented)
  - Clear error messages without breaking functionality

#### ✅ Principle 6: Testing Requirements
- **Status**: ENHANCED
- **Purpose**: This feature IS the testing infrastructure
- **Implementation**: Will enable validation of all other framework tracking (meta-testing tool)

#### ✅ Principle 7: Performance Constraints
- **Status**: COMPLIANT
- **Target**: <500ms for all operations (stricter than <50ms tracking overhead)
- **Rationale**: Admin tool allows slightly relaxed constraint while maintaining responsiveness

#### ✅ Principle 8: Security Principles
- **Status**: COMPLIANT
- **Implementation**:
  - Admin-only access (manage_options capability)
  - Test data clearly marked (test_mode flag)
  - No PII in console logs (production mode)
  - Nonce validation for AJAX endpoints

### Constitutional Deviations

**None** - All constitutional principles can be fully satisfied.

### New Patterns Introduced

1. **Separate Test Database Table** (FR-029)
   - **Rationale**: Prevents test data pollution in production click tracking table
   - **Alignment**: Supports Principle 8 (Security) and Principle 6 (Testing)

2. **Admin-Only Menu Page**
   - **Rationale**: Testing tool should not be exposed to non-administrators
   - **Alignment**: Security principle - appropriate capability gating

3. **localStorage for Test Session Data**
   - **Rationale**: Browser-side persistence allows test data to survive page reloads without database overhead
   - **Alignment**: Performance principle - minimal backend load

## Project Structure

### Documentation (this feature)
```
specs/002-when-the-plugin/
├── plan.md              # This file (/plan command output)
├── spec.md              # Feature specification (exists)
├── research.md          # Phase 0 output (/plan command) - PENDING
├── data-model.md        # Phase 1 output (/plan command) - PENDING
├── quickstart.md        # Phase 1 output (/plan command) - PENDING
├── contracts/           # Phase 1 output (/plan command) - PENDING
│   ├── admin-page.md
│   ├── ajax-endpoints.md
│   └── javascript-api.md
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (WordPress plugin structure)
```
choice-uft/                          # Repository root
├── includes/                         # PHP backend
│   ├── admin/
│   │   └── class-cuft-testing-dashboard.php    # Main dashboard class
│   ├── ajax/
│   │   ├── class-cuft-test-data-generator.php  # Test data generation
│   │   ├── class-cuft-event-simulator.php      # Event simulation
│   │   └── class-cuft-test-form-builder.php    # Dynamic form creation
│   └── database/
│       └── class-cuft-test-events-table.php    # Separate test events table
│
├── assets/                           # JavaScript frontend
│   ├── admin/
│   │   ├── cuft-testing-dashboard.js           # Main dashboard UI
│   │   ├── cuft-test-data-manager.js           # localStorage management
│   │   ├── cuft-event-simulator.js             # Event simulation
│   │   ├── cuft-event-validator.js             # dataLayer validation
│   │   └── cuft-testing-dashboard.css          # Dashboard styles
│   └── forms/
│       └── cuft-test-form-renderer.js          # Framework-specific form rendering
│
└── tests/
    ├── integration/
    │   ├── test-dashboard-access.php           # Access control tests
    │   ├── test-data-generation.php            # Test data generation tests
    │   └── test-event-simulation.php           # Event simulation tests
    └── manual/
        └── testing-dashboard-quickstart.md     # Manual testing guide
```

**Structure Decision**: WordPress plugin structure (existing codebase extension)

## Phase 0: Outline & Research

### Research Tasks

1. **WordPress Admin Page Best Practices**
   - **Question**: How to create admin pages with modern UI that matches WordPress standards?
   - **Research**: WordPress Settings API, admin menu registration, capability checking patterns
   - **Output**: Admin page structure and security patterns

2. **localStorage API for Test Data Persistence**
   - **Question**: Best practices for structuring test data in localStorage for reliability?
   - **Research**: localStorage size limits, JSON serialization, fallback strategies, expiration patterns
   - **Output**: Test data storage schema and management patterns

3. **dataLayer Event Monitoring**
   - **Question**: How to build a real-time event viewer that captures and displays dataLayer pushes?
   - **Research**: Intercepting window.dataLayer.push, event filtering, real-time UI updates
   - **Output**: Event monitoring and display patterns

4. **Dynamic Form Generation Across Frameworks**
   - **Question**: How to programmatically create forms for each framework (Elementor, CF7, Ninja, Gravity, Avada)?
   - **Research**: Framework-specific APIs, shortcode generation, programmatic form creation
   - **Output**: Form generation patterns for each supported framework

5. **Separate Database Table for Test Events**
   - **Question**: WordPress patterns for creating and managing custom tables?
   - **Research**: dbDelta schema definition, table versioning, CRUD operations, cleanup strategies
   - **Output**: Database table schema and management patterns

6. **Click ID and UTM Parameter Generation**
   - **Question**: Realistic test data patterns for all supported tracking parameters?
   - **Research**: Click ID formats (gclid, fbclid, etc.), UTM parameter conventions, randomization strategies
   - **Output**: Test data generation algorithms

7. **AJAX Endpoint Security in WordPress**
   - **Question**: Best practices for securing admin-only AJAX endpoints?
   - **Research**: Nonce validation, capability checking, sanitization/validation patterns
   - **Output**: Secure AJAX endpoint patterns

### Research Output Location
`/home/r11/dev/choice-uft/specs/002-when-the-plugin/research.md`

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

### Design Artifacts to Generate

1. **data-model.md** - Data structures:
   - Test Session (localStorage schema)
   - Test Form Configuration (localStorage schema)
   - Test Events Table (MySQL schema)
   - Simulated Event (dataLayer event schema)
   - Event Validation Result (validation response schema)

2. **contracts/** - API contracts:
   - `admin-page.md` - Dashboard UI structure and capabilities
   - `ajax-endpoints.md` - AJAX API contracts (generate data, simulate events, build forms)
   - `javascript-api.md` - Client-side API for test data, simulation, validation

3. **quickstart.md** - Manual testing guide:
   - Access dashboard
   - Generate test data
   - Simulate phone/email clicks
   - Build and submit test form
   - Validate events in viewer
   - Clean up test data

4. **CLAUDE.md update** - Agent context:
   - Run `.specify/scripts/bash/update-agent-context.sh claude`
   - Add testing dashboard patterns
   - Document test data management approach
   - Preserve existing constitutional guidelines

### Contract Test Generation

Each contract will generate corresponding test files:
- `tests/integration/test-admin-page-access.php` - Verify capability gating
- `tests/integration/test-ajax-generate-data.php` - Verify test data generation
- `tests/integration/test-ajax-simulate-event.php` - Verify event simulation
- `tests/integration/test-ajax-build-form.php` - Verify form creation
- `tests/manual/test-event-validation.md` - Manual dataLayer validation steps

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
1. Load `.specify/templates/tasks-template.md` as base
2. Generate implementation tasks from Phase 1 contracts:
   - Database schema creation (test events table)
   - Admin page registration and rendering
   - AJAX endpoint implementation (generate data, simulate events, build forms)
   - JavaScript modules (test data manager, event simulator, event validator)
   - CSS styling for dashboard
3. Generate testing tasks:
   - Contract tests for each AJAX endpoint
   - Integration tests for admin access control
   - Manual testing walkthrough (quickstart validation)
4. Generate validation tasks:
   - Performance testing (<500ms requirement)
   - Cross-framework form testing
   - Event isolation verification

**Ordering Strategy**:
- [P] Database schema → Admin page setup → AJAX endpoints
- [P] JavaScript modules (independent: test data, simulator, validator)
- Integration tests after corresponding implementation
- Manual testing after full integration

**Estimated Output**: 20-25 numbered, ordered tasks in tasks.md

**Key Task Categories**:
1. Database & Infrastructure (3-4 tasks)
2. Admin Page Setup (2-3 tasks)
3. AJAX Endpoints (4-5 tasks)
4. JavaScript Client (5-6 tasks)
5. Testing & Validation (5-6 tasks)
6. Documentation (1-2 tasks)

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

No violations detected. All constitutional principles can be fully satisfied within this feature's design.

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command) - research.md created
- [x] Phase 1: Design complete (/plan command) - data-model.md, contracts/, quickstart.md created
- [x] Phase 2: Task planning complete (/plan command - approach described)
- [x] Phase 3: Tasks generated (/tasks command) - tasks.md with 30 implementation tasks created
- [ ] Phase 4: Implementation complete - Ready to execute tasks
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS (all principles satisfied)
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented (none required)

**Phase 1 Artifacts Created**:
- ✅ data-model.md - Complete data structures (localStorage, MySQL, dataLayer schemas)
- ✅ contracts/ajax-endpoints.md - AJAX API specifications with security requirements
- ✅ quickstart.md - Manual testing guide with 11-step validation procedure

---
*Based on Choice UFT Constitution v1.0 - See `.specify/memory/constitution.md`*
