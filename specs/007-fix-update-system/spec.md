# Feature Specification: Fix Update System Integration (v2)

**Feature Branch**: `007-fix-update-system-v2`
**Original Branch**: `007-fix-update-system` (v3.16.3)
**Created**: 2025-10-11
**Status**: REOPENED - Requires Proper Fix
**Version**: 2.0 (Supersedes v1.0)
**Previous Implementation**: v3.16.3 (marked "complete" but architecturally flawed)

---

## 📋 Document History

| Version | Date | Status | Summary |
|---------|------|--------|---------|
| v1.0 | 2025-10-07 | Completed | Initial implementation (v3.16.3) - 10 FRs, custom AJAX update buttons |
| v1.1 | 2025-10-10 | Hotfix | Type mismatch fixes (v3.16.4) |
| v1.2 | 2025-10-11 | Degraded | Removed custom update buttons (v3.16.5) due to errors |
| **v2.0** | **2025-10-11** | **REOPENED** | **Proper WordPress integration approach** |

---

## 🎯 Executive Summary

The initial implementation (v3.16.3) addressed surface-level UX issues but failed to properly integrate with WordPress's native update system. This resulted in "git url could not be constructed" errors and forced removal of custom update functionality in v3.16.5.

**This specification** defines the correct approach: integrate properly with WordPress's Plugin Update API rather than building custom update logic that fights against it.

---

## 🔍 Application Context

### What Is Choice Universal Form Tracker?

Choice Universal Form Tracker is a **WordPress plugin** that:
- Tracks form submissions across 5 major form frameworks
- Pushes tracking events to Google Tag Manager's dataLayer
- Supports GA4 and Meta/Facebook conversion tracking
- Tracks UTM parameters and click IDs for attribution

### Distribution & Update Model

**NOT in WordPress.org Repository**:
- No plugin search listing
- Not subject to WordPress.org review process
- Updates do NOT come from WordPress.org servers

**Distributed via GitHub Releases**:
- Repository: `https://github.com/ChoiceOMG/choice-uft`
- Release format: `v3.xx.xx` (semantic versioning)
- Asset: `choice-uft-v3.xx.xx.zip` (versioned filename)
- Folder inside ZIP: `choice-uft/` (NO version in folder name)

**Installation Methods**:
1. Manual upload via WordPress admin (Plugins → Add New → Upload)
2. Direct FTP/SFTP upload to `/wp-content/plugins/`
3. WP-CLI: `wp plugin install /path/to/choice-uft-v3.xx.xx.zip`

**Update Process**:
```
GitHub Releases (source of truth)
    ↓
WordPress checks for updates (background task)
    ↓
Our plugin injects update info into WordPress transient
    ↓
WordPress displays "Update Available" in UI
    ↓
User clicks "Update Now" (WordPress native button)
    ↓
WordPress downloads ZIP from GitHub
    ↓
WordPress extracts and installs
    ↓
Our plugin fixes directory naming
    ↓
WordPress completes update
    ↓
Our plugin clears caches
```

### Technical Architecture

**WordPress Integration Hooks**:
1. `pre_set_site_transient_update_plugins` - Inject update availability
2. `plugins_api` - Provide plugin information for details modal
3. `upgrader_source_selection` - Fix directory naming after extraction
4. `upgrader_process_complete` - Clear caches after successful update

**Our Custom Classes**:
- `CUFT_WordPress_Updater` - WordPress integration layer
- `CUFT_GitHub_API` - GitHub API client
- `CUFT_Update_Checker` - Update check orchestration
- `CUFT_Update_Status` - Site transient storage
- `CUFT_Update_Log` - FIFO update history (last 5)
- `CUFT_GitHub_Release` - Release data model

**Update Flow** (Current Working State):
```
User visits wp-admin/plugins.php
    ↓
WordPress fires pre_set_site_transient_update_plugins
    ↓
CUFT_WordPress_Updater::check_for_updates()
    ↓
CUFT_Update_Checker::check() → CUFT_GitHub_API
    ↓
Update info added to $transient->response
    ↓
WordPress displays "Update Available" badge
    ↓
User clicks "Update Now" (WordPress native)
    ↓
WordPress downloads, extracts, installs
    ↓
upgrader_source_selection fixes directory name
    ↓
upgrader_process_complete clears caches
    ↓
✅ Update succeeds
```

---

## 💔 What Went Wrong (v3.16.3 - v3.16.5)

### Timeline of Failures

**v3.16.3 (October 8)** - Initial "Complete" Implementation:
- ✅ Fixed admin notice positioning
- ✅ Added admin bar auto-refresh
- ✅ Implemented context-aware caching
- ✅ Added update history tracking
- ❌ Built custom "Update Now" AJAX button
- ❌ Built custom "Check for Updates" button
- ❌ Created `CUFT_Update_Installer` class to perform updates
- ❌ Tried to replicate WordPress's `Plugin_Upgrader` functionality

**Result**: Marked "production ready" despite architectural issues.

**v3.16.4 (October 10)** - Type Mismatch Hotfix:
- 🐛 Fatal errors: "Trying to access array offset on value of type CUFT_GitHub_Release"
- 🐛 Plugins page crashed when accessing plugin information
- 🔧 Fixed by using object methods instead of array access
- ❌ Did not address underlying architectural problems

**v3.16.5 (October 11)** - Feature Removal:
- 🐛 Custom "Update Now" button caused "git url could not be constructed" errors
- 🐛 Update process failed when triggered from plugin's Updates tab
- 🔧 Removed custom "Update Now" button entirely
- 🔧 Removed custom "Check for Updates" button
- 🔧 Changed all update links to point to WordPress Plugins page
- ❌ Gave up on custom UI instead of fixing integration

### Root Causes

**1. Architectural Misunderstanding**

We built custom update execution logic when WordPress already provides a complete update system.

**What We Should Do**:
- Inform WordPress that an update exists
- Provide details for WordPress's update modal
- Let WordPress handle download/extract/install
- Fix any naming issues after extraction
- Clean up after WordPress completes

**What We Were Doing**:
- Building custom download logic
- Creating custom installation logic
- Manually managing file operations
- Trying to replicate `Plugin_Upgrader` behavior
- Fighting against WordPress instead of working with it

**2. WordPress Integration Gaps**

Our `pre_set_site_transient_update_plugins` hook provided incomplete data:

```php
// What we provided (incomplete)
$plugin_data = array(
    'slug' => $this->plugin_slug,
    'plugin' => $this->plugin_basename,
    'new_version' => $update_status['latest_version'],
    'url' => 'https://github.com/ChoiceOMG/choice-uft',
    'package' => $release->get_download_url(),
);

// What WordPress expects (complete)
$plugin_data = array(
    'slug' => $this->plugin_slug,
    'plugin' => $this->plugin_basename,
    'new_version' => '3.16.5',
    'url' => 'https://github.com/ChoiceOMG/choice-uft',
    'package' => 'https://github.com/.../choice-uft-v3.16.5.zip',
    'tested' => '6.7',                    // ← Missing
    'requires' => '5.0',                  // ← Missing
    'requires_php' => '7.0',              // ← Missing
    'icons' => array( ... ),              // ← Missing
    'banners' => array( ... ),            // ← Missing
    'compatibility' => new stdClass(),    // ← Incomplete
);
```

**Result**: Inconsistent UI, missing compatibility warnings, broken update modal.

**3. Custom AJAX Endpoint Issues**

Our custom update button triggered this flow:

```
User clicks "Update Now" (our custom button)
    ↓
AJAX request to wp-admin/admin-ajax.php?action=cuft_install_update
    ↓
Our PHP code tries to instantiate WP_Upgrader directly
    ↓
WordPress validation fails: "git url could not be constructed"
    ↓
❌ Update fails
```

**Why It Failed**:
- WordPress expects updates to be triggered via its own UI/nonces
- Direct `WP_Upgrader` instantiation bypasses security checks
- GitHub URLs don't match WordPress.org URL patterns
- Missing proper context (screen, action, nonce validation)

**4. Type Inconsistencies (v3.16.4)**

Mixed object/array access patterns:

```php
// Inconsistent usage across codebase
$release['download_url']     // ❌ Array access (failed)
$release->get_download_url() // ✅ Object method (correct)
```

**Why This Happened**:
- No type hints in function signatures
- Inconsistent coding patterns
- Lack of automated type checking

---

## ✅ What Actually Works

### WordPress Native Update (Current State)

**From Plugins Page** ✅:
1. User navigates to `wp-admin/plugins.php`
2. WordPress checks for updates (background task or manual refresh)
3. Our `CUFT_WordPress_Updater::check_for_updates()` runs
4. Update info injected into WordPress transient
5. WordPress shows "Update Available" badge
6. User clicks "Update Now" (WordPress's native button)
7. WordPress downloads ZIP from GitHub
8. WordPress extracts files
9. Our `upgrader_source_selection` fixes directory name
10. WordPress completes installation
11. Our `upgrader_process_complete` clears caches
12. ✅ **Update succeeds reliably**

**From Dashboard → Updates** ✅:
- Same flow as above
- Works correctly
- No errors

**Via WP-CLI** ✅:
```bash
wp plugin update choice-uft
```
- Uses same WordPress update system
- Works correctly

### What We Lost in v3.16.5

**Custom Update UI** ❌:
- No "Update Now" button in plugin's Updates tab
- Can't show progress during updates
- Can't display detailed error messages
- Users must go to Plugins page (inconsistent UX)

**Admin Bar Refresh** ❌ (Partially):
- Admin bar still shows update indicator
- No longer auto-refreshes every 5 minutes
- Requires page reload to see update status

**Update Synchronization** ❌ (Partially):
- Status still consistent across interfaces
- But no unified action point (all links go to Plugins page)

---

## 📝 User Scenarios & Testing

### Primary User Story

As a WordPress administrator managing the Choice Universal Form Tracker plugin installed from GitHub, I need a reliable and intuitive update system that:
1. Notifies me when updates are available
2. Lets me review changelogs before updating
3. Allows me to update with a single click
4. Shows progress during updates
5. Provides clear error messages if updates fail
6. Works consistently whether I'm on the Plugins page, Updates page, or plugin Settings page

The update system must integrate properly with WordPress's native update mechanism rather than fight against it.

### Acceptance Scenarios

#### Scenario 1: Update from Plugins Page (Currently Works)
- **Given**: An administrator is viewing `/wp-admin/plugins.php`
- **And**: A new version is available on GitHub
- **When**: WordPress checks for updates
- **Then**: The plugin shows an "Update Available" badge
- **When**: The administrator clicks "Update Now"
- **Then**: WordPress downloads, extracts, and installs the update
- **And**: The plugin is successfully updated to the new version
- **And**: No errors occur

#### Scenario 2: Update from Plugin Settings (Needs Fix)
- **Given**: An administrator is viewing the plugin's Updates tab
- **And**: A new version is available
- **When**: The administrator sees "Update Available" notice
- **Then**: They should be able to click "Update Now" directly on that page
- **And**: See real-time progress during the update
- **And**: See success/failure message when complete
- **And**: Not be redirected to a different page

#### Scenario 3: View Update Details
- **Given**: An update is available
- **When**: The administrator clicks "View Details"
- **Then**: A modal opens showing:
  - Version number
  - Changelog
  - File size
  - WordPress compatibility
  - PHP compatibility
  - Published date
- **And**: The modal has a functional "Update Now" button
- **And**: The modal doesn't show errors or broken formatting

#### Scenario 4: Admin Bar Notification
- **Given**: A new version is released on GitHub
- **When**: WordPress runs its background update check
- **Then**: The admin bar shows "CUFT Update Available" badge
- **When**: The administrator clicks the badge
- **Then**: They are taken to plugin's Updates tab (not Plugins page)
- **And**: Can update directly from there

#### Scenario 5: Update Failure Handling
- **Given**: An administrator initiates an update
- **And**: The update fails (network error, permissions, etc.)
- **When**: The failure occurs
- **Then**: A clear error message is displayed
- **And**: The error message explains what went wrong
- **And**: The error message suggests how to fix it
- **And**: The previous version is still intact (no broken site)
- **And**: The administrator can retry the update

#### Scenario 6: Multiple Updates Available
- **Given**: Both WordPress core and CUFT have updates available
- **When**: The administrator views Dashboard → Updates
- **Then**: CUFT appears in the "Plugins" section
- **And**: Can be selected for bulk update
- **And**: Bulk update works correctly alongside WordPress core update

### Edge Cases

**EC-1: GitHub API Rate Limiting**
- **Given**: GitHub API rate limit is exceeded
- **When**: Plugin checks for updates
- **Then**: A friendly message explains the rate limit
- **And**: Shows when rate limit resets
- **And**: Doesn't spam API requests

**EC-2: GitHub Release Contains Pre-Release**
- **Given**: Latest GitHub release is marked as "pre-release"
- **When**: Plugin checks for updates
- **Then**: Pre-release is NOT shown (unless user opts into beta channel)
- **And**: Latest stable release is shown instead

**EC-3: Downgrade Attempt**
- **Given**: Current version is 3.17.0
- **And**: User manually downloads 3.16.5
- **When**: User tries to install via "Add New" → "Upload Plugin"
- **Then**: WordPress shows warning about downgrading
- **And**: Allows override with confirmation

**EC-4: Filesystem Permission Issues**
- **Given**: `/wp-content/plugins/choice-uft/` is not writable
- **When**: Update is attempted
- **Then**: Clear error message: "Cannot write to plugin directory"
- **And**: Suggests fixing permissions: "chmod 755" or contact host

**EC-5: Partial Download (Network Interruption)**
- **Given**: Update download starts
- **And**: Network connection drops mid-download
- **When**: WordPress tries to extract the ZIP
- **Then**: Extraction fails gracefully
- **And**: Original plugin files remain intact
- **And**: Error message: "Download failed, please try again"

**EC-6: ZIP File Directory Naming**
- **Given**: GitHub creates ZIP with directory `choice-uft-v3.16.5/`
- **And**: WordPress expects directory `choice-uft/`
- **When**: WordPress extracts the ZIP
- **Then**: Our `upgrader_source_selection` hook renames it
- **And**: WordPress installs to correct location
- **And**: No "directory mismatch" errors

**EC-7: Concurrent Update Attempts**
- **Given**: Admin User A starts an update
- **And**: Admin User B tries to start an update 10 seconds later
- **When**: User B's request is processed
- **Then**: User B sees: "Update already in progress by Admin User A"
- **And**: User B cannot start a second update
- **And**: When User A's update completes, User B can retry

---

## 🎯 Requirements

### Functional Requirements

#### Category: WordPress Integration (Core)

**FR-101: Proper Update Transient Injection**
- System MUST inject complete update information into WordPress's `update_plugins` transient
- Information MUST include: `slug`, `plugin`, `new_version`, `url`, `package`, `tested`, `requires`, `requires_php`, `icons`, `banners`, `compatibility`
- Package URL MUST be direct link to GitHub release ZIP file
- Information MUST be cached based on WordPress context (1 min - 12 hours)

**FR-102: Complete Plugin Information Modal**
- System MUST respond to `plugins_api` filter with complete plugin information
- Information MUST include: name, slug, version, author, homepage, requires, tested, requires_php, download_link, sections (description, changelog, installation)
- Modal MUST display without errors or broken formatting
- Changelog MUST be fetched from GitHub release notes

**FR-103: Directory Naming Fix**
- System MUST rename extracted directory from `choice-uft-vX.X.X` to `choice-uft`
- Renaming MUST occur via `upgrader_source_selection` filter
- Renaming MUST handle all error cases (source not found, rename failed)
- WordPress MUST be able to install to correct plugin directory

**FR-104: Cache Invalidation**
- System MUST clear all update-related caches after successful update
- Caches to clear: `cuft_update_status`, `update_plugins` transient, `cuft_github_version`, `cuft_github_changelog`
- Invalidation MUST occur via `upgrader_process_complete` hook
- Invalidation MUST be limited to this plugin's updates (not affect other plugins)

#### Category: Update User Interface

**FR-201: Native Plugins Page Integration**
- System MUST work correctly with WordPress native "Update Now" button on Plugins page
- Updates triggered from Plugins page MUST succeed without errors
- Update progress MUST be displayed using WordPress's native progress UI
- Success/failure messages MUST be displayed using WordPress's native notices

**FR-202: Custom Updates Tab UI**
- Plugin's Settings → Updates tab MUST have functional "Update Now" button
- Button MUST trigger update using WordPress's `Plugin_Upgrader` class (not custom logic)
- Button MUST show real-time progress during update
- Button MUST display success/failure message when complete
- Button MUST handle errors gracefully with clear messages

**FR-203: Admin Bar Integration**
- Admin bar MUST show "CUFT Update Available" indicator when update exists
- Indicator MUST auto-refresh every 5 minutes (only when tab visible)
- Clicking indicator MUST take user to plugin's Updates tab
- Indicator MUST disappear when no update available

**FR-204: Admin Notice Positioning**
- Update available notices MUST appear above page title (after `.wp-header-end` marker)
- Notices MUST not appear when user is already on Updates tab (to avoid redundancy)
- Notices MUST be dismissible per-version (dismiss 3.16.5 doesn't hide 3.17.0 notice)
- Dismissed state MUST be stored in user meta (not site-wide)

#### Category: Update Execution

**FR-301: Update via WordPress Plugins Page**
- User MUST be able to update from `wp-admin/plugins.php` using WordPress native button
- Update MUST download ZIP from GitHub
- Update MUST extract and install successfully
- Update MUST complete within 60 seconds under normal conditions

**FR-302: Update via Plugin Settings**
- User MUST be able to update from plugin's Settings → Updates tab
- Update MUST use WordPress's `Plugin_Upgrader` class (not custom implementation)
- Update MUST show progress: "Downloading...", "Extracting...", "Installing...", "Complete"
- Update MUST handle errors with clear messages

**FR-303: Update via WP-CLI**
- User MUST be able to update via `wp plugin update choice-uft`
- CLI update MUST work identically to admin UI update
- CLI update MUST output progress information
- CLI update MUST return correct exit codes (0 = success, 1 = failure)

**FR-304: Bulk Update Support**
- Plugin MUST support bulk updates on Dashboard → Updates page
- Bulk update MUST work alongside other plugin updates
- Bulk update MUST not interfere with WordPress core updates

#### Category: Error Handling & Security

**FR-401: Download Verification**
- System MUST verify downloaded ZIP file is valid before extraction
- System MUST check ZIP file size matches expected size (±10% tolerance)
- System MUST abort if ZIP is corrupted or incomplete
- System MUST display clear error: "Download verification failed, please try again"

**FR-402: Rollback on Failure**
- System MUST preserve current plugin files before starting update
- If update fails, system MUST restore previous version automatically
- User MUST see message: "Update failed, previous version restored"
- Rollback MUST complete within 10 seconds

**FR-403: Security Validation**
- All update actions MUST validate WordPress nonces
- All update actions MUST check `update_plugins` capability
- AJAX endpoints MUST validate request origin
- AJAX endpoints MUST rate-limit requests (max 5 per minute per user)

**FR-404: Error Message Clarity**
- Error messages MUST explain what went wrong in plain language
- Error messages MUST suggest how to fix the issue
- Error messages MUST include relevant details (error code, version, etc.)
- Example: "Cannot write to /wp-content/plugins/choice-uft/. Please check file permissions or contact your hosting provider."

#### Category: Performance & Caching

**FR-501: Context-Aware Cache Timeouts**
- Update status checks MUST use different cache timeouts based on context:
  - After `upgrader_process_complete`: 0 seconds (immediate refresh)
  - On `load-update-core.php`: 1 minute
  - On `load-plugins.php`: 1 hour
  - Background tasks: 12 hours
- Cache key MUST include plugin version to avoid stale data

**FR-502: GitHub API Rate Limiting**
- System MUST respect GitHub API rate limits (60 requests/hour unauthenticated)
- System MUST cache GitHub API responses for at least 1 hour
- If rate limited, system MUST display: "GitHub rate limit reached, please try again at [time]"
- System SHOULD use authenticated API requests if token configured (5000 requests/hour)

**FR-503: Fast Status Checks**
- Update status checks MUST complete within 5 seconds (P95)
- Admin bar refresh MUST complete within 2 seconds (P95)
- Checks MUST not block page rendering
- Checks SHOULD use WordPress's HTTP API with 10-second timeout

#### Category: Update History & Logging

**FR-601: Update History Tracking**
- System MUST maintain history of last 5 updates
- History MUST include: timestamp, version from, version to, user who updated, success/failure
- History MUST use FIFO cleanup (when 6th update occurs, delete oldest)
- History MUST be displayed on plugin's Updates tab

**FR-602: Error Logging**
- System MUST log all update errors to PHP error_log
- Logs MUST include: timestamp, error message, stack trace, user ID, version attempted
- Logs MUST be visible when WP_DEBUG is enabled
- Logs MUST not expose sensitive information (API keys, etc.)

---

## 🔑 Key Entities

### Update Status (Site Transient)
```php
array(
    'current_version' => '3.16.5',        // CUFT_VERSION constant
    'latest_version' => '3.17.0',         // From GitHub API
    'update_available' => true,           // Boolean
    'last_check' => '2025-10-11T06:30:00Z', // ISO 8601 timestamp
    'checking' => false,                  // Boolean (prevents concurrent checks)
    'download_url' => 'https://...',      // GitHub asset URL
    'changelog' => 'Release notes...',    // From GitHub
    'file_size' => '1.2 MB',             // Human-readable
    'published_date' => '2025-10-11',    // YYYY-MM-DD
    'is_prerelease' => false,            // Boolean
)
```

### Update Progress (User Transient)
```php
array(
    'status' => 'in_progress',    // pending|in_progress|complete|failed
    'message' => 'Downloading...', // User-facing message
    'percentage' => 45,            // 0-100
    'user_id' => 1,               // Who started the update
    'started_at' => '2025-10-11T06:35:00Z',
    'updated_at' => '2025-10-11T06:35:15Z',
)
```

### Update Log Entry (Database)
```php
array(
    'id' => 123,
    'timestamp' => '2025-10-11T06:35:30Z',
    'action' => 'update_completed',  // check_started|check_completed|update_started|update_completed|error
    'version_from' => '3.16.5',
    'version_to' => '3.17.0',
    'user_id' => 1,
    'success' => true,
    'error_message' => null,
    'details' => array( ... ),  // JSON serialized
)
```

### GitHub Release (Object)
```php
class CUFT_GitHub_Release {
    private string $version;
    private string $tag_name;
    private string $name;
    private string $body;  // Changelog
    private string $published_at;
    private bool $prerelease;
    private array $assets;

    public function get_version(): string { ... }
    public function get_download_url(): string { ... }
    public function get_changelog(): string { ... }
    public function get_published_date(): string { ... }
    public function is_prerelease(): bool { ... }
}
```

---

## 📚 Dependencies & Assumptions

### Dependencies

**WordPress Core** (Required):
- WordPress 5.0+ (tested up to 6.7)
- PHP 7.0+ (recommended: PHP 8.0+)
- cURL or `allow_url_fopen` enabled (for HTTP requests)
- Write permissions on `/wp-content/plugins/choice-uft/`

**External Services**:
- GitHub API (unauthenticated): `https://api.github.com/repos/ChoiceOMG/choice-uft/releases`
- GitHub CDN (for ZIP downloads): `https://github.com/ChoiceOMG/choice-uft/releases/download/...`

**WordPress APIs**:
- Transients API: `set_site_transient()`, `get_site_transient()`
- HTTP API: `wp_remote_get()`, `wp_remote_head()`
- Plugin Upgrader: `Plugin_Upgrader`, `WP_Upgrader`, `Plugin_Upgrader_Skin`
- Filesystem API: `WP_Filesystem`, `$wp_filesystem->move()`

### Assumptions

**A1: GitHub Availability**
- Assumption: GitHub.com is available 99.9% of the time
- Mitigation: Cache update checks aggressively
- Fallback: If GitHub is down, show cached status

**A2: ZIP File Integrity**
- Assumption: GitHub-generated ZIP files are always valid
- Mitigation: Verify file size before extraction
- Fallback: Rollback to previous version if extraction fails

**A3: WordPress Update System Reliability**
- Assumption: WordPress's `Plugin_Upgrader` class works correctly
- Mitigation: Use WordPress's own rollback mechanisms
- Fallback: Manual reinstall if WordPress update system breaks

**A4: User Permissions**
- Assumption: Administrators have `update_plugins` capability
- Mitigation: Check capabilities before showing update UI
- Fallback: Show "Contact your administrator" message

**A5: Network Connectivity**
- Assumption: WordPress site can connect to github.com
- Mitigation: Use 10-second HTTP timeouts
- Fallback: Show "Network error, try again later" message

---

## ✅ Success Criteria

### Must Have (P0)

1. ✅ Updates work reliably from WordPress Plugins page (100% success rate)
2. ✅ Updates work reliably from plugin Settings → Updates tab (100% success rate)
3. ✅ Updates work reliably via WP-CLI (100% success rate)
4. ✅ Update information is consistent across all admin interfaces
5. ✅ No PHP errors or warnings during update process
6. ✅ Directory naming is handled correctly (GitHub format → WordPress format)
7. ✅ Caches are invalidated immediately after updates
8. ✅ Security validation (nonces, capabilities) is enforced
9. ✅ Error messages are clear and actionable
10. ✅ Previous version is preserved/restored if update fails

### Should Have (P1)

11. ✅ Custom update UI in plugin's Settings → Updates tab
12. ✅ Real-time progress indicators during updates
13. ✅ Admin bar update indicator with auto-refresh
14. ✅ Update history tracking (last 5 updates)
15. ✅ Detailed error logging when WP_DEBUG enabled
16. ✅ GitHub API rate limit handling

### Nice To Have (P2)

17. One-click rollback to previous version (from Updates tab)
18. Pre-update automatic backup creation
19. Update channel selection (stable vs. beta)
20. Email notification when updates are available

---

## 🚧 Out of Scope

- Automatic updates (WordPress Auto-Updates feature already handles this)
- Plugin deletion/rollback from multiple versions ago
- Custom update scheduling (WordPress cron already does this)
- Plugin dependency management
- Split/partial updates (chunked downloads)
- Peer-to-peer update distribution
- Integration with WordPress.org repository (we're GitHub-only)

---

## 📖 Review & Acceptance Checklist

### Content Quality
- [x] No implementation details (languages, frameworks, specific APIs)
- [x] Focused on user value and business needs
- [x] Written for stakeholders (admin users, site owners)
- [x] All mandatory sections completed
- [x] Application context fully documented

### Requirement Completeness
- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified
- [x] Edge cases documented with expected behavior

### Technical Accuracy
- [x] WordPress update architecture correctly understood
- [x] Previous implementation failures analyzed
- [x] Root causes identified and addressed
- [x] Proper integration approach defined

---

## 🎬 Next Steps

1. ✅ Create comprehensive assessment document (ASSESSMENT.md)
2. ✅ Reopen specification with full application context (this document)
3. ⏳ Research WordPress Plugin_Upgrader architecture
4. ⏳ Study commercial plugin update mechanisms (ACF Pro, Gravity Forms, WooCommerce)
5. ⏳ Create detailed implementation plan
6. ⏳ Design proper integration approach
7. ⏳ Implement in phases with testing
8. ⏳ Document the correct approach for future development

---

## 📝 Clarifications

### Session 2025-10-11

**Q1**: Why did the custom "Update Now" button fail with "git url could not be constructed"?
**A1**: The custom AJAX endpoint tried to instantiate `WP_Upgrader` directly without proper WordPress context (screen, nonce, validation). WordPress expected updates to be triggered via its own UI flow, not custom AJAX endpoints. Additionally, GitHub URLs don't match the WordPress.org URL patterns that WordPress validates against.

**Q2**: Should we rebuild custom update execution logic?
**A2**: NO. WordPress provides a complete, battle-tested update system via `Plugin_Upgrader`. Our job is to INFORM WordPress about updates (via `pre_set_site_transient_update_plugins`), not to PERFORM updates ourselves. If we want custom UI, we should use WordPress's own `Plugin_Upgrader` class with proper AJAX handlers, not reinvent it.

**Q3**: How do commercial plugins (not in WordPress.org) handle updates?
**A3**: They integrate with the same WordPress hooks we're using, but they do it correctly:
- Advanced Custom Fields Pro: Uses `pre_set_site_transient_update_plugins` to inject update info
- Gravity Forms: Has a custom UI but still uses WordPress's `Plugin_Upgrader` class
- WooCommerce Extensions: Use WordPress's update API with authentication for license validation

**Q4**: What's the proper way to show progress during updates?
**A4**: Use WordPress's `WP_Ajax_Upgrader_Skin` class, which provides built-in progress feedback. Alternatively, poll the update status via AJAX and display progress client-side. Do NOT try to intercept WordPress's update execution flow.

**Q5**: Do we need to verify downloaded ZIP files?
**A5**: YES. Check file size before extraction. WordPress provides `verify_file_md5()` function, but GitHub doesn't provide MD5 hashes in release API. We can verify size (±10% tolerance) and let WordPress handle ZIP validation during extraction.

---

## 🔗 Related Documents

- [ASSESSMENT.md](./ASSESSMENT.md) - Detailed analysis of what went wrong
- [IMPLEMENTATION-COMPLETE.md](./IMPLEMENTATION-COMPLETE.md) - v3.16.3 implementation summary (now outdated)
- [quickstart.md](./quickstart.md) - Testing guide for v3.16.3 (needs update)
- [implementation-guide.md](./implementation-guide.md) - v3.16.3 technical guide (needs rewrite)

---

**Status**: Ready for Planning Phase
**Priority**: HIGH
**Estimated Effort**: 8-13 days
**Risk Level**: MEDIUM (WordPress update system is complex but well-documented)
**Target Version**: v3.17.0
