# Implementation Tasks: Force Install Update

**Feature**: 009-force-install-update
**Branch**: `009-force-install-update`
**Date**: 2025-10-12
**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

---

## Task Execution Guide

### Task Format
Each task follows this structure:
```
## T### [P]: Task Name
**File(s)**: path/to/file.php
**Dependencies**: T### (comma-separated if multiple)
**Description**: What needs to be implemented
**Acceptance**: How to verify completion
```

### Markers
- **[P]**: Can be executed in parallel with other [P] tasks (independent files)
- **No [P]**: Sequential execution required (shared files or dependencies)

### Execution Order
Tasks are numbered in dependency order:
1. **T001-T007**: Infrastructure layer (can parallelize)
2. **T008-T010**: Service layer (depends on infrastructure)
3. **T011-T013**: AJAX endpoints (depends on services)
4. **T014-T020**: UI layer (depends on AJAX)
5. **T021-T025**: Integration layer (depends on UI)
6. **T026-T041**: Testing layer (depends on implementation)

---

## Phase 1: Infrastructure Layer (Foundation)

### T001 [P]: Implement Update Lock Manager

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-update-lock-manager.php`

**Dependencies**: None

**Description**: Create transient-based locking mechanism to prevent concurrent update operations by multiple administrators.

**Implementation**:
- Create `CUFT_Update_Lock_Manager` class
- Implement `acquire_lock( $operation_type, $user_id )` method
  - Set transient `cuft_force_update_lock` with 120-second TTL
  - Store: `array( 'user_id' => $user_id, 'operation' => $operation_type, 'started_at' => time() )`
  - Return `true` on success, `WP_Error` if lock already exists
- Implement `release_lock()` method
  - Delete transient `cuft_force_update_lock`
- Implement `is_locked()` method
  - Check if transient exists, return lock details or `false`
- Implement `get_lock_info()` method
  - Return current lock holder information (user_id, operation, started_at)

**Acceptance**:
- Class file exists at specified path
- All four methods implemented with correct signatures
- Methods use WordPress Transients API (`set_transient`, `get_transient`, `delete_transient`)
- Lock automatically expires after 120 seconds (transient TTL)
- Returns WP_Error with code `operation_in_progress` when lock acquisition fails

---

### T002 [P]: Implement Disk Space Validator

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-disk-space-validator.php`

**Dependencies**: None

**Description**: Create disk space validation utility to check available space before force reinstall operations.

**Implementation**:
- Create `CUFT_Disk_Space_Validator` class
- Implement `get_directory_size( $path )` method
  - Use `RecursiveIteratorIterator` + `RecursiveDirectoryIterator`
  - Sum file sizes recursively
  - Return total size in bytes
- Implement `get_available_space( $path )` method
  - Use `disk_free_space( $path )`
  - Return available bytes or `false` on error
- Implement `validate_space_for_reinstall( $plugin_path, $required_multiplier = 3 )` method
  - Calculate plugin size using `get_directory_size()`
  - Calculate required space: `plugin_size * $required_multiplier`
  - Get available space using `get_available_space( WP_CONTENT_DIR )`
  - Return `true` if sufficient, `WP_Error` with details if insufficient
  - Error message must include required MB and available MB (use `size_format()`)

**Acceptance**:
- Class file exists at specified path
- All three methods implemented
- `validate_space_for_reinstall()` checks for 3x plugin size by default (backup + download + extraction)
- Returns WP_Error with code `insufficient_disk_space` when space is insufficient
- Error message includes human-readable sizes (MB/GB)

---

### T003 [P]: Implement Cache Clearing Utility

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-cache-clearer.php`

**Dependencies**: None

**Description**: Create utility to clear WordPress plugin update cache and force immediate recognition of new versions.

**Implementation**:
- Create `CUFT_Cache_Clearer` class
- Implement `clear_plugin_cache()` method
  - Call `delete_site_transient( 'update_plugins' )`
  - Call `wp_clean_plugins_cache( true )`
  - Log action if debug mode enabled
  - Return `true` on success
- Implement `clear_plugin_cache_for_specific_plugin( $plugin_basename )` method
  - Clear full cache (call `clear_plugin_cache()`)
  - Optionally trigger `wp_update_plugins()` to force recheck
  - Return `true` on success

**Acceptance**:
- Class file exists at specified path
- Methods clear WordPress `update_plugins` transient
- Methods call `wp_clean_plugins_cache()`
- Cache clearing forces WordPress to recognize new version immediately (testable by checking admin UI)

---

### T004 [P]: Create Force Reinstall Operation Model

**File(s)**: `/home/r11/dev/choice-uft/includes/models/class-cuft-force-reinstall-operation.php`

**Dependencies**: None

**Description**: Implement data model for Force Reinstall Operation with state tracking (data-model.md line 51-98).

**Implementation**:
- Create `CUFT_Force_Reinstall_Operation` class
- Define status constants: `STATUS_PENDING`, `STATUS_BACKUP_CREATED`, `STATUS_DOWNLOADING`, `STATUS_VALIDATING`, `STATUS_INSTALLING`, `STATUS_SUCCESS`, `STATUS_FAILED`, `STATUS_ROLLED_BACK`
- Implement constructor accepting operation data array
- Implement getters: `get_source_version()`, `get_target_version()`, `get_backup_location()`, `get_status()`, `get_error_details()`, `get_started_at()`, `get_completed_at()`
- Implement `set_status( $status )` with state transition validation
- Implement `set_backup_location( $path )` with path validation (must be in `/wp-content/uploads/cuft-backups/`)
- Implement `set_error_details( $error_code, $error_message, $context = array() )`
- Implement `mark_completed()` to set `completed_at` timestamp
- Implement `to_array()` for serialization to update history

**Acceptance**:
- Class file exists at specified path
- All status constants defined
- All getters and setters implemented
- State transitions validated (cannot go from `success` to `pending`)
- Backup path validation prevents directory traversal attacks
- Object can be serialized to array format matching data-model.md line 89-98

---

### T005 [P]: Create Plugin Installation State Model

**File(s)**: `/home/r11/dev/choice-uft/includes/models/class-cuft-plugin-installation-state.php`

**Dependencies**: None

**Description**: Implement data model for Plugin Installation State cached in transient (data-model.md line 102-133).

**Implementation**:
- Create `CUFT_Plugin_Installation_State` class
- Implement static `get()` method
  - Fetch transient `cuft_plugin_installation_state`
  - Return cached state if valid (check TTL)
  - Return `null` if no cache or expired
- Implement static `set( $installed_version, $github_latest_version )` method
  - Calculate `update_available` using `version_compare()`
  - Set `last_check_timestamp` to `time()`
  - Set `cache_ttl` to 300 (5 minutes)
  - Store in transient `cuft_plugin_installation_state` with 300s TTL
  - Return state array
- Implement static `clear()` method to delete transient
- Implement static `is_cache_valid()` method
  - Check if cache exists and TTL not expired
  - Return boolean

**Acceptance**:
- Class file exists at specified path
- Methods use transient `cuft_plugin_installation_state` with 5-minute TTL
- `update_available` calculated correctly using `version_compare()`
- Cache automatically expires after 5 minutes (transient TTL)
- State format matches data-model.md line 126-133

---

### T006 [P]: Create Update History Entry Model

**File(s)**: `/home/r11/dev/choice-uft/includes/models/class-cuft-update-history-entry.php`

**Dependencies**: None

**Description**: Implement data model for Update History Entry with FIFO persistence (data-model.md line 137-196).

**Implementation**:
- Create `CUFT_Update_History_Entry` class
- Define operation type constants: `TYPE_MANUAL_CHECK`, `TYPE_FORCE_REINSTALL`
- Define status constants: `STATUS_COMPLETE`, `STATUS_FAILED`
- Implement constructor accepting entry data array
- Implement static `log_operation( $operation_type, $user_id, $status, $details )` method
  - Get current user display name
  - Create entry array with `timestamp`, `trigger_location='force_update_button'`
  - Append to `cuft_update_log` WordPress option
  - Enforce FIFO: keep only last 5 entries (array_slice)
  - Return created entry
- Implement static `get_history( $limit = 5 )` method
  - Fetch `cuft_update_log` option
  - Return array of entries (most recent first)
- Implement static `clear_history()` method to delete option
- Implement `to_array()` for API responses

**Acceptance**:
- Class file exists at specified path
- History stored in WordPress option `cuft_update_log`
- FIFO enforcement: max 5 entries retained
- Entry format matches data-model.md line 180-196
- Operation types match data-model.md line 146

---

### T007 [P]: Create Update Check Request Model

**File(s)**: `/home/r11/dev/choice-uft/includes/models/class-cuft-update-check-request.php`

**Dependencies**: None

**Description**: Implement ephemeral data model for Update Check Request (data-model.md line 9-47).

**Implementation**:
- Create `CUFT_Update_Check_Request` class
- Define status constants: `STATUS_PENDING`, `STATUS_SUCCESS`, `STATUS_ERROR`, `STATUS_TIMEOUT`
- Implement constructor initializing request with `timestamp` and `user_id`
- Implement `set_status( $status )` method with validation
- Implement `set_github_response( $version, $release_date, $changelog_summary, $download_url )` method
- Implement `set_error( $error_message )` method
- Implement getters: `get_status()`, `get_github_response()`, `get_error_message()`, `get_timestamp()`, `get_user_id()`
- Implement `to_array()` for serialization

**Note**: This model is ephemeral (memory-only), not persisted to database. Used for request lifecycle tracking only.

**Acceptance**:
- Class file exists at specified path
- All status constants defined
- Object lifecycle: created ’ status updated ’ used for response ’ discarded
- Format matches data-model.md line 35-47
- No database persistence (memory-only)

---

## Phase 2: Service Layer (Orchestration)

### T008: Implement Force Update Handler Service

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-force-update-handler.php`

**Dependencies**: T001, T002, T003, T004, T005, T006, T007

**Description**: Create orchestrator class that coordinates force update operations using infrastructure and model classes.

**Implementation**:
- Create `CUFT_Force_Update_Handler` class
- Implement `handle_check_updates()` method:
  1. Create `CUFT_Update_Check_Request` instance (T007)
  2. Try to acquire lock via `CUFT_Update_Lock_Manager::acquire_lock()` (T001)
  3. Call existing `CUFT_Update_Checker::check( $force = true )` (Feature 007)
  4. Clear cache via `CUFT_Cache_Clearer::clear_plugin_cache()` (T003)
  5. Update `CUFT_Plugin_Installation_State` (T005)
  6. Log to history via `CUFT_Update_History_Entry::log_operation()` (T006)
  7. Release lock via `CUFT_Update_Lock_Manager::release_lock()` (T001)
  8. Return structured response for AJAX
- Implement `handle_force_reinstall()` method:
  1. Create `CUFT_Force_Reinstall_Operation` instance (T004)
  2. Try to acquire lock (T001)
  3. Validate disk space via `CUFT_Disk_Space_Validator::validate_space_for_reinstall()` (T002)
  4. Create backup via existing `CUFT_Backup_Manager::create_backup()` (Feature 008)
  5. Update operation status to `STATUS_DOWNLOADING`
  6. Fetch latest release via existing `CUFT_GitHub_Release::fetch_latest()` (Feature 007)
  7. Update status to `STATUS_VALIDATING`
  8. Validate download via existing `CUFT_Update_Validator::validate_file_size()` and `validate_zip_format()` (Feature 008)
  9. Update status to `STATUS_INSTALLING`
  10. Trigger WordPress plugin upgrade via WP_Upgrader API
  11. On success: mark operation `STATUS_SUCCESS`, delete backup, clear cache (T003)
  12. On failure: mark `STATUS_FAILED` or `STATUS_ROLLED_BACK`, restore backup
  13. Log to history (T006)
  14. Release lock (T001)
  15. Return structured response for AJAX
- Implement timeout enforcement using `time()` checks (5s for check, 60s for reinstall)
- Wrap all operations in try-catch blocks, return WP_Error on exceptions

**Acceptance**:
- Class file exists at specified path
- `handle_check_updates()` completes within 5 seconds or times out gracefully
- `handle_force_reinstall()` completes within 60 seconds or times out gracefully
- All Feature 007/008 classes integrated correctly
- Lock acquired before operations, released after (success or failure)
- History logged for all operations (success and failure)
- Returns structured array for AJAX responses (matching ajax-endpoints.md contracts)

---

### T009: Add Force Update AJAX Handler Methods

**File(s)**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-updater-ajax.php`

**Dependencies**: T008

**Description**: Extend existing `CUFT_Updater_Ajax` class (Feature 007/008) with three new AJAX endpoints for force update functionality.

**Implementation**:
- Open existing `/home/r11/dev/choice-uft/includes/ajax/class-cuft-updater-ajax.php`
- In constructor, add WordPress AJAX hooks:
  - `add_action( 'wp_ajax_cuft_check_updates', array( $this, 'handle_check_updates_ajax' ) )`
  - `add_action( 'wp_ajax_cuft_force_reinstall', array( $this, 'handle_force_reinstall_ajax' ) )`
  - `add_action( 'wp_ajax_cuft_get_update_history', array( $this, 'handle_get_update_history_ajax' ) )`
- Implement `handle_check_updates_ajax()` method:
  1. Validate nonce: `wp_verify_nonce( $_POST['nonce'], 'cuft_force_update' )`
  2. Check capability: `current_user_can( 'update_plugins' )`
  3. Call `CUFT_Force_Update_Handler::handle_check_updates()` (T008)
  4. Return response via `wp_send_json_success()` or `wp_send_json_error()`
- Implement `handle_force_reinstall_ajax()` method:
  1. Validate nonce
  2. Check capability
  3. Check `DISALLOW_FILE_MODS` constant
  4. Call `CUFT_Force_Update_Handler::handle_force_reinstall()` (T008)
  5. Return response via `wp_send_json_success()` or `wp_send_json_error()`
- Implement `handle_get_update_history_ajax()` method:
  1. Validate nonce
  2. Check capability
  3. Call `CUFT_Update_History_Entry::get_history()` (T006)
  4. Format timestamps using `date_i18n()`
  5. Return response via `wp_send_json_success()`
- All methods must match contracts in `/home/r11/dev/choice-uft/specs/009-force-install-update/contracts/ajax-endpoints.md`

**Acceptance**:
- Three AJAX actions registered in WordPress
- All methods validate nonce with error code `invalid_nonce` on failure (403 status)
- All methods check `update_plugins` capability with error code `insufficient_permissions` on failure (403 status)
- `handle_force_reinstall_ajax()` checks `DISALLOW_FILE_MODS` with error code `file_mods_disabled` on failure (403 status)
- Response formats match ajax-endpoints.md exactly (lines 42-140 for check_updates, 170-306 for force_reinstall, 336-407 for get_history)
- HTTP status codes match contracts (200, 403, 409, 422, 500, 502, 504, 507)

---

### T010: Register AJAX Handler in Plugin Bootstrap

**File(s)**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`

**Dependencies**: T009

**Description**: Ensure `CUFT_Updater_Ajax` class is loaded and instantiated during plugin initialization.

**Implementation**:
- Open `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
- Verify `includes/ajax/class-cuft-updater-ajax.php` is in the `$includes` array within `load_dependencies()` method
- If not present, add it after other AJAX includes
- Ensure class is instantiated in `init_hooks()` or appropriate initialization method

**Acceptance**:
- `CUFT_Updater_Ajax` file included in plugin dependencies list
- Class instantiated during plugin initialization
- AJAX endpoints accessible at `/wp-admin/admin-ajax.php?action=cuft_check_updates` (etc.)
- Test with `wp_ajax_nopriv_*` actions NOT registered (admin-only)

---

## Phase 3: Admin UI Layer (Frontend)

### T011: Create Force Update Admin View Template

**File(s)**: `/home/r11/dev/choice-uft/includes/admin/views/force-update-tab.php`

**Dependencies**: T009

**Description**: Create admin UI template for force update controls in Settings ’ Universal Form Tracker.

**Implementation**:
- Create new file `/home/r11/dev/choice-uft/includes/admin/views/force-update-tab.php`
- Structure:
  ```php
  <div class="cuft-force-update-section">
      <h2><?php esc_html_e( 'Manual Update Control', 'choice-universal-form-tracker' ); ?></h2>
      <p class="description">
          <?php esc_html_e( 'Manually check for plugin updates or force reinstall the latest version from GitHub.', 'choice-universal-form-tracker' ); ?>
      </p>

      <!-- Current Version Info -->
      <div class="cuft-current-version">
          <strong><?php esc_html_e( 'Current Version:', 'choice-universal-form-tracker' ); ?></strong>
          <code><?php echo esc_html( CUFT_VERSION ); ?></code>
      </div>

      <!-- Update Controls -->
      <div class="cuft-update-controls">
          <button type="button" id="cuft-check-updates" class="button button-secondary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cuft_force_update' ) ); ?>">
              <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
              <?php esc_html_e( 'Check for Updates', 'choice-universal-form-tracker' ); ?>
          </button>

          <button type="button" id="cuft-force-reinstall" class="button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cuft_force_update' ) ); ?>" <?php echo ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ? 'disabled' : ''; ?>>
              <span class="dashicons dashicons-download" style="margin-top:3px;"></span>
              <?php esc_html_e( 'Force Reinstall Latest Version', 'choice-universal-form-tracker' ); ?>
          </button>
      </div>

      <!-- Status Display -->
      <div id="cuft-update-status" class="cuft-update-status" style="display:none;"></div>

      <!-- Progress Indicator -->
      <div id="cuft-update-progress" class="cuft-update-progress" style="display:none;">
          <div class="cuft-progress-bar">
              <div class="cuft-progress-fill" style="width:0%;"></div>
          </div>
          <p class="cuft-progress-message"></p>
      </div>

      <!-- Update History -->
      <div class="cuft-update-history">
          <h3><?php esc_html_e( 'Recent Update Operations', 'choice-universal-form-tracker' ); ?></h3>
          <table class="widefat" id="cuft-history-table">
              <thead>
                  <tr>
                      <th><?php esc_html_e( 'Operation', 'choice-universal-form-tracker' ); ?></th>
                      <th><?php esc_html_e( 'User', 'choice-universal-form-tracker' ); ?></th>
                      <th><?php esc_html_e( 'Date/Time', 'choice-universal-form-tracker' ); ?></th>
                      <th><?php esc_html_e( 'Status', 'choice-universal-form-tracker' ); ?></th>
                      <th><?php esc_html_e( 'Details', 'choice-universal-form-tracker' ); ?></th>
                  </tr>
              </thead>
              <tbody id="cuft-history-body">
                  <tr><td colspan="5"><?php esc_html_e( 'Loading history...', 'choice-universal-form-tracker' ); ?></td></tr>
              </tbody>
          </table>
      </div>

      <?php if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) : ?>
      <p class="notice notice-info inline">
          <strong><?php esc_html_e( 'Note:', 'choice-universal-form-tracker' ); ?></strong>
          <?php esc_html_e( 'File modifications are disabled on this site (DISALLOW_FILE_MODS constant). Force reinstall is not available.', 'choice-universal-form-tracker' ); ?>
      </p>
      <?php endif; ?>
  </div>
  ```
- Use WordPress escaping functions: `esc_html_e()`, `esc_attr()`, `esc_html()`
- Generate nonce using `wp_create_nonce( 'cuft_force_update' )`
- Disable "Force Reinstall" button if `DISALLOW_FILE_MODS` is true

**Acceptance**:
- Template file exists at specified path
- Two buttons rendered: "Check for Updates" and "Force Reinstall Latest Version"
- Nonce embedded in button `data-nonce` attributes
- Status display area hidden by default (`display:none`)
- Progress indicator hidden by default
- Update history table structure in place
- DISALLOW_FILE_MODS check disables force reinstall button when needed
- All text translatable via `choice-universal-form-tracker` text domain

---

### T012: Integrate Force Update Tab into Admin Settings

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`

**Dependencies**: T011

**Description**: Modify existing `CUFT_Admin` class to add "Force Update" tab to Settings ’ Universal Form Tracker page.

**Implementation**:
- Open `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`
- Locate `register_settings()` or tab rendering method (likely `render_settings_page()`)
- Add new tab:
  - Tab label: "Force Update"
  - Tab slug: `force-update`
  - Tab callback: Include `/home/r11/dev/choice-uft/includes/admin/views/force-update-tab.php`
- Position tab after "Testing" or "About" tab (last tab)
- Follow existing tab structure pattern from CUFT_Admin

**Example**:
```php
$tabs = array(
    'settings' => __( 'Settings', 'choice-universal-form-tracker' ),
    'testing' => __( 'Testing', 'choice-universal-form-tracker' ),
    'force-update' => __( 'Force Update', 'choice-universal-form-tracker' ), // NEW
);
```

**Acceptance**:
- "Force Update" tab visible in Settings ’ Universal Form Tracker admin page
- Clicking tab displays force-update-tab.php content
- Tab follows existing admin page styling
- No JavaScript errors when tab is loaded
- Tab accessible to users with `update_plugins` capability

---

### T013: Create Force Update JavaScript Module

**File(s)**: `/home/r11/dev/choice-uft/assets/admin/cuft-force-update.js`

**Dependencies**: T009, T011

**Description**: Implement client-side JavaScript for AJAX interactions, button state management, and progress polling.

**Implementation**:
- Create file `/home/r11/dev/choice-uft/assets/admin/cuft-force-update.js`
- Use jQuery (WordPress standard)
- Implement initialization:
  ```javascript
  (function($) {
      'use strict';

      const CuftForceUpdate = {
          nonce: '', // Set from button data-nonce attribute

          init: function() {
              this.nonce = $('#cuft-check-updates').data('nonce');
              this.bindEvents();
              this.loadHistory();
          },

          bindEvents: function() {
              $('#cuft-check-updates').on('click', this.handleCheckUpdates.bind(this));
              $('#cuft-force-reinstall').on('click', this.handleForceReinstall.bind(this));
          },

          // ... (methods below)
      };

      $(document).ready(function() {
          CuftForceUpdate.init();
      });

  })(jQuery);
  ```
- Implement `handleCheckUpdates()` method:
  - Disable button, change text to "Checking..."
  - Show loading spinner
  - POST to `ajaxurl` with `action=cuft_check_updates`, `nonce=this.nonce`
  - On success: display result in status area (update available or up-to-date message)
  - On error: display error message with retry button
  - Re-enable button after completion
  - Refresh history table
- Implement `handleForceReinstall()` method:
  - Show confirmation dialog: "This will download and reinstall the latest version. Continue?"
  - If confirmed: disable button, change text to "Installing..."
  - Show progress indicator
  - POST to `ajaxurl` with `action=cuft_force_reinstall`, `nonce=this.nonce`
  - Poll progress every 500ms using `pollProgress()` method
  - On success: display success message, refresh page after 3 seconds
  - On error: display error with details, offer retry
  - Re-enable button after completion
- Implement `pollProgress()` method:
  - Not needed if operations complete quickly
  - Optional: poll transient for progress updates (future enhancement)
- Implement `loadHistory()` method:
  - POST to `ajaxurl` with `action=cuft_get_update_history`, `nonce=this.nonce`
  - On success: populate history table tbody with entries
  - Format timestamps using local time
  - Display operation type, user, status with appropriate icons/colors
- Implement `showStatus( message, type )` method:
  - `type`: 'success', 'error', 'info'
  - Display message in `#cuft-update-status` div
  - Apply WordPress notice classes: `notice-success`, `notice-error`, `notice-info`
- Implement `showProgress( percentage, message )` method:
  - Update progress bar width
  - Update progress message text
  - Show/hide progress indicator div

**Acceptance**:
- File exists at specified path
- "Check for Updates" button triggers AJAX call to `cuft_check_updates`
- "Force Reinstall" button shows confirmation, then triggers AJAX call to `cuft_force_reinstall`
- Status messages display correctly (success/error/info)
- Button states managed: disabled during operations, re-enabled after
- Update history table populated on page load
- No JavaScript console errors
- All AJAX calls include nonce parameter

---

### T014: Create Force Update CSS Stylesheet

**File(s)**: `/home/r11/dev/choice-uft/assets/admin/cuft-force-update.css`

**Dependencies**: T011

**Description**: Create stylesheet for force update UI components (buttons, status, progress bar, history table).

**Implementation**:
- Create file `/home/r11/dev/choice-uft/assets/admin/cuft-force-update.css`
- Style `.cuft-force-update-section`:
  - Max width: 900px
  - Padding: 20px
  - Background: white
  - Border: 1px solid #ccd0d4
  - Box shadow: 0 1px 1px rgba(0,0,0,.04)
- Style `.cuft-current-version`:
  - Margin bottom: 20px
  - Font size: 14px
  - `code` element: background #f0f0f1, padding 2px 6px, border-radius 3px
- Style `.cuft-update-controls`:
  - Margin: 20px 0
  - Display: flex, gap: 10px
  - Buttons: standard WordPress button styling, enhanced with dashicons
- Style `.cuft-update-status`:
  - Margin: 15px 0
  - Padding: 12px
  - Border-left: 4px solid (color based on type)
  - Match WordPress notice styles
- Style `.cuft-update-progress`:
  - Margin: 20px 0
  - `.cuft-progress-bar`: background #ddd, height 20px, border-radius 10px, overflow hidden
  - `.cuft-progress-fill`: background #2271b1, height 100%, transition: width 0.3s ease
  - `.cuft-progress-message`: font-size 13px, color #646970, margin-top 8px
- Style `.cuft-update-history`:
  - Margin-top: 30px
  - `table`: standard WordPress widefat table styling
  - Success status: green badge
  - Failure status: red badge
  - Operation type icons using dashicons

**Acceptance**:
- File exists at specified path
- Styles match WordPress admin design patterns
- Buttons visually distinct (secondary vs primary)
- Progress bar animated smoothly
- Status messages color-coded (green success, red error, blue info)
- History table readable with proper spacing
- Responsive design (works on narrow screens)

---

## Phase 4: Integration Layer (Hooks & Enqueues)

### T015: Enqueue Force Update Assets in Admin

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`

**Dependencies**: T013, T014

**Description**: Enqueue JavaScript and CSS assets on Settings ’ Universal Form Tracker page only.

**Implementation**:
- Open `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`
- Locate or create `enqueue_admin_scripts( $hook )` method
- Check if current page is plugin settings page:
  ```php
  if ( $hook !== 'settings_page_choice-universal-form-tracker' ) {
      return;
  }
  ```
- Enqueue JavaScript:
  ```php
  wp_enqueue_script(
      'cuft-force-update',
      CUFT_URL . '/assets/admin/cuft-force-update.js',
      array( 'jquery' ),
      CUFT_VERSION,
      true
  );
  ```
- Localize script with AJAX URL:
  ```php
  wp_localize_script( 'cuft-force-update', 'cuftForceUpdate', array(
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'nonce' => wp_create_nonce( 'cuft_force_update' ),
  ) );
  ```
- Enqueue CSS:
  ```php
  wp_enqueue_style(
      'cuft-force-update',
      CUFT_URL . '/assets/admin/cuft-force-update.css',
      array(),
      CUFT_VERSION
  );
  ```
- Hook method to `admin_enqueue_scripts` action in constructor

**Acceptance**:
- JavaScript file loaded only on Settings ’ Universal Form Tracker page
- CSS file loaded only on Settings ’ Universal Form Tracker page
- `cuftForceUpdate` JavaScript object available with `ajaxUrl` and `nonce` properties
- Assets NOT loaded on other admin pages (efficient)
- Browser Network tab shows assets loaded with correct version query string

---

### T016: Add WP-Cron Job for History Cleanup

**File(s)**: `/home/r11/dev/choice-uft/includes/class-cuft-cron-manager.php`

**Dependencies**: T006

**Description**: Create or extend existing cron manager to add daily cleanup job for old update history entries (7-day retention per spec clarifications).

**Implementation**:
- Check if `/home/r11/dev/choice-uft/includes/class-cuft-cron-manager.php` exists (from Feature 007/008)
- If exists: extend with new method
- If not exists: create new `CUFT_Cron_Manager` class
- Implement `schedule_history_cleanup()` method:
  - Check if `cuft_daily_cleanup` event scheduled
  - If not: `wp_schedule_event( time(), 'daily', 'cuft_daily_cleanup' )`
- Implement static `cleanup_old_history()` callback method:
  - Get `cuft_update_log` option
  - Filter entries: keep only those with `timestamp > (time() - 7 * DAY_IN_SECONDS)`
  - Update option with filtered entries
  - Log cleanup action if debug mode enabled
- Hook `cuft_daily_cleanup` action to callback:
  - `add_action( 'cuft_daily_cleanup', array( 'CUFT_Cron_Manager', 'cleanup_old_history' ) )`
- Call `schedule_history_cleanup()` on plugin activation (hook `register_activation_hook`)

**Note**: Spec clarifications specify 7-day retention (not auto-cleanup), but cleanup job ensures FIFO + time-based expiry.

**Acceptance**:
- WP-Cron event `cuft_daily_cleanup` scheduled on plugin activation
- Event runs daily (check with `wp cron event list` in Docker)
- Cleanup method filters history entries older than 7 days
- FIFO limit (5 entries) still enforced by `CUFT_Update_History_Entry::log_operation()` (T006)
- Cleanup does not interfere with recent entries

---

### T017: Add Cleanup to Plugin Deactivation Hook

**File(s)**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`

**Dependencies**: T016

**Description**: Clean up transients and cron jobs when plugin is deactivated.

**Implementation**:
- Open `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
- Create or locate deactivation callback method
- Register deactivation hook:
  ```php
  register_deactivation_hook( __FILE__, array( 'Choice_Universal_Form_Tracker', 'on_deactivation' ) );
  ```
- Implement `on_deactivation()` static method:
  - Clear transients:
    - `delete_transient( 'cuft_force_update_lock' )`
    - `delete_transient( 'cuft_plugin_installation_state' )`
  - Unschedule cron:
    - `wp_clear_scheduled_hook( 'cuft_daily_cleanup' )`
  - Do NOT delete `cuft_update_log` option (preserve history)

**Acceptance**:
- Deactivation hook registered
- Transients cleared on deactivation (verify with `wp transient list` before/after)
- Cron job unscheduled (verify with `wp cron event list`)
- Update history preserved (option remains in database)

---

### T018: Update Plugin Loader to Include New Classes

**File(s)**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`

**Dependencies**: T001, T002, T003, T004, T005, T006, T007, T008, T009

**Description**: Ensure all new PHP classes are included in plugin's autoloader/includes array.

**Implementation**:
- Open `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
- Locate `load_dependencies()` method in `Choice_Universal_Form_Tracker` class
- Add new file paths to `$includes` array (order matters):
  ```php
  $includes = array(
      // ... existing includes ...

      // Force Update Infrastructure (T001-T003)
      'includes/class-cuft-update-lock-manager.php',
      'includes/class-cuft-disk-space-validator.php',
      'includes/class-cuft-cache-clearer.php',

      // Force Update Models (T004-T007)
      'includes/models/class-cuft-force-reinstall-operation.php',
      'includes/models/class-cuft-plugin-installation-state.php',
      'includes/models/class-cuft-update-history-entry.php',
      'includes/models/class-cuft-update-check-request.php',

      // Force Update Service (T008)
      'includes/class-cuft-force-update-handler.php',

      // AJAX Handler already included (T009 modifies existing file)

      // Cron Manager (T016 - may be existing file)
      'includes/class-cuft-cron-manager.php',
  );
  ```
- Verify includes with error handling (existing pattern in plugin)

**Acceptance**:
- All new class files added to includes array
- Order respects dependencies (models before services, services before handlers)
- Plugin activates without PHP errors
- Check PHP error log for missing file warnings
- Test: temporarily remove one include, verify plugin shows error

---

## Phase 5: Contract Testing Layer

### T019 [P]: Contract Test - cuft_check_updates Endpoint

**File(s)**: `/home/r11/dev/choice-uft/tests/contract/test-cuft-check-updates-endpoint.php` (manual test in Docker)

**Dependencies**: T009, T015

**Description**: Validate `cuft_check_updates` AJAX endpoint contract compliance (ajax-endpoints.md lines 22-140).

**Implementation**:
- Test in Docker environment (http://localhost:8080/wp-admin/)
- Open browser DevTools ’ Network tab
- Navigate to Settings ’ Universal Form Tracker ’ Force Update tab
- Execute test cases:

**Test Case 1: Valid request with update available**
- Prerequisite: Ensure GitHub has newer version than installed
- Click "Check for Updates" button
- Verify AJAX call to `admin-ajax.php?action=cuft_check_updates`
- Verify response: 200 OK
- Verify JSON structure:
  ```json
  {
    "success": true,
    "data": {
      "installed_version": "<current>",
      "latest_version": "<newer>",
      "update_available": true,
      "release_date": "<date>",
      "changelog_summary": "<text>",
      "download_url": "<github_url>",
      "last_check": <timestamp>,
      "message": "A new version (...) is available!"
    }
  }
  ```

**Test Case 2: Valid request with no update**
- Prerequisite: Ensure plugin at latest version
- Click "Check for Updates"
- Verify response: `update_available: false`, `message: "Plugin is up to date"`

**Test Case 3: Invalid nonce**
- Open DevTools Console
- Execute: `jQuery.post(ajaxurl, {action: 'cuft_check_updates', nonce: 'invalid'}, console.log)`
- Verify response: 403 Forbidden, `error_code: "invalid_nonce"`

**Test Case 4: Non-admin user**
- Log out, log in as Editor or Subscriber role
- Attempt to access Settings ’ Universal Form Tracker
- Verify: page not accessible or button not functional
- Alternative: Use WP-CLI to test as non-admin

**Test Case 5: GitHub API timeout**
- Difficult to test without network manipulation
- Verify timeout handling in code (research.md line 84-117)
- Check error message displays after 5 seconds

**Test Case 6: GitHub rate limited**
- Trigger 60+ update checks within 1 hour (rate limit)
- Verify response: 429 status (or 200 with cached data), `error_code: "rate_limited"`, `cached: true`

**Acceptance**:
- All 6 test cases pass
- Response structures match ajax-endpoints.md exactly
- HTTP status codes correct (200, 403, 429, 504)
- Error codes match specification
- User feedback messages clear and actionable

---

### T020 [P]: Contract Test - cuft_force_reinstall Endpoint

**File(s)**: `/home/r11/dev/choice-uft/tests/contract/test-cuft-force-reinstall-endpoint.php` (manual test in Docker)

**Dependencies**: T009, T015

**Description**: Validate `cuft_force_reinstall` AJAX endpoint contract compliance (ajax-endpoints.md lines 144-306).

**Implementation**:
- Test in Docker environment
- Execute test cases:

**Test Case 1: Valid request with sufficient space**
- Click "Force Reinstall Latest Version" button
- Confirm dialog
- Wait for completion (max 60 seconds)
- Verify response: 200 OK
- Verify JSON structure:
  ```json
  {
    "success": true,
    "data": {
      "message": "Plugin successfully reinstalled to version X.Y.Z",
      "source_version": "<old>",
      "target_version": "<new>",
      "duration_seconds": <number>,
      "backup_location": "<path>",
      "backup_deleted": true
    }
  }
  ```
- Verify plugin still active and functional after reinstall
- Verify backup ZIP deleted from `/wp-content/uploads/cuft-backups/`

**Test Case 2: Invalid nonce**
- Console: `jQuery.post(ajaxurl, {action: 'cuft_force_reinstall', nonce: 'invalid'}, console.log)`
- Verify: 403 Forbidden, `error_code: "invalid_nonce"`

**Test Case 3: Non-admin user**
- Test as non-admin role
- Verify: 403 Forbidden, `error_code: "insufficient_permissions"`

**Test Case 4: DISALLOW_FILE_MODS enabled**
- Add `define( 'DISALLOW_FILE_MODS', true );` to `wp-config.php`
- Reload admin page
- Verify: "Force Reinstall" button disabled
- Attempt AJAX call via console
- Verify: 403 Forbidden, `error_code: "file_mods_disabled"`
- Remove constant after test

**Test Case 5: Insufficient disk space**
- Difficult to test without disk manipulation
- Verify validation in code (T002)
- Expected: 507 status, `error_code: "insufficient_disk_space"`, space requirements in response

**Test Case 6: Concurrent operation**
- Open two browser windows (same user)
- Click "Force Reinstall" in first window
- Immediately click in second window
- Verify second request: 409 Conflict, `error_code: "operation_in_progress"`

**Test Case 7: Backup creation fails**
- Temporarily chmod 000 on `/wp-content/uploads/cuft-backups/` (if possible)
- Click "Force Reinstall"
- Verify: 500 status, `error_code: "backup_failed"`
- Restore permissions

**Test Case 8: Installation fails, rollback succeeds**
- Difficult to simulate
- Verify rollback logic in code (T008)
- Expected: 500 status, `rollback_successful: true`

**Test Case 9: Operation exceeds 60s timeout**
- Difficult to simulate
- Verify timeout enforcement in code (T008, research.md line 84-117)
- Expected: 504 status, `error_code: "operation_timeout"`

**Acceptance**:
- All 9 test cases documented
- Cases 1-4, 6 executed successfully in Docker
- Cases 5, 7-9 validated in code review
- Response structures match ajax-endpoints.md
- Rollback mechanism preserves plugin functionality

---

### T021 [P]: Contract Test - cuft_get_update_history Endpoint

**File(s)**: `/home/r11/dev/choice-uft/tests/contract/test-cuft-get-update-history-endpoint.php` (manual test in Docker)

**Dependencies**: T009, T015

**Description**: Validate `cuft_get_update_history` AJAX endpoint contract compliance (ajax-endpoints.md lines 310-407).

**Implementation**:
- Test in Docker environment
- Execute test cases:

**Test Case 1: Valid request with history**
- Prerequisite: Perform update check and force reinstall operations
- Load Settings ’ Universal Form Tracker ’ Force Update tab
- Verify history table populated on page load
- Inspect AJAX call in Network tab
- Verify response: 200 OK
- Verify JSON structure:
  ```json
  {
    "success": true,
    "data": {
      "history": [
        {
          "operation_type": "force_reinstall" | "manual_check",
          "user_display_name": "<name>",
          "timestamp": <unix_timestamp>,
          "timestamp_formatted": "YYYY-MM-DD HH:MM:SS",
          "status": "complete" | "failed",
          "details": { ... }
        }
      ],
      "count": <number>,
      "max_entries": 5
    }
  }
  ```
- Verify most recent entry at top (descending order)
- Verify max 5 entries shown

**Test Case 2: Valid request with no history**
- Prerequisite: Clear history via `wp option delete cuft_update_log`
- Reload page
- Verify response: `history: []`, `count: 0`, `message: "No update operations in history yet."`

**Test Case 3: Invalid nonce**
- Console: `jQuery.post(ajaxurl, {action: 'cuft_get_update_history', nonce: 'invalid'}, console.log)`
- Verify: 403 Forbidden, `error_code: "invalid_nonce"`

**Test Case 4: Non-admin user**
- Test as non-admin
- Verify: 403 Forbidden, `error_code: "insufficient_permissions"`

**Acceptance**:
- All 4 test cases pass
- Response structure matches ajax-endpoints.md (lines 336-407)
- Timestamps formatted correctly
- History sorted descending (newest first)
- FIFO limit enforced (max 5 entries)

---

## Phase 6: Manual Validation Layer (Quickstart Scenarios)

### T022 [P]: Manual Validation - QS-1: Manual Update Check

**File(s)**: N/A (manual test following quickstart.md lines 21-56)

**Dependencies**: All implementation tasks (T001-T021)

**Description**: Execute quickstart Scenario 1 to validate manual update check functionality end-to-end.

**Test Steps** (from quickstart.md):
1. Navigate to Settings ’ Universal Form Tracker ’ Force Update tab
2. Click "Check for Updates" button
3. Observe button changes to "Checking..." with loading indicator
4. Wait for operation to complete (max 5 seconds)
5. Verify result message displays

**Expected Results**:
- If Update Available:
  - Message: "Update available: Version X.Y.Z"
  - Release date shown
  - Changelog summary displayed
  - WordPress admin bar shows update badge immediately
- If Up-to-Date:
  - Message: "Plugin is up to date (Version X.Y.Z)"
  - Current version shown
  - Last checked timestamp displayed
- If Error:
  - Error message clear
  - Button re-enabled
  - Retry possible

**Validation**:
- [ ] Check browser console for AJAX call
- [ ] Verify no JavaScript errors
- [ ] Navigate to Plugins page - update notice visible immediately (cache cleared)
- [ ] Check WordPress admin bar for update indicator

**Acceptance**:
- All expected results achieved
- Cache clearing forces WordPress to recognize new version
- User feedback immediate and clear

---

### T023 [P]: Manual Validation - QS-2: Force Reinstall (Update Available)

**File(s)**: N/A (manual test following quickstart.md lines 59-86)

**Dependencies**: All implementation tasks

**Description**: Execute quickstart Scenario 2 to validate force reinstall with update available.

**Test Steps**:
1. Ensure update available (if not, create test release on GitHub or mock)
2. Click "Force Reinstall Latest Version" button
3. Confirm action in dialog
4. Observe progress indicator
5. Wait for completion (max 60 seconds)

**Expected Results**:
- Button changes to "Installing..."
- Progress indicator shows stages (backing up, downloading, installing)
- Operation completes within 60 seconds
- Success message: "Plugin successfully reinstalled to version X.Y.Z"
- Previous version shown
- Plugin remains active
- All functionality works (test form tracking)

**Validation**:
- [ ] Check `/wp-content/uploads/cuft-backups/` - backup ZIP deleted
- [ ] Verify CUFT_VERSION constant updated: `grep "CUFT_VERSION" choice-universal-form-tracker.php`
- [ ] Check update history in UI shows reinstall entry
- [ ] Submit test form, verify dataLayer event fires

**Acceptance**:
- Reinstall successful
- Version updated correctly
- Backup cleanup completed
- Plugin functionality preserved

---

### T024 [P]: Manual Validation - QS-3: Force Reinstall (Already Current)

**File(s)**: N/A (manual test following quickstart.md lines 89-112)

**Dependencies**: All implementation tasks

**Description**: Execute quickstart Scenario 3 to verify force reinstall works when already up-to-date.

**Test Steps**:
1. Ensure plugin at latest version (run QS-1 first)
2. Click "Force Reinstall Latest Version" button
3. Confirm action
4. Wait for completion

**Expected Results**:
- Operation proceeds without "already up-to-date" blocking message
- Downloads and reinstalls same version
- Success message confirms reinstallation
- Plugin remains active and functional
- All settings preserved (GTM ID, debug flags, etc.)

**Validation**:
- [ ] Verify reinstall occurred (check file timestamps: `ls -la choice-uft/`)
- [ ] Confirm no version number change
- [ ] Verify GTM ID preserved: `wp option get cuft_gtm_id`
- [ ] Test form tracking still works

**Acceptance**:
- Force reinstall succeeds even when current
- Settings and configuration preserved
- Use case validated: fixing corrupted files without version change

---

### T025 [P]: Manual Validation - QS-EC1: Insufficient Disk Space

**File(s)**: N/A (manual edge case test following quickstart.md lines 115-129)

**Dependencies**: All implementation tasks

**Description**: Validate disk space validation prevents broken installations.

**Test Steps**:
1. Difficult to simulate low disk space in Docker
2. Alternative: Review T002 code for validation logic
3. Optional: Temporarily modify validator to simulate insufficient space

**Expected Results**:
- Error message: "Insufficient disk space... Free at least X MB"
- Exact space requirements shown
- Operation aborts before backup attempt
- Plugin functionality preserved

**Acceptance**:
- Validation logic in T002 correct (3x plugin size)
- Error message format matches expectation
- Operation fails gracefully without breaking plugin

---

### T026 [P]: Manual Validation - QS-EC2: GitHub API Timeout

**File(s)**: N/A (manual edge case test following quickstart.md lines 131-147)

**Dependencies**: All implementation tasks

**Description**: Validate graceful handling of slow/unavailable GitHub API.

**Test Steps**:
1. Difficult to simulate GitHub timeout
2. Review T008 code for timeout enforcement (5 seconds)
3. Optional: Temporarily block GitHub API in `/etc/hosts` or firewall

**Expected Results**:
- Operation times out after 5 seconds
- Error message: "Unable to check for updates. Please try again later."
- Details include timeout information
- Button re-enabled, retry possible

**Acceptance**:
- Timeout logic in T008 correct (5s for check, 60s for reinstall)
- Error handling graceful
- User can retry operation

---

### T027 [P]: Manual Validation - QS-EC3: Concurrent Operations

**File(s)**: N/A (manual edge case test following quickstart.md lines 149-166)

**Dependencies**: All implementation tasks

**Description**: Validate locking prevents simultaneous updates.

**Test Steps**:
1. Open Settings page in two browser windows (same WordPress user)
2. Click "Force Reinstall" in first window
3. Immediately click "Force Reinstall" in second window

**Expected Results**:
- First request proceeds normally
- Second request fails immediately: "Another update operation is already in progress"
- Optional: Second window shows who started first operation
- After first completes, second can retry successfully

**Validation**:
- [ ] Verify transient lock created: `wp transient get cuft_force_update_lock`
- [ ] Lock includes user_id and operation type
- [ ] Lock expires after 120 seconds (transient TTL)

**Acceptance**:
- Concurrent prevention works (T001 lock mechanism)
- Clear error message for second request
- Lock released after first operation completes
- No race conditions or deadlocks

---

## Phase 7: Documentation & Finalization

### T028: Update Plugin Changelog

**File(s)**: `/home/r11/dev/choice-uft/readme.txt`, `/home/r11/dev/choice-uft/CHANGELOG.md`

**Dependencies**: All implementation and testing tasks

**Description**: Document Feature 009 in plugin changelog.

**Implementation**:
- Open `/home/r11/dev/choice-uft/readme.txt`
- Add entry under `== Changelog ==`:
  ```
  = 3.19.0 - 2025-10-XX =
  * Feature: Manual update control with "Check for Updates" button (Feature 009)
  * Feature: Force reinstall latest version from GitHub (Feature 009)
  * Feature: Update history tracking with 7-day retention (Feature 009)
  * Enhancement: WordPress plugin cache clearing for immediate version recognition
  * Enhancement: Transient-based operation locking prevents concurrent updates
  * Enhancement: Disk space validation (3x plugin size) before force reinstall
  * Enhancement: Automatic backup/restore on reinstall failures
  * Security: Capability checks (update_plugins) for all manual update operations
  * Security: DISALLOW_FILE_MODS constant support
  * UI: New "Force Update" tab in Settings ’ Universal Form Tracker
  ```
- Open `/home/r11/dev/choice-uft/CHANGELOG.md` (if exists)
- Add similar detailed entry with links to specs

**Acceptance**:
- Changelog entries added to both files
- Version number incremented appropriately
- Features clearly described for end users
- Links to relevant documentation (optional)

---

### T029: Update Plugin Version Number

**File(s)**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`

**Dependencies**: T028

**Description**: Bump plugin version number for Feature 009 release.

**Implementation**:
- Open `/home/r11/dev/choice-uft/choice-universal-form-tracker.php`
- Update version in plugin header comment:
  ```php
  * Version:           3.19.0
  ```
- Update `CUFT_VERSION` constant:
  ```php
  define( 'CUFT_VERSION', '3.19.0' );
  ```
- Follow semantic versioning (minor version bump for new feature)

**Acceptance**:
- Version updated in both locations
- Version number consistent across files
- Follows semantic versioning conventions

---

### T030: Test Plugin Activation/Deactivation Cycle

**File(s)**: N/A (manual test)

**Dependencies**: All implementation tasks

**Description**: Validate plugin activation, deactivation, and reactivation work correctly with new code.

**Test Steps**:
1. In WordPress admin, navigate to Plugins page
2. Deactivate "Choice Universal Form Tracker"
3. Verify deactivation hook runs (T017):
   - Check transients cleared: `wp transient list | grep cuft`
   - Check cron unscheduled: `wp cron event list | grep cuft_daily_cleanup`
4. Reactivate plugin
5. Verify activation hook runs:
   - Cron rescheduled
   - No PHP errors
   - Settings page loads correctly
6. Navigate to Settings ’ Universal Form Tracker ’ Force Update tab
7. Verify UI renders correctly

**Acceptance**:
- Deactivation cleans up transients
- Deactivation unschedules cron
- Reactivation succeeds without errors
- All functionality available after reactivation
- Update history preserved across activation cycles

---

### T031: Integration Test - Full User Journey

**File(s)**: N/A (end-to-end test)

**Dependencies**: All tasks

**Description**: Execute complete user journey from first visit to successful force reinstall.

**Test Scenario**:
1. Fresh WordPress installation with plugin installed
2. User logs in as Administrator
3. Navigates to Settings ’ Universal Form Tracker
4. Configures GTM ID (if not done)
5. Navigates to Force Update tab
6. Clicks "Check for Updates" ’ sees version information
7. Clicks "Force Reinstall Latest Version" ’ confirms dialog
8. Waits for completion ’ sees success message
9. Reviews update history ’ sees both operations logged
10. Tests form tracking ’ verifies plugin still works
11. Deactivates and reactivates plugin ’ settings preserved

**Acceptance**:
- Complete user journey successful
- No errors at any step
- History logged correctly
- Settings preserved
- Form tracking unaffected by update operations

---

### T032: Code Review - Security Validation

**File(s)**: All new PHP files

**Dependencies**: All implementation tasks

**Description**: Comprehensive security review of new code.

**Review Checklist**:
- [ ] All AJAX endpoints validate nonce (T009)
- [ ] All AJAX endpoints check `update_plugins` capability (T009)
- [ ] `DISALLOW_FILE_MODS` respected (T009)
- [ ] SQL injection: N/A (no direct SQL queries, using WordPress APIs)
- [ ] XSS prevention: All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` (T011)
- [ ] CSRF protection: Nonce validation on all sensitive operations (T009)
- [ ] Directory traversal: Backup paths validated (T004)
- [ ] Remote code execution: No `eval()`, no unsanitized includes
- [ ] File upload validation: ZIP integrity checked via `CUFT_Update_Validator` (T008)
- [ ] GitHub URL validation: URLs verified against official repository (T008 integration with Feature 008)
- [ ] Capability escalation: No role manipulation
- [ ] Transient security: Auto-expiry prevents lock bypass (T001)

**Acceptance**:
- All security checklist items pass
- No vulnerabilities identified
- Code follows WordPress security best practices
- Ready for production deployment

---

### T033: Performance Testing

**File(s)**: N/A (manual performance validation)

**Dependencies**: All implementation tasks

**Description**: Validate performance meets NFR-101 and NFR-103 requirements (plan.md line 43).

**Test Cases**:
1. **Update Check Timeout**: Verify operation completes or times out within 5 seconds
   - Normal GitHub response: ~1-2 seconds expected
   - Timeout scenario: exactly 5 seconds with error
2. **Force Reinstall Timeout**: Verify operation completes or times out within 60 seconds
   - Typical reinstall: 10-35 seconds (backup + download + install)
   - Timeout scenario: exactly 60 seconds with error and rollback
3. **Cache Clearing Performance**: Verify minimal overhead (<100ms)
   - Measure time for `CUFT_Cache_Clearer::clear_plugin_cache()` (T003)
4. **History Log Performance**: Verify O(n) where n=5 (FIFO operations)
   - Measure time for `CUFT_Update_History_Entry::log_operation()` (T006)
   - Should be <10ms even with 5 existing entries
5. **Disk Space Check Performance**: Verify completes in <100ms
   - Measure time for `CUFT_Disk_Space_Validator::validate_space_for_reinstall()` (T002)
   - Plugin with ~500 files should complete quickly

**Acceptance**:
- Update check: d5s timeout enforced
- Force reinstall: d60s timeout enforced
- Cache operations: <100ms
- History logging: <10ms
- Disk validation: <100ms for typical plugin size
- Performance requirements from plan.md satisfied

---

### T034: Browser Compatibility Testing

**File(s)**: N/A (manual cross-browser test)

**Dependencies**: T013, T014

**Description**: Validate admin UI works in major browsers.

**Test Browsers**:
1. Chrome/Chromium (primary - WordPress admin standard)
2. Firefox
3. Safari (if macOS available)
4. Edge

**Test Points**:
- UI renders correctly (buttons, progress bar, history table)
- JavaScript executes without errors
- AJAX requests succeed
- CSS styling consistent
- Responsive design on narrow windows

**Acceptance**:
- All functionality works in Chrome/Chromium
- No JavaScript errors in Firefox or Edge
- UI acceptable in all tested browsers
- Responsive design verified (narrow viewport ~800px)

---

### T035: WP-CLI Integration Test

**File(s)**: N/A (manual WP-CLI test in Docker)

**Dependencies**: All implementation tasks

**Description**: Validate update operations accessible and observable via WP-CLI.

**Test Commands**:
1. Check transients:
   ```bash
   docker exec wp-pdev-cli wp transient get cuft_plugin_installation_state
   docker exec wp-pdev-cli wp transient get cuft_force_update_lock
   ```
2. Check update history:
   ```bash
   docker exec wp-pdev-cli wp option get cuft_update_log --format=json
   ```
3. Check cron jobs:
   ```bash
   docker exec wp-pdev-cli wp cron event list | grep cuft_daily_cleanup
   ```
4. Trigger cleanup manually:
   ```bash
   docker exec wp-pdev-cli wp cron event run cuft_daily_cleanup
   ```
5. Check capabilities:
   ```bash
   docker exec wp-pdev-cli wp user list --field=caps
   ```

**Acceptance**:
- Transients readable via WP-CLI
- History option readable and parseable
- Cron job listed and runnable
- All data structures accessible for debugging

---

### T036: Update Specification with Implementation Notes

**File(s)**: `/home/r11/dev/choice-uft/specs/009-force-install-update/plan.md`

**Dependencies**: All tasks

**Description**: Document final implementation details and any deviations from original plan.

**Implementation**:
- Open `/home/r11/dev/choice-uft/specs/009-force-install-update/plan.md`
- Add "Implementation Notes" section at end
- Document:
  - Actual task count vs. estimated (35-40 estimated)
  - Any architectural changes during implementation
  - Performance test results
  - Known limitations or future enhancements
  - Links to related PRs or commits
- Update "Progress Tracking" section:
  - Mark Phase 3 complete
  - Mark Phase 4 complete
  - Mark Phase 5 complete (if all validation passed)

**Acceptance**:
- Plan updated with final implementation details
- Deviations documented with rationale
- Progress tracking complete
- Serves as historical record for feature

---

## Task Summary

**Total Tasks**: 36

**Breakdown by Phase**:
- Phase 1 (Infrastructure): 7 tasks (T001-T007) - **All [P]**
- Phase 2 (Services): 3 tasks (T008-T010)
- Phase 3 (UI): 4 tasks (T011-T014)
- Phase 4 (Integration): 4 tasks (T015-T018)
- Phase 5 (Contract Tests): 3 tasks (T019-T021) - **All [P]**
- Phase 6 (Manual Validation): 6 tasks (T022-T027) - **All [P]**
- Phase 7 (Finalization): 9 tasks (T028-T036)

**Parallel Execution Groups**:
- **Group 1** (Infrastructure): T001, T002, T003, T004, T005, T006, T007 - Run in parallel
- **Group 2** (Contract Tests): T019, T020, T021 - Run in parallel after implementation
- **Group 3** (Manual Validation): T022, T023, T024, T025, T026, T027 - Run in parallel after implementation

**Estimated Completion Time**:
- Phase 1: 4-6 hours (parallel execution)
- Phase 2: 3-4 hours
- Phase 3: 2-3 hours
- Phase 4: 1-2 hours
- Phase 5: 2-3 hours
- Phase 6: 3-4 hours
- Phase 7: 2-3 hours
- **Total**: 17-25 hours

---

## Next Steps

1. Review this tasks.md for completeness
2. Begin Phase 1 (Infrastructure) - execute T001-T007 in parallel
3. After Phase 1 complete, proceed sequentially through Phases 2-4
4. Execute testing phases (5-6) after implementation complete
5. Finalize with Phase 7 documentation
6. Create pull request following plan.md Next Steps (line 160-164)
7. Execute quickstart.md manual validation before merging

**Ready to Begin**: All tasks defined, dependencies clear, acceptance criteria specified.
