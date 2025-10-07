
# Implementation Plan: Custom GTM Server Domain with Health Checks

**Branch**: `006-provide-users-with` | **Date**: 2025-10-06 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/006-provide-users-with/spec.md`

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
Enable administrators to configure custom first-party GTM server domains with automatic health monitoring and fallback to Google's default endpoints. The primary issue is an AJAX security check failure (nonce mismatch) that prevents health checks from working. Research identified the exact issue: PHP expects nonce action `cuft_ajax_nonce` but JavaScript provides `cuft_admin`. Solution requires updating 4 nonce verification calls and implementing the full health check system with periodic monitoring, persistent storage, and automatic server switching.

## Technical Context
**Language/Version**: PHP 7.0+, JavaScript ES6+
**Primary Dependencies**: WordPress 5.0+, wp_remote_get, wp_cron, wp_options API
**Storage**: WordPress wp_options table for health check results and server status
**Testing**: Manual testing with mock servers, WordPress admin interface testing
**Target Platform**: WordPress plugin running on Linux/Windows servers
**Project Type**: WordPress plugin (single codebase)
**Performance Goals**: Health checks complete within 5 seconds, no impact on page load
**Constraints**: Must not break tracking if custom server fails, 5-second timeout requirement
**Scale/Scope**: Single feature addition to existing plugin, ~500 lines of code
**User-Provided Context**: AJAX error "security check failed" - nonce validation issue already resolved in other endpoints

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Core Principle Compliance

1. **JavaScript-First Compatibility** ✅
   - Health checks use server-side PHP (wp_remote_get)
   - Client-side JavaScript only for AJAX calls and UI updates
   - Multiple fallback methods planned (fetch → jQuery → XMLHttpRequest)

2. **DataLayer Standardization** ✅
   - Feature doesn't directly interact with dataLayer
   - Any tracking events will follow snake_case convention
   - Will include `cuft_tracked: true` if events are added

3. **Framework Compatibility** ✅
   - Feature is framework-agnostic (affects all frameworks equally)
   - No interference with existing form tracking
   - Silent operation in background

4. **Event Firing Rules** N/A
   - Feature doesn't fire tracking events
   - Only manages GTM script loading source

5. **Error Handling Philosophy** ✅
   - Implements fallback chain: Custom Server → Google Default → Error Notice
   - All remote calls wrapped in try-catch
   - Health check failures don't break tracking
   - Graceful degradation to default endpoints

6. **Testing Requirements** ✅
   - Manual test scenarios defined in spec
   - Will create test page for health check validation
   - Cross-browser testing for AJAX calls

7. **Performance Constraints** ✅
   - 5-second timeout meets <50ms overhead requirement
   - Async health checks don't block page load
   - Cron-based checks minimize runtime impact

8. **Security Principles** ✅
   - No PII logged during health checks
   - URL validation before remote requests
   - Sanitization of admin input fields
   - Nonce validation for all AJAX endpoints

**GATE STATUS**: ✅ PASS - No constitutional violations detected

## Project Structure

### Documentation (this feature)
```
specs/[###-feature]/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure]
```

**Structure Decision**: [DEFAULT to Option 1 unless Technical Context indicates web/mobile app]

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context** above:
   - For each NEEDS CLARIFICATION → research task
   - For each dependency → best practices task
   - For each integration → patterns task

2. **Generate and dispatch research agents**:
   ```
   For each unknown in Technical Context:
     Task: "Research {unknown} for {feature context}"
   For each technology choice:
     Task: "Find best practices for {tech} in {domain}"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all NEEDS CLARIFICATION resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - Entity name, fields, relationships
   - Validation rules from requirements
   - State transitions if applicable

2. **Generate API contracts** from functional requirements:
   - For each user action → endpoint
   - Use standard REST/GraphQL patterns
   - Output OpenAPI/GraphQL schema to `/contracts/`

3. **Generate contract tests** from contracts:
   - One test file per endpoint
   - Assert request/response schemas
   - Tests must fail (no implementation yet)

4. **Extract test scenarios** from user stories:
   - Each story → integration test scenario
   - Quickstart test = story validation steps

5. **Update agent file incrementally** (O(1) operation):
   - Run `.specify/scripts/bash/update-agent-context.sh claude`
     **IMPORTANT**: Execute it exactly as specified above. Do not add or remove any arguments.
   - If exists: Add only NEW tech from current plan
   - Preserve manual additions between markers
   - Update recent changes (keep last 3)
   - Keep under 150 lines for token efficiency
   - Output to repository root

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, agent-specific file

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `.specify/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs (contracts, data model, quickstart)
- Critical security fix first (nonce validation)
- AJAX endpoint implementation tasks
- Health check logic implementation
- Frontend script loading updates
- Admin UI enhancements
- Cron job setup for periodic checks
- Testing and validation tasks

**Task Categories**:
1. **Critical Fix** (1 task): Fix nonce validation issue
2. **Backend Infrastructure** (5-6 tasks): AJAX handlers, health check logic
3. **Data Layer** (3-4 tasks): Options management, state tracking
4. **Frontend Integration** (3-4 tasks): Script loading, UI updates
5. **Automation** (2-3 tasks): Cron jobs, background checks
6. **Testing** (4-5 tasks): Manual test scenarios from quickstart

**Ordering Strategy**:
- Critical fix MUST be first (blocks all other functionality)
- Backend before frontend (API must exist before UI can use it)
- Core functionality before enhancements
- Testing tasks after implementation
- Mark [P] for parallel execution where possible

**Estimated Output**: 20-25 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)  
**Phase 4**: Implementation (execute tasks.md following constitutional principles)  
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |


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
- [ ] Complexity deviations documented (none required)

---
*Based on Constitution v2.1.1 - See `/memory/constitution.md`*
