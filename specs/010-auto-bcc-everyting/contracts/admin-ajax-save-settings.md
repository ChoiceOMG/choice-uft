# Contract: AJAX Save Auto-BCC Settings

**Endpoint**: `wp-admin/admin-ajax.php?action=cuft_save_auto_bcc_settings`
**Method**: POST
**Authentication**: WordPress nonce + `update_plugins` capability

## Request

```javascript
{
  action: "cuft_save_auto_bcc_settings",
  nonce: "{wp_nonce}",
  enabled: true,
  bcc_email: "testing@example.com",
  selected_email_types: ["form_submission", "user_registration"],
  rate_limit_threshold: 100,
  rate_limit_action: "log_only"
}
```

## Response (Success)

```javascript
{
  success: true,
  data: {
    message: "Auto-BCC settings saved successfully",
    config: {
      enabled: true,
      bcc_email: "testing@example.com",
      selected_email_types: ["form_submission", "user_registration"],
      rate_limit_threshold: 100,
      rate_limit_action: "log_only"
    },
    warnings: [] // Optional WordPress mail validation warnings
  }
}
```

## Response (Error)

```javascript
{
  success: false,
  data: {
    message: "Invalid email address format",
    errors: ["Invalid email address format"]
  }
}
```

**Test**: Contract test must verify nonce validation, capability check, email validation, and successful save.
