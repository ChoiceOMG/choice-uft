# Tasks: Custom GTM Server Domain with Health Checks

**Input**: Design documents from `/specs/006-provide-users-with/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/
**Branch**: `006-provide-users-with`

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → Tech stack: PHP 7.0+, JavaScript ES6+, WordPress 5.0+
   → Storage: wp_options table
   → Structure: WordPress plugin (single codebase)
2. Load optional design documents:
   → data-model.md: 2 entities (Custom Server Config, Health Check Result)
   → contracts/: 2 files (ajax-endpoints.md, frontend-loading.md)
   → research.md: Critical nonce fix identified
3. Generate tasks by category:
   → Critical Fix: Nonce validation (1 task)
   → Tests: AJAX endpoint validation (5 tasks)
   → Backend: Health check logic, AJAX handlers (6 tasks)
   → Storage: wp_options management (3 tasks)
   → Frontend: Script loading, UI updates (4 tasks)
   → Automation: Cron jobs (2 tasks)
   → Integration: Admin UI (3 tasks)
   → Validation: Testing scenarios (4 tasks)
4. Apply task rules:
   → Critical fix FIRST (blocks everything)
   → Different files = mark [P] for parallel
   → Same file (class-cuft-admin.php) = sequential
5. Number tasks sequentially (T001-T028)
6. Validate task completeness:
   → All AJAX endpoints tested ✓
   → Health check logic complete ✓
   → Frontend loading updated ✓
   → Cron automation configured ✓
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **File paths**: Absolute paths from repository root

## Phase 3.1: Critical Fix (MUST BE FIRST)
**BLOCKING**: All AJAX functionality depends on this fix

- [ ] **T001** Fix nonce validation in `/includes/class-cuft-admin.php`
  - Update line 780: Change `wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )` to `wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )`
  - Update line 940: Change `wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )` to `wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )`
  - Update line 1073: Change `wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )` to `wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )`
  - Update line 2066: Change `wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' )` to `wp_verify_nonce( $_POST['nonce'], 'cuft_admin' )`
  - Test that AJAX endpoints respond without "Security check failed" error

## Phase 3.2: Backend Health Check Logic
**PREREQUISITE**: T001 must be complete

- [ ] **T002** Update existing health check timeout in `/includes/class-cuft-admin.php` (lines 985-1065)
  - Change timeout from 10 seconds to 5 seconds in `wp_remote_get()` calls
  - Update both `gtm.js` and `ns.html` endpoint checks
  - Ensure error handling for timeout scenarios

- [ ] **T003** Add health check result storage in `/includes/class-cuft-admin.php`
  - Store `cuft_sgtm_health_last_check` (timestamp) after each check
  - Store `cuft_sgtm_health_last_result` (boolean) with success/failure
  - Store `cuft_sgtm_health_last_message` (string) with error details
  - Store `cuft_sgtm_health_response_time` (float) with timing data

- [ ] **T004** Implement consecutive success/failure counters in `/includes/class-cuft-admin.php`
  - Add `cuft_sgtm_health_consecutive_success` counter (increments on success, resets on failure)
  - Add `cuft_sgtm_health_consecutive_failure` counter (increments on failure, resets on success)
  - Implement logic: switch to custom after 3 consecutive successes
  - Implement logic: switch to fallback on first failure

- [ ] **T005** Add server switching logic in `/includes/class-cuft-admin.php`
  - Update `cuft_sgtm_active_server` option based on health check results
  - Set to 'custom' when consecutive_success >= 3
  - Set to 'fallback' when health check fails
  - Create admin notice for status changes (use `add_option()` for notice trigger)

## Phase 3.3: AJAX Endpoint Handlers
**PREREQUISITE**: T001-T005 must be complete

- [ ] **T006** [P] Create AJAX handler for `cuft_save_sgtm_config` in `/includes/class-cuft-admin.php`
  - Add action hook: `add_action('wp_ajax_cuft_save_sgtm_config', ...)`
  - Verify nonce with `cuft_admin` action
  - Sanitize and validate URL input
  - Save to `cuft_sgtm_enabled` and `cuft_sgtm_url` options
  - Trigger initial health check
  - Return JSON response with configuration status

- [ ] **T007** [P] Create AJAX handler for `cuft_manual_health_check` in `/includes/class-cuft-admin.php`
  - Add action hook: `add_action('wp_ajax_cuft_manual_health_check', ...)`
  - Verify nonce with `cuft_admin` action
  - Call existing health check function
  - Update all health check options
  - Return JSON with health status and timing

- [ ] **T008** [P] Create AJAX handler for `cuft_get_sgtm_status` in `/includes/class-cuft-admin.php`
  - Add action hook: `add_action('wp_ajax_cuft_get_sgtm_status', ...)`
  - Verify nonce with `cuft_admin` action
  - Retrieve all configuration and health check options
  - Calculate next scheduled check time
  - Return JSON with complete status including human-readable timestamps

- [ ] **T009** Update existing `cuft_test_sgtm` AJAX handler in `/includes/class-cuft-admin.php`
  - Ensure it uses updated 5-second timeout
  - Verify nonce validation uses `cuft_admin` (should be fixed by T001)
  - Return structured response matching contract (valid, message, response_time, endpoints_tested)

## Phase 3.4: Cron Automation
**PREREQUISITE**: T002-T005 must be complete

- [X] **T010** Create cron job registration in `/choice-universal-form-tracker.php` activation hook
  - Add custom interval: `six_hours` = 6 * 60 * 60 seconds (already in class-cuft-admin.php:1168)
  - Register cron event: `cuft_scheduled_health_check` on activation (added to activate() method)
  - Hook: `add_action('cuft_scheduled_health_check', 'scheduled_health_check_callback')` (already exists)
  - Unregister cron on deactivation (added to deactivate() method)

- [ ] **T011** Implement scheduled health check callback in `/includes/class-cuft-admin.php`
  - Check if custom server is enabled
  - Perform health check on configured URL
  - Update all health check options
  - Handle server switching based on consecutive counters
  - Create admin notice if status changes

## Phase 3.5: Frontend GTM Script Loading
**PREREQUISITE**: T002-T005 must be complete (need reliable server status)

- [ ] **T012** Create GTM loader class method in `/includes/class-cuft-admin.php` or new file
  - Method: `get_gtm_server_url()` - returns appropriate server URL
  - Check `cuft_sgtm_enabled` option
  - Check `cuft_sgtm_active_server` option
  - Return custom URL if active_server == 'custom', else return Google default

- [ ] **T013** Update GTM head script output in `/includes/class-cuft-admin.php`
  - Modify existing GTM head script injection
  - Use `get_gtm_server_url()` for script source
  - Add data attributes: `data-cuft-gtm-source`, `data-cuft-gtm-server`
  - Add fallback reason attribute when using fallback

- [ ] **T014** Update GTM noscript output in `/includes/class-cuft-admin.php`
  - Modify existing GTM noscript iframe
  - Use `get_gtm_server_url()` for iframe source
  - Ensure both `gtm.js` and `ns.html` use same server

## Phase 3.6: Admin UI Enhancements
**PREREQUISITE**: T006-T008 must be complete (AJAX handlers exist)

- [ ] **T015** Add custom server configuration fields to settings page
  - Add checkbox: "Enable Custom GTM Server"
  - Add text input: "Custom Server URL" with validation
  - Add button: "Test Server" (triggers AJAX `cuft_test_sgtm`)
  - Display validation status (green checkmark or red error)

- [ ] **T016** Add health check status display to settings page
  - Display current active server (custom/fallback)
  - Display last check timestamp (human-readable: "5 minutes ago")
  - Display last check result (success/failure with icon)
  - Display consecutive success/failure counts
  - Add button: "Run Health Check Now" (triggers AJAX `cuft_manual_health_check`)

- [ ] **T017** Add admin notices for server status changes
  - Check for status change triggers in options (e.g., `cuft_sgtm_server_recovered`)
  - Display success notice: "✅ Custom GTM server is now active"
  - Display warning notice: "⚠️ Custom GTM server unavailable, using fallback"
  - Make notices dismissible
  - Clean up notice triggers after display

## Phase 3.7: JavaScript Admin Integration
**PREREQUISITE**: T006-T008 must be complete (AJAX handlers exist)

- [ ] **T018** Update admin JavaScript in `/assets/cuft-admin.js`
  - Add event handler for "Test Server" button
  - AJAX call to `cuft_test_sgtm` with URL from input field
  - Display validation result to user (success/error message)
  - Update UI with response time and endpoint test results

- [ ] **T019** Add manual health check JavaScript in `/assets/cuft-admin.js`
  - Add event handler for "Run Health Check Now" button
  - AJAX call to `cuft_manual_health_check`
  - Update status display with latest results
  - Show loading spinner during check

- [ ] **T020** Add status polling JavaScript in `/assets/cuft-admin.js`
  - AJAX call to `cuft_get_sgtm_status` on page load
  - Update all status displays with retrieved data
  - Format timestamps for human readability
  - Display next scheduled check time

## Phase 3.8: Frontend Background Health Check (Optional Enhancement)
**PREREQUISITE**: T007 must be complete

- [ ] **T021** Add frontend health check script
  - Create new file: `/assets/cuft-health-check.js`
  - Check localStorage for last check timestamp
  - Skip if checked within last hour
  - AJAX call to `cuft_manual_health_check` after 30-second delay
  - Silent fail on errors (don't interrupt user experience)
  - Enqueue script only when custom server is enabled

## Phase 3.9: Testing & Validation
**PREREQUISITE**: All implementation tasks (T001-T021) must be complete

- [ ] **T022** Test Scenario 1: Happy path with working custom server
  - Follow quickstart.md "Scenario 1: Happy Path"
  - Configure working custom server URL
  - Verify test passes and settings save
  - Check frontend loads from custom domain
  - Verify health checks maintain custom server status

- [ ] **T023** Test Scenario 2: Server initially offline
  - Follow quickstart.md "Scenario 2: Server Initially Offline"
  - Configure offline server URL
  - Verify automatic fallback to Google
  - Check frontend loads from Google domain
  - Verify appropriate warning messages

- [ ] **T024** Test Scenario 3: Server goes down after working
  - Follow quickstart.md "Scenario 3: Server Goes Down After Working"
  - Start with working server, then simulate failure
  - Trigger health check (manual or wait for cron)
  - Verify automatic switch to fallback
  - Check admin notice appears

- [ ] **T025** Test Scenario 4: Server recovery (3 consecutive successes)
  - Follow quickstart.md "Scenario 4: Server Recovery"
  - Start in fallback mode with offline server
  - Bring server back online
  - Trigger 3 consecutive health checks
  - Verify switch back to custom server after 3rd success
  - Check recovery admin notice

## Phase 3.10: Documentation & Cleanup

- [ ] **T026** [P] Update plugin documentation
  - Add custom server feature to README
  - Document new AJAX endpoints
  - Add troubleshooting section for common issues
  - Include mock server setup instructions

- [ ] **T027** [P] Add inline code documentation
  - PHPDoc comments for all new methods
  - Document nonce action requirements
  - Document health check logic and thresholds
  - Explain consecutive counter behavior

- [ ] **T028** Final validation and version bump
  - Run all test scenarios from Phase 3.9
  - Verify no console errors in browser
  - Verify no PHP errors in logs
  - Update plugin version number
  - Update CHANGELOG.md

## Dependencies

```
T001 (Critical Fix)
  ├── Blocks → T002-T009 (Backend)
  │
T002-T005 (Health Check Logic)
  ├── Blocks → T006-T009 (AJAX Handlers)
  ├── Blocks → T010-T011 (Cron)
  └── Blocks → T012-T014 (Frontend)

T006-T008 (AJAX Handlers)
  └── Blocks → T015-T020 (Admin UI & JS)

T007 (Manual Health Check Handler)
  └── Blocks → T021 (Frontend Background Check)

T001-T021 (All Implementation)
  └── Blocks → T022-T025 (Testing)

T022-T025 (Testing)
  └── Blocks → T028 (Final Validation)

Parallel Groups:
- T006, T007, T008 can run in parallel (different action hooks)
- T015, T016, T017 can run in parallel (different UI sections)
- T018, T019, T020 can run in parallel (different event handlers)
- T022, T023, T024, T025 can run in parallel (independent test scenarios)
- T026, T027 can run in parallel (documentation tasks)
```

## Parallel Execution Examples

### Backend AJAX Handlers (after T001-T005 complete)
```bash
# Launch T006-T008 together:
# Task agent for T006
Task: "Create AJAX handler for cuft_save_sgtm_config in /includes/class-cuft-admin.php. Add action hook, verify nonce with cuft_admin, sanitize URL, save to options, trigger health check, return JSON response."

# Task agent for T007
Task: "Create AJAX handler for cuft_manual_health_check in /includes/class-cuft-admin.php. Add action hook, verify nonce, call health check, update options, return JSON status."

# Task agent for T008
Task: "Create AJAX handler for cuft_get_sgtm_status in /includes/class-cuft-admin.php. Add action hook, verify nonce, retrieve all options, calculate next check time, return complete status."
```

### Admin UI Components (after T006-T008 complete)
```bash
# Launch T015-T017 together:
# Task agent for T015
Task: "Add custom server configuration fields to WordPress admin settings page. Include enable checkbox, URL input with validation, test button, and status display."

# Task agent for T016
Task: "Add health check status display to admin settings. Show active server, last check time, result icons, counters, and manual trigger button."

# Task agent for T017
Task: "Add admin notices for server status changes. Check for triggers, display success/warning notices, make dismissible, clean up triggers."
```

### Testing Scenarios (after all implementation complete)
```bash
# Launch T022-T025 together:
# Task agent for T022
Task: "Test happy path scenario with working custom server following quickstart.md Scenario 1. Verify configuration, frontend loading, and health checks."

# Task agent for T023
Task: "Test server initially offline scenario following quickstart.md Scenario 2. Verify fallback behavior and warnings."

# Task agent for T024
Task: "Test server failure scenario following quickstart.md Scenario 3. Simulate working server going down, verify fallback switch."

# Task agent for T025
Task: "Test server recovery scenario following quickstart.md Scenario 4. Verify 3-consecutive-success recovery logic."
```

## Validation Checklist
*GATE: Checked before marking tasks complete*

- [x] All AJAX endpoints have handlers (T006-T009)
- [x] All health check logic implemented (T002-T005)
- [x] All frontend loading updated (T012-T014)
- [x] All admin UI components added (T015-T017)
- [x] All JavaScript handlers created (T018-T020)
- [x] All test scenarios from quickstart.md covered (T022-T025)
- [x] Critical nonce fix is first task (T001)
- [x] Parallel tasks are truly independent (different methods/files)
- [x] Each task specifies exact file path and line numbers where applicable
- [x] Dependencies prevent premature parallel execution
- [x] WordPress hooks and filters properly documented

## Notes
- **Critical**: T001 MUST be completed first - all AJAX depends on correct nonce validation
- **[P] tasks**: Different methods/functions, can run in parallel
- **Sequential tasks**: Same file (class-cuft-admin.php) when modifying existing methods
- **Testing**: Manual testing with mock server recommended (see quickstart.md)
- **Commit strategy**: Commit after each phase completion (not individual tasks)
- **Nonce action**: Always use `cuft_admin` for all new AJAX endpoints

## Implementation Tips

1. **Nonce Validation Pattern** (from working implementations):
   ```php
   if ( ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) ) {
       wp_send_json_error( 'Security check failed', 403 );
       return;
   }
   ```

2. **Health Check Timeout**:
   ```php
   $response = wp_remote_get( $url, array(
       'timeout' => 5,
       'sslverify' => false
   ));
   ```

3. **Consecutive Counter Logic**:
   ```php
   if ( $success ) {
       $consecutive_success = get_option( 'cuft_sgtm_health_consecutive_success', 0 ) + 1;
       update_option( 'cuft_sgtm_health_consecutive_success', $consecutive_success );
       update_option( 'cuft_sgtm_health_consecutive_failure', 0 );

       if ( $consecutive_success >= 3 ) {
           update_option( 'cuft_sgtm_active_server', 'custom' );
       }
   }
   ```

4. **AJAX Response Format**:
   ```php
   wp_send_json_success( array(
       'valid' => true,
       'message' => 'Server validated successfully',
       'response_time' => $response_time
   ));
   ```