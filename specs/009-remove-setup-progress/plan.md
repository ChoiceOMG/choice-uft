# Implementation Plan: Remove Setup Progress Tracker

**Branch**: `009-remove-setup-progress` | **Date**: 2025-10-12 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/home/r11/dev/choice-uft/specs/009-remove-setup-progress/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path ✓
2. Fill Technical Context ✓
3. Fill Constitution Check ✓
4. Evaluate Constitution Check → PASS ✓
5. Execute Phase 0 → research.md ✓
6. Execute Phase 1 → quickstart.md, CLAUDE.md ✓
7. Re-evaluate Constitution Check → PASS ✓
8. Plan Phase 2 → Task generation approach ✓
9. STOP - Ready for /tasks command ✓
```

## Summary

Remove the setup progress tracker UI component from the plugin admin interface while preserving all update progress functionality. This is a cleanup/refactoring task that removes:
- Setup progress indicator display (`render_setup_progress()` method)
- CSS styles for setup progress (`.cuft-setup-progress`, `.cuft-progress-bar`, `.cuft-progress-fill`, `.cuft-progress-steps`)
- Setup completion calculation logic

**Important**: This does NOT affect update progress indicators which track plugin version updates (distinct functionality).

## Technical Context

**Language/Version**: PHP 7.0+ (WordPress plugin)
**Primary Dependencies**:
- WordPress 5.0+
- WordPress Admin UI framework
**Storage**: WordPress options API (cuft_gtm_id, framework detection state)
**Testing**: Manual verification via WordPress admin interface
**Target Platform**: WordPress 5.0+ on any server
**Project Type**: Single project (WordPress plugin)
**Performance Goals**: N/A (UI removal, no performance impact)
**Constraints**:
- Must not affect update progress indicators (CUFT_Admin_Notices, cuft-progress-indicator.js)
- Must preserve all existing admin functionality
- Must maintain clean UI without progress display
**Scale/Scope**: WordPress plugin with ~500 LOC in admin class

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Relevant Constitutional Principles

**1. JavaScript-First Compatibility Principle**: ✅ NOT APPLICABLE
- This feature only removes PHP-rendered HTML and CSS
- No JavaScript event handling or tracking involved
- Update progress indicator (separate component) uses JavaScript correctly

**2. DataLayer Standardization Rule**: ✅ NOT APPLICABLE
- No dataLayer interactions in this feature
- Only removes UI display logic

**3. Framework Compatibility Principle**: ✅ NOT APPLICABLE
- No form framework interactions
- Only affects admin UI display

**4. Event Firing Rules**: ✅ NOT APPLICABLE
- No form submission or event tracking involved

**5. Error Handling Philosophy**: ✅ COMPLIANT
- Removal is simple code deletion
- No error handling changes needed
- Existing admin functionality remains unchanged

**6. Testing Requirements**: ✅ COMPLIANT
- Manual verification via admin interface
- Test that setup progress doesn't display
- Test that update progress still works
- Test that all admin settings remain functional

**7. Performance Constraints**: ✅ COMPLIANT
- Removing code improves performance
- No new overhead introduced

**8. Security Principles**: ✅ COMPLIANT
- No security implications
- No data handling changes
- No XSS/PII concerns

### Implementation Standards Compliance

**Code Organization**: ✅ COMPLIANT
- Changes confined to existing files:
  - `/includes/class-cuft-admin.php` (remove render_setup_progress method)
  - `/assets/cuft-admin.css` (remove setup progress styles)
- No new files needed

**Documentation Standards**: ✅ COMPLIANT
- Changes will be documented in this spec
- Inline comments explain removal

**Constitutional Assessment**: ✅ PASS
- No constitutional violations
- Straightforward cleanup task
- All principles either compliant or not applicable

## Project Structure

### Documentation (this feature)
```
specs/009-remove-setup-progress/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

**Note**: No `data-model.md` or `contracts/` needed for this feature (UI removal only).

### Source Code (repository root)
```
WordPress Plugin Structure:
choice-universal-form-tracker.php  # Main plugin file (no changes)
includes/
├── class-cuft-admin.php          # MODIFY: Remove render_setup_progress()
└── admin/
    └── class-cuft-admin-notices.php  # NO CHANGES (update progress only)
assets/
├── cuft-admin.css                # MODIFY: Remove setup progress styles
└── admin/js/
    └── cuft-progress-indicator.js    # NO CHANGES (update progress only)
```

**Structure Decision**: Single WordPress plugin project (Option 1)

## Phase 0: Outline & Research

**Research Tasks**:
1. ✓ Identify all setup progress components in codebase
2. ✓ Differentiate setup progress from update progress components
3. ✓ Verify no dependencies on setup progress logic
4. Document removal scope and verification approach

**Findings** (from initial codebase analysis):

### Setup Progress Components (TO REMOVE):
1. **PHP Method**: `CUFT_Admin::render_setup_progress()` in `/includes/class-cuft-admin.php:1397-1434`
   - Calculates setup completion based on GTM ID and framework detection
   - Renders progress bar HTML conditionally (only when setup incomplete)
   - Called from `render_admin_page()` at line 114

2. **CSS Styles** in `/assets/cuft-admin.css:36-84`:
   - `.cuft-setup-progress` (lines 37-44)
   - `.cuft-progress-bar` (lines 52-58)
   - `.cuft-progress-fill` (lines 60-65)
   - `.cuft-progress-steps` (lines 67-72)
   - `.cuft-progress-step` (lines 74-83)

3. **Setup Calculation Logic**:
   - Checks `cuft_gtm_id` option
   - Checks detected frameworks via `CUFT_Form_Detector::get_framework_status()`
   - Calculates percentage: `(completed_steps / total_steps) * 100`

### Update Progress Components (PRESERVE - DO NOT TOUCH):
1. **PHP Class**: `CUFT_Admin_Notices` in `/includes/admin/class-cuft-admin-notices.php`
   - Method: `maybe_display_update_progress_notice()` (lines 198-229)
   - Renders update progress bar (lines 221-223)
   - Uses different CSS classes (in inline styles)

2. **JavaScript**: `/assets/admin/js/cuft-progress-indicator.js`
   - Update progress modal component
   - Stage-based progress (checking, downloading, installing, etc.)
   - Completely separate from setup progress

3. **CSS Styles** in `CUFT_Admin_Notices::enqueue_scripts()` (inline):
   - `.cuft-progress-bar` (update context)
   - `.cuft-progress-fill` (update context)
   - `.cuft-progress-percentage`
   - Scoped to `.cuft-update-progress-notice`

### No Dependencies Found:
- Setup progress is purely display logic
- No other components depend on `render_setup_progress()`
- Removing it will not break any functionality
- Admin settings form works independently

**Output**: See research.md (to be generated)

## Phase 1: Design & Contracts

*Prerequisites: research.md complete*

### Design Approach

This is a **code removal** task with no new data models or API contracts needed.

**Changes Required**:
1. Remove `render_setup_progress()` method call from admin page rendering
2. Remove `render_setup_progress()` method definition
3. Remove setup progress CSS styles from admin stylesheet
4. Verify admin interface renders correctly without progress indicator

**No Data Model Changes**: This feature doesn't modify data structures.

**No API Contracts**: This feature doesn't add/modify endpoints.

**Testing Approach**:
1. **Manual Verification**: Load plugin admin page and verify no setup progress displays
2. **Visual Regression**: Compare admin UI before/after (should be identical except no progress bar)
3. **Functional Test**: Verify all admin settings work (GTM ID, debug mode, etc.)
4. **Update Progress Test**: Trigger an update check and verify update progress still displays

**Output**: quickstart.md, CLAUDE.md update

## Phase 2: Task Planning Approach

*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
1. **Preparation Tasks**:
   - Create backup of files to be modified
   - Document current setup progress behavior (screenshots if possible)

2. **Removal Tasks** (ordered by dependency):
   - Remove `render_setup_progress()` method call from `render_admin_page()`
   - Remove `render_setup_progress()` method definition
   - Remove setup progress CSS styles from `/assets/cuft-admin.css`
   - Remove any orphaned variables or calculations

3. **Verification Tasks**:
   - Load admin page with no GTM ID configured (partial setup)
   - Load admin page with GTM ID configured (complete setup)
   - Verify no setup progress displays in either case
   - Verify update progress still works
   - Verify all admin settings are accessible and functional

4. **Cleanup Tasks**:
   - Check for any references to removed code in comments
   - Update any relevant documentation

**Ordering Strategy**:
- Sequential execution (not parallelizable - single file edits)
- Remove call first, then definition, then styles
- Verification after each major removal

**Estimated Output**: 8-10 numbered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation

*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (manual testing in WordPress admin)

## Complexity Tracking

*No constitutional violations - section not needed*

This is a straightforward code removal task with no complexity concerns.

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
- [x] All NEEDS CLARIFICATION resolved (none existed)
- [x] Complexity deviations documented (none)

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
