# Research Document: GTM Server Health Check AJAX Security Fix

**Date**: 2025-10-06
**Feature**: Custom GTM Server Domain with Health Checks (006)
**Issue**: AJAX endpoint `cuft_test_sgtm` returning "Security check failed"
**Priority**: Critical - blocking health check functionality

---

## Executive Summary

The GTM server health check AJAX endpoint is failing with "Security check failed" due to a **nonce action mismatch**. The server-side handler expects nonce action `cuft_ajax_nonce`, but the JavaScript sends a nonce created with action `cuft_admin`. This is a common pattern seen and resolved in other parts of the application.

**Root Cause**: Inconsistent nonce action names between PHP nonce generation and verification.

**Recommended Fix**: Standardize on `cuft_admin` nonce action (already generated and available in JavaScript).

---

## 1. Existing Working AJAX Patterns

### Pattern 1: Event Recorder (WORKING ✅)

**PHP Handler**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-event-recorder.php`

```php
// Line 32-33: Registration
add_action( 'wp_ajax_cuft_record_event', array( $this, 'record_event' ) );
add_action( 'wp_ajax_nopriv_cuft_record_event', array( $this, 'record_event' ) );

// Line 58: Nonce verification
$nonce_check = check_ajax_referer( 'cuft-event-recorder', 'nonce', false );
```

**PHP Nonce Generation**: `/home/r11/dev/choice-uft/choice-universal-form-tracker.php:369`

```php
$nonce = wp_create_nonce( 'cuft-event-recorder' );
```

**Key Insight**: Nonce action `cuft-event-recorder` is consistent between generation and verification.

---

### Pattern 2: Form Builder AJAX (WORKING ✅)

**PHP Handler**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-form-builder-ajax.php`

```php
// Line 393-395: Nonce verification
private function verify_nonce() {
    $nonce = $_REQUEST['nonce'] ?? '';
    return wp_verify_nonce($nonce, 'cuft_form_builder_nonce');
}
```

**PHP Nonce Generation**: `/home/r11/dev/choice-uft/includes/admin/class-cuft-testing-dashboard.php:235`

```php
wp_localize_script('cuft-form-builder', 'cuftFormBuilder', array(
    'nonce' => wp_create_nonce('cuft_form_builder_nonce'),
    // ...
));
```

**JavaScript Usage**: `/home/r11/dev/choice-uft/assets/admin/js/cuft-form-builder.js:134`

```javascript
formData.append('nonce', this.config.nonce);
```

**Key Insight**: Nonce action `cuft_form_builder_nonce` is consistent throughout the chain.

---

### Pattern 3: Updater AJAX (WORKING ✅)

**PHP Handler**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-updater-ajax.php`

```php
// Line 25: Nonce action constant
const NONCE_ACTION = 'cuft_updater_nonce';

// Line 72: Nonce verification
if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
    wp_send_json_error( array(
        'message' => 'Security check failed',
        'code' => 'invalid_nonce'
    ), 403 );
    return false;
}
```

**PHP Nonce Generation**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php:707`

```php
wp_localize_script( 'cuft-updater', 'cuftUpdater', array(
    'nonce' => wp_create_nonce( 'cuft_updater_nonce' ), // Must match CUFT_Updater_Ajax::NONCE_ACTION
    // ...
));
```

**JavaScript Usage**: `/home/r11/dev/choice-uft/assets/admin/js/cuft-updater.js:114`

```javascript
const data = new URLSearchParams({
    action: 'cuft_check_update',
    nonce: this.config.nonce,
    force: force ? 'true' : 'false'
});
```

**Key Insight**: Uses a constant for nonce action and includes helpful comment linking them.

---

## 2. Current Broken Implementation

### AJAX Handler (BROKEN ❌)

**File**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php:938-942`

```php
public function ajax_test_sgtm() {
    // Verify nonce and permissions
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }
    // ...
}
```

**Expected Nonce Action**: `cuft_ajax_nonce`

---

### Nonce Generation (MISMATCH ❌)

**File**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php:687-693`

```php
wp_localize_script( 'cuft-admin', 'cuftAdmin', array(
    'ajax_url' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'cuft_admin' ),  // ❌ MISMATCH: creates 'cuft_admin'
    'current_version' => CUFT_VERSION,
    'plugin_url' => CUFT_URL,
    'admin_url' => admin_url( 'options-general.php?page=choice-universal-form-tracker' )
));
```

**Generated Nonce Action**: `cuft_admin`

---

### JavaScript Usage

**File**: `/home/r11/dev/choice-uft/assets/cuft-admin.js:205-212`

```javascript
$.ajax({
  url: cuftAdmin.ajax_url,
  type: "POST",
  data: {
    action: "cuft_test_sgtm",
    nonce: cuftAdmin.nonce,  // ❌ Sends nonce created with 'cuft_admin' action
    sgtm_url: sgtmUrl,
  },
  // ...
});
```

---

### Problem Summary

| Component | Nonce Action |
|-----------|--------------|
| **PHP Generation** | `cuft_admin` |
| **PHP Verification** | `cuft_ajax_nonce` |
| **Result** | ❌ MISMATCH → "Security check failed" |

---

## 3. WordPress Nonce Patterns in This Codebase

### Standard Pattern

1. **Create nonce in PHP** with a specific action:
   ```php
   wp_create_nonce( 'my_action_name' )
   ```

2. **Pass to JavaScript** via `wp_localize_script()`:
   ```php
   wp_localize_script( 'my-script', 'myConfig', array(
       'nonce' => wp_create_nonce( 'my_action_name' )
   ));
   ```

3. **Send in AJAX request** from JavaScript:
   ```javascript
   data: {
       action: 'my_ajax_action',
       nonce: myConfig.nonce
   }
   ```

4. **Verify in PHP** using the SAME action:
   ```php
   wp_verify_nonce( $_POST['nonce'], 'my_action_name' )
   ```

### Alternative: `check_ajax_referer()`

Some endpoints use `check_ajax_referer()` which is a wrapper around `wp_verify_nonce()`:

```php
// Equivalent to wp_verify_nonce( $_REQUEST['nonce'], 'my_action_name' )
check_ajax_referer( 'my_action_name', 'nonce', false );
```

---

## 4. Nonce Actions Currently in Use

### Active Nonce Actions

| Nonce Action | Used By | Files |
|--------------|---------|-------|
| `cuft-event-recorder` | Event recording AJAX | `class-cuft-event-recorder.php` |
| `cuft_form_builder_nonce` | Form builder AJAX | `class-cuft-form-builder-ajax.php` |
| `cuft_updater_nonce` | Update system AJAX | `class-cuft-updater-ajax.php` |
| `cuft_admin` | Admin scripts | `class-cuft-admin.php` (generation) |
| `cuft_ajax_nonce` | Legacy admin AJAX | `class-cuft-admin.php` (verification only) |

### Issue Identified

`cuft_ajax_nonce` appears in **verification** but is **never generated** in the codebase:

```bash
# Search shows it's only used in verification, never in wp_create_nonce()
$ grep -r "wp_create_nonce.*cuft_ajax_nonce" .
# No results
```

This suggests `cuft_ajax_nonce` is a legacy/orphaned nonce action that should be replaced with `cuft_admin`.

---

## 5. Recommended Solution

### Option A: Change Verification to Use `cuft_admin` (RECOMMENDED ✅)

**Rationale**: The nonce is already generated and available in JavaScript. Minimal code changes required.

**Changes Required**:

1. Update `ajax_test_sgtm()` to verify `cuft_admin` instead of `cuft_ajax_nonce`
2. Update any other handlers using `cuft_ajax_nonce` (found 4 locations)

**Files to Modify**:
- `/home/r11/dev/choice-uft/includes/class-cuft-admin.php` (lines 780, 940, 1073, 2066)

**Code Change Example**:

```php
// BEFORE
if ( ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) ) {
    // ...
}

// AFTER
if ( ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) ) {
    // ...
}
```

---

### Option B: Generate New `cuft_ajax_nonce` (NOT RECOMMENDED ❌)

**Rationale**: Adds unnecessary complexity when a working nonce already exists.

**Changes Required**:
1. Add new nonce generation in `wp_localize_script()`
2. Update JavaScript to use new nonce field

**Why Not Recommended**:
- Increases code complexity
- Creates duplicate nonces for the same purpose
- Goes against DRY principle

---

## 6. Health Check Implementation Patterns

### Existing Health Check Example

**File**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php:985-1065`

The application already has a working health check implementation for GTM server endpoints:

```php
private function test_sgtm_endpoints( $sgtm_url, $gtm_id ) {
    $results = array(
        'success' => false,
        'message' => '',
        'details' => array()
    );

    // Test gtm.js endpoint
    $gtm_js_url = $sgtm_url . '/gtm.js?id=' . $gtm_id;
    $gtm_js_response = wp_remote_get( $gtm_js_url, array(
        'timeout' => 10,
        'sslverify' => $ssl_verify
    ) );

    if ( is_wp_error( $gtm_js_response ) ) {
        $results['message'] = 'Failed to connect: ' . $gtm_js_response->get_error_message();
        return $results;
    }

    $code = wp_remote_retrieve_response_code( $gtm_js_response );
    $body = wp_remote_retrieve_body( $gtm_js_response );

    if ( $code !== 200 ) {
        $results['message'] = 'Invalid status code: ' . $code;
        return $results;
    }

    // Validate JavaScript content
    if ( strpos( $body, 'google' ) === false && strpos( $body, 'gtm' ) === false ) {
        $results['message'] = 'Invalid response content';
        return $results;
    }

    $results['success'] = true;
    $results['message'] = 'Validated successfully';
    return $results;
}
```

**Key Patterns**:
1. ✅ Uses `wp_remote_get()` with timeout (10 seconds)
2. ✅ Checks for `WP_Error` response
3. ✅ Validates HTTP status code (200)
4. ✅ Validates response content (parseable JavaScript)
5. ✅ Returns structured array with success/message/details

---

### Timeout Handling

**Current Implementation**: 10 seconds
**Spec Requirement**: 5 seconds (FR-013)

**Update Required**:

```php
// BEFORE
$response = wp_remote_get( $url, array(
    'timeout' => 10,
    'sslverify' => $ssl_verify
) );

// AFTER (to match spec)
$response = wp_remote_get( $url, array(
    'timeout' => 5,  // FR-013: 5 second timeout
    'sslverify' => $ssl_verify
) );
```

---

### Storage Patterns for Health Check Results

The application uses `wp_options` for persistent storage:

```php
// Save validation status
update_option( 'cuft_sgtm_validated', true );

// Retrieve status
$validated = get_option( 'cuft_sgtm_validated', false );
```

**For Health Check Feature**, we'll need additional options:

| Option Key | Type | Purpose |
|------------|------|---------|
| `cuft_sgtm_health_last_check` | int (timestamp) | Last health check timestamp |
| `cuft_sgtm_health_last_result` | bool | Last health check result (true/false) |
| `cuft_sgtm_health_consecutive_success` | int | Count of consecutive successes (for FR-009) |
| `cuft_sgtm_active_server` | string | Current active server ('custom' or 'fallback') |

---

## 7. JavaScript AJAX Patterns

### Pattern 1: jQuery AJAX (Used in cuft-admin.js)

```javascript
$.ajax({
  url: cuftAdmin.ajax_url,
  type: "POST",
  data: {
    action: "cuft_test_sgtm",
    nonce: cuftAdmin.nonce,
    sgtm_url: sgtmUrl,
  },
  dataType: "json",
  timeout: 15000,
  success: function (response) {
    if (response.success) {
      // Handle success
    } else {
      // Handle error
    }
  },
  error: function (xhr, status, error) {
    // Handle AJAX error
  }
});
```

**Key Features**:
- Uses jQuery `$.ajax()`
- Timeout set in JavaScript (15 seconds)
- Standard WordPress AJAX response format (`response.success`, `response.data`)

---

### Pattern 2: Fetch API with Fallback (Used in cuft-updater.js)

**File**: `/home/r11/dev/choice-uft/assets/admin/js/cuft-updater.js:343-423`

```javascript
makeRequest: function(method, data) {
    // Try native fetch first
    if (window.fetch) {
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (method === 'POST') {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = data;
        }

        return fetch(this.config.ajaxUrl, options)
            .then(function(response) {
                return response.json();
            });
    }

    // Fallback to jQuery if available
    if (window.jQuery) {
        return new Promise(function(resolve, reject) {
            jQuery.ajax({
                url: self.config.ajaxUrl,
                type: method,
                data: data.toString(),
                dataType: 'json',
                success: resolve,
                error: function(xhr, status, error) {
                    reject(new Error(error || status));
                }
            });
        });
    }

    // Fallback to XMLHttpRequest
    return new Promise(function(resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open(method, self.config.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(JSON.parse(xhr.responseText));
            } else {
                reject(new Error('Request failed'));
            }
        };
        xhr.send(data.toString());
    });
}
```

**Key Features**:
- Triple fallback: fetch → jQuery → XMLHttpRequest
- Consistent promise-based interface
- Follows constitutional "maximum compatibility" principle

---

## 8. Admin Notice Patterns

### WordPress Admin Notices

The application already displays admin notices:

**File**: `/home/r11/dev/choice-uft/includes/admin/class-cuft-admin-notices.php`

```php
// Display notice
add_action( 'admin_notices', array( $this, 'display_notices' ) );

public function display_notices() {
    // Check if notice should be shown
    if ( ! $this->should_show_notice() ) {
        return;
    }

    // Display notice HTML
    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p>' . esc_html( $message ) . '</p>';
    echo '</div>';
}
```

**For Health Check Feature**, we'll need:
- Notice when server status changes (custom → fallback)
- Notice when server recovers (fallback → custom)
- Persistent notice (not dismissible) when using fallback

---

## 9. Implementation Checklist

### Phase 1: Fix Security Issue (CRITICAL)

- [ ] Update `ajax_test_sgtm()` nonce verification from `cuft_ajax_nonce` to `cuft_admin`
- [ ] Update other AJAX handlers using `cuft_ajax_nonce` (lines 780, 1073, 2066)
- [ ] Test AJAX endpoint returns success instead of "Security check failed"
- [ ] Verify existing health check functionality works

### Phase 2: Add Periodic Health Checks

- [ ] Create cron job for 6-hour health checks (FR-006)
- [ ] Implement health check on frontend page loads
- [ ] Store health check results in `wp_options` (FR-007, FR-008)
- [ ] Implement consecutive success counter (FR-009)

### Phase 3: Automatic Fallback

- [ ] Implement fallback logic when health check fails (FR-004, FR-010)
- [ ] Implement recovery logic after 3 consecutive successes (FR-009)
- [ ] Update GTM script loading to use active server

### Phase 4: Admin Interface

- [ ] Display current server status in settings (FR-011)
- [ ] Display last health check timestamp and result (FR-012)
- [ ] Add manual health check trigger button (FR-015)
- [ ] Implement admin notices for status changes (FR-017)

### Phase 5: Testing & Validation

- [ ] Test health check with working custom server
- [ ] Test health check with offline custom server
- [ ] Test fallback behavior
- [ ] Test recovery behavior
- [ ] Test manual health check trigger
- [ ] Verify 5-second timeout (FR-013)

---

## 10. Code Examples for Implementation

### Fix 1: Nonce Action Update

**File**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`

```php
// Line 938-942: Update ajax_test_sgtm()
public function ajax_test_sgtm() {
    // BEFORE:
    // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {

    // AFTER:
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }
    // ... rest of method
}

// Line 780: Update ajax_submit_test_form()
public function ajax_submit_test_form() {
    // BEFORE:
    // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'update_plugins' ) ) {

    // AFTER:
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) || ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }
    // ... rest of method
}

// Line 1073: Update ajax_test_form_submit()
public function ajax_test_form_submit() {
    // BEFORE:
    // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {

    // AFTER:
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }
    // ... rest of method
}

// Line 2066: Update ajax_dismiss_notice()
public function ajax_dismiss_notice() {
    // BEFORE:
    // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_ajax_nonce' ) ) {

    // AFTER:
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cuft_admin' ) ) {
        wp_send_json_error();
    }
    // ... rest of method
}
```

---

### Fix 2: Update Timeout to Match Spec

**File**: `/home/r11/dev/choice-uft/includes/class-cuft-admin.php`

```php
// Line 1002-1004: Update timeout from 10 to 5 seconds
$gtm_js_response = wp_remote_get( $gtm_js_url, array(
    'timeout' => 5,  // FR-013: Changed from 10 to 5 seconds
    'sslverify' => $ssl_verify
) );

// Line 1033-1035: Update timeout from 10 to 5 seconds
$ns_html_response = wp_remote_get( $ns_html_url, array(
    'timeout' => 5,  // FR-013: Changed from 10 to 5 seconds
    'sslverify' => $ssl_verify
) );
```

---

## 11. References

### Working AJAX Endpoints (for pattern reference)

1. **Event Recorder**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-event-recorder.php`
   - Action: `cuft_record_event`
   - Nonce: `cuft-event-recorder`
   - Pattern: Dedicated AJAX class with proper nonce handling

2. **Form Builder**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-form-builder-ajax.php`
   - Actions: Multiple (`cuft_create_test_form`, `cuft_get_test_forms`, etc.)
   - Nonce: `cuft_form_builder_nonce`
   - Pattern: Singleton with private `verify_nonce()` method

3. **Updater**: `/home/r11/dev/choice-uft/includes/ajax/class-cuft-updater-ajax.php`
   - Actions: Multiple (`cuft_check_update`, `cuft_perform_update`, etc.)
   - Nonce: `cuft_updater_nonce` (constant)
   - Pattern: Private `verify_request()` method with capability checks

### Related Documentation

- **Spec**: `/home/r11/dev/choice-uft/specs/006-provide-users-with/spec.md`
- **Review Checklist**: `/home/r11/dev/choice-uft/.specify/memory/review-checklist.md`
- **Constitution**: `/home/r11/dev/choice-uft/.specify/memory/constitution.md`

---

## Conclusion

The AJAX security issue is a **simple nonce action mismatch** that can be fixed by updating 4 lines of code to use `cuft_admin` instead of `cuft_ajax_nonce`.

The existing codebase already has:
- ✅ Working health check implementation (`test_sgtm_endpoints()`)
- ✅ Proper storage patterns (`wp_options`)
- ✅ Admin notice system
- ✅ Multiple working AJAX patterns to follow

**Next Steps**:
1. Fix the nonce mismatch (immediate)
2. Update timeout to 5 seconds (spec compliance)
3. Implement periodic health checks (new feature)
4. Add automatic fallback logic (new feature)
5. Enhance admin interface (new feature)

All patterns needed for implementation already exist in the codebase and can be followed directly.
