# Contract: AJAX Send Test Email

**Endpoint**: `wp-admin/admin-ajax.php?action=cuft_send_test_bcc_email`
**Method**: POST
**Authentication**: WordPress nonce + `update_plugins` capability

## Request

```javascript
{
  action: "cuft_send_test_bcc_email",
  nonce: "{wp_nonce}",
  bcc_email: "testing@example.com"
}
```

## Response (Success)

```javascript
{
  success: true,
  data: {
    message: "Test email sent successfully to testing@example.com",
    subject: "[CUFT Test Email] Auto-BCC Feature Test",
    sent_at: 1729094400 // Unix timestamp
  }
}
```

## Response (Error)

```javascript
{
  success: false,
  data: {
    message: "Failed to send test email",
    error: "wp_mail() returned false"
  }
}
```

**Test**: Contract test must verify email sent with correct subject, BCC header, and success/failure handling.
