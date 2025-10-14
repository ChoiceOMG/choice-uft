
# Implementation Plan: Force Install Update

**Branch**: `009-force-install-update` | **Date**: 2025-10-12 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/home/r11/dev/choice-uft/specs/009-force-install-update/spec.md`

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
This feature adds manual update control capabilities to the Choice Universal Form Tracker WordPress plugin, allowing administrators to force-check for GitHub updates bypassing WordPress's automatic schedule, and force-reinstall the latest version when automatic updates fail. The implementation extends the existing GitHub-based update system (CUFT_Update_Checker, CUFT_GitHub_API, CUFT_WordPress_Updater) with admin UI buttons, update cache clearing, disk space validation, backup/restore mechanisms, and WordPress transient-based operation locking for single-site installations.

## Technical Context
**Language/Version**: PHP 7.4+ (WordPress 5.0+, current plugin version 3.18.0)
**Primary Dependencies**: WordPress Core APIs (WP_Upgrader, WP_Filesystem, Options API, Transients API, AJAX API), existing update system (CUFT_Update_Checker, CUFT_GitHub_API, CUFT_WordPress_Updater, CUFT_Update_Status, CUFT_GitHub_Release, CUFT_Backup_Manager)
**Storage**: WordPress Options table (plugin settings), Transients (operation locking, cache), Filesystem (plugin backups in wp-content/uploads/cuft-backups/)
**Testing**: Manual testing in Docker development environment (localhost:8080), WP-CLI for validation, existing test forms infrastructure
**Target Platform**: WordPress 5.0+ on Linux/Apache/Nginx, PHP 7.4-8.3, single-site installations only (not multisite)
**Project Type**: Single WordPress plugin project (existing structure: includes/, assets/, admin/)
**Performance Goals**: Update check <5s timeout, force reinstall <60s timeout, minimal UI blocking with AJAX progress indicators
**Constraints**: WordPress DISALLOW_FILE_MODS constant must be respected, must verify 'update_plugins' capability, 3x plugin size disk space required, transient-based single-operation locking, 7-day history retention
**Scale/Scope**: Single admin operation at a time, typical plugin size ~2-5MB, update history entries auto-expire after 7 days

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Analysis**: This feature implements WordPress plugin administrative functionality, not form tracking. The core constitutional principles (JavaScript-First Compatibility, DataLayer Standardization, Framework Compatibility, Event Firing Rules) apply to form tracking features only and are **NOT APPLICABLE** to this update system feature.

**Applicable Constitutional Principles**:

### 5. Error Handling Philosophy ✅ COMPLIANT
- **Graceful Degradation**: Update checks will return cached results on API failure, operations will timeout gracefully
- **Try-Catch Requirement**: All GitHub API calls, filesystem operations, and WordPress API interactions will be wrapped in try-catch blocks
- **Error Isolation**: Force reinstall errors will preserve existing plugin functionality through backup/restore mechanism

### 6. Testing Requirements ✅ COMPLIANT
- **Production Flow Testing**: Manual testing will use production WordPress admin interfaces in Docker environment
- **Validation**: Tests will verify capability checks, disk space validation, backup creation, and transient locking

### 7. Performance Constraints ✅ COMPLIANT
- **Minimal Overhead**: Update check operations timeout at 5 seconds, force reinstall at 60 seconds (explicit requirements)
- **Memory Management**: Transients will be properly cleaned up, 7-day auto-expiry for history entries

### 8. Security Principles ✅ COMPLIANT
- **Capability Verification**: All operations will verify 'update_plugins' capability (FR-401)
- **Data Sanitization**: User input will be validated, GitHub URLs will be verified against official repository (FR-404)
- **XSS Prevention**: Admin UI will use WordPress escaping functions (esc_html, esc_attr, esc_url)

**Additional WordPress-Specific Security**:
- **DISALLOW_FILE_MODS Respect**: Force reinstall will be disabled when WordPress constant prohibits file modifications (FR-403)
- **Nonce Verification**: All AJAX endpoints will require valid nonces
- **Transient Locking**: Concurrent operation prevention via transient-based locks (FR-402)

**Verdict**: ✅ **PASS** - No constitutional violations. Feature aligns with applicable error handling, testing, performance, and security principles.

---

## Post-Design Constitution Re-Check

**Re-evaluation Date**: 2025-10-12 (after Phase 1 completion)

**Changes Since Initial Check**: Completed research, data model, AJAX contracts, and quickstart guide.

**Review of Design Artifacts Against Constitution**:

### Error Handling Philosophy ✅ STILL COMPLIANT
- ✅ **Graceful Degradation**: AJAX endpoints return cached results on GitHub timeout (ajax-endpoints.md line 106)
- ✅ **Try-Catch Requirement**: All operations wrapped in error handlers with WP_Error responses (research.md section 4)
- ✅ **Error Isolation**: Force reinstall failures trigger rollback, preserve plugin functionality (data-model.md line 76)

### Testing Requirements ✅ STILL COMPLIANT
- ✅ **Production Flow Testing**: Quickstart.md defines 6 test scenarios using production admin UI
- ✅ **Validation**: Contract compliance tests defined (ajax-endpoints.md lines 516-542)

### Performance Constraints ✅ STILL COMPLIANT
- ✅ **Minimal Overhead**: 5s update check timeout, 60s reinstall timeout enforced (NFR-101, NFR-103)
- ✅ **Memory Management**: Transients with TTL expiry (5min cache, 120s lock), FIFO history (5 entries max) (data-model.md line 285)

### Security Principles ✅ STILL COMPLIANT
- ✅ **Capability Verification**: All 3 AJAX endpoints validate `update_plugins` capability (ajax-endpoints.md)
- ✅ **Data Sanitization**: Nonce validation required for all endpoints (ajax-endpoints.md security section)
- ✅ **XSS Prevention**: All responses use `wp_send_json_success/error`, WordPress escaping functions

**Additional WordPress-Specific Security**: ✅ STILL COMPLIANT
- ✅ **DISALLOW_FILE_MODS**: Checked before force_reinstall (ajax-endpoints.md line 179)
- ✅ **Nonce Verification**: Contract requires nonce validation (ajax-endpoints.md security flow)
- ✅ **Transient Locking**: 120s TTL prevents orphaned locks (research.md line 68)

**New Violations Identified**: None

**Design Refactoring Required**: No

**Final Verdict**: ✅ **PASS** - Design remains constitutionally compliant. Ready for Phase 2 (Task Planning).

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

**Structure Decision**: Option 1 (Single project) - WordPress plugin with standard structure (includes/, assets/, admin/)

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
- Load `.specify/templates/tasks-template.md` as base template
- Generate tasks from Phase 1 design artifacts:
  * **research.md** → Architecture decision validation tasks
  * **data-model.md** → Entity/model implementation tasks (4 entities)
  * **contracts/ajax-endpoints.md** → AJAX endpoint implementation + contract test tasks (3 endpoints)
  * **quickstart.md** → Manual validation scenario tasks (6 scenarios)

**Task Categories & Sources**:

1. **Infrastructure Tasks** (from research.md)
   - Implement transient-based locking mechanism
   - Implement disk space validation
   - Implement operation timeout enforcement
   - Implement cache clearing mechanism

2. **Data Model Tasks** (from data-model.md)
   - Implement Update Check Request (ephemeral)
   - Implement Force Reinstall Operation tracker
   - Implement Plugin Installation State (transient cache)
   - Implement Update History Entry (persistent log)

3. **AJAX Endpoint Tasks** (from ajax-endpoints.md)
   - Implement `cuft_check_updates` endpoint + contract tests
   - Implement `cuft_force_reinstall` endpoint + contract tests
   - Implement `cuft_get_update_history` endpoint + contract tests
   - Create AJAX handler class extending existing `CUFT_Updater_Ajax`

4. **Service Layer Tasks** (from research.md + data-model.md)
   - Implement `CUFT_Force_Update_Handler` orchestrator class
   - Implement `CUFT_Update_Lock_Manager` for transient locking
   - Implement `CUFT_Disk_Space_Validator` for pre-flight checks
   - Integrate with existing `CUFT_Backup_Manager` (Feature 008)
   - Integrate with existing `CUFT_Update_Checker` (Feature 007)

5. **Admin UI Tasks** (from research.md + quickstart.md)
   - Create `includes/admin/views/force-update-tab.php` UI template
   - Implement "Check for Updates" button with AJAX binding
   - Implement "Force Reinstall Latest Version" button with confirmation
   - Create progress indicator UI (polling-based)
   - Display update history table (last 5 entries)
   - Modify `CUFT_Admin::register_settings()` to add new tab

6. **JavaScript Tasks** (from ajax-endpoints.md + research.md)
   - Create `assets/admin/cuft-force-update.js` client-side logic
   - Implement AJAX request handlers with error handling
   - Implement progress polling mechanism (500ms interval)
   - Implement button state management (loading, disabled, enabled)

7. **CSS/Styling Tasks**
   - Create `assets/admin/cuft-force-update.css` for UI styling
   - Style loading indicators and progress bars
   - Style update history table

8. **Integration Tasks**
   - Add WP-Cron daily cleanup job for old history entries (7-day TTL)
   - Enqueue admin scripts/styles on Settings page only
   - Generate nonce in admin page render
   - Add cleanup to plugin deactivation hook

9. **Contract Test Tasks** (from ajax-endpoints.md testing section)
   - Test `cuft_check_updates`: 6 test cases (valid, no update, invalid nonce, timeout, rate limit, concurrent)
   - Test `cuft_force_reinstall`: 9 test cases (valid, errors, rollback, timeout, disk space, etc.)
   - Test `cuft_get_update_history`: 4 test cases (with history, empty, permissions, nonce)

10. **Manual Validation Tasks** (from quickstart.md)
    - QS-1: Manual update check scenario
    - QS-2: Force reinstall (update available)
    - QS-3: Force reinstall (already current)
    - QS-EC1: Insufficient disk space edge case
    - QS-EC2: GitHub timeout edge case
    - QS-EC3: Concurrent operations edge case

**Task Ordering Strategy**:
1. **Foundation Layer** (infrastructure, utilities, models) - Can be parallelized [P]
2. **Service Layer** (orchestrators, managers, validators) - Depends on foundation
3. **AJAX Layer** (endpoint handlers) - Depends on services
4. **UI Layer** (admin views, JavaScript, CSS) - Depends on AJAX endpoints
5. **Integration Layer** (hooks, cron, enqueue) - Depends on UI
6. **Testing Layer** (contract tests, manual validation) - Depends on all implementation

**Parallel Execution Markers [P]**:
- Mark independent file creations as [P] (e.g., model classes, utility classes)
- Sequential dependencies: Services → AJAX → UI → Integration
- Test tasks always last (TDD principle: implementation makes tests pass)

**Estimated Task Count**: 35-40 tasks
- Infrastructure: 4 tasks
- Data Models: 4 tasks
- AJAX Endpoints: 4 tasks (including test tasks)
- Service Layer: 5 tasks
- Admin UI: 6 tasks
- JavaScript: 4 tasks
- CSS: 2 tasks
- Integration: 4 tasks
- Contract Tests: 3 tasks (19 total test cases)
- Manual Validation: 6 tasks

**IMPORTANT**: This phase is executed by the `/tasks` command, NOT by `/plan`. The `/plan` command stops here.

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
- [x] Phase 0: Research complete (/plan command) ✅ 2025-10-12
- [x] Phase 1: Design complete (/plan command) ✅ 2025-10-12
- [x] Phase 2: Task planning complete (/plan command - describe approach only) ✅ 2025-10-12
- [x] Phase 3: Tasks generated (/tasks command) ✅ 2025-10-14
- [ ] Phase 4: Implementation complete - **Next Step**
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS ✅
- [x] Post-Design Constitution Check: PASS ✅
- [x] All NEEDS CLARIFICATION resolved ✅ (No NEEDS CLARIFICATION markers in Technical Context)
- [x] Complexity deviations documented ✅ (No deviations - Complexity Tracking table empty)

**Generated Artifacts**:
- [x] `specs/009-force-install-update/plan.md` (this file)
- [x] `specs/009-force-install-update/research.md` (Phase 0)
- [x] `specs/009-force-install-update/data-model.md` (Phase 1)
- [x] `specs/009-force-install-update/contracts/ajax-endpoints.md` (Phase 1)
- [x] `specs/009-force-install-update/quickstart.md` (Phase 1)
- [x] `CLAUDE.md` updated with feature context (Phase 1)
- [x] `specs/009-force-install-update/tasks.md` (Phase 3 - /tasks command) ✅ 2025-10-14

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*

## 🎯 Next Steps

**Command to Execute**: `/tasks`

**What This Will Do**:
- Load `.specify/templates/tasks-template.md`
- Generate 35-40 numbered, dependency-ordered tasks from Phase 1 artifacts
- Create `specs/009-force-install-update/tasks.md`
- Mark parallelizable tasks with [P]
- Organize tasks into: Infrastructure → Services → AJAX → UI → Integration → Testing

**After /tasks Completion**:
- Review generated tasks.md for completeness
- Begin Phase 4 implementation following task order
- Execute manual validation scenarios from quickstart.md
- Mark feature complete when all acceptance criteria pass
