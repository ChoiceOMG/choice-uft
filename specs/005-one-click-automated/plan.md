# Implementation Plan: One-Click Automated Update

**Branch**: `005-one-click-automated` | **Date**: 2025-10-03 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-one-click-automated/spec.md`

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
6. Execute Phase 1 → contracts, data-model.md, quickstart.md, agent-specific template file (e.g., `CLAUDE.md` for Claude Code)
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
The plugin requires a one-click automated update feature that integrates with WordPress's standard update mechanism, checking for new releases on GitHub twice daily and providing seamless updates with automatic rollback on failure. The existing AJAX implementation for checking updates has broken due to recent nonce security changes, requiring investigation and fix.

## Technical Context
**Language/Version**: PHP 7.0+, JavaScript ES6+
**Primary Dependencies**: WordPress Core 5.0+, WordPress Plugin Update API, GitHub API v3
**Storage**: WordPress Options API for version metadata, transients for update caching
**Testing**: PHPUnit for backend, Manual testing via admin interface
**Target Platform**: WordPress Admin Dashboard (all modern browsers)
**Project Type**: web (WordPress plugin with admin interface)
**Performance Goals**: Update check < 2 seconds, Download/install < 30 seconds for typical plugin size
**Constraints**: Must not break during plugin deactivation/reactivation, respect WordPress file permissions
**Scale/Scope**: Single plugin updates, no bulk operations required

**Critical Issue from User**: The current implementation's AJAX endpoints are returning "⚠️ Security check failed" due to recent nonce handling changes. The update checker that previously worked is now blocked by security validation.

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Core Principles Compliance:

1. **JavaScript-First Compatibility Principle** ✅
   - Frontend will use vanilla JavaScript for AJAX calls
   - jQuery as fallback if available
   - No external dependencies

2. **DataLayer Standardization Rule** ✅
   - Update events will follow snake_case naming
   - Include `cuft_tracked: true` for update events

3. **Framework Compatibility Principle** ✅
   - Update system isolated from form tracking
   - No interference with existing functionality

4. **Event Firing Rules** ✅
   - Update events distinct from form events
   - Clear success/failure tracking

5. **Error Handling Philosophy** ✅
   - Graceful degradation if GitHub unavailable
   - Automatic rollback on failure
   - Multiple fallback options

6. **Testing Requirements** ✅
   - Test mode for update simulation
   - Production flow validation

7. **Performance Constraints** ✅
   - Minimal overhead for update checks
   - Background processing where possible

8. **Security Principles** ✅
   - Nonce validation for all AJAX calls
   - Capability checks (update_plugins)
   - No PII in logs

## Project Structure

### Documentation (this feature)
```
specs/005-one-click-automated/
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
│   └── class-cuft-updater.php          # Main updater class
├── ajax/
│   └── class-cuft-updater-ajax.php     # AJAX endpoints for updates
└── class-cuft-github-api.php           # GitHub API integration

assets/
├── admin/
│   ├── js/
│   │   └── cuft-updater.js             # Frontend update UI
│   └── css/
│       └── cuft-updater.css             # Update UI styles

tests/
├── test-updater.php                     # PHPUnit tests
└── test-github-api.php                  # API integration tests
```

**Structure Decision**: WordPress plugin standard structure (existing project)

## Phase 0: Outline & Research
1. **Extract unknowns from Technical Context** above:
   - Current nonce implementation causing security failures
   - WordPress Plugin Update API integration requirements
   - GitHub API rate limiting and authentication
   - WordPress filesystem methods for safe updates
   - Transient caching strategy for update checks

2. **Generate and dispatch research agents**:
   ```
   Task: "Research WordPress nonce validation in AJAX handlers"
   Task: "Research WordPress Plugin Update API hooks and filters"
   Task: "Research GitHub API v3 release endpoints and rate limits"
   Task: "Research WordPress Filesystem API for plugin updates"
   Task: "Research WordPress transients for update check caching"
   ```

3. **Consolidate findings** in `research.md` using format:
   - Decision: [what was chosen]
   - Rationale: [why chosen]
   - Alternatives considered: [what else evaluated]

**Output**: research.md with all NEEDS CLARIFICATION resolved

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

1. **Extract entities from feature spec** → `data-model.md`:
   - UpdateStatus entity (current version, latest version, check timestamp)
   - UpdateLog entity (timestamp, action, status, details)
   - GitHubRelease entity (version, download_url, published_at, changelog)
   - UpdateProgress entity (stage, percentage, message)

2. **Generate API contracts** from functional requirements:
   - POST `/wp-admin/admin-ajax.php?action=cuft_check_update` - Check for updates
   - POST `/wp-admin/admin-ajax.php?action=cuft_perform_update` - Execute update
   - GET `/wp-admin/admin-ajax.php?action=cuft_update_status` - Get current status
   - POST `/wp-admin/admin-ajax.php?action=cuft_rollback_update` - Rollback on failure

3. **Generate contract tests** from contracts:
   - Test nonce validation for each endpoint
   - Test capability checks (update_plugins)
   - Test response formats and error handling

4. **Extract test scenarios** from user stories:
   - Update available scenario
   - Already up-to-date scenario
   - Update in progress prevention
   - Network failure handling
   - Automatic rollback on failure

5. **Update agent file incrementally** (O(1) operation):
   - Add update feature context to CLAUDE.md
   - Document nonce issue resolution

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, CLAUDE.md update

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Fix existing nonce validation issue (Priority 1)
- Implement GitHub API integration
- Create update status management
- Build admin UI components
- Implement WordPress update API integration
- Add automatic rollback mechanism
- Create update scheduling (twice daily)
- Add logging and monitoring

**Ordering Strategy**:
- Fix critical bug first (nonce issue)
- Backend infrastructure before frontend
- Core functionality before enhancements
- Tests parallel with implementation

**Estimated Output**: 20-25 numbered, ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*No constitution violations detected - standard WordPress patterns applied*

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command)
- [x] Phase 1: Design complete (/plan command)
- [x] Phase 2: Task planning complete (/plan command - describe approach only)
- [ ] Phase 3: Tasks generated (/tasks command)
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved
- [x] Complexity deviations documented (none needed)

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*