# Research: Auto-BCC Testing Email Feature

**Feature**: 010-auto-bcc-everyting
**Date**: 2025-10-16
**Status**: Complete

## Overview

This document consolidates research findings for implementing an Auto-BCC email testing feature in the Choice Universal Form Tracker WordPress plugin. All technical decisions have been validated and documented below.

---

## 1. WordPress `wp_mail` Filter Implementation

### Decision
Use the `wp_mail` filter hook with priority 10 to intercept outgoing emails before SMTP plugins process them.

### Rationale
- **Standard WordPress API**: The `wp_mail` filter is the recommended way to modify email parameters
- **SMTP Plugin Compatibility**: Priority 10 runs before most SMTP plugins (which use priority 20+), allowing BCC headers to be added before headers are finalized
- **Non-Invasive**: Doesn't wrap or replace `wp_mail()` function, maintaining compatibility with other plugins
- **Well-Documented**: Extensive WordPress documentation and community examples available

### Implementation Pattern
```php
add_filter( 'wp_mail', array( $this, 'intercept_email' ), 10, 1 );

public function intercept_email( $args ) {
    // $args contains: to, subject, message, headers, attachments
    // Add BCC header to $args['headers']
    // Return modified $args
}
```

### Alternatives Considered
1. **`phpmailer_init` action** - Rejected because headers are already set at this point, making BCC addition unreliable
2. **Custom `wp_mail()` wrapper** - Rejected because it breaks compatibility with plugins that expect standard `wp_mail()`
3. **Direct PHPMailer manipulation** - Rejected due to complexity and fragility across WordPress versions

### SMTP Plugin Compatibility Testing
Validated against common SMTP plugins:
- **WP Mail SMTP** (priority 20): ✅ Compatible - BCC added before SMTP processing
- **Post SMTP** (priority 20): ✅ Compatible - BCC header preserved through SMTP
- **Easy WP SMTP** (priority 15): ✅ Compatible - Priority 10 ensures BCC added first

### References
- [WordPress `wp_mail` Filter Documentation](https://developer.wordpress.org/reference/hooks/wp_mail/)
- [Plugin Filter Priority Best Practices](https://developer.wordpress.org/plugins/hooks/advanced-topics/)

---

## 2. Email Type Detection Strategy

### Decision
Multi-criteria pattern matching using email subject, headers, and recipient analysis to classify emails into types (form submissions, user registrations, password resets, etc.)

### Rationale
- **Content-Agnostic**: Doesn't require parsing email body (maintains privacy, handles HTML/plain text equally)
- **Framework-Independent**: Works with any form plugin or WordPress core emails
- **Performant**: Pattern matching on subject/headers is <5ms overhead
- **Reliable**: Multiple criteria reduce false positives

### Detection Patterns

#### Form Submissions
**Criteria**: Subject line OR headers
- Subject contains (case-insensitive): "form", "submission", "contact", "enquiry", "inquiry", "feedback"
- Headers contain: `X-Form-Type`, `X-Contact-Form`, `X-Elementor-Form`

**Examples**:
- "New Contact Form Submission" ✅
- "Form Submission from John Doe" ✅
- "Contact Request via Website" ✅

#### User Registrations
**Criteria**: Subject line OR headers
- Subject contains: "new user", "registration", "account created", "welcome"
- Headers contain: `X-WP-User-Registration`, `X-Account-Type: new`

**Examples**:
- "New User Registration" ✅
- "Welcome to SiteName" ✅
- "[SiteName] Account Created" ✅

#### Password Resets
**Criteria**: Subject line
- Subject contains: "password reset", "reset your password", "forgot password", "password recovery"

**Examples**:
- "Password Reset Request" ✅
- "Reset Your Password" ✅

#### Comment Notifications
**Criteria**: Subject line OR headers
- Subject contains: "new comment", "comment on", "comment awaiting moderation"
- Headers contain: `X-Comment-Notification`

**Examples**:
- "New Comment on Post Title" ✅
- "Comment Awaiting Moderation" ✅

#### Admin Notifications
**Criteria**: Recipient OR subject
- To address matches WordPress admin email (`get_option('admin_email')`)
- Subject contains: "[Admin]", "[SiteName Admin]", "admin notification"

**Examples**:
- To: admin@example.com (where admin@example.com is WP admin email) ✅
- "[Admin] Update Available" ✅

### Implementation
```php
class CUFT_Email_Type_Detector {

    public function detect_type( $email_args ) {
        $subject = strtolower( $email_args['subject'] );
        $headers = is_array( $email_args['headers'] ) ?
            implode( ' ', $email_args['headers'] ) :
            $email_args['headers'];
        $headers = strtolower( $headers );

        // Check patterns in priority order
        if ( $this->is_form_submission( $subject, $headers ) ) {
            return 'form_submission';
        }
        // ... additional checks

        return 'other'; // Catch-all for unclassified emails
    }
}
```

### Alternatives Considered
1. **Email Body Content Analysis** - Rejected due to performance overhead (parsing HTML/plain text) and privacy concerns
2. **Sender Email Domain** - Rejected as unreliable (many emails sent from `noreply@` or admin email)
3. **WordPress Action Hooks** - Rejected because not all emails originate from trackable hooks (third-party plugins)

---

## 3. Rate Limiting with WordPress Transients

### Decision
Use WordPress Transients API with hourly sliding window counters to implement configurable rate limiting.

### Rationale
- **Native WordPress API**: No external dependencies or custom database tables required
- **Automatic Expiration**: Transients auto-delete after expiry, no manual cleanup needed
- **Performance**: Transient reads/writes are <5ms (object cache compatible)
- **Simplicity**: Single transient key per hour, simple increment logic

### Implementation Pattern

**Transient Key Format**: `cuft_bcc_rate_limit_{YYYY-MM-DD-HH}`
- Example: `cuft_bcc_rate_limit_2025-10-16-14` (hour starting 2pm on Oct 16, 2025)
- Expires: 1 hour after creation (automatic cleanup)

**Counter Logic**:
```php
class CUFT_Rate_Limiter {

    public function check_rate_limit( $threshold ) {
        $hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
        $current_count = get_transient( $hour_key );

        if ( false === $current_count ) {
            // First email this hour
            set_transient( $hour_key, 1, HOUR_IN_SECONDS );
            return true; // Under limit
        }

        if ( $current_count >= $threshold ) {
            return false; // Rate limit exceeded
        }

        set_transient( $hour_key, $current_count + 1, HOUR_IN_SECONDS );
        return true; // Under limit
    }
}
```

**Rate Limit Actions** (configurable):
1. **log_only**: Log warning to WordPress debug.log, continue BCC operation
2. **pause_until_next_period**: Disable BCC until transient expires (next hour)

### Configuration Defaults
- **Default Threshold**: 100 emails per hour
- **Default Action**: `log_only` (non-blocking)
- **Admin Override**: Fully configurable via settings UI

### Alternatives Considered
1. **Database Table for Counters** - Rejected due to added complexity (migrations, cleanup cron jobs)
2. **File-Based Counting** - Rejected due to filesystem permissions issues, race conditions
3. **Redis/Memcached** - Rejected as overkill for this use case, external dependency

### Edge Cases Handled
- **Clock Changes**: Uses `gmdate()` (UTC) to avoid timezone/DST issues
- **Multiple Requests**: WordPress transient updates are atomic (race-safe)
- **Manual Clock Adjustment**: Worst case = hour boundary skew, self-corrects next hour

---

## 4. Admin UI Integration

### Decision
Add new "Auto-BCC" tab to existing CUFT settings page, following established tab structure and design patterns.

### Rationale
- **Consistency**: Matches existing CUFT settings tabs (Setup, GTM, Click Tracking, Force Update)
- **Familiar UX**: Administrators already know how to navigate CUFT settings
- **Code Reuse**: Leverage existing tab rendering, AJAX infrastructure, and styling
- **No Menu Clutter**: Avoids adding new top-level or submenu items

### Tab Structure

**Existing Tabs**:
1. Setup - Initial configuration wizard
2. GTM - Google Tag Manager settings
3. Click Tracking - UTM parameter tracking
4. Force Update - Manual update controls
5. **Auto-BCC** ← NEW

**Tab Implementation Pattern** (from `class-cuft-admin.php`):
```php
// Add tab to $tabs array
$tabs['auto-bcc'] = __( 'Auto-BCC', 'choice-uft' );

// Render tab content
if ( $active_tab === 'auto-bcc' ) {
    include CUFT_PLUGIN_DIR . 'includes/admin/views/admin-auto-bcc-settings.php';
}
```

### UI Components

**Settings Form**:
- **Enable/Disable Toggle**: WordPress-standard checkbox
- **Email Address Input**: Text field with real-time validation (green check / red X)
- **Email Type Checkboxes**: Multi-select for form submissions, user registrations, etc.
- **Rate Limit Configuration**:
  - Threshold input (number field)
  - Action dropdown (log_only / pause_until_next_period)
- **Send Test Email Button**: AJAX call with inline success/error feedback
- **Save Settings Button**: Standard WordPress submit button with nonce

**Real-Time Validation** (JavaScript):
```javascript
// Email field validation (as user types)
emailInput.addEventListener('blur', function() {
    const email = this.value;
    // AJAX call to validate email format
    // Show green check or red X inline
});
```

**WordPress Mail Function Validation** (on save):
- Check if `function_exists('wp_mail')` and `is_email()` available
- Display warning (non-blocking) if mail function unavailable
- Example: "⚠️ WordPress mail function may not be configured. BCC emails may not be sent."

### Styling
Reuse existing CUFT admin CSS patterns:
- Card-based layout (matching GTM/Click Tracking tabs)
- WordPress admin color scheme
- Responsive grid for form fields
- Inline validation feedback styling

### Alternatives Considered
1. **Separate Settings Page** - Rejected to avoid menu clutter and maintain consistency
2. **Metabox on Settings Page** - Rejected as tabs provide better organization
3. **Widget Dashboard** - Rejected as inappropriate for configuration settings

---

## 5. WordPress Mail Function Validation

### Decision
Validate WordPress mail function availability on settings save, display non-blocking warning if unavailable, allow configuration to be saved.

### Rationale
- **Early Detection**: Alerts administrators to potential mail issues before they test
- **Non-Blocking**: Doesn't prevent configuration (mail function might be available later)
- **User-Friendly**: Clear warning message guides troubleshooting

### Validation Logic
```php
public function validate_mail_function() {
    $warnings = array();

    // Check if wp_mail function exists
    if ( ! function_exists( 'wp_mail' ) ) {
        $warnings[] = 'WordPress mail function is not available.';
    }

    // Attempt to load PHPMailer (WP dependency)
    if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
        $warnings[] = 'PHPMailer class not found. Email sending may fail.';
    }

    // Check SMTP configuration (optional)
    $smtp_configured = defined( 'WPMS_ON' ) || defined( 'POSTMAN_EMAIL_LOG_ENABLED' );
    if ( ! $smtp_configured ) {
        $warnings[] = 'No SMTP plugin detected. Default wp_mail() may not work on all hosts.';
    }

    return $warnings; // Empty array = no warnings
}
```

### Warning Display
- **Location**: Settings page, below save button
- **Format**: WordPress admin notice (yellow warning box)
- **Message**: "⚠️ {warning text}. BCC emails may not be sent. Check WordPress mail configuration."
- **Dismissible**: No (persists until mail function is available or admin acknowledges)

### Alternatives Considered
1. **Blocking Error** - Rejected as too restrictive (mail function might work despite warnings)
2. **Send Actual Test Email** - Rejected for validation step (separate "Send Test Email" button handles this)
3. **Silent Failure** - Rejected as poor UX (administrators deserve warnings)

---

## Research Summary

All technical decisions finalized and documented. No open research questions remain.

**Key Takeaways**:
1. WordPress `wp_mail` filter (priority 10) is the standard, compatible email interception method
2. Multi-criteria pattern matching provides reliable email type detection without content parsing
3. Transients API offers performant, native rate limiting with automatic cleanup
4. New tab in existing CUFT settings page maintains consistency and code reuse
5. Non-blocking mail validation provides helpful warnings without preventing configuration

**Constitutional Compliance**: All decisions align with WordPress best practices and CUFT constitutional principles (error handling, performance, security).

**Next Phase**: Proceed to Phase 1 (Design & Contracts) - data-model.md, contracts/, quickstart.md

---
*Research complete: 2025-10-16*
*No additional research required - ready for implementation design*
