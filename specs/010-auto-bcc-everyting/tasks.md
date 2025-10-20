# Tasks: Auto-BCC Testing Email (Feature 010)

**Branch**: `010-auto-bcc-everyting`
**Input**: Design documents from `/home/r11/dev/choice-uft/specs/010-auto-bcc-everyting/`
**Prerequisites**: plan.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅ (3 files), quickstart.md ✅

## Execution Flow (main)
```
1. Load plan.md from feature directory
   ✓ Found: PHP 7.4+, WordPress 5.0+, WordPress Core APIs
   ✓ Structure: WordPress plugin (includes/email/, includes/admin/, assets/admin/)
2. Load design documents:
   ✓ data-model.md: CUFT_Auto_BCC_Config entity
   ✓ contracts/: 3 contracts (save-settings, send-test-email, wp-mail-filter)
   ✓ research.md: wp_mail filter, transients rate limiting, email detection patterns
   ✓ quickstart.md: 6 test scenarios
3. Generate tasks by category:
   ✓ Setup: Directory structure, WordPress integration
   ✓ Tests: 3 contract tests, 7 integration tests
   ✓ Core: 4 classes (manager, interceptor, rate limiter, detector), validator
   ✓ Admin: AJAX handlers, settings UI, JavaScript, CSS
   ✓ Polish: Unit tests, constitutional validation
4. Apply task rules:
   ✓ Contract tests [P] (different files)
   ✓ Models [P] (independent classes)
   ✓ Services sequential (depend on models)
   ✓ Admin UI sequential (depends on AJAX)
5. Number tasks: T001-T027 (27 tasks total)
6. Dependencies validated: TDD order enforced
7. Parallel execution examples: Grouped [P] tasks
8. Validation complete: All contracts tested, all entities modeled
9. SUCCESS - Tasks ready for execution
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no shared dependencies)
- All paths are absolute from repository root: `/home/r11/dev/choice-uft/`

## Path Convention
WordPress plugin structure (per plan.md):
- **Source**: `includes/email/`, `includes/admin/`, `includes/ajax/`, `assets/admin/`
- **Tests**: `tests/contract/`, `tests/unit/email/`, `tests/integration/email/`

---

## Phase 3.1: Setup & Structure

- [x] **T001** Create email directory structure
  - **Path**: `/home/r11/dev/choice-uft/includes/email/`
  - **Action**: Create directory for email-related classes
  - **Acceptance**: Directory exists and is empty

- [x] **T002** Create test directory structure
  - **Paths**:
    - `/home/r11/dev/choice-uft/tests/contract/`
    - `/home/r11/dev/choice-uft/tests/unit/email/`
    - `/home/r11/dev/choice-uft/tests/integration/email/`
  - **Action**: Create test directories for contract, unit, and integration tests
  - **Acceptance**: All directories exist

- [x] **T003** [P] Create AJAX directory if not exists
  - **Path**: `/home/r11/dev/choice-uft/includes/ajax/`
  - **Action**: Ensure AJAX directory exists for Auto-BCC AJAX handler
  - **Acceptance**: Directory exists (may already exist from other features)

---

## Phase 3.2: Tests First (TDD) ⚠️ MUST COMPLETE BEFORE 3.3

**CRITICAL**: These tests MUST be written and MUST FAIL before ANY implementation begins.

### Contract Tests (Parallel - Different Files)

- [x] **T004** [P] Contract test for AJAX save settings endpoint
  - **Path**: `/home/r11/dev/choice-uft/tests/contract/test-ajax-save-settings-contract.php`
  - **Contract**: `contracts/admin-ajax-save-settings.md`
  - **Test Cases**:
    - Nonce validation fails → 403 error
    - Capability check fails (`update_plugins`) → 403 error
    - Invalid email format → error response with message
    - Valid configuration → success response with saved config
    - WordPress mail validation warning included if mail function unavailable
  - **Expected**: TEST MUST FAIL (endpoint not implemented)

- [x] **T005** [P] Contract test for AJAX send test email endpoint
  - **Path**: `/home/r11/dev/choice-uft/tests/contract/test-ajax-send-test-email-contract.php`
  - **Contract**: `contracts/admin-ajax-send-test-email.md`
  - **Test Cases**:
    - Nonce validation fails → 403 error
    - Capability check fails → 403 error
    - Email sent successfully → success response with subject and timestamp
    - Email send fails → error response with wp_mail failure message
  - **Expected**: TEST MUST FAIL (endpoint not implemented)

- [x] **T006** [P] Contract test for wp_mail filter hook
  - **Path**: `/home/r11/dev/choice-uft/tests/contract/test-wp-mail-filter-contract.php`
  - **Contract**: `contracts/wp-mail-filter.md`
  - **Test Cases**:
    - Filter registered at priority 10
    - BCC header added when feature enabled and email type matches
    - BCC header NOT added when feature disabled
    - BCC header NOT added when email type not selected
    - BCC header NOT added when BCC address already in TO/CC
    - Original email arguments preserved (no modification except headers)
    - Rate limit respected (BCC skipped when limit exceeded)
  - **Expected**: TEST MUST FAIL (filter not registered)

### Integration Tests (Parallel - Different Files)

- [x] **T007** [P] Integration test: Enable → Configure → Test Email workflow
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-auto-bcc-end-to-end.php`
  - **Scenario**: Acceptance scenario #7 from spec.md
  - **Test Flow**:
    1. Enable Auto-BCC feature
    2. Configure email address
    3. Select "form_submission" email type
    4. Save settings
    5. Click "Send Test Email" button
    6. Verify test email received with correct subject "[CUFT Test Email]"
  - **Expected**: TEST MUST FAIL (feature not implemented)

- [x] **T008** [P] Integration test: Form submission triggers BCC
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-form-submission-bcc.php`
  - **Scenario**: Acceptance scenario #2 from spec.md
  - **Test Flow**:
    1. Enable Auto-BCC with test email
    2. Select "form_submission" email type
    3. Trigger WordPress email with subject "Contact Form Submission"
    4. Verify BCC header added to email
    5. Verify original TO address unchanged
  - **Expected**: TEST MUST FAIL (interceptor not implemented)

- [x] **T009** [P] Integration test: Rate limiting enforcement
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-rate-limiting.php`
  - **Scenario**: Edge case - high-volume email scenarios
  - **Test Flow**:
    1. Enable Auto-BCC with rate limit threshold = 2
    2. Send 3 emails
    3. Verify first 2 emails have BCC header
    4. Verify 3rd email does NOT have BCC (rate limit exceeded)
    5. Verify warning logged to debug.log
  - **Expected**: TEST MUST FAIL (rate limiter not implemented)

- [x] **T010** [P] Integration test: SMTP plugin compatibility
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-smtp-plugin-compatibility.php`
  - **Scenario**: Research finding - SMTP plugins use priority 20+
  - **Test Flow**:
    1. Mock SMTP plugin filter at priority 20
    2. Enable Auto-BCC (priority 10)
    3. Send email
    4. Verify BCC added BEFORE SMTP plugin processes (priority order)
    5. Verify BCC header preserved through SMTP processing
  - **Expected**: TEST MUST FAIL (filter priority not set)

- [x] **T011** [P] Integration test: Duplicate email skip logic
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-duplicate-skip.php`
  - **Scenario**: Acceptance scenario - BCC address already a recipient
  - **Test Flow**:
    1. Enable Auto-BCC with email "test@example.com"
    2. Send email where TO = "test@example.com"
    3. Verify BCC header NOT added (duplicate prevention)
    4. Send email where TO = "other@example.com"
    5. Verify BCC header IS added
  - **Expected**: TEST MUST FAIL (duplicate detection not implemented)

- [x] **T012** [P] Integration test: WordPress mail validation warning
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-mail-validation-warning.php`
  - **Scenario**: Acceptance scenario - WordPress mail function validation
  - **Test Flow**:
    1. Mock WordPress mail function as unavailable
    2. Attempt to save Auto-BCC settings
    3. Verify warning displayed: "WordPress mail function may not be configured"
    4. Verify settings STILL saved (warning is non-blocking)
  - **Expected**: TEST MUST FAIL (validation not implemented)

- [x] **T013** [P] Integration test: BCC failure doesn't block primary email
  - **Path**: `/home/r11/dev/choice-uft/tests/integration/email/test-bcc-failure-graceful.php`
  - **Scenario**: Edge case - BCC delivery failures
  - **Test Flow**:
    1. Enable Auto-BCC
    2. Mock BCC email as invalid/bounced
    3. Send primary email
    4. Verify primary email SENT successfully (not blocked by BCC failure)
    5. Verify BCC failure logged to debug.log
  - **Expected**: TEST MUST FAIL (error handling not implemented)

---

## Phase 3.3: Data Model & Validation (ONLY after tests failing)

### Models & Validators (Parallel - Independent Classes)

- [x] **T014** [P] Create CUFT_Auto_BCC_Config data model class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-auto-bcc-config.php`
  - **Data Model**: `data-model.md` - CUFT_Auto_BCC_Config entity
  - **Implementation**:
    - 7 fields: enabled, bcc_email, selected_email_types, rate_limit_threshold, rate_limit_action, last_modified, last_modified_by
    - `get_config()`: Load from WordPress options (`cuft_auto_bcc_config`)
    - `save_config()`: Save to WordPress options with automatic timestamps
    - Default values: enabled=false, threshold=100, action=log_only
  - **Acceptance**: Class exists, get/save methods work with WordPress Options API

- [x] **T015** [P] Create CUFT_Auto_BCC_Validator class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-auto-bcc-validator.php`
  - **Data Model**: `data-model.md` - Validation Rules section
  - **Implementation**:
    - `validate_config()`: Full config validation, returns WP_Error on failure
    - `validate_email()`: Uses `is_email()` and `sanitize_email()`
    - `validate_email_types()`: Filters to valid types (form_submission, user_registration, etc.)
    - `validate_rate_limit()`: Ensures threshold 0-10,000
    - `sanitize_config()`: Sanitizes all fields before save
  - **Acceptance**: All validation methods return expected true/WP_Error, sanitization works

---

## Phase 3.4: Core Services (Sequential - Depend on Models)

- [x] **T016** Create CUFT_Email_Type_Detector service class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-email-type-detector.php`
  - **Research**: `research.md` - Email Type Detection Strategy
  - **Implementation**:
    - `detect_type( $email_args )`: Returns email type string
    - Detection patterns:
      - `is_form_submission()`: Subject contains "form", "submission", "contact"
      - `is_user_registration()`: Subject contains "new user", "registration"
      - `is_password_reset()`: Subject contains "password reset"
      - `is_comment_notification()`: Subject contains "new comment"
      - `is_admin_notification()`: TO matches admin email
    - Default: returns 'other' if no match
  - **Dependencies**: T014 complete (uses config for debugging)
  - **Acceptance**: Correctly classifies emails based on subject/headers

- [x] **T017** Create CUFT_Rate_Limiter service class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-rate-limiter.php`
  - **Research**: `research.md` - Rate Limiting with WordPress Transients
  - **Implementation**:
    - `check_rate_limit( $threshold )`: Returns true if under limit, false if exceeded
    - Transient key: `cuft_bcc_rate_limit_{YYYY-MM-DD-HH}` (hourly buckets)
    - Increment counter on each check
    - `get_current_count()`: Get current hour's BCC count
    - `reset_count()`: Clear transient (for testing)
  - **Dependencies**: T014 complete (uses config for threshold/action)
  - **Acceptance**: Rate limiting enforces threshold, transients expire after 1 hour

- [x] **T018** Create CUFT_Email_Interceptor service class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-email-interceptor.php`
  - **Contract**: `contracts/wp-mail-filter.md`
  - **Implementation**:
    - `init()`: Register `wp_mail` filter at priority 10
    - `intercept_email( $args )`: Filter callback
      - Check if feature enabled
      - Detect email type (use T016)
      - Check if type selected in config
      - Check rate limit (use T017)
      - Check for duplicates (BCC not in TO/CC)
      - Add BCC header to $args['headers']
      - Log failures to debug.log
    - `is_bcc_duplicate()`: Check if BCC already in TO/CC
  - **Dependencies**: T014, T016, T017 complete
  - **Acceptance**: wp_mail filter registered, BCC header added when conditions met

- [x] **T019** Create CUFT_Auto_BCC_Manager orchestrator class
  - **Path**: `/home/r11/dev/choice-uft/includes/email/class-cuft-auto-bcc-manager.php`
  - **Plan**: `plan.md` - Main orchestrator
  - **Implementation**:
    - `init()`: Initialize all services (interceptor, rate limiter, detector)
    - `get_config()`: Proxy to config model
    - `save_config()`: Proxy to config model with validation
    - `send_test_email()`: Send test email with subject "[CUFT Test Email]"
    - `validate_mail_function()`: Check if wp_mail exists and PHPMailer available
    - Static instance management
  - **Dependencies**: T014, T015, T016, T017, T018 complete
  - **Acceptance**: All services initialized, test email sends successfully

---

## Phase 3.5: Admin AJAX Handlers (Sequential - Depend on Manager)

- [x] **T020** Create CUFT_Auto_BCC_Ajax class
  - **Path**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-auto-bcc-ajax.php`
  - **Contracts**: `admin-ajax-save-settings.md`, `admin-ajax-send-test-email.md`
  - **Implementation**:
    - `init()`: Register AJAX actions (save_settings, send_test_email)
    - `save_settings()`:
      - Verify nonce
      - Check `update_plugins` capability
      - Validate and sanitize input (use T015)
      - Save config (use T019)
      - Validate WordPress mail function (use T019)
      - Return JSON success/error with warnings
    - `send_test_email()`:
      - Verify nonce
      - Check `update_plugins` capability
      - Send test email (use T019)
      - Return JSON success/error
  - **Dependencies**: T019 complete (uses manager)
  - **Acceptance**: AJAX endpoints return correct JSON, nonce/capability validated

---

## Phase 3.6: Admin UI (Sequential - Depends on AJAX)

- [x] **T021** Modify class-cuft-admin.php to add Auto-BCC tab
  - **Path**: `/home/r11/dev/choice-uft/includes/admin/class-cuft-admin.php`
  - **Research**: `research.md` - Admin UI Integration
  - **Implementation**:
    - Add 'auto-bcc' to `$tabs` array
    - Add tab content rendering:
      ```php
      if ( $active_tab === 'auto-bcc' ) {
          include CUFT_PLUGIN_DIR . 'includes/admin/views/admin-auto-bcc-settings.php';
      }
      ```
    - Enqueue JavaScript and CSS for Auto-BCC tab only
  - **Dependencies**: T022 (view file must exist)
  - **Acceptance**: Auto-BCC tab appears in settings, view renders

- [x] **T022** Create admin-auto-bcc-settings.php view file
  - **Path**: `/home/r11/dev/choice-uft/includes/admin/views/admin-auto-bcc-settings.php`
  - **Plan**: `plan.md` - UI Components
  - **Implementation**:
    - Enable/Disable toggle checkbox
    - Email address input field (with inline validation placeholder)
    - Email type checkboxes (form_submission, user_registration, etc.)
    - Rate limit threshold number input
    - Rate limit action dropdown (log_only, pause_until_next_period)
    - "Send Test Email" button (AJAX)
    - "Save Settings" button (AJAX)
    - WordPress nonce field
    - Help text explaining feature
  - **Dependencies**: T020 complete (AJAX handlers must exist)
  - **Acceptance**: Form renders, all fields present, nonce included

- [x] **T023** Create cuft-auto-bcc-admin.js JavaScript file
  - **Path**: `/home/r11/dev/choice-uft/assets/admin/js/cuft-auto-bcc-admin.js`
  - **Plan**: `plan.md` - Real-Time Validation
  - **Implementation**:
    - Email input blur event → AJAX validate email format → show green check / red X
    - "Save Settings" button click → AJAX save_settings → show success/error message
    - "Send Test Email" button click → AJAX send_test_email → show success/error message
    - Disable buttons during AJAX (prevent double-submit)
    - Clear messages after 5 seconds
  - **Dependencies**: T020 complete (AJAX endpoints must exist)
  - **Acceptance**: Real-time validation works, AJAX calls successful, UI feedback shown

- [x] **T024** Create cuft-auto-bcc-admin.css stylesheet
  - **Path**: `/home/r11/dev/choice-uft/assets/admin/css/cuft-auto-bcc-admin.css`
  - **Plan**: `plan.md` - Styling
  - **Implementation**:
    - Card-based layout (match existing CUFT admin tabs)
    - Inline validation feedback styles (green check, red X icons)
    - Button hover/active states
    - Responsive grid for form fields
    - Success/error message styling (WordPress admin notice boxes)
  - **Dependencies**: T022 complete (view HTML must exist for styling)
  - **Acceptance**: Settings tab matches existing CUFT admin design

---

## Phase 3.7: Plugin Integration (Sequential - Wire Everything Together)

- [x] **T025** Register Auto-BCC classes in main plugin file
  - **Path**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
  - **Action**: Add class autoloading and initialization
  - **Implementation**:
    - Include class files for email/ directory classes
    - Initialize CUFT_Auto_BCC_Manager (singleton)
    - Initialize CUFT_Auto_BCC_Ajax
  - **Dependencies**: T019, T020 complete (classes must exist)
  - **Acceptance**: Plugin loads without errors, Auto-BCC feature active

---

## Phase 3.8: Polish & Validation

### Unit Tests (Parallel - Independent Test Files)

- [x] **T026** [P] Unit tests for CUFT_Email_Type_Detector
  - **Path**: `/home/r11/dev/choice-uft/tests/unit/email/test-email-type-detector.php`
  - **Test Cases**:
    - Form submission subjects detected correctly
    - User registration subjects detected correctly
    - Password reset subjects detected correctly
    - Comment notification subjects detected correctly
    - Admin notification TO addresses detected correctly
    - Unrecognized emails return 'other'
  - **Dependencies**: T016 complete (detector class must exist)
  - **Acceptance**: All detection patterns validated with unit tests

- [x] **T027** [P] Unit tests for CUFT_Rate_Limiter
  - **Path**: `/home/r11/dev/choice-uft/tests/unit/email/test-rate-limiter.php`
  - **Test Cases**:
    - First email of hour allows BCC
    - Nth email under threshold allows BCC
    - (N+1)th email over threshold blocks BCC
    - Transient expires after 1 hour (reset count)
    - get_current_count() returns accurate count
    - reset_count() clears transient
  - **Dependencies**: T017 complete (rate limiter class must exist)
  - **Acceptance**: Rate limiting logic validated with unit tests

---

## Dependencies Graph

```
Phase 3.1: Setup
  T001, T002, T003 [P]
    ↓
Phase 3.2: Tests (MUST FAIL)
  T004, T005, T006 [P] (Contract tests)
  T007, T008, T009, T010, T011, T012, T013 [P] (Integration tests)
    ↓
Phase 3.3: Data Model
  T014, T015 [P] (Model & Validator)
    ↓
Phase 3.4: Core Services
  T016 (Detector) → depends on T014
  T017 (Rate Limiter) → depends on T014
  T016, T017 → T018 (Interceptor)
  T018 → T019 (Manager)
    ↓
Phase 3.5: AJAX
  T020 (AJAX Handlers) → depends on T019
    ↓
Phase 3.6: Admin UI
  T022 (View) → depends on T020
  T021 (Admin Tab) → depends on T022
  T023 (JavaScript) → depends on T020
  T024 (CSS) → depends on T022
    ↓
Phase 3.7: Integration
  T025 (Plugin Bootstrap) → depends on T019, T020
    ↓
Phase 3.8: Polish
  T026, T027 [P] (Unit tests)
```

---

## Parallel Execution Examples

### Phase 3.1 - Setup (All Parallel)
```bash
# All directory creation can run in parallel
Task T001 & Task T002 & Task T003
```

### Phase 3.2 - Contract Tests (All Parallel)
```bash
# All contract tests are independent files
Task T004 & Task T005 & Task T006
```

### Phase 3.2 - Integration Tests (All Parallel)
```bash
# All integration tests are independent files
Task T007 & Task T008 & Task T009 & Task T010 & Task T011 & Task T012 & Task T013
```

### Phase 3.3 - Models (Both Parallel)
```bash
# Config and Validator are independent classes
Task T014 & Task T015
```

### Phase 3.8 - Unit Tests (Both Parallel)
```bash
# Unit tests are independent files
Task T026 & Task T027
```

---

## Validation Checklist

- [x] All contracts have tests? YES (T004: save-settings, T005: send-test-email, T006: wp-mail-filter)
- [x] All entities have models? YES (T014: CUFT_Auto_BCC_Config)
- [x] All endpoints implemented? YES (T020: AJAX save_settings, send_test_email)
- [x] All integration scenarios tested? YES (T007-T013: 7 integration tests matching spec.md acceptance scenarios)
- [x] TDD order enforced? YES (Tests T004-T013 before implementation T014-T025)
- [x] Dependencies clear? YES (Dependency graph provided above)
- [x] Parallel execution identified? YES ([P] markers on 16 tasks)

---

## Manual Testing Workflow

After all tasks complete, execute quickstart.md:

1. Navigate to Settings → Universal Form Tracker → Auto-BCC tab
2. Enable feature, configure email, select form_submission
3. Save settings (verify success message)
4. Click "Send Test Email" (verify email received)
5. Submit real contact form (verify BCC received)
6. Test rate limiting (submit 101 forms, verify 101st skipped)
7. Disable feature (verify BCC stops)

**Success**: All 7 acceptance scenarios from spec.md validated ✅

---

## Constitutional Validation

After implementation, verify:
- [ ] **Performance**: Email interception overhead <50ms (measure with timer)
- [ ] **Error Handling**: BCC failures don't block primary emails (test with invalid BCC)
- [ ] **Security**: Nonce validation, capability checks, input sanitization (code review)
- [ ] **Zero Impact**: Primary email delivery unaffected (send email with feature disabled)

---

**Total Tasks**: 27 (T001-T027)
**Parallel Tasks**: 16 tasks marked [P]
**Estimated Complexity**: Medium (WordPress plugin, standard patterns)
**Ready for Execution**: ✅ All tasks defined with clear acceptance criteria

---
*Tasks generated: 2025-10-16*
*Ready for Phase 3 execution*
*No new releases planned - feature development continues*
