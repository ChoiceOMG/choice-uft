# Feature 007 Audit Results: Custom Update Execution Logic and UI

**Date**: 2025-10-11
**Branch**: 007-fix-update-system
**Auditor**: Feature 008 Implementation
**Purpose**: Identify conflicting custom update logic and UI for removal

---

## Executive Summary

Feature 007 implemented a **complete custom update system** that bypasses WordPress's native `Plugin_Upgrader` flow. This conflicts with Feature 008's requirement to use WordPress standard update methods.

**Critical Finding**: Feature 007 contains custom download, extraction, and installation logic that must be removed to align with WordPress conventions.

---

## 1. Custom Update Execution Logic (REMOVE)

### 1.1 Custom Download Logic

**File**: `includes/class-cuft-update-installer.php`
- **Lines**: 228-257
- **Method**: `download_package()`
- **Anti-pattern**: Uses `CUFT_GitHub_API::download_file()` for custom download
- **Action**: REMOVE - WordPress `Plugin_Upgrader` handles downloads

```php
// ANTI-PATTERN: Custom download implementation
private function download_package( $url ) {
    $temp_file = CUFT_Filesystem_Handler::tempnam( 'cuft_update_' );
    $result = CUFT_GitHub_API::download_file( $url, $temp_file );
    // ...
}
```

**File**: `includes/class-cuft-github-api.php`
- **Methods**: ZIP download and verification methods
- **Action**: Keep verification methods, remove download execution

### 1.2 Custom Extraction Logic

**File**: `includes/class-cuft-update-installer.php`
- **Lines**: 307-324
- **Method**: `extract_package()`
- **Anti-pattern**: Uses `CUFT_Filesystem_Handler::unzip()` for custom extraction
- **Action**: REMOVE - WordPress `Plugin_Upgrader` handles extraction

```php
// ANTI-PATTERN: Custom extraction implementation
private function extract_package( $package_file ) {
    $temp_dir = CUFT_Filesystem_Handler::get_temp_dir() . '/cuft_update_' . time();
    $result = CUFT_Filesystem_Handler::unzip( $package_file, $temp_dir );
    // ...
}
```

### 1.3 Custom Installation Logic

**File**: `includes/class-cuft-update-installer.php`
- **Lines**: 332-361
- **Method**: `install_files()`
- **Anti-pattern**: Manually deletes old plugin and copies new files
- **Action**: REMOVE - WordPress `Plugin_Upgrader` handles installation

```php
// ANTI-PATTERN: Manual file installation
private function install_files( $source_dir ) {
    // Remove current plugin files
    CUFT_Filesystem_Handler::delete( $plugin_dir, true );

    // Copy new files
    $this->copy_directory( $plugin_source, $plugin_dir );
    // ...
}
```

### 1.4 Custom Update Orchestration

**File**: `includes/class-cuft-update-installer.php`
- **Lines**: 60-158
- **Method**: `execute()`
- **Anti-pattern**: Orchestrates entire update process manually
- **Action**: REMOVE ENTIRE CLASS - WordPress `Plugin_Upgrader::upgrade()` handles this

**File**: `includes/ajax/class-cuft-updater-ajax.php`
- **Lines**: 133-174
- **Method**: `perform_update()`
- **Anti-pattern**: AJAX endpoint triggers custom update execution
- **Action**: REMOVE - WordPress handles updates via standard endpoints

```php
// ANTI-PATTERN: AJAX-triggered custom update
public function perform_update() {
    wp_schedule_single_event( time() + 1, 'cuft_process_update', array( $update_id, $version, $backup ) );
    // ...
}
```

---

## 2. Custom Update UI (REMOVE)

### 2.1 Admin Bar Integration

**File**: `includes/admin/class-cuft-admin-bar.php`
- **Lines**: ENTIRE FILE (213 lines)
- **Hook**: `admin_bar_menu` (line 26)
- **Anti-pattern**: Custom admin bar menu for updates
- **Action**: REMOVE ENTIRE FILE

**Menu Items Added**:
- `cuft-updates` - Parent menu with update indicator
- `cuft-check-updates` - Manual check trigger
- `cuft-view-updates` - Version details
- `cuft-update-settings` - Links to Settings page Updates tab
- `cuft-update-history` - Links to update history
- `cuft-next-check` - Next scheduled check info

**Assets**:
- **File**: `assets/admin/js/cuft-admin-bar.js`
- **Action**: REMOVE - Admin bar JavaScript

### 2.2 Settings Page - Updates Tab

**File**: `includes/class-cuft-admin.php`
- **Line**: 1 (approximate - contains `'updates' => __( 'Updates', 'choice-universal-form-tracker' )`)
- **Anti-pattern**: Custom Updates tab in Settings page
- **Action**: REMOVE tab registration and content rendering

**Tab Content** (approximate):
- Update status display
- "Update Now" button
- Update progress UI
- Update history display

### 2.3 Settings Page - GitHub Auto-Updates Section

**File**: `includes/class-cuft-admin.php`
- **Lines**: Contains "GitHub Auto-Updates" heading
- **Anti-pattern**: Custom auto-update toggle (WordPress 5.5+ provides this)
- **Action**: REMOVE entire section

**Settings**:
- Auto-update enable/disable toggle
- Update check frequency
- Pre-release inclusion
- Notification email

---

## 3. AJAX Endpoints for Update Execution (REMOVE/MODIFY)

**File**: `includes/ajax/class-cuft-updater-ajax.php`

### 3.1 REMOVE These Endpoints (Trigger Updates):

| Endpoint | Line | Action | Reason |
|----------|------|--------|---------|
| `cuft_perform_update` | 42 | **REMOVE** | Triggers custom update execution |
| `cuft_rollback_update` | 48 | **REMOVE** | Manual rollback (WordPress handles this) |
| `cuft_update_settings` | 54 | **MODIFY** | Remove if only for Updates tab, keep if for other settings |

### 3.2 MODIFY These Endpoints (Keep for Status Display):

| Endpoint | Line | Action | Reason |
|----------|------|--------|---------|
| `cuft_check_update` | 39 | **KEEP** | Status checking only (no execution) |
| `cuft_update_status` | 45 | **KEEP** | Progress display only |
| `cuft_update_history` | 51 | **KEEP** | Read-only history display |
| `cuft_dismiss_update_notice` | 57 | **MODIFY** | Update to dismiss per-version notices |

### 3.3 Additional AJAX Endpoints

**File**: `includes/class-cuft-admin.php`
- **Endpoints**:
  - `cuft_manual_update_check` - Keep (triggers status check only)
  - `cuft_install_update` - **REMOVE** (triggers update execution)

---

## 4. Supporting Classes (AUDIT INDIVIDUALLY)

### 4.1 REMOVE Entire Classes

| File | Purpose | Action |
|------|---------|---------|
| `includes/class-cuft-update-installer.php` | Custom update execution | **DELETE FILE** |
| `includes/class-cuft-wordpress-updater.php` | Likely redundant | **AUDIT & DELETE** |
| `includes/admin/class-cuft-admin-bar.php` | Admin bar integration | **DELETE FILE** |

### 4.2 KEEP These Classes (Status/Logging Only)

| File | Purpose | Action |
|------|---------|---------|
| `includes/class-cuft-update-checker.php` | Status checking | **KEEP** (no execution) |
| `includes/models/class-cuft-update-status.php` | Status model | **KEEP** |
| `includes/models/class-cuft-update-progress.php` | Progress tracking | **KEEP** |
| `includes/models/class-cuft-update-log.php` | Update history | **KEEP** (FIFO logging) |
| `includes/models/class-cuft-update-configuration.php` | Settings storage | **AUDIT** (may need modification) |

### 4.3 AUDIT These Classes (May Need Modification)

| File | Purpose | Action |
|------|---------|---------|
| `includes/class-cuft-github-updater.php` | GitHub integration | **AUDIT** - Keep API methods, remove execution |
| `includes/class-cuft-github-api.php` | GitHub API client | **AUDIT** - Keep verification, remove download execution |
| `includes/class-cuft-download-verifier.php` | ZIP validation | **KEEP** - Validation methods are useful |

---

## 5. Assets to Remove

### 5.1 JavaScript Files

| File | Purpose | Action |
|------|---------|---------|
| `assets/admin/js/cuft-admin-bar.js` | Admin bar functionality | **DELETE** |
| `assets/admin/js/cuft-update-progress.js` | Update progress UI (if exists) | **AUDIT & DELETE** |

### 5.2 CSS Files

| File | Purpose | Action |
|------|---------|---------|
| `assets/admin/css/cuft-update.css` | Update UI styles (if exists) | **AUDIT & DELETE** |

---

## 6. Admin Notice Modifications (MODIFY)

**Current Behavior**:
- Shows update available notification
- Links to Settings page Updates tab
- May not be dismissible per-version

**Required Changes** (T000c):
1. Make dismissible with X button
2. Change link from Settings page to Plugins page (`/wp-admin/plugins.php`)
3. Update message text to WordPress standard format
4. Implement version-specific dismissal (user meta: `cuft_notice_dismissed_v{VERSION}`)
5. Update button text to "View Plugin Updates"

**Files to Modify**:
- `includes/class-cuft-admin.php` (or wherever admin notices are rendered)
- AJAX handler for dismiss action (already exists: `cuft_dismiss_update_notice`)

---

## 7. Removal Plan

### Phase 1: Remove Custom Update Execution (T000a)

**Priority: CRITICAL**

1. **Delete Files**:
   ```bash
   rm includes/class-cuft-update-installer.php
   rm includes/admin/class-cuft-admin-bar.php
   rm assets/admin/js/cuft-admin-bar.js
   ```

2. **Remove AJAX Endpoints** (in `includes/ajax/class-cuft-updater-ajax.php`):
   - Remove `perform_update()` method and registration (lines 42, 133-174)
   - Remove `rollback_update()` method and registration (lines 48, 209-253)

3. **Remove From Admin** (in `includes/class-cuft-admin.php`):
   - Remove `cuft_install_update` AJAX handler

4. **Audit and Clean**:
   - `includes/class-cuft-wordpress-updater.php` - Review and likely delete
   - `includes/class-cuft-github-api.php` - Remove download execution, keep verification

**Estimated Time**: 3-4 hours

### Phase 2: Remove Custom Update UI (T000b)

**Priority**: CRITICAL

1. **Remove Admin Bar** (already in Phase 1):
   - Delete `includes/admin/class-cuft-admin-bar.php`
   - Delete `assets/admin/js/cuft-admin-bar.js`

2. **Remove Settings Page Updates Tab** (in `includes/class-cuft-admin.php`):
   - Remove `'updates'` tab from tab registration
   - Remove tab content rendering method
   - Remove associated AJAX handlers (if Settings-specific)

3. **Remove GitHub Auto-Updates Section**:
   - Remove section from Settings page
   - Remove associated settings fields
   - Keep settings for other features (if any)

4. **Clean Up Assets**:
   - Search for and remove update-related CSS/JS files
   ```bash
   find assets/ -name "*update*" -type f
   ```

**Estimated Time**: 2-3 hours

### Phase 3: Modify Admin Notice Behavior (T000c)

**Priority**: HIGH

**File**: Find where admin notices are rendered (likely `includes/class-cuft-admin.php`)

**Changes**:
1. Add dismissible class to notice
2. Update link: `admin_url( 'plugins.php' )` instead of settings page
3. Update message text
4. Implement per-version dismissal:
   ```php
   $user_id = get_current_user_id();
   $meta_key = 'cuft_notice_dismissed_v' . $new_version;
   if ( get_user_meta( $user_id, $meta_key, true ) ) {
       return; // Don't show notice
   }
   ```
5. Update button text to "View Plugin Updates"

**Estimated Time**: 1-2 hours

### Phase 4: Test WordPress Native Update Flow (T000d)

**Priority**: CRITICAL

**Tests**:
1. Verify `wp plugin update choice-uft` works
2. Verify Plugins page "Update Now" button works
3. Verify bulk updates work
4. Verify no custom code intercepts updates
5. Verify admin bar no longer shows update indicator
6. Verify Settings page no longer has Updates tab
7. Verify WordPress auto-update toggle works (Plugins page)

**Documentation**: `/home/r11/dev/choice-uft/specs/007-fix-update-system/MIGRATION-TEST-RESULTS.md`

**Estimated Time**: 1-2 hours

---

## 8. Files Summary

### DELETE (7 files):
1. `includes/class-cuft-update-installer.php` ✅
2. `includes/admin/class-cuft-admin-bar.php` ✅
3. `assets/admin/js/cuft-admin-bar.js` ✅
4. `includes/class-cuft-wordpress-updater.php` (audit first)
5. `assets/admin/css/cuft-update.css` (if exists)
6. `assets/admin/js/cuft-update-progress.js` (if exists)

### MODIFY (2 files):
1. `includes/ajax/class-cuft-updater-ajax.php` - Remove execution endpoints
2. `includes/class-cuft-admin.php` - Remove Updates tab, modify notices

### KEEP (6+ files):
1. `includes/class-cuft-update-checker.php` ✅
2. `includes/models/class-cuft-update-status.php` ✅
3. `includes/models/class-cuft-update-progress.php` ✅
4. `includes/models/class-cuft-update-log.php` ✅
5. `includes/class-cuft-download-verifier.php` ✅
6. `includes/models/class-cuft-update-configuration.php` (audit settings)

---

## 9. Success Criteria

**Migration complete when**:
- [ ] No custom download/install code remains
- [ ] No AJAX endpoints trigger updates
- [ ] Admin bar has no update menu
- [ ] Settings page has no Updates tab
- [ ] Settings page has no GitHub Auto-Updates section
- [ ] Admin notices link to Plugins page
- [ ] Admin notices are dismissible per-version
- [ ] `wp plugin update choice-uft` works via WordPress native flow
- [ ] Plugins page "Update Now" button works
- [ ] Bulk updates work
- [ ] No Feature 007 code interferes with WordPress Plugin_Upgrader

---

## 10. Risk Assessment

### High Risk:
- Deleting files that other features depend on
- Breaking update history logging
- Breaking admin notices

### Medium Risk:
- Incomplete removal leaving orphaned code
- Breaking Settings page (other tabs)

### Low Risk:
- CSS/JS asset cleanup
- Admin bar removal (isolated component)

### Mitigation:
1. Create rollback branch before deletion
2. Test after each phase
3. Review file dependencies before deletion
4. Keep logging infrastructure intact

---

**Total Estimated Time**: 8-12 hours
**Critical Path**: T000a → T000d (must verify WordPress flow works)
**Blocker**: None identified
**Dependencies**: None (can start immediately)
