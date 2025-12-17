
# Implementation Plan: Auto-BCC Testing Email

**Branch**: `010-auto-bcc-everyting` | **Date**: 2025-10-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/010-auto-bcc-everyting/spec.md`

## Execution Flow (/plan command scope)
```
1. Load feature spec from Input path
   ✓ Feature spec loaded successfully
2. Fill Technical Context (scan for NEEDS CLARIFICATION)
   ✓ Project Type: WordPress plugin (PHP + JavaScript)
   ✓ Structure Decision: Single project (WordPress plugin architecture)
3. Fill the Constitution Check section
   ✓ Constitution requirements analyzed
4. Evaluate Constitution Check section
   → Constitution check in progress
5. Execute Phase 0 → research.md
   → Pending
6. Execute Phase 1 → contracts, data-model.md, quickstart.md
   → Pending
7. Re-evaluate Constitution Check section
   → Pending
8. Plan Phase 2 → Describe task generation approach
   → Pending
9. STOP - Ready for /tasks command
```

**IMPORTANT**: The /plan command STOPS at step 9. Phases 2-4 are executed by other commands:
- Phase 2: /tasks command creates tasks.md
- Phase 3-4: Implementation execution (manual or via tools)

## Summary

Add a catch-all BCC email feature to CUFT settings that allows WordPress administrators to receive copies of selected WordPress emails (form submissions, user registrations, etc.) for testing purposes. The feature is disabled by default and includes:
- Configurable email type selection (admin chooses which email types to BCC)
- Real-time email address validation
- Rate limiting with admin-defined thresholds
- "Send Test Email" functionality
- BCC failure logging to WordPress debug log
- Duplicate detection (skip BCC when address already a recipient)

This expedites form testing by eliminating the need to modify individual form settings.

## Technical Context
**Language/Version**: PHP 7.4+ (WordPress 5.0+), JavaScript ES6+
**Primary Dependencies**: WordPress Core (wp_mail, admin hooks, settings API), WordPress Debug Log
**Storage**: WordPress options table (`wp_options` - settings persistence)
**Testing**: PHPUnit (contract tests, integration tests), JavaScript unit tests
**Target Platform**: WordPress 5.0+ on PHP 7.4+ (Linux/Windows server)
**Project Type**: WordPress plugin (single project - PHP backend + vanilla JS frontend)
**Performance Goals**: <5ms overhead per email (BCC header addition), <50ms for validation, no blocking operations
**Constraints**: Must not affect primary email delivery, must respect WordPress email hooks, must work with SMTP plugins
**Scale/Scope**: Small feature (~500 lines PHP, ~200 lines JS), single admin settings section, 3-5 WordPress options

## Constitution Check
*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Core Principles Compliance

✅ **JavaScript-First Compatibility Principle**
- Admin UI uses vanilla JavaScript for real-time email validation
- No jQuery dependency for primary functionality
- Progressive enhancement if jQuery available

✅ **DataLayer Standardization Rule**
- NOT APPLICABLE - This feature does not push dataLayer events
- Backend-only email interception feature

✅ **Framework Compatibility Principle**
- NOT APPLICABLE - This is a WordPress core email hook, not form framework-specific

✅ **Event Firing Rules**
- NOT APPLICABLE - This feature intercepts emails, does not fire tracking events

✅ **Error Handling Philosophy**
- Graceful degradation: BCC failures logged but don't affect primary delivery
- Try-catch around all email interception logic
- Fallback: If BCC fails, log error and continue

✅ **Testing Requirements**
- Contract tests for email interception hooks
- Integration tests for BCC functionality with mock emails
- Test scenarios cover all email types and edge cases

✅ **Performance Constraints**
- Minimal overhead: <5ms per email (BCC header addition only)
- No blocking operations (validation async where possible)
- Efficient duplicate detection (O(n) where n = recipients)

✅ **Security Principles**
- Email addresses sanitized using WordPress functions (`sanitize_email()`)
- Capability checks (`manage_options`) for settings access
- No PII logged to console (debug log only)
- CSRF protection via WordPress nonces

### Implementation Standards Compliance

✅ **Code Organization**
- PHP structure: `/includes/email/class-cuft-auto-bcc-manager.php`
- JS structure: `/assets/admin/js/cuft-auto-bcc-admin.js`
- CSS structure: `/assets/admin/css/cuft-auto-bcc-admin.css`
- Follows existing CUFT plugin structure

✅ **Documentation Standards**
- Inline comments for complex logic
- Specification compliance documented
- Change documentation in commit messages

**Gate Status**: ✅ PASS - No constitutional violations detected

## Project Structure

### Documentation (this feature)
```
specs/010-auto-bcc-everyting/
├── plan.md              # This file (/plan command output)
├── research.md          # Phase 0 output (/plan command)
├── data-model.md        # Phase 1 output (/plan command)
├── quickstart.md        # Phase 1 output (/plan command)
├── contracts/           # Phase 1 output (/plan command)
│   ├── wp-mail-filter.md
│   ├── admin-settings-page.md
│   └── test-email-ajax.md
└── tasks.md             # Phase 2 output (/tasks command - NOT created by /plan)
```

### Source Code (repository root)
```
# WordPress Plugin Structure
choice-uft/
├── includes/
│   ├── email/
│   │   ├── class-cuft-auto-bcc-manager.php        # Core BCC logic
│   │   └── class-cuft-email-type-detector.php     # Email type classification
│   ├── admin/
│   │   └── views/
│   │       └── admin-auto-bcc-settings.php        # Settings UI view
│   ├── ajax/
│   │   └── class-cuft-auto-bcc-ajax.php           # Test email endpoint
│   └── class-cuft-admin.php                       # Existing - add settings tab
├── assets/
│   └── admin/
│       ├── js/
│       │   └── cuft-auto-bcc-admin.js             # Real-time validation
│       └── css/
│           └── cuft-auto-bcc-admin.css            # Settings page styles
└── tests/
    ├── contract/
    │   ├── test-wp-mail-filter-contract.php
    │   └── test-admin-settings-contract.php
    ├── integration/
    │   └── email/
    │       ├── test-auto-bcc-manager.php
    │       ├── test-email-type-detection.php
    │       └── test-rate-limiting.php
    └── unit/
        └── email/
            ├── test-email-validation.php
            └── test-duplicate-detection.php
```

**Structure Decision**: Single project (WordPress plugin) - follows existing CUFT architecture

## Phase 0: Outline & Research
*Status: Ready to execute*

### Research Tasks

1. **WordPress Email Hook System**
   - Research: `wp_mail` filter usage and best practices
   - Research: How to intercept emails without breaking other plugins
   - Research: WordPress email header manipulation
   - Decision needed: Which hooks to use (`wp_mail`, `phpmailer_init`, or both)

2. **Email Type Detection**
   - Research: Methods to classify WordPress email types
   - Research: Common email subject/content patterns for form submissions
   - Research: How other plugins detect email types
   - Decision needed: Hook-based vs content-based detection

3. **Rate Limiting Patterns**
   - Research: WordPress transient-based rate limiting
   - Research: Time window strategies (sliding vs fixed)
   - Decision needed: Storage mechanism for rate limit counters

4. **SMTP Plugin Compatibility**
   - Research: How WP Mail SMTP and similar plugins work
   - Research: Hook execution order for email plugins
   - Decision needed: Early vs late hook priority

5. **Real-time Email Validation**
   - Research: Client-side vs server-side validation patterns
   - Research: WordPress AJAX best practices for settings pages
   - Decision needed: Validation debouncing strategy

**Output**: research.md with all research findings and decisions

## Phase 1: Design & Contracts
*Prerequisites: research.md complete*

### Data Model (data-model.md)

**Primary Entity: Auto-BCC Configuration**
- Stored as WordPress option: `cuft_auto_bcc_config`
- Fields:
  - `enabled` (boolean) - Feature on/off
  - `email` (string, validated) - BCC recipient address
  - `email_types` (array) - Selected email types to BCC
  - `rate_limit` (integer) - Max emails per hour (0 = unlimited)
  - `rate_limit_action` (enum: 'log_only', 'pause') - Action when limit exceeded
  - `last_modified` (timestamp) - Last settings change
  - `last_modified_by` (integer) - User ID who changed settings

**Supporting Entity: Rate Limit State**
- Stored as WordPress transient: `cuft_auto_bcc_rate_limit`
- Fields:
  - `count` (integer) - Emails sent in current period
  - `period_start` (timestamp) - Start of current hour
  - `paused_until` (timestamp) - When to resume if paused

**Email Type Classifications**
- `form_submission` - Form plugin emails
- `user_registration` - New user notifications
- `password_reset` - Password reset emails
- `comment_notification` - Comment notifications
- `admin_notification` - WordPress admin emails
- `other` - Unclassified emails

### API Contracts (contracts/)

1. **wp-mail-filter.md** - WordPress `wp_mail` filter contract
   - Input: `$args` array (to, subject, message, headers, attachments)
   - Output: Modified `$args` with BCC header added (or unchanged)
   - Validation: Email type detection, duplicate check, rate limit check
   - Error handling: Log failures, never throw exceptions

2. **admin-settings-page.md** - Settings page rendering contract
   - Input: None (WordPress admin context)
   - Output: HTML form with settings fields
   - Validation: Capability check (`manage_options`)
   - AJAX endpoints: Save settings, validate email, send test

3. **test-email-ajax.md** - Test email AJAX endpoint contract
   - Input: `{ email: string }` (via POST)
   - Output: `{ success: boolean, message: string }`
   - Validation: Nonce, capability, email format
   - Action: Send test email to specified address

### Contract Tests

**Contract tests MUST fail initially (no implementation)**

1. `tests/contract/test-wp-mail-filter-contract.php`
   - Assert: BCC header added when enabled
   - Assert: No modification when disabled
   - Assert: Skips when email already a recipient

2. `tests/contract/test-admin-settings-contract.php`
   - Assert: Settings form renders with all fields
   - Assert: Non-admin users cannot access
   - Assert: Settings save correctly

3. `tests/contract/test-test-email-contract.php`
   - Assert: Test email sends successfully
   - Assert: Requires valid nonce
   - Assert: Returns proper JSON response

### Quickstart Test (quickstart.md)

**Manual validation steps extracted from user stories:**

1. Enable Auto-BCC feature
2. Configure test email address
3. Select "Form Submissions" email type
4. Click "Send Test Email" button
5. Verify test email received
6. Submit a test form
7. Verify form submission email received
8. Disable Auto-BCC feature
9. Submit another test form
10. Verify no BCC received

### Agent Context Update

Run: `.specify/scripts/bash/update-agent-context.sh claude`

Add to agent context:
- Feature: Auto-BCC Testing Email
- Technologies: WordPress email hooks, transient-based rate limiting
- Files: Auto-BCC manager, email type detector, admin settings view

**Output**: data-model.md, /contracts/*, failing tests, quickstart.md, updated CLAUDE.md

## Phase 2: Task Planning Approach
*This section describes what the /tasks command will do - DO NOT execute during /plan*

**Task Generation Strategy**:
- Load `.specify/templates/tasks-template.md` as base
- Generate tasks from Phase 1 design docs
- Each contract → contract test task [P]
- Each entity → model/manager creation task
- Each user story → integration test task
- Implementation tasks to make tests pass

**Ordering Strategy**:
- **Foundation** (can run in parallel):
  1. [P] Create Auto-BCC configuration data model
  2. [P] Create Email Type Detector class
  3. [P] Create Rate Limit Manager class
  4. [P] Write contract tests (failing)

- **Core Implementation** (sequential):
  5. Implement Auto-BCC Manager core logic
  6. Implement wp_mail filter hook
  7. Implement duplicate detection
  8. Implement rate limiting

- **Admin UI** (can run after Foundation):
  9. [P] Create admin settings page view
  10. [P] Create admin JavaScript (validation)
  11. [P] Create admin CSS
  12. Integrate settings page into CUFT admin

- **AJAX & Testing** (can run after Core):
  13. [P] Create test email AJAX endpoint
  14. [P] Write integration tests
  15. Write end-to-end quickstart validation

- **Validation & Polish**:
  16. Make all contract tests pass
  17. Make all integration tests pass
  18. Performance validation (<5ms overhead)
  19. SMTP plugin compatibility testing

**Estimated Output**: 19 numbered, dependency-ordered tasks in tasks.md

**IMPORTANT**: This phase is executed by the /tasks command, NOT by /plan

## Phase 3+: Future Implementation
*These phases are beyond the scope of the /plan command*

**Phase 3**: Task execution (/tasks command creates tasks.md)
**Phase 4**: Implementation (execute tasks.md following constitutional principles)
**Phase 5**: Validation (run tests, execute quickstart.md, performance validation)

## Complexity Tracking
*Fill ONLY if Constitution Check has violations that must be justified*

No constitutional violations detected. This feature follows all established patterns and principles.

## Progress Tracking
*This checklist is updated during execution flow*

**Phase Status**:
- [x] Phase 0: Research complete (/plan command) - research.md generated
- [x] Phase 1: Design complete (/plan command) - data-model.md, contracts/, quickstart.md generated
- [x] Phase 2: Task planning complete (/plan command - approach described)
- [ ] Phase 3: Tasks generated (/tasks command) - **NEXT STEP**
- [ ] Phase 4: Implementation complete
- [ ] Phase 5: Validation passed

**Gate Status**:
- [x] Initial Constitution Check: PASS
- [x] Post-Design Constitution Check: PASS
- [x] All NEEDS CLARIFICATION resolved (via spec clarifications)
- [x] Complexity deviations documented (none - no violations)

**Artifacts Generated**:
- ✅ research.md - Technical decisions and best practices
- ✅ data-model.md - CUFT_Auto_BCC_Config entity, validation rules
- ✅ contracts/wp-mail-filter.md - Email interception contract
- ✅ quickstart.md - Manual validation test plan
- ✅ CLAUDE.md - Updated agent context

---
*Based on Constitution v1.0 - See `.specify/memory/constitution.md`*
