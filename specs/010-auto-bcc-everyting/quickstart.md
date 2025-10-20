# Quickstart: Auto-BCC Testing Email

**Feature**: 010-auto-bcc-everyting
**Purpose**: Manual end-to-end validation

## Prerequisites
- WordPress site with CUFT plugin installed
- Admin access
- Email sending capability configured
- Access to test email inbox

## Test Steps

### 1. Enable Auto-BCC Feature
1. Navigate to Settings → Universal Form Tracker → Auto-BCC tab
2. Check "Enable Auto-BCC" checkbox
3. Enter test email address (e.g., `testing@example.com`)
4. Select "Form Submissions" email type
5. Click "Save Settings"
6. Verify success message appears

### 2. Send Test Email
1. Click "Send Test Email" button
2. Verify success message appears
3. Check test email inbox
4. Confirm test email received with subject "[CUFT Test Email]"

### 3. Test Form Submission BCC
1. Navigate to test form page
2. Submit form with valid email
3. Check test email inbox
4. Confirm form submission email received as BCC

### 4. Test Email Type Filtering
1. Return to Auto-BCC settings
2. Uncheck "Form Submissions"
3. Check "Password Resets"
4. Save settings
5. Submit test form
6. Verify NO BCC received (form emails disabled)
7. Trigger password reset
8. Verify password reset email BCC received

### 5. Disable Feature
1. Uncheck "Enable Auto-BCC"
2. Save settings
3. Submit test form
4. Verify NO BCC received

## Expected Results
- ✅ All BCC emails received when enabled
- ✅ No BCC emails when disabled
- ✅ Email type filtering works correctly
- ✅ Primary email delivery unaffected
- ✅ No console errors or warnings

## Performance Validation
- Email sending latency increase: <50ms
- Settings page load time: <500ms
- No memory leaks after 100 email operations

---
**Status**: Ready for testing after implementation
