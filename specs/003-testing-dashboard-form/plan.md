# Implementation Plan: Testing Dashboard Form Builder

**Branch**: `003-testing-dashboard-form` | **Date**: 2025-01-10 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/003-testing-dashboard-form/spec.md`

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
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file (e.g., `CLAUDE.md` for Claude Code, `.github/copilot-instructions.md` for GitHub Copilot, `GEMINI.md` for Gemini CLI, `QWEN.md` for Qwen Code or `AGENTS.md` for opencode).
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
Building on the existing admin testing dashboard, this feature adds the capability to generate real test forms within any installed form framework (Elementor, Contact Form 7, Gravity Forms, etc.). The system creates actual form instances, displays them in an iframe, and allows administrators to populate fields with existing test data to validate tracking functionality. All form submissions are routed to a dedicated test endpoint that logs but doesn't process data, preventing real actions like emails or database entries.

## Technical Context
**Language/Version**: PHP 7.0+, JavaScript ES6+
**Primary Dependencies**: WordPress 5.0+, existing form frameworks (Elementor Pro, Contact Form 7, Gravity Forms, Ninja Forms, Avada Forms)
**Storage**: WordPress MySQL database for test form metadata, WordPress options API for configuration
**Testing**: PHPUnit for backend, Browser DevTools for JavaScript validation
**Target Platform**: WordPress admin dashboard (wp-admin)
**Project Type**: WordPress Plugin extension (adding to existing Choice UFT plugin)
**Performance Goals**: < 100ms to generate form, < 50ms iframe load time
**Constraints**: Must work within WordPress admin context, respect admin-only permissions
**Scale/Scope**: Support 5+ form frameworks, generate unlimited test forms (manual cleanup)

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### JavaScript-First Compatibility ✅
- Form field population will use vanilla JavaScript with jQuery fallback
- Iframe communication will use postMessage API (native)
- Event capture from iframe will use multiple detection methods

### DataLayer Standardization ✅
- Test forms will generate standard snake_case events
- All events will include `cuft_tracked: true` and `cuft_source`
- Events captured from iframe will maintain standardization

### Framework Compatibility ✅
- Each framework adapter will only process its own form generation
- Silent failures for unsupported frameworks
- Non-interference between multiple frameworks

### Event Firing Rules ✅
- Test forms will trigger standard form_submit events
- generate_lead events only when email + phone + click_id present
- Event deduplication handled by existing tracking code

### Error Handling Philosophy ✅
- Graceful degradation if framework doesn't support generation
- Fallback to manual form creation instructions
- Try-catch wrapping for all framework interactions

### Testing Requirements ✅
- Feature extends existing testing dashboard
- Uses production tracking code path
- Validates cross-framework compatibility

### Performance Constraints ✅
- Lazy loading of framework adapters
- Minimal overhead for form generation
- Efficient iframe embedding

## Project Structure

### Documentation (this feature)
```
specs/003-testing-dashboard-form/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# WordPress Plugin Structure (existing)
includes/
├── admin/
│   ├── class-cuft-testing-dashboard.php  # Extend existing
│   ├── class-cuft-form-builder.php       # New core class
│   └── framework-adapters/               # New directory
│       ├── class-elementor-adapter.php
│       ├── class-cf7-adapter.php
│       ├── class-gravity-adapter.php
│       ├── class-ninja-adapter.php
│       └── class-avada-adapter.php
└── ajax/
    └── class-cuft-form-builder-ajax.php  # AJAX handlers

assets/
├── admin/
│   ├── js/
│   │   ├── cuft-form-builder.js         # Main builder script
│   │   └── cuft-iframe-bridge.js        # Iframe communication
│   └── css/
│       └── cuft-form-builder.css        # Builder styles

templates/
└── admin/
    └── form-builder/
        ├── builder-interface.php         # Main UI template
        └── iframe-container.php          # Iframe wrapper
```

**Structure Decision**: WordPress plugin extension pattern - adding to existing plugin structure

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context**:
   - Framework-specific form generation APIs
   - Iframe security and sandbox requirements
   - Cross-origin communication patterns
   - Test endpoint architecture

2. **Generate and dispatch research agents**:
   ```
   Task: "Research WordPress form framework APIs for programmatic form creation"
   Task: "Find best practices for iframe sandboxing in WordPress admin"
   Task: "Research postMessage patterns for cross-frame communication"
   Task: "Investigate WordPress AJAX endpoint patterns for test data"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all technical decisions resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - Test Form Template entity
   - Test Data Set entity (existing, to be referenced)
   - Generated Form Instance entity
   - Framework Adapter interface
   - Test Session entity

2. **Generate API contracts** from functional requirements:
   - POST /wp-admin/admin-ajax.php?action=cuft_create_test_form
   - GET /wp-admin/admin-ajax.php?action=cuft_get_test_forms
   - DELETE /wp-admin/admin-ajax.php?action=cuft_delete_test_form
   - POST /wp-admin/admin-ajax.php?action=cuft_populate_form
   - POST /wp-admin/admin-ajax.php?action=cuft_test_submit

3. **Generate contract tests** from contracts:
   - Test AJAX endpoint security (admin-only)
   - Test form generation response format
   - Test iframe URL generation
   - Test data population messages

4. **Extract test scenarios** from user stories:
   - Generate Elementor form scenario
   - Populate form fields scenario
   - Capture submission events scenario
   - Delete test form scenario

5. **Update agent file incrementally** (O(1) operation):
   - Run `.specify/scripts/bash/update-agent-context.sh claude`
   - Add form builder feature context
   - Update recent changes
   - Keep under 150 lines

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, CLAUDE.md update

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Generate framework adapter base class task [P]
- Generate individual framework adapter tasks [P] (5 tasks, one per framework)
- Create AJAX endpoint handler tasks
- Create admin UI components tasks
- Create iframe bridge communication tasks
- Integration test tasks for each framework

**Ordering Strategy**:
- Base classes and interfaces first
- Framework adapters in parallel [P]
- AJAX endpoints before UI
- UI components before integration
- Tests follow implementation

**Estimated Output**: 20-25 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*No constitution violations requiring justification*

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [x] Ready for /tasks command: YES

## Artifacts Generated

### Phase 0 Artifacts
- ✅ `research.md` - Technical decisions and architecture choices
- ✅ Framework detection strategy
- ✅ Iframe security approach
- ✅ PostMessage protocol design

### Phase 1 Artifacts
- ✅ `data-model.md` - Complete entity definitions and relationships
- ✅ `contracts/ajax-endpoints.md` - AJAX API specifications
- ✅ `contracts/postmessage-protocol.md` - Cross-frame communication protocol
- ✅ `quickstart.md` - Testing and validation guide
- ✅ `CLAUDE.md` - Updated with project context

### Constitution Compliance Re-Check
- ✅ JavaScript-First: Vanilla JS with jQuery fallback maintained
- ✅ DataLayer Standardization: Snake_case naming enforced
- ✅ Framework Compatibility: Silent failures, non-interference design
- ✅ Event Firing Rules: Test endpoint prevents real actions
- ✅ Error Handling: Try-catch wrapping, graceful degradation
- ✅ Testing Requirements: Comprehensive test scenarios defined
- ✅ Performance Constraints: Lazy loading, minimal overhead

---

**Status: Implementation plan complete. Ready for task generation via /tasks command.**